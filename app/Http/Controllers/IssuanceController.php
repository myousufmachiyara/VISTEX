<?php

namespace App\Http\Controllers;

use App\Models\{Issuance, Location, LocationStockLedger, Product, ProductCategory, PurchaseOrder};
use App\Services\IssuanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IssuanceController extends Controller
{
    public function __construct(private IssuanceService $service) {}

    public function index(Request $request)
    {
        $issuances = Issuance::with('purchaseOrder.vendor', 'sourceLocation', 'destinationLocation', 'items.product')
            ->when($request->filled('type'), fn($q) => $q->where('issue_type', $request->type))
            ->orderByDesc('issue_date')->orderByDesc('id')->get();

        return view('issuances.index', compact('issuances'));
    }

    public function create(Request $request)
    {
        $type = $request->get('type');
        if ($type && !isset(Issuance::TYPES[$type])) $type = null;
        if ($type && !Issuance::TYPES[$type]['active']) {
            return redirect()->route('issuances.create')->with('error', Issuance::TYPES[$type]['label'] . ' is locked pending client discussion.');
        }

        return view('issuances.create', array_merge($this->formData($type), [
            'issuance' => null,
            'preselectPo' => $request->integer('purchase_order_id') ?: null,
        ]));
    }

    public function edit($id)
    {
        $issuance = Issuance::with('items.product', 'purchaseOrder')->findOrFail($id);
        return view('issuances.create', array_merge($this->formData($issuance->issue_type), [
            'issuance' => $issuance, 'preselectPo' => $issuance->purchase_order_id,
        ]));
    }

    private function formData(?string $type): array
    {
        $poType = $type ? Issuance::TYPES[$type]['po_type'] : null;
        $orders = $poType
            ? PurchaseOrder::where('type', $poType)
                ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ISSUED, PurchaseOrder::STATUS_PARTIAL])
                ->with('vendor')->orderByDesc('order_date')->get()
            : collect();

        return [
            'type'       => $type,
            'types'      => Issuance::TYPES,
            'orders'     => $orders,
            'warehouses' => Location::own()->active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']),
        ];
    }

    // AJAX: what may be issued against this PO from this warehouse
    public function poDetails(Request $request, $poId)
    {
        $po = PurchaseOrder::with('vendor', 'items.product', 'warpProduct', 'weftProduct')->findOrFail($poId);
        $sourceId = $request->integer('source_location_id') ?: Location::defaultId();
        $excludeId = $request->integer('exclude') ?: null;

        if ($po->type === 'weaving') {
            $req = $this->service->yarnRequirement($po, $excludeId);
            $lines = [];
            foreach ($req as $productId => $r) {
                $product = Product::find($productId);
                $lines[] = array_merge($r, [
                    'product_id' => $productId, 'product_name' => $product->name ?? '',
                    'role' => $productId == $po->warp_product_id && $productId == $po->weft_product_id ? 'Warp + Weft'
                        : ($productId == $po->warp_product_id ? 'Warp' : 'Weft'),
                    'available' => $sourceId ? round(LocationStockLedger::balance($sourceId, $productId), 3) : 0,
                    'lots' => $sourceId ? LocationStockLedger::availableLots($sourceId, $productId) : [],
                ]);
            }
            return response()->json(['type' => 'weaving', 'vendor' => $po->vendor->name ?? '', 'lines' => $lines]);
        }

        // processing: greige in stock at the source warehouse + the mill's locations
        $greigeCategoryIds = ProductCategory::where('code', 'greige')->pluck('id');
        $stock = $sourceId ? LocationStockLedger::where('location_id', $sourceId)->where('status', 'fresh')
            ->whereHas('product', fn($q) => $q->whereIn('category_id', $greigeCategoryIds))
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) as qty')->having('qty', '>', 0.001)
            ->with('product')->get() : collect();

        return response()->json([
            'type' => 'processing',
            'vendor' => $po->vendor->name ?? '',
            'destinations' => Location::where('vendor_id', $po->vendor_id)->active()->orderBy('name')->get(['id', 'name']),
            'po_items' => $po->items->map(fn($i) => [
                'id' => $i->id, 'label' => trim(($i->pattern_code ?? '') . ' ' . ($i->description ?? $i->product->name ?? '')),
                'quantity' => (float) $i->quantity,
            ]),
            'stock' => $stock->map(fn($r) => [
                'product_id' => $r->product_id, 'product_name' => $r->product->name ?? '', 'available' => round((float) $r->qty, 3),
                'lots' => LocationStockLedger::availableLots($sourceId, $r->product_id),
            ])->values(),
        ]);
    }

    private function rules(): array
    {
        return [
            'purchase_order_id'       => 'required|exists:purchase_orders,id',
            'source_location_id'      => 'required|exists:locations,id',
            'destination_location_id' => 'nullable|exists:locations,id',
            'issue_date'              => 'required|date',
            'remarks'                 => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity'        => 'nullable|numeric|min:0',
            'items.*.lot_no'          => 'nullable|string|max:100',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'attachments.*'           => 'nullable|file|max:5120',
        ];
    }

    private function storeAttachments(Request $request, array $existing = []): ?array
    {
        foreach ($request->file('attachments', []) as $file) $existing[] = $file->store('issuance_attachments', 'public');
        return $existing ?: null;
    }

    public function store(Request $request)
    {
        $request->validate(array_merge($this->rules(), ['issue_type' => 'required|in:' . implode(',', array_keys(Issuance::TYPES))]));
        try {
            $issuance = $this->service->create(
                array_merge($request->except('attachments'), ['attachments' => $this->storeAttachments($request)]),
                $request->items, auth()->id()
            );
            Log::info('[Issuance] Created', ['id' => $issuance->id, 'type' => $issuance->issue_type, 'by' => auth()->id()]);
            return redirect()->route('issuances.show', $issuance->id)->with('success', $issuance->issue_no . ' posted.');
        } catch (\Exception $e) {
            Log::error('[Issuance] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $issuance = Issuance::with('items', 'purchaseOrder')->findOrFail($id);
        $request->validate($this->rules());
        try {
            $this->service->update($issuance,
                array_merge($request->except('attachments'), ['attachments' => $this->storeAttachments($request, $issuance->attachments ?? [])]),
                $request->items, auth()->id());
            return redirect()->route('issuances.show', $issuance->id)->with('success', 'Issuance updated.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $issuance = Issuance::with('items.product.measurementUnit', 'purchaseOrder.vendor', 'sourceLocation', 'destinationLocation', 'creator')->findOrFail($id);
        return view('issuances.show', compact('issuance'));
    }

    public function destroy($id)
    {
        try {
            $issuance = Issuance::with('items', 'purchaseOrder')->findOrFail($id);
            $this->service->delete($issuance);
            return redirect()->route('issuances.index')->with('success', "{$issuance->issue_no} reversed and deleted.");
        } catch (\Exception $e) {
            Log::error('[Issuance] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function print($id)
    {
        $issuance = Issuance::with('items.product.measurementUnit', 'purchaseOrder.vendor', 'sourceLocation', 'destinationLocation')->findOrFail($id);

        $pdf = new \App\Services\myPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('VISTEX (Private) Limited');
        $pdf->SetTitle($issuance->issue_no);
        $pdf->AddPage();

        $logoPath = public_path('assets/img/vistex-logo.png');
        if (file_exists($logoPath)) $pdf->Image($logoPath, 12, 8, 60);

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(110, 10);
        $pdf->Cell(90, 8, strtoupper($issuance->type_label), 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(110, 18);
        $pdf->Cell(90, 6, $issuance->issue_no . '  ·  ' . $issuance->issue_date->format('d-M-Y'), 0, 1, 'R');
        $pdf->Ln(15);

        $info = '<table border="1" cellpadding="4" style="font-size:10px;">
            <tr><td width="20%"><b>Issued To</b></td><td width="30%">' . e($issuance->purchaseOrder->vendor->name ?? '-') . '</td>
                <td width="20%"><b>Against</b></td><td width="30%">' . e($issuance->purchaseOrder->order_no ?? '-') . '</td></tr>
            <tr><td><b>From</b></td><td>' . e($issuance->sourceLocation->name ?? '-') . '</td>
                <td><b>To</b></td><td>' . e($issuance->destinationLocation->name ?? ($issuance->purchaseOrder->vendor->name ?? '-')) . '</td></tr>
        </table>';
        $pdf->SetFont('helvetica', '', 10);
        $pdf->writeHTML($info, true, false, false, false, '');
        $pdf->Ln(3);

        $html = '<table border="1" cellpadding="4" style="font-size:10px;">
            <tr style="font-weight:bold;background-color:#f5f5f5;"><th width="6%">#</th><th width="44%">Item</th><th width="20%">Lot</th><th width="18%" align="right">Quantity</th><th width="12%">Unit</th></tr>';
        foreach ($issuance->items as $i => $item) {
            $html .= '<tr><td>' . ($i + 1) . '</td><td>' . e($item->product->name ?? '') . '</td><td>' . e($item->lot_no ?? '') . '</td>
                <td align="right">' . number_format($item->quantity, 3) . '</td><td>' . e($item->product->measurementUnit->shortcode ?? '') . '</td></tr>';
        }
        $html .= '<tr><td colspan="3" align="right"><b>Total</b></td><td align="right"><b>' . number_format($issuance->total_quantity, 3) . '</b></td><td></td></tr></table>';
        $pdf->writeHTML($html, true, false, false, false, '');

        if ($issuance->remarks) { $pdf->Ln(2); $pdf->MultiCell(0, 5, 'Remarks: ' . $issuance->remarks, 0, 'L'); }

        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->Line(20, $y, 80, $y); $pdf->Line(130, $y, 190, $y);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(20, $y + 1); $pdf->Cell(60, 6, 'Issued By', 0, 0, 'C');
        $pdf->SetXY(130, $y + 1); $pdf->Cell(60, 6, 'Received By (Mill)', 0, 0, 'C');

        return $pdf->Output($issuance->issue_no . '.pdf', 'I');
    }
}
