<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceiving;
use App\Services\PurchaseReceivingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseReceivingController extends Controller
{
    public function __construct(private PurchaseReceivingService $service) {}

    public function index(Request $request)
    {
        $receivings = PurchaseReceiving::with('purchaseOrder.vendor', 'purchaseOrder.category', 'location', 'items.product')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('receiving_date')
            ->get();

        return view('purchase_receivings.index', compact('receivings'));
    }

    public function create()
    {
        $user = auth()->user();

        $purchaseOrders = PurchaseOrder::with('vendor', 'dropOffLocation', 'items.product')
            ->whereIn('status', ['Pending', 'PartiallyReceived'])
            ->when(!$user->hasRole('superadmin') && !$user->hasRole('gatekeeper'), function ($q) use ($user) {
                $q->whereHas('dropOffLocation', fn($q2) => $q2->where('in_charge_user_id', $user->id));
            })
            ->orderByDesc('order_date')
            ->get();

        return view('purchase_receivings.create', compact('purchaseOrders'));
    }

    public function outstanding($purchaseOrderId)
    {
        $po = PurchaseOrder::with('items.product')->findOrFail($purchaseOrderId);

        if (!$po->isReceivableBy(auth()->user())) {
            return response()->json(['error' => 'You are not authorized to receive this PO.'], 403);
        }

        $rows = $po->items->map(function ($item) {
            $outstanding = round((float) $item->quantity - (float) $item->quantity_received, 3);
            return [
                'purchase_order_item_id' => $item->id,
                'product_id'             => $item->product_id,
                'product_name'           => $item->product->name ?? '',
                'ordered'                => (float) $item->quantity,
                'already_received'       => (float) $item->quantity_received,
                'outstanding'            => $outstanding,
            ];
        })->filter(fn($row) => $row['outstanding'] > 0.001)->values();

        return response()->json($rows);
    }

    public function history($purchaseOrderId)
    {
        return response()->json($this->service->historyForPo($purchaseOrderId));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'               => 'required|exists:purchase_orders,id',
            'location_id'                      => 'required|exists:locations,id',
            'receiving_date'                   => 'required|date',
            'vendor_challan_no'                => 'required|string|max:50',
            'attachments'                       => 'required|array|min:1',
            'attachments.*'                     => 'file|max:5120',
            'remarks'                           => 'nullable|string',
            'items'                              => 'required|array|min:1',
            'items.*.purchase_order_item_id'   => 'required|integer|exists:purchase_order_items,id',
            'items.*.product_id'               => 'required|integer|exists:products,id',
            'items.*.quantity_received'        => 'required|numeric|min:0',
        ]);

        $po = PurchaseOrder::findOrFail($request->purchase_order_id);

        if (!$po->isReceivableBy(auth()->user())) {
            abort(403, 'Only this Purchase Order\'s drop-off location in-charge (or a gatekeeper) can receive it.');
        }

        try {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('purchase_receiving_attachments', 'public');
            }

            $receiving = $this->service->create([
                'purchase_order_id'  => $request->purchase_order_id,
                'location_id'        => $request->location_id,
                'receiving_date'     => $request->receiving_date,
                'vendor_challan_no'  => $request->vendor_challan_no,
                'remarks'            => $request->remarks,
                'attachments'        => $attachments,
            ], $request->items, auth()->id());

            Log::info('[PurchaseReceiving] Created', ['id' => $receiving->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_receivings.index')
                ->with('success', 'Receiving ' . $receiving->receiving_no . ' recorded — pending approval.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReceiving] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve($id)
    {
        $receiving = PurchaseReceiving::with('purchaseOrder')->findOrFail($id);

        if (!$receiving->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only this category\'s in-charge or a superadmin can approve this receiving.');
        }

        try {
            $this->service->approve($receiving, auth()->id());
            Log::info('[PurchaseReceiving] Approved', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'Receiving approved — stock and accounting posted.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReceiving] Approve failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $receiving = PurchaseReceiving::with('purchaseOrder')->findOrFail($id);

        if (!$receiving->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only this category\'s in-charge or a superadmin can reject this receiving.');
        }

        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->service->reject($receiving, auth()->id(), $request->reason);
            Log::info('[PurchaseReceiving] Rejected', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'Receiving rejected.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReceiving] Reject failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function objectionForm($purchaseOrderId)
    {
        $po = PurchaseOrder::with('vendor', 'items.product')->findOrFail($purchaseOrderId);

        if (!$po->isReceivableBy(auth()->user())) {
            abort(403, 'Only this Purchase Order\'s drop-off location in-charge (or a gatekeeper) can raise an objection.');
        }

        return view('purchase_receivings.objection', compact('po'));
    }

    public function objectionStore(Request $request, $purchaseOrderId)
    {
        $po = PurchaseOrder::findOrFail($purchaseOrderId);

        if (!$po->isReceivableBy(auth()->user())) {
            abort(403, 'Only this Purchase Order\'s drop-off location in-charge (or a gatekeeper) can raise an objection.');
        }

        $request->validate(['remarks' => 'required|string|max:1000']);

        $this->service->raiseObjection($purchaseOrderId, $request->remarks, auth()->id());

        return redirect()->route('purchase_receivings.create')
            ->with('success', "Objection raised on {$po->order_no}. The creator has been notified.");
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('superadmin')) {
            abort(403, 'Only a superadmin can delete a receiving.');
        }

        try {
            $receiving = PurchaseReceiving::findOrFail($id);
            $this->service->delete($receiving);

            return redirect()->route('purchase_receivings.index')->with('success', 'Receiving deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReceiving] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
    
    public function show($id)
    {
        $receiving = PurchaseReceiving::with([
            'purchaseOrder.vendor',
            'purchaseOrder.category',
            'location',
            'items.product.measurementUnit',
            'approver',
        ])->findOrFail($id);

        return view('purchase_receivings.show', compact('receiving'));
    }


    public function print($id)
    {
        $receiving = PurchaseReceiving::with([
            'purchaseOrder.vendor',
            'purchaseOrder.category',
            'location',
            'items.product.measurementUnit',
        ])->findOrFail($id);

        $pdf = new \App\Services\myPDF();

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('VISTEX (Private) Limited');
        $pdf->SetTitle($receiving->receiving_no);
        $pdf->SetSubject('Purchase Receiving (GRN)');
        $pdf->SetKeywords('GRN, VISTEX, PDF');

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logoPath = public_path('assets/img/vistex-logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 60);
        }

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(120, 10);
        $pdf->Cell(80, 8, 'Goods Received Note', 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(120, 18);
        $pdf->Cell(80, 6, $receiving->receiving_no, 0, 1, 'R');

        $pdf->Ln(15);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="100%">
            <tr>
                <td width="50%">
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr><td width="35%"><b>Vendor</b></td><td width="65%">' . e($receiving->purchaseOrder->vendor->name ?? '-') . '</td></tr>
                        <tr><td width="35%"><b>PO Number</b></td><td width="65%">' . e($receiving->purchaseOrder->order_no ?? '-') . '</td></tr>
                        <tr><td width="35%"><b>Category</b></td><td width="65%">' . e($receiving->purchaseOrder->category->name ?? '-') . '</td></tr>
                        <tr><td width="35%"><b>Vendor Challan #</b></td><td width="65%">' . e($receiving->vendor_challan_no ?? '-') . '</td></tr>
                    </table>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr><td width="40%"><b>Receiving Date</b></td><td width="60%">' . \Carbon\Carbon::parse($receiving->receiving_date)->format('d-m-Y') . '</td></tr>
                        <tr><td width="40%"><b>Received At</b></td><td width="60%">' . e($receiving->location->name ?? '-') . '</td></tr>
                        <tr><td width="40%"><b>Status</b></td><td width="60%">' . e($receiving->status) . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($infoHtml, true, false, false, false, '');
        $pdf->Ln(3);

        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold; background-color:#f5f5f5;">
                <th width="8%">#</th><th width="42%">Item</th><th width="15%">Quantity Received</th><th width="15%">Unit</th><th width="20%">Amount</th>
            </tr>';

        $count = 0; $totalQty = 0;
        foreach ($receiving->items as $item) {
            $count++;
            $html .= '<tr>
                <td>' . $count . '</td>
                <td style="text-align:left;">' . e($item->product->name ?? '') . '</td>
                <td>' . number_format($item->quantity_received, 3) . '</td>
                <td>' . e($item->product->measurementUnit->shortcode ?? '') . '</td>
                <td>' . number_format($item->amount, 2) . '</td>
            </tr>';
            $totalQty += $item->quantity_received;
        }
        $html .= '<tr>
            <td colspan="2" align="right"><b>Total</b></td>
            <td><b>' . number_format($totalQty, 3) . '</b></td>
            <td></td>
            <td><b>' . number_format($receiving->amount, 2) . '</b></td>
        </tr></table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        if ($receiving->remarks) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 6, 'Remarks:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $receiving->remarks, 0, 'L');
        }

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(15);
        $lineWidth = 60;
        $yPosition = $pdf->GetY();
        $pdf->Line(28, $yPosition, 20 + $lineWidth, $yPosition);
        $pdf->Line(130, $yPosition, 120 + $lineWidth, $yPosition);
        $pdf->Ln(5);
        $pdf->SetXY(23, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Received By', 0, 0, 'C');
        $pdf->SetXY(125, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Checked By', 0, 0, 'C');

        return $pdf->Output($receiving->receiving_no . '.pdf', 'I');
    }
}