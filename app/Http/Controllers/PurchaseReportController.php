<?php
namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\ConversionPurchaseOrder;
use Illuminate\Http\Request;

class PurchaseReportController extends Controller
{
    public function index() { return view('reports.purchase'); }

    public function purchaseOrders(Request $request)
    {
        $orders = PurchaseOrder::with('vendor', 'category')
            ->when($request->filled('from_date'), fn($q) => $q->where('order_date', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn($q) => $q->where('order_date', '<=', $request->to_date))
            ->when($request->filled('category_id'), fn($q) => $q->where('product_category_id', $request->category_id))
            ->orderByDesc('order_date')->get();

        $summary = [
            'total_ordered_value'  => $orders->sum('total_amount'),
            'fully_received'       => $orders->where('status', 'Received')->count(),
            'partially_received'   => $orders->where('status', 'PartiallyReceived')->count(),
            'pending'               => $orders->where('status', 'Pending')->count(),
        ];

        return view('reports.purchase_orders', compact('orders', 'summary'));
    }

    public function weaving(Request $request)
    {
        $cpos = ConversionPurchaseOrder::with('vendor', 'warpProduct', 'weftProduct')
            ->when($request->filled('from_date'), fn($q) => $q->where('po_date', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn($q) => $q->where('po_date', '<=', $request->to_date))
            ->orderByDesc('po_date')->get()
            ->map(function ($cpo) {
                $cpo->yarn_issued = $cpo->yarn_issued_total;
                $cpo->greige_received = $cpo->greigeReceives->sum(fn($r) => $r->outputs->sum('quantity_output'));
                $cpo->total_weaving_charge = $cpo->greigeReceives->sum('weaving_charge_amount');
                return $cpo;
            });

        return view('reports.weaving', compact('cpos'));
    }
}