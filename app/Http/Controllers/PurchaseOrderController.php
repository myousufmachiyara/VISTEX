<?php
namespace App\Http\Controllers;

use App\Models\{PurchaseOrder, Vendor, ProductCategory, Product, Location, TaxMaster, Broker, ServiceType, Forecast, MeasurementUnit, Job};
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderService $service) {}

    public function index(Request $request)
    {
        $orders = PurchaseOrder::with('vendor', 'category', 'dropOffLocation')
            ->visibleTo(auth()->user())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->type))
            ->orderByDesc('order_date')->get();
        return view('purchase_orders.index', compact('orders'));
    }

    private function formData()
    {
        $user = auth()->user();
        $categories = $user->hasRole('superadmin')
            ? ProductCategory::orderBy('name')->get()
            : ProductCategory::whereHas('incharges', fn($q) => $q->where('user_id', $user->id))->orderBy('name')->get();

        $vendors = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();
        $taxes = TaxMaster::active()->orderBy('rate', 'desc')->get();
        $brokers = Broker::active()->orderBy('name')->get();
        $serviceTypes = ServiceType::active()->orderBy('name')->get();
        $units = MeasurementUnit::orderBy('name')->get();
        $dropOffLocations = Location::whereNull('vendor_id')->where('is_active', true)->orderBy('name')->get();
        $approvedJobs = Job::approved()->with('customer')->orderByDesc('order_date')->get();

        $yarnCategory = ProductCategory::where('code', 'yarn')->first();
        $yarnProducts = $yarnCategory ? Product::active()->where('category_id', $yarnCategory->id)->orderBy('name')->get() : collect();
        $greigeCategory = ProductCategory::where('code', 'greige')->first();
        $greigeProducts = $greigeCategory ? Product::active()->where('category_id', $greigeCategory->id)->orderBy('name')->get() : collect();

        return compact('categories', 'vendors', 'products', 'taxes', 'brokers', 'serviceTypes', 'units', 'dropOffLocations', 'approvedJobs', 'yarnProducts', 'greigeProducts');
    }

    public function create() { return view('purchase_orders.create', $this->formData()); }

    public function edit($id)
    {
        $order = PurchaseOrder::with('items')->findOrFail($id);
        if (!$order->canBeEditedBy(auth()->user())) abort(403, 'Only the creator or a superadmin can edit a Pending PO.');
        return view('purchase_orders.edit', array_merge($this->formData(), ['order' => $order]));
    }

    public function vendorLocations($vendorId) { return response()->json($this->service->vendorLocations($vendorId)); }
    public function categoryProducts($categoryId) { return response()->json($this->service->categoryProducts($categoryId)); }

    public function forecastsForProduct($productId)
    {
        $forecasts = Forecast::approved()->where('product_id', $productId)->with('customer')->get();
        return response()->json($forecasts->map(fn($f) => [
            'id' => $f->id, 'forecast_no' => $f->forecast_no,
            'customer_name' => $f->customer->name ?? 'General', 'shortfall_qty' => (float) $f->shortfall_qty,
        ]));
    }

    public function jobItems($jobId)
    {
        $job = Job::with('items.product')->findOrFail($jobId);
        return response()->json([
            'collection' => $job->customer_reference,
            'items' => $job->items->map(fn($i) => [
                'id' => $i->id, 'pattern_code' => $i->product->sku ?? '', 'description' => $i->product->name ?? '',
                'outstanding' => $i->outstanding_qty, 'unit' => $i->measurement_unit,
            ]),
        ]);
    }

    private function rules(string $type): array
    {
        $base = [
            'type' => 'required|in:purchase,weaving,processing',
            'vendor_id' => 'required|exists:vendors,id',
            'product_category_id' => 'required|exists:product_categories,id',
            'drop_off_location_id' => 'required|exists:locations,id',
            'from_location_id' => 'nullable|exists:locations,id',
            'order_date' => 'required|date', 'expected_date' => 'nullable|date|after_or_equal:order_date',
            'broker_id' => 'nullable|exists:brokers,id',
            'broker_commission_amount' => 'required_with:broker_id|nullable|numeric|min:0',
            'payment_term_type' => 'required|in:cash,credit,pdc,other',
            'payment_term_days' => 'required_if:payment_term_type,credit,pdc|nullable|integer|min:1',
            'payment_term_note' => 'required_if:payment_term_type,other|nullable|string|max:255',
            'gst_applicable' => 'required|boolean', 'tax_id' => 'required_if:gst_applicable,1|nullable|exists:tax_masters,id',
            'remarks' => 'nullable|string',
        ];

        if ($type === 'purchase') {
            return array_merge($base, [
                'items' => 'required|array|min:1', 'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.001', 'items.*.rate' => 'required|numeric|min:0',
                'items.*.measurement_unit' => 'nullable|exists:measurement_units,id', 'items.*.forecast_id' => 'nullable|exists:forecasts,id',
            ]);
        }
        if ($type === 'weaving') {
            return array_merge($base, [
                'warp_product_id' => 'required|exists:products,id', 'weft_product_id' => 'required|exists:products,id',
                'greige_product_id' => 'nullable|exists:products,id',
                'warp_count' => 'required|numeric|min:0.01', 'weft_count' => 'required|numeric|min:0.01',
                'reed_count' => 'required|numeric|min:0.01', 'pick' => 'required|numeric|min:0.01', 'width' => 'required|numeric|min:0.01',
                'total_meters_required' => 'required|numeric|min:0.001', 'rate_per_pick' => 'required|numeric|min:0',
                'sizing_lbs' => 'nullable|numeric|min:0', 'warp_conversion_pct' => 'nullable|numeric|min:0',
                'warp_shrinkage_pct' => 'nullable|numeric|min:0|max:100', 'weft_shrinkage_pct' => 'nullable|numeric|min:0|max:100',
            ]);
        }
        return array_merge($base, [
            'job_id' => 'required|exists:jobs,id', 'program' => 'nullable|string|max:255', 'fabric_specs' => 'nullable|array',
            'items' => 'required|array|min:1', 'items.*.collection' => 'nullable|string|max:255',
            'items.*.pattern_code' => 'required|string|max:255', 'items.*.description' => 'nullable|string|max:255',
            'items.*.job_item_id' => 'nullable|exists:job_items,id', 'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.rate' => 'required|numeric|min:0', 'items.*.measurement_unit' => 'nullable|exists:measurement_units,id',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate($this->rules($request->type));
        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) $attachments[] = $file->store('purchase_order_attachments', 'public');
            }
            $order = $this->service->create(array_merge($request->all(), ['attachments' => $attachments ?: null]), $request->input('items', []), auth()->id());
            Log::info('[PurchaseOrder] Created', ['id' => $order->id, 'type' => $order->type, 'by' => auth()->id()]);
            return redirect()->route('purchase_orders.index')->with('success', $order->order_no . ' created — pending superadmin approval.');
        } catch (\Exception $e) {
            Log::error('[PurchaseOrder] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $order = PurchaseOrder::findOrFail($id);
        if (!$order->canBeEditedBy(auth()->user())) abort(403, 'Only the creator or a superadmin can edit a Pending PO.');

        $rules = $this->rules($order->type); unset($rules['type']);
        $request->validate($rules);

        try {
            $this->service->update($order, $request->all(), $request->input('items', []), auth()->id());
            return redirect()->route('purchase_orders.show', $order->id)->with('success', 'Purchase Order updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $order = PurchaseOrder::with([
            'vendor', 'category', 'serviceType', 'fromLocation', 'dropOffLocation', 'broker', 'tax', 'forecast',
            'approver', 'creator', 'job', 'items.product.measurementUnit', 'items.forecast', 'items.jobItem',
            'warpProduct', 'weftProduct', 'greigeProduct', 'openObjections.raisedBy',
            'yarnIssues.items.product', 'processingIssues.items.product',
            'receivings.items.product', 'receivings.challan', // ← make sure this line is present
            'amendments',
        ])->findOrFail($id);
        return view('purchase_orders.show', compact('order'));
    }

    public function destroy($id)
    {
        try {
            $order = PurchaseOrder::findOrFail($id);
            if (!$order->canBeEditedBy(auth()->user())) abort(403, 'Only the creator or a superadmin can delete a Pending PO.');
            $this->service->delete($order);
            return redirect()->route('purchase_orders.index')->with('success', 'Purchase Order deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve($id)
    {
        $order = PurchaseOrder::findOrFail($id);
        if (!$order->canBeApprovedBy(auth()->user())) abort(403, 'Only a superadmin can approve a Purchase Order.');
        try {
            $this->service->approve($order, auth()->id());
            return back()->with('success', $order->order_no . ' approved.');
        } catch (\Exception $e) { return back()->with('error', $e->getMessage()); }
    }

    public function reject(Request $request, $id)
    {
        $order = PurchaseOrder::findOrFail($id);
        if (!$order->canBeApprovedBy(auth()->user())) abort(403, 'Only a superadmin can reject a Purchase Order.');
        $request->validate(['reason' => 'required|string|max:500']);
        try {
            $this->service->reject($order, auth()->id(), $request->reason);
            return back()->with('success', $order->order_no . ' rejected.');
        } catch (\Exception $e) { return back()->with('error', $e->getMessage()); }
    }

    public function print($id)
    {
        $order = PurchaseOrder::with([
            'vendor', 'category', 'serviceType', 'fromLocation', 'dropOffLocation',
            'broker', 'tax', 'items.product.measurementUnit', 'terms',
        ])->findOrFail($id);

        $pdf = new \App\Services\myPDF();

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('VISTEX (Private) Limited');
        $pdf->SetTitle($order->order_no);
        $pdf->SetSubject('Purchase Order');

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logoPath = public_path('assets/img/vistex-logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 6, 8, 60);
        }

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(120, 10);
        $pdf->Cell(80, 8, 'PURCHASE ORDER', 0, 1, 'R');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(120, 20);
        $pdf->Cell(80, 6, $order->order_no, 0, 1, 'R');

        $pdf->Ln(10);

        // ── Vendor (left) / Company (right) — single bordered boxes, not tables ──
        $vendorBox = '
        <table cellpadding="6" cellspacing="0" width="100%">
        <tr>
            <td width="50%" style="border:1px solid #333; vertical-align:top;">
            <b style="font-size:11px;">SUPPLIER</b><br><br>
            <b>' . e($order->vendor->name ?? '-') . '</b><br>
            NTN: ' . e($order->vendor->ntn_number ?? '-') . '<br>
            STRN: ' . e($order->vendor->strn_number ?? '-') . '<br>
            ' . nl2br(e($order->vendor->address ?? '-')) . '
            </td>
            <td width="4%"></td>
            <td width="46%" style="border:1px solid #333; vertical-align:top;">
            <b style="font-size:11px;">BUYER</b><br><br>
            <b>VISTEX (Private) Limited</b><br>
            NTN: 1234567-8<br>
            STRN: 12-34-5678-901-23<br>
            F-128, Hub River Road, SITE Area, Karachi 75600, Pakistan
            </td>
        </tr>
        </table>';

        $pdf->writeHTML($vendorBox, true, false, false, false, '');
        $pdf->Ln(4);

        // ── PO Details section ──
        $detailsHtml = '
        <table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #333; font-size:10px;">
        <tr>
            <td width="25%"><b>PO Date</b><br>' . \Carbon\Carbon::parse($order->order_date)->format('d-M-Y') . '</td>
            <td width="25%"><b>Expected Date</b><br>' . ($order->expected_date ? \Carbon\Carbon::parse($order->expected_date)->format('d-M-Y') : '-') . '</td>
            <td width="25%"><b>Broker</b><br>' . e($order->broker->name ?? '-') . '</td>
            <td width="25%"><b>Payment Terms</b><br>' . e(ucfirst($order->payment_term_type)) . ($order->payment_term_days ? ' (' . $order->payment_term_days . ' days)' : '') . '</td>
        </tr>
        </table>';

        $pdf->writeHTML($detailsHtml, true, false, false, false, '');
        $pdf->Ln(4);

        // ── Items table — clean borders, no footer totals here ──
        $itemsHtml = '
        <table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #333; font-size:10px;">
        <tr style="font-weight:bold; background-color:#f0f0f0;">
            <th width="6%" style="border:1px solid #333; text-align:center;">#</th>
            <th width="38%" style="border:1px solid #333;">Item</th>
            <th width="14%" style="border:1px solid #333; text-align:center;">Quantity</th>
            <th width="12%" style="border:1px solid #333; text-align:center;">Unit</th>
            <th width="15%" style="border:1px solid #333; text-align:right;">Rate</th>
            <th width="15%" style="border:1px solid #333; text-align:right;">Amount</th>
        </tr>';

        $count = 0;
        foreach ($order->items as $item) {
            $count++;
            $amount = (float) $item->quantity * (float) $item->rate;
            $itemsHtml .= '
        <tr>
            <td style="border:1px solid #333; text-align:center;">' . $count . '</td>
            <td style="border:1px solid #333;">' . e($item->product->name ?? '') . '</td>
            <td style="border:1px solid #333; text-align:center;">' . number_format($item->quantity, 3) . '</td>
            <td style="border:1px solid #333; text-align:center;">' . e($item->product->measurementUnit->shortcode ?? '') . '</td>
            <td style="border:1px solid #333; text-align:right;">' . number_format($item->rate, 2) . '</td>
            <td style="border:1px solid #333; text-align:right;">' . number_format($amount, 2) . '</td>
        </tr>';
        }
        $itemsHtml .= '</table>';

        $pdf->writeHTML($itemsHtml, true, false, false, false, '');
        $pdf->Ln(3);

        // ── Summary — right-aligned, professional ──
        $summaryHtml = '
        <table cellpadding="4" cellspacing="0" width="100%">
        <tr>
            <td width="60%"></td>
            <td width="40%">
            <table cellpadding="4" cellspacing="0" width="100%" style="border:1px solid #333; font-size:10px;">
                <tr><td width="50%">Amount</td><td width="50%" style="text-align:right;">' . number_format($order->subtotal, 2) . '</td></tr>';

        if ($order->gst_applicable && $order->gst_amount > 0) {
            $summaryHtml .= '<tr><td>GST (' . number_format($order->gst_rate, 2) . '%)</td><td style="text-align:right;">' . number_format($order->gst_amount, 2) . '</td></tr>';
        }
        if ($order->broker_commission_amount > 0) {
            $summaryHtml .= '<tr><td>Broker Commission</td><td style="text-align:right;">' . number_format($order->broker_commission_amount, 2) . '</td></tr>';
        }

        $summaryHtml .= '
                <tr style="font-weight:bold; background-color:#f0f0f0;">
                <td>Net Total</td><td style="text-align:right;">' . number_format($order->total_amount, 2) . '</td>
                </tr>
            </table>
            </td>
        </tr>
        </table>';

        $pdf->writeHTML($summaryHtml, true, false, false, false, '');
        $pdf->Ln(4);

        // ── Amount in words — bold, readable ──
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->MultiCell(0, 6, 'Amount in Words: ' . $pdf->convertCurrencyToWords(round($order->total_amount)), 0, 'L');
        $pdf->SetFont('helvetica', '', 10);

        // ── Terms & Conditions ──
        if ($order->terms->isNotEmpty()) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 6, 'Terms & Conditions:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 8);
            foreach ($order->terms as $i => $term) {
                $pdf->MultiCell(0, 4, ($i + 1) . '. ' . $term->title . ' — ' . $term->description, 0, 'L');
            }
        }

        if ($order->remarks) {
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 6, 'Remarks:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $order->remarks, 0, 'L');
        }

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(15);
        $lineWidth = 60;
        $yPosition = $pdf->GetY();
        $pdf->Line(28, $yPosition, 20 + $lineWidth, $yPosition);
        $pdf->Line(130, $yPosition, 120 + $lineWidth, $yPosition);
        $pdf->Ln(5);
        $pdf->SetXY(23, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Prepared By', 0, 0, 'C');
        $pdf->SetXY(125, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Approved By', 0, 0, 'C');

        return $pdf->Output($order->order_no . '.pdf', 'I');
    }
}