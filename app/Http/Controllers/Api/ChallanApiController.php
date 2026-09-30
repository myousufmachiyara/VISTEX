<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Challan, PurchaseOrder, ProductCategory};
use App\Services\ChallanService;
use Illuminate\Http\Request;

class ChallanApiController extends Controller
{
    private const OPEN_STATUSES = ['Pending', 'Approved', 'Issued', 'PartiallyReceived'];
    private const VOICE_EXT = ['m4a', 'aac', 'mp3', 'wav', 'ogg', '3gp', 'webm', 'mp4'];

    public function __construct(private ChallanService $service) {}

    // Category tiles for the "IN" screen
    public function categories()
    {
        $counts = PurchaseOrder::whereIn('status', self::OPEN_STATUSES)
            ->selectRaw('product_category_id, COUNT(*) AS c')->groupBy('product_category_id')->pluck('c', 'product_category_id');

        return response()->json(ProductCategory::with('inchargeUsers')->orderBy('name')->get()->map(fn($c) => [
            'id' => $c->id, 'name' => $c->name, 'code' => $c->code,
            'open_po_count' => (int) ($counts[$c->id] ?? 0),
            'incharge_names' => $c->inchargeUsers->pluck('name')->values(),
        ]));
    }

    // POs of one category, grouped by vendor
    public function categoryPos(Request $request)
    {
        $request->validate(['category_id' => 'required|exists:product_categories,id']);
        $category = ProductCategory::with('inchargeUsers')->findOrFail($request->category_id);
        $incharges = $category->inchargeUsers->pluck('name')->values();

        $orders = PurchaseOrder::with('vendor')
            ->where('product_category_id', $category->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderByDesc('order_date')->get();

        $groups = $orders->groupBy('vendor_id')->map(fn($g) => [
            'vendor_id' => $g->first()->vendor_id,
            'vendor_name' => $g->first()->vendor->name ?? '—',
            'pos' => $g->map(function ($o) use ($incharges) {
                [$selectable, $note] = $this->receivableState($o);
                return [
                    'id' => $o->id, 'order_no' => $o->order_no, 'type' => $o->type, 'status' => $o->status,
                    'order_date' => $o->order_date->format('d-M-Y'),
                    'selectable' => $selectable, 'note' => $note,
                    'incharge_names' => $o->status === 'Pending' ? $incharges : [],
                ];
            })->values(),
        ])->sortBy('vendor_name')->values();

        return response()->json($groups);
    }

    private function receivableState(PurchaseOrder $o): array
    {
        if ($o->status === 'Pending') return [false, 'Awaiting approval'];
        if ($o->type === 'purchase') return [in_array($o->status, ['Approved', 'PartiallyReceived']), null];
        if (in_array($o->status, ['Issued', 'PartiallyReceived'])) return [true, null];
        return [false, 'Awaiting material issue'];
    }

    public function poExpectedItems($poId)
    {
        $po = PurchaseOrder::with('items.product')->findOrFail($poId);

        if ($po->type === 'weaving') {
            return response()->json([[
                'purchase_order_item_id' => null, 'product_id' => $po->greige_product_id,
                'description' => $po->item_name ?? 'Greige (per formula)', 'expected' => (float) $po->total_meters_required,
            ]]);
        }

        return response()->json($po->items->map(fn($i) => [
            'purchase_order_item_id' => $i->id, 'product_id' => $i->product_id,
            'description' => $i->product->name ?? $i->description ?? '',
            'expected' => round((float) $i->quantity - (float) $i->quantity_received, 3),
        ])->filter(fn($r) => $r['expected'] > 0.001)->values());
    }

    public function index(Request $request)
    {
        $challans = Challan::with('purchaseOrder.vendor', 'vendor')
            ->forCategoryIncharge($request->user())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('received_date')->orderByDesc('id')->get();

        return response()->json($challans->map(fn($c) => [
            'id' => $c->id, 'challan_no' => $c->challan_no, 'entry_type' => $c->entry_type,
            'vendor_name' => $c->display_vendor_name,
            'po_order_no' => $c->purchaseOrder->order_no ?? null,
            'received_date' => $c->received_date->format('Y-m-d'), 'status' => $c->status,
            'thumbnail' => !empty($c->challan_images) ? asset('storage/' . $c->challan_images[0]) : null,
        ]));
    }

    public function show($id)
    {
        $c = Challan::with('purchaseOrder.vendor', 'category', 'vendor', 'receivedBy', 'items.product', 'directItems')->findOrFail($id);

        return response()->json([
            'id' => $c->id, 'challan_no' => $c->challan_no, 'entry_type' => $c->entry_type,
            'category_name' => $c->category->name ?? null,
            'vendor_name' => $c->display_vendor_name,
            'po' => $c->purchaseOrder ? ['id' => $c->purchaseOrder->id, 'order_no' => $c->purchaseOrder->order_no, 'type' => $c->purchaseOrder->type] : null,
            'vendor_challan_no' => $c->vendor_challan_no,
            'received_date' => $c->received_date->format('Y-m-d'),
            'images' => collect($c->challan_images)->map(fn($p) => asset('storage/' . $p))->values(),
            'status' => $c->status,
            'has_objection' => $c->has_objection, 'objection_remarks' => $c->objection_remarks,
            'objection_voice_url' => $c->objection_voice_note ? asset('storage/' . $c->objection_voice_note) : null,
            'remarks' => $c->remarks, 'received_by' => $c->receivedBy->name ?? '', 'created_at' => $c->created_at,
            'items' => $c->items->map(fn($i) => [
                'id' => $i->id, 'product_name' => $i->product->name ?? $i->description,
                'expected_qty' => (float) $i->expected_qty, 'received_qty' => (float) $i->received_qty, 'decision' => $i->decision,
            ]),
            'direct_items' => $c->directItems->map(fn($i) => [
                'id' => $i->id, 'description' => $i->description, 'unit' => $i->unit,
                'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price, 'amount' => (float) $i->amount,
                'treatment' => $i->treatment,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'vendor_challan_no' => 'nullable|string|max:50',
            'received_date' => 'required|date',
            'has_objection' => 'nullable|boolean',
            'objection_remarks' => 'nullable|string|max:1000',
            'objection_voice' => 'nullable|file|max:10240',
            'remarks' => 'nullable|string',
            'challan_images' => 'required|array|min:1', 'challan_images.*' => 'file|image|max:5120',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.expected_qty' => 'required|numeric|min:0',
            'items.*.received_qty' => 'required|numeric|min:0',
        ]);

        if ($request->boolean('has_objection') && !$request->filled('objection_remarks') && !$request->hasFile('objection_voice')) {
            return response()->json(['message' => 'Add an objection note or a voice note.'], 422);
        }

        try {
            $voicePath = null;
            if ($request->hasFile('objection_voice')) {
                $file = $request->file('objection_voice');
                if (!in_array(strtolower($file->getClientOriginalExtension()), self::VOICE_EXT)) {
                    return response()->json(['message' => 'Unsupported voice note format.'], 422);
                }
                $voicePath = $file->store('challan_voice', 'public');
            }

            $images = [];
            foreach ($request->file('challan_images') as $f) $images[] = $f->store('challan_images', 'public');

            $challan = $this->service->create([
                'purchase_order_id' => $request->purchase_order_id, 'vendor_challan_no' => $request->vendor_challan_no,
                'received_date' => $request->received_date, 'has_objection' => $request->boolean('has_objection'),
                'objection_remarks' => $request->objection_remarks, 'objection_voice_note' => $voicePath,
                'challan_images' => $images, 'remarks' => $request->remarks,
            ], $request->items, $request->user()->id);

            return response()->json([
                'id' => $challan->id, 'challan_no' => $challan->challan_no, 'status' => $challan->status,
                'has_objection' => $challan->has_objection, 'message' => $challan->challan_no . ' logged successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function storeDirect(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:product_categories,id',
            'vendor_name' => 'required|string|max:191',
            'received_date' => 'required|date',
            'remarks' => 'nullable|string',
            'challan_images' => 'required|array|min:1', 'challan_images.*' => 'file|image|max:5120',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'nullable|string|max:30',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $images = [];
            foreach ($request->file('challan_images') as $f) $images[] = $f->store('challan_images', 'public');

            $challan = $this->service->createDirect([
                'category_id' => $request->category_id, 'vendor_name' => $request->vendor_name,
                'received_date' => $request->received_date, 'challan_images' => $images, 'remarks' => $request->remarks,
            ], $request->items, $request->user()->id);

            return response()->json([
                'id' => $challan->id, 'challan_no' => $challan->challan_no, 'status' => $challan->status,
                'message' => $challan->challan_no . ' logged — sent to category incharge for review.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}