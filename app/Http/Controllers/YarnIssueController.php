<?php

namespace App\Http\Controllers;

use App\Models\YarnIssue;
use App\Models\ConversionPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\Location;
use App\Models\LocationStockLedger;
use App\Services\YarnIssueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YarnIssueController extends Controller
{
    public function __construct(private YarnIssueService $service) {}

    public function index()
    {
        $issues = YarnIssue::with('purchaseOrder.vendor', 'items.product')->orderByDesc('issue_date')->get();
        return view('yarn_issues.index', compact('issues'));
    }

    public function create()
    {
        $orders = PurchaseOrder::where('type', 'weaving')
            ->whereIn('status', ['Approved', 'PartiallyReceived'])
            ->with('vendor', 'warpProduct', 'weftProduct')
            ->orderByDesc('order_date')
            ->get();

        return view('yarn_issues.create', ['cpos' => $orders]);
    }

    public function cpoDetails($poId)
    {
        $order = PurchaseOrder::with('warpProduct', 'weftProduct')->findOrFail($poId);

        $warpBalance = \App\Models\YarnInProcessLedger::balanceForCpoProduct($order->id, $order->warp_product_id);
        $weftBalance = \App\Models\YarnInProcessLedger::balanceForCpoProduct($order->id, $order->weft_product_id);

        $defaultLocationId = \App\Models\Location::whereNull('vendor_id')->value('id');

        $warpAvailable = \App\Models\LocationStockLedger::balance($defaultLocationId, $order->warp_product_id, 'fresh');
        $weftAvailable = \App\Models\LocationStockLedger::balance($defaultLocationId, $order->weft_product_id, 'fresh');

        return response()->json([
            'warp_product_id'     => $order->warp_product_id,
            'warp_product_name'   => $order->warpProduct->name ?? '',
            'warp_available'      => $warpAvailable,
            'weft_product_id'     => $order->weft_product_id,
            'weft_product_name'   => $order->weftProduct->name ?? '',
            'weft_available'      => $weftAvailable,
            'total_yarn_required' => (float) $order->total_yarn_weight_consumed,
            'already_issued'      => (float) $order->yarn_issued_total,
            'outstanding'         => round((float) $order->total_yarn_weight_consumed - (float) $order->yarn_issued_total, 3),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'issue_date'         => 'required|date',
            'remarks'            => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
        ]);

        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('yarn_issue_attachments', 'public');
                }
            }

            $issue = $this->service->create([
                'purchase_order_id' => $request->purchase_order_id,
                'issue_date'         => $request->issue_date,
                'remarks'            => $request->remarks,
                'attachments'        => $attachments ?: null,
            ], $request->items, auth()->id());

            Log::info('[YarnIssue] Created', ['id' => $issue->id, 'by' => auth()->id()]);

            return redirect()->route('yarn_issues.index')->with('success', $issue->issue_no . ' created successfully.');

        } catch (\Exception $e) {
            Log::error('[YarnIssue] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
    public function destroy($id)
    {
        try {
            $issue = YarnIssue::findOrFail($id);
            $this->service->delete($issue);
            return redirect()->route('yarn_issues.index')->with('success', 'Yarn issue deleted.');
        } catch (\Exception $e) {
            Log::error('[YarnIssue] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
    
    public function edit($id)
    {
        $issue = YarnIssue::with('items.product', 'purchaseOrder')->findOrFail($id);

        if (\App\Models\PurchaseReceiving::where('purchase_order_id', $issue->purchase_order_id)->exists()) {
            return redirect()->route('yarn_issues.index')->with('error', 'Cannot edit — greige has already been received against this Purchase Order.');
        }

        return view('yarn_issues.edit', compact('issue'));
    }

    public function update(Request $request, $id)
    {
        $issue = YarnIssue::findOrFail($id);

        $request->validate([
            'issue_date' => 'required|date',
            'remarks'    => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
        ]);

        try {
            $attachments = $issue->attachments ?? [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('yarn_issue_attachments', 'public');
                }
            }

            $this->service->update($issue, array_merge($request->all(), ['attachments' => $attachments ?: null]), $request->items, auth()->id());

            return redirect()->route('yarn_issues.index')->with('success', 'Yarn Issue updated successfully.');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function print($id)
    {
        $issue = YarnIssue::with('items.product.measurementUnit', 'purchaseOrder.vendor')->findOrFail($id);

        $pdf = new \App\Services\myPDF();

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('VISTEX (Private) Limited');
        $pdf->SetTitle($issue->issue_no);
        $pdf->SetSubject('Yarn Issue');
        $pdf->SetKeywords('Yarn Issue, VISTEX, PDF');

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logoPath = public_path('assets/img/vistex-logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 60);
        }

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetXY(120, 10);
        $pdf->Cell(80, 8, 'Yarn Issue', 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY(120, 18);
        $pdf->Cell(80, 6, $issue->issue_no, 0, 1, 'R');

        $pdf->Ln(15);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="100%">
            <tr>
                <td width="50%">
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr>
                            <td width="35%"><b>Weaving Mill</b></td>
                            <td width="65%">' . e($issue->purchaseOrder->vendor->name ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td width="35%"><b>PO Number</b></td>
                            <td width="65%">' . e($issue->purchaseOrder->order_no ?? '-') . '</td>
                        </tr>
                    </table>
                </td>
                <td width="50%">
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr>
                            <td width="40%"><b>Issue Date</b></td>
                            <td width="60%">' . \Carbon\Carbon::parse($issue->issue_date)->format('d-m-Y') . '</td>
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
                <th width="8%">#</th><th width="42%">Yarn</th><th width="15%">Quantity Issued</th>
                <th width="10%">Unit</th><th width="12%">Rate</th><th width="13%">Amount</th>
            </tr>';

        $count = 0; $totalQty = 0; $totalAmount = 0;
        foreach ($issue->items as $item) {
            $count++;
            $html .= '<tr>
                <td>' . $count . '</td>
                <td style="text-align:left;">' . e($item->product->name ?? '') . '</td>
                <td>' . number_format($item->quantity, 3) . '</td>
                <td>' . e($item->product->measurementUnit->shortcode ?? '') . '</td>
                <td>' . number_format($item->rate, 4) . '</td>
                <td>' . number_format($item->amount, 2) . '</td>
            </tr>';
            $totalQty += $item->quantity;
            $totalAmount += $item->amount;
        }

        $html .= '<tr>
            <td colspan="2" align="right"><b>Total</b></td>
            <td><b>' . number_format($totalQty, 3) . '</b></td>
            <td></td><td></td>
            <td><b>' . number_format($totalAmount, 2) . '</b></td>
        </tr></table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        if ($issue->remarks) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 6, 'Remarks:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $issue->remarks, 0, 'L');
        }

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(15);
        $lineWidth = 60;
        $yPosition = $pdf->GetY();
        $pdf->Line(28, $yPosition, 20 + $lineWidth, $yPosition);
        $pdf->Line(130, $yPosition, 120 + $lineWidth, $yPosition);
        $pdf->Ln(5);
        $pdf->SetXY(23, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Issued By', 0, 0, 'C');
        $pdf->SetXY(125, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Received By (Mill)', 0, 0, 'C');

        return $pdf->Output($issue->issue_no . '.pdf', 'I');
    }
}