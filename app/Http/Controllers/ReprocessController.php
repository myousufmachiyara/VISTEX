<?php

namespace App\Http\Controllers;

use App\Models\{Location, PurchaseOrder, StockMovement};
use App\Services\ReprocessService;
use Illuminate\Http\Request;

class ReprocessController extends Controller
{
    public function __construct(private ReprocessService $service) {}

    public function index()
    {
        $orders = PurchaseOrder::whereNotNull('reprocess_of_po_id')->with('vendor', 'items', 'reprocessOf')
            ->orderByDesc('order_date')->orderByDesc('id')->get();
        $movements = StockMovement::whereIn('purchase_order_id', $orders->pluck('id'))->where('status', '!=', StockMovement::REJECTED)
            ->get()->keyBy('purchase_order_id');
        $hold = collect($this->service->available())->where('source', 'reprocess');
        return view('reprocess.index', compact('orders', 'movements', 'hold'));
    }

    public function create(Request $request)
    {
        $warehouses = Location::whereNull('vendor_id')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $mills = Location::whereNotNull('vendor_id')->where('is_active', true)->with('vendor:id,name')->orderBy('name')->get(['id', 'name', 'vendor_id']);
        return view('reprocess.create', [
            'warehouses' => $warehouses, 'mills' => $mills,
            'preset' => $request->only('location_id', 'lot_no'),
        ]);
    }

    // Lots that can be sent from a warehouse, with the mill that processed each lot
    public function available(Request $request)
    {
        $rows = $this->service->available((int) $request->location_id ?: null);
        $lots = collect($rows)->pluck('lot_no')->unique()->values();
        $vendors = PurchaseOrder::whereIn('order_no', $lots)->pluck('vendor_id', 'order_no');
        return response()->json(['rows' => $rows, 'lot_vendors' => $vendors]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'from_location_id' => 'required|exists:locations,id',
            'mill_location_id' => 'required|exists:locations,id',
            'lot_no'           => 'required|string',
            'is_foc'           => 'nullable|boolean',
            'order_date'       => 'required|date',
            'expected_date'    => 'nullable|date',
            'reason'           => 'nullable|string|max:500',
            'remarks'          => 'nullable|string',
            'lines'            => 'required|array',
            'lines.*.product_id' => 'required|integer',
            'lines.*.source'   => 'required|in:reprocess,A,B,C',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'rates'            => 'nullable|array',
        ]);
        try {
            $rates = $request->input('rates', []);
            $lines = array_map(fn($l) => $l + ['rate' => $rates[$l['product_id']] ?? 0], $request->input('lines'));
            $order = $this->service->create($request->only('from_location_id', 'mill_location_id', 'lot_no', 'order_date', 'expected_date', 'reason', 'remarks')
                + ['is_foc' => $request->boolean('is_foc')], $lines, auth()->id());
            $msg = $order->is_foc
                ? "{$order->order_no} created (FOC). Its gate pass is waiting for the warehouse in-charge's approval."
                : "{$order->order_no} sent for approval. The gate pass is created once it is approved.";
            return redirect()->route('purchase_orders.show', $order->id)->with('success', $msg);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
