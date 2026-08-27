<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Location;
use App\Models\TaxMaster;
use App\Models\Forecast;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderService $service) {}

    public function index()
    {
        $orders = PurchaseOrder::with('vendor', 'category', 'fromLocation', 'dropOffLocation', 'items')
            ->visibleTo(auth()->user())
            ->orderByDesc('order_date')
            ->get();

        return view('purchase_orders.index', compact('orders'));
    }

    public function create()
    {
        $vendors    = Vendor::active()->orderBy('name')->get();
        $categories = ProductCategory::orderBy('name')->get();
        $products   = Product::active()->orderBy('name')->get();
        $taxes      = TaxMaster::active()->orderBy('rate', 'desc')->get();
        $dropOffLocations = Location::whereNull('vendor_id')->where('is_active', true)->orderBy('name')->get();

        return view('purchase_orders.create', compact('vendors', 'categories', 'products', 'taxes', 'dropOffLocations'));
    }

    public function vendorLocations($vendorId)
    {
        return response()->json($this->service->vendorLocations($vendorId));
    }

    public function categoryProducts($categoryId)
    {
        return response()->json($this->service->categoryProducts($categoryId));
    }

    // AJAX: approved forecasts for a given product, to optionally link
    public function forecastsForProduct($productId)
    {
        $forecasts = Forecast::approved()->where('product_id', $productId)->with('customer')->get();

        return response()->json($forecasts->map(fn($f) => [
            'id'            => $f->id,
            'forecast_no'   => $f->forecast_no,
            'customer_name' => $f->customer->name ?? 'General',
            'shortfall_qty' => (float) $f->shortfall_qty,
        ]));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id'               => 'required|exists:vendors,id',
            'product_category_id'     => 'required|exists:product_categories,id',
            'from_location_id'        => 'nullable|exists:locations,id',
            'drop_off_location_id'    => 'required|exists:locations,id',
            'forecast_id'             => 'nullable|exists:forecasts,id',
            'order_date'              => 'required|date',
            'expected_date'           => 'nullable|date|after_or_equal:order_date',
            'gst_applicable'          => 'required|boolean',
            'tax_id'                  => 'required_if:gst_applicable,1|nullable|exists:tax_masters,id',
            'remarks'                 => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity'        => 'required|numeric|min:0.001',
            'items.*.rate'            => 'required|numeric|min:0',
        ]);

        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('purchase_order_attachments', 'public');
                }
            }

            $order = $this->service->create(array_merge($request->all(), ['attachments' => $attachments ?: null]), $request->items, auth()->id());

            Log::info('[PurchaseOrder] Created', ['id' => $order->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_orders.index')->with('success', 'Purchase Order ' . $order->order_no . ' created successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseOrder] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $order = PurchaseOrder::with('items.product')->findOrFail($id);

        if (!$order->canBeEditedBy(auth()->user())) {
            abort(403, 'Only the creator or a superadmin can edit this Purchase Order.');
        }

        $vendors    = Vendor::active()->orderBy('name')->get();
        $categories = ProductCategory::orderBy('name')->get();
        $products   = Product::active()->orderBy('name')->get();
        $taxes      = TaxMaster::active()->orderBy('rate', 'desc')->get();
        $dropOffLocations = Location::whereNull('vendor_id')->where('is_active', true)->orderBy('name')->get();
        $fromLocations = Location::where('vendor_id', $order->vendor_id)->active()->get();

        return view('purchase_orders.edit', compact('order', 'vendors', 'categories', 'products', 'taxes', 'dropOffLocations', 'fromLocations'));
    }

    public function update(Request $request, $id)
    {
        $order = PurchaseOrder::findOrFail($id);

        if (!$order->canBeEditedBy(auth()->user())) {
            abort(403, 'Only the creator or a superadmin can edit this Purchase Order.');
        }

        $request->validate([
            'vendor_id'               => 'required|exists:vendors,id',
            'product_category_id'     => 'required|exists:product_categories,id',
            'from_location_id'        => 'nullable|exists:locations,id',
            'drop_off_location_id'    => 'required|exists:locations,id',
            'forecast_id'             => 'nullable|exists:forecasts,id',
            'order_date'              => 'required|date',
            'expected_date'           => 'nullable|date|after_or_equal:order_date',
            'gst_applicable'          => 'required|boolean',
            'tax_id'                  => 'required_if:gst_applicable,1|nullable|exists:tax_masters,id',
            'remarks'                 => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity'        => 'required|numeric|min:0.001',
            'items.*.rate'            => 'required|numeric|min:0',
        ]);

        try {
            $this->service->update($order, $request->all(), $request->items, auth()->id());

            Log::info('[PurchaseOrder] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('purchase_orders.index')->with('success', 'Purchase Order updated successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseOrder] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $order = PurchaseOrder::findOrFail($id);

            if (!$order->canBeEditedBy(auth()->user())) {
                abort(403, 'Only the creator or a superadmin can delete this Purchase Order.');
            }

            $this->service->delete($order);

            return redirect()->route('purchase_orders.index')->with('success', 'Purchase Order deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseOrder] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function print($id)
    {
        $order = PurchaseOrder::with([
            'vendor',
            'category',
            'items.product.measurementUnit',
        ])->findOrFail($id);

        $pdf = new \App\Services\myPDF();

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('VISTEX (Private) Limited');
        $pdf->SetTitle($order->order_no);
        $pdf->SetSubject('Purchase Order');
        $pdf->SetKeywords('PO, VISTEX, PDF');

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logoPath = public_path('assets/img/vistex-logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 60);
        }

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(120, 10);
        $pdf->Cell(80, 8, 'Purchase Order', 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(120, 18);
        $pdf->Cell(80, 6, $order->order_no . '  (Rev ' . $order->revision_no . ')', 0, 1, 'R');

        $pdf->Ln(15);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="100%">
            <tr>
                <td width="50%">
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr>
                            <td width="35%"><b>Vendor</b></td>
                            <td width="65%">' . e($order->vendor->name ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td width="35%"><b>Address</b></td>
                            <td width="65%">' . e($order->vendor->address ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td width="35%"><b>Category</b></td>
                            <td width="65%">' . e($order->category->name ?? '-') . '</td>
                        </tr>
                    </table>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr>
                            <td width="40%"><b>PO Date</b></td>
                            <td width="60%">' . \Carbon\Carbon::parse($order->order_date)->format('d-m-Y') . '</td>
                        </tr>
                        <tr>
                            <td width="40%"><b>Expected Date</b></td>
                            <td width="60%">' . ($order->expected_date ? \Carbon\Carbon::parse($order->expected_date)->format('d-m-Y') : '-') . '</td>
                        </tr>
                        <tr>
                            <td width="40%"><b>Status</b></td>
                            <td width="60%">' . e($order->status) . '</td>
                        </tr>
                        <tr>
                            <td width="40%"><b>GST</b></td>
                            <td width="60%">' . ($order->gst_applicable ? $order->gst_rate . '%' : 'Not Applicable') . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        $pdf->Ln(3);

        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold; background-color:#f5f5f5;">
                <th width="6%">#</th>
                <th width="34%">Item</th>
                <th width="14%">Quantity</th>
                <th width="12%">Unit</th>
                <th width="17%">Rate</th>
                <th width="17%">Amount</th>
            </tr>';

        $count    = 0;
        $totalQty = 0;

        foreach ($order->items as $item) {
            $count++;
            $amount = (float) $item->quantity * (float) $item->rate;
            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td style="text-align:left;">' . e($item->product->name ?? '') . '</td>
                <td>' . number_format($item->quantity, 3) . '</td>
                <td>' . e($item->product->measurementUnit->shortcode ?? '') . '</td>
                <td>' . number_format($item->rate, 2) . '</td>
                <td>' . number_format($amount, 2) . '</td>
            </tr>';
            $totalQty += $item->quantity;
        }

        $html .= '
        <tr>
            <td colspan="2" align="right"><b>Total</b></td>
            <td><b>' . number_format($totalQty, 3) . '</b></td>
            <td></td>
            <td></td>
            <td><b>' . number_format($order->subtotal, 2) . '</b></td>
        </tr>';

        if ($order->gst_applicable && $order->gst_amount > 0) {
            $html .= '
            <tr>
                <td colspan="5" align="right"><b>GST (' . number_format($order->gst_rate, 2) . '%)</b></td>
                <td><b>' . number_format($order->gst_amount, 2) . '</b></td>
            </tr>
            <tr>
                <td colspan="5" align="right"><b>Net Total</b></td>
                <td><b>' . number_format($order->total_amount, 2) . '</b></td>
            </tr>';
        }

        $html .= '</table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 6, 'Amount in Words: ' . $pdf->convertCurrencyToWords(round($order->total_amount)), 0, 1, 'L');

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

        return $pdf->Output($order->order_no . '_rev' . $order->revision_no . '.pdf', 'I');
    }
}