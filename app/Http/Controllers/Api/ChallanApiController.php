<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Challan, PurchaseOrder, Vendor, ChartOfAccounts};
use App\Services\ChallanService;
use Illuminate\Http\Request;

class ChallanApiController extends Controller
{
    public function __construct(private ChallanService $service) {}

    public function vendorsForType(Request $request)
    {
        $request->validate(['type' => 'required|in:purchase,weaving,processing']);

        $statuses = $request->type === 'purchase'
            ? ['Pending', 'Approved', 'PartiallyReceived']
            : ['Pending', 'Issued', 'PartiallyReceived'];

        $vendorIds = PurchaseOrder::where('type', $request->type)
            ->whereIn('status', $statuses)
            ->distinct()->pluck('vendor_id');

        return response()->json(
            Vendor::whereIn('id', $vendorIds)->orderBy('name')->get(['id', 'name'])
        );
    }

    public function posForVendor(Request $request)
    {
        $request->validate([
            'type' => 'required|in:purchase,weaving,processing',
            'vendor_id' => 'required|exists:vendors,id',
        ]);

        $statuses = $request->type === 'purchase'
            ? ['Pending', 'Approved', 'PartiallyReceived']
            : ['Pending', 'Issued', 'PartiallyReceived'];

        $orders = PurchaseOrder::where('type', $request->type)
            ->where('vendor_id', $request->vendor_id)
            ->whereIn('status', $statuses)
            ->orderByDesc('order_date')
            ->get(['id', 'order_no', 'status']);

        return response()->json($orders->map(fn($o) => [
            'id' => $o->id, 'order_no' => $o->order_no, 'status' => $o->status,
            'selectable' => $o->status !== 'Pending',
        ]));
    }

    public function poExpectedItems($poId)
    {
        $po = PurchaseOrder::with('items.product')->findOrFail($poId);

        if ($po->type === 'weaving') {
            return response()->json([[
                'purchase_order_item_id' => null,
                'product_id' => $po->greige_product_id,
                'description' => $po->item_name ?? 'Greige (per formula)',
                'expected' => (float) $po->total_meters_required,
            ]]);
        }

        return response()->json($po->items->map(fn($i) => [
            'purchase_order_item_id' => $i->id,
            'product_id' => $i->product_id,
            'description' => $i->product->name ?? $i->description ?? '',
            'expected' => round((float) $i->quantity - (float) $i->quantity_received, 3),
        ])->filter(fn($row) => $row['expected'] > 0.001)->values());
    }

    public function expenseAccounts()
    {
        return response()->json(
            ChartOfAccounts::active()->where('account_type', 'expense')->orderBy('name')->get(['id', 'name'])
        );
    }

    public function index(Request $request)
    {
        $challans = Challan::with('purchaseOrder.vendor', 'vendor')
            ->forCategoryIncharge($request->user())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('received_date')
            ->get();

        return response()->json($challans->map(fn($c) => [
            'id' => $c->id, 'challan_no' => $c->challan_no, 'entry_type' => $c->entry_type,
            'vendor_name' => $c->display_vendor->name ?? '',
            'po_order_no' => $c->purchaseOrder->order_no ?? null,
            'received_date' => $c->received_date->format('Y-m-d'),
            'status' => $c->status,
            'thumbnail' => !empty($c->challan_images) ? asset('storage/' . $c->challan_images[0]) : null,
        ]));
    }

    public function show($id)
    {
        $c = Challan::with('purchaseOrder.vendor', 'vendor', 'receivedBy', 'items.product', 'directItems.expenseAccount')
            ->findOrFail($id);

        return response()->json([
            'id' => $c->id, 'challan_no' => $c->challan_no, 'entry_type' => $c->entry_type,
            'vendor_name' => $c->display_vendor->name ?? '',
            'po' => $c->purchaseOrder ? [
                'id' => $c->purchaseOrder->id, 'order_no' => $c->purchaseOrder->order_no, 'type' => $c->purchaseOrder->type,
            ] : null,
            'vendor_challan_no' => $c->vendor_challan_no,
            'received_date' => $c->received_date->format('Y-m-d'),
            'images' => collect($c->challan_images)->map(fn($p) => asset('storage/' . $p))->values(),
            'status' => $c->status,
            'has_objection' => $c->has_objection,
            'objection_remarks' => $c->objection_remarks,
            'remarks' => $c->remarks,
            'received_by' => $c->receivedBy->name ?? '',
            'created_at' => $c->created_at,
            'items' => $c->items->map(fn($i) => [
                'id' => $i->id, 'product_name' => $i->product->name ?? $i->description,
                'expected_qty' => $i->expected_qty, 'received_qty' => $i->received_qty, 'decision' => $i->decision,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'          => 'required|exists:purchase_orders,id',
            'vendor_challan_no'          => 'nullable|string|max:50',
            'received_date'              => 'required|date',
            'has_objection'              => 'nullable|boolean',
            'objection_remarks'          => 'required_if:has_objection,1|nullable|string|max:1000',
            'remarks'                    => 'nullable|string',
            'challan_images'              => 'required|array|min:1',
            'challan_images.*'            => 'file|image|max:5120',
            'items'                        => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id'         => 'nullable|exists:products,id',
            'items.*.expected_qty'       => 'required|numeric|min:0',
            'items.*.received_qty'       => 'required|numeric|min:0',
        ]);

        try {
            $images = [];
            foreach ($request->file('challan_images') as $file) {
                $images[] = $file->store('challan_images', 'public');
            }

            $challan = $this->service->create([
                'purchase_order_id' => $request->purchase_order_id,
                'vendor_challan_no' => $request->vendor_challan_no,
                'received_date'     => $request->received_date,
                'has_objection'     => $request->boolean('has_objection'),
                'objection_remarks' => $request->objection_remarks,
                'challan_images'    => $images,
                'remarks'           => $request->remarks,
            ], $request->items, $request->user()->id);

            return response()->json([
                'id' => $challan->id, 'challan_no' => $challan->challan_no,
                'status' => $challan->status, 'has_objection' => $challan->has_objection,
                'message' => $challan->challan_no . ' logged successfully.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function storeDirect(Request $request)
    {
        $request->validate([
            'direct_vendor_id'                    => 'required|exists:vendors,id',
            'received_date_direct'                => 'required|date',
            'challan_images'                        => 'required|array|min:1',
            'challan_images.*'                      => 'file|image|max:5120',
            'remarks'                              => 'nullable|string',
            'direct_items'                          => 'required|array|min:1',
            'direct_items.*.description'          => 'required|string|max:255',
            'direct_items.*.quantity'             => 'required|numeric|min:0.001',
            'direct_items.*.unit_price'           => 'required|numeric|min:0',
            'direct_items.*.expense_account_id'   => 'required|exists:chart_of_accounts,id',
        ]);

        try {
            $images = [];
            foreach ($request->file('challan_images') as $file) {
                $images[] = $file->store('challan_images', 'public');
            }

            $challan = $this->service->createDirect([
                'vendor_id'      => $request->direct_vendor_id,
                'received_date'  => $request->received_date_direct,
                'challan_images' => $images,
                'remarks'        => $request->remarks,
            ], $request->direct_items, $request->user()->id);

            return response()->json([
                'id' => $challan->id, 'challan_no' => $challan->challan_no,
                'status' => $challan->status,
                'message' => $challan->challan_no . ' logged — pending approval.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}