<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ChallanController as WebChallan;
use App\Models\{Challan, CategoryIncharge, PurchaseOrder, ProductCategory};
use App\Services\{ChallanReviewService, ChallanService};
use Illuminate\Http\Request;

class ChallanApiController extends Controller
{
    private const OPEN_STATUSES = ['Pending', 'Approved', 'Issued', 'PartiallyReceived'];
    private const VOICE_EXT = ['m4a', 'aac', 'mp3', 'wav', 'ogg', '3gp', 'webm', 'mp4'];

    public function __construct(
        private ChallanService $service,
        private ChallanReviewService $reviewService,
    ) {}

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
            ->visibleTo($request->user())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('received_date')->orderByDesc('id')->get();

        return response()->json($challans->map(fn($c) => [
            'id' => $c->id, 'challan_no' => $c->challan_no, 'entry_type' => $c->entry_type,
            'vendor_name' => $c->display_vendor_name,
            'po_order_no' => $c->purchaseOrder->order_no ?? null,
            'received_date' => $c->received_date->format('Y-m-d'), 'status' => $c->status,
            'status_label' => $c->status_label,
            'thumbnail' => !empty($c->challan_images) ? \App\Support\Media::url($c->challan_images[0]) : null,
        ]));
    }

    public function show($id)
    {
        $c = Challan::with('purchaseOrder.vendor', 'category', 'vendor', 'receivedBy', 'reviewedBy', 'receiving', 'items.product', 'directItems')->findOrFail($id);

        return response()->json([
            'id' => $c->id, 'challan_no' => $c->challan_no, 'entry_type' => $c->entry_type,
            'category_name' => $c->category->name ?? null,
            'vendor_name' => $c->display_vendor_name,
            'po' => $c->purchaseOrder ? ['id' => $c->purchaseOrder->id, 'order_no' => $c->purchaseOrder->order_no, 'type' => $c->purchaseOrder->type] : null,
            'category_id' => $c->category_id ?? $c->purchaseOrder?->product_category_id,
            'direct_vendor_name' => $c->direct_vendor_name,
            'vendor_challan_no' => $c->vendor_challan_no,
            'vehicle_no' => $c->vehicle_no, 'driver_name' => $c->driver_name, 'driver_contact' => $c->driver_contact,
            'received_date' => $c->received_date->format('Y-m-d'),
            'images' => collect($c->challan_images)->map(fn($p) => \App\Support\Media::url($p))->values(),
            // raw paths, sent back as keep_images[] when editing
            'image_paths' => collect($c->challan_images)->values(),
            'can_edit' => $c->canBeEditedBy(request()->user()),
            'status' => $c->status, 'status_label' => $c->status_label,
            'decision' => $c->decision, 'decision_remarks' => $c->decision_remarks,
            'reviewed_by' => $c->reviewedBy->name ?? null,
            'grn_no' => $c->receiving->receiving_no ?? null,
            'can_review' => $c->isAwaitingReview() && $c->entry_type === 'po' && $c->canBeReviewedBy(request()->user()),
            'has_objection' => $c->has_objection, 'objection_remarks' => $c->objection_remarks,
            'objection_voice_url' => $c->objection_voice_note ? \App\Support\Media::url($c->objection_voice_note) : null,
            'remarks' => $c->remarks, 'received_by' => $c->receivedBy->name ?? '', 'created_at' => $c->created_at,
            'items' => $c->items->map(fn($i) => [
                'id' => $i->id, 'product_name' => $i->product->name ?? $i->description,
                'expected_qty' => (float) $i->expected_qty, 'received_qty' => (float) $i->received_qty,
                'accepted_qty' => (float) $i->accepted_qty, 'rejected_qty' => (float) $i->rejected_qty, 'decision' => $i->decision,
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
            ...WebChallan::TRANSPORT_RULES,
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
                $voicePath = $this->storeVoice($request->file('objection_voice'));
                if (!$voicePath) return response()->json(['message' => 'Unsupported voice note format.'], 422);
            }

            $images = [];
            foreach ($request->file('challan_images') as $f) $images[] = $f->store('challan_images', 'public');

            $challan = $this->service->create([
                'purchase_order_id' => $request->purchase_order_id, 'vendor_challan_no' => $request->vendor_challan_no,
                ...$request->only(WebChallan::TRANSPORT_FIELDS),
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
            ...WebChallan::TRANSPORT_RULES,
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
                ...$request->only(WebChallan::TRANSPORT_FIELDS),
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

    // Edit before the incharge decides. Multipart POST:
    //   received_date, remarks, vehicle_no, driver_name, driver_contact,
    //   keep_images[] (paths from show.image_paths), challan_images[] (new photos),
    //   PO:     vendor_challan_no, has_objection, objection_remarks, objection_voice (new file), remove_voice, items[i][id], items[i][received_qty]
    //   direct: category_id, vendor_name, items[i][description|quantity|unit|unit_price]
    public function update(Request $request, $id)
    {
        $challan = Challan::findOrFail($id);
        if (!$challan->canBeEditedBy($request->user())) {
            return response()->json(['message' => $challan->isAwaitingReview()
                ? 'You can only edit challans you logged, or those of your category.'
                : "This challan is {$challan->status_label} and can no longer be edited."], 403);
        }

        $rules = [
            'received_date'    => 'required|date',
            'remarks'          => 'nullable|string',
            'keep_images'      => 'nullable|array',
            'keep_images.*'    => 'string',
            'challan_images'   => 'nullable|array',
            'challan_images.*' => 'file|image|max:5120',
            ...WebChallan::TRANSPORT_RULES,
        ];
        $rules += $challan->entry_type === 'direct' ? [
            'category_id'         => 'required|exists:product_categories,id',
            'vendor_name'         => 'required|string|max:191',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.unit'        => 'nullable|string|max:30',
            'items.*.unit_price'  => 'required|numeric|min:0',
        ] : [
            'vendor_challan_no'    => 'nullable|string|max:50',
            'has_objection'        => 'nullable|boolean',
            'objection_remarks'    => 'nullable|string|max:1000',
            'objection_voice'      => 'nullable|file|max:10240',
            'remove_voice'         => 'nullable|boolean',
            'items'                => 'nullable|array',
            'items.*.id'           => 'required|integer',
            'items.*.received_qty' => 'required|numeric|min:0',
        ];
        $request->validate($rules);

        $newFiles = [];
        try {
            $keep = array_values(array_intersect($challan->challan_images ?? [], (array) $request->input('keep_images', [])));
            foreach ($request->file('challan_images', []) as $f) $newFiles[] = $f->store('challan_images', 'public');

            $data = [
                'received_date'  => $request->received_date,
                'challan_images' => array_merge($keep, $newFiles),
                ...$request->only([...WebChallan::TRANSPORT_FIELDS, 'remarks', 'vendor_challan_no']),
            ];

            if ($challan->entry_type === 'direct') {
                $data += ['category_id' => $request->category_id, 'vendor_name' => $request->vendor_name];
            } else {
                $voice = $request->boolean('remove_voice') ? null : $challan->objection_voice_note;
                if ($request->hasFile('objection_voice')) {
                    $voice = $this->storeVoice($request->file('objection_voice'));
                    if (!$voice) throw new \Exception('Unsupported voice note format.');
                    $newFiles[] = $voice;
                }
                $data += [
                    'has_objection'        => $request->boolean('has_objection'),
                    'objection_remarks'    => $request->objection_remarks,
                    'objection_voice_note' => $voice,
                ];
            }

            $challan = $this->service->update($challan, $data, $request->input('items', []), $request->user()->id);

            return response()->json([
                'id' => $challan->id, 'challan_no' => $challan->challan_no, 'status' => $challan->status,
                'message' => $challan->challan_no . ' updated.',
            ]);
        } catch (\Exception $e) {
            foreach ($newFiles as $f) \Illuminate\Support\Facades\Storage::disk('public')->delete($f);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // Keep the recorder's extension (.m4a) — content sniffing often mislabels
    // AAC audio, and browsers won't play a file saved as .bin/.mp4-video.
    private function storeVoice(\Illuminate\Http\UploadedFile $file): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::VOICE_EXT)) return null;
        return $file->storeAs('challan_voice', \Illuminate\Support\Str::random(40) . '.' . $ext, 'public');
    }

    // ── Category incharge inspection ─────────────────────────────────

    // Challans waiting for this user's decision (PO-based only; no-PO review stays on web)
    public function pendingReview(Request $request)
    {
        $challans = Challan::awaitingInspection()->where('entry_type', 'po')
            ->with('purchaseOrder.vendor', 'purchaseOrder.category', 'receivedBy')
            ->forCategoryIncharge($request->user())
            ->orderBy('received_date')->get();

        return response()->json($challans->map(fn($c) => [
            'id' => $c->id, 'challan_no' => $c->challan_no,
            'po_order_no' => $c->purchaseOrder->order_no ?? '', 'po_type' => $c->purchaseOrder->type ?? '',
            'vendor_name' => $c->display_vendor_name, 'category_name' => $c->purchaseOrder->category->name ?? '',
            'received_date' => $c->received_date->format('Y-m-d'), 'received_by' => $c->receivedBy->name ?? '',
            'has_objection' => (bool) $c->has_objection,
            'thumbnail' => !empty($c->challan_images) ? \App\Support\Media::url($c->challan_images[0]) : null,
        ]));
    }

    public function reviewData(Request $request, $id)
    {
        $challan = Challan::with('purchaseOrder')->findOrFail($id);
        if (!$challan->canBeReviewedBy($request->user())) return response()->json(['message' => 'Only the category incharge can review this challan.'], 403);
        if ($challan->entry_type !== 'po') return response()->json(['message' => 'Challans without a PO are reviewed on the web.'], 422);

        return response()->json($this->reviewService->reviewData($challan));
    }

    public function review(Request $request, $id)
    {
        $challan = Challan::with('purchaseOrder')->findOrFail($id);
        if (!$challan->canBeReviewedBy($request->user())) return response()->json(['message' => 'Only the category incharge can review this challan.'], 403);

        $request->validate([
            'decision'                       => 'required|in:' . implode(',', array_keys(Challan::DECISIONS)),
            'remarks'                        => 'nullable|string|max:2000',
            'receiving_date'                 => 'nullable|date',
            'is_final_receiving'             => 'nullable|boolean',
            'lines'                          => 'required|array|min:1',
            'lines.*.purchase_order_item_id' => 'nullable|integer',
            'lines.*.accepted_qty'           => 'nullable|numeric|min:0',
            'lines.*.rejected_qty'           => 'nullable|numeric|min:0',
            'lines.*.new_quantity'           => 'nullable|numeric|min:0',
            'lines.*.new_rate'               => 'nullable|numeric|min:0',
            'lines.*.note'                   => 'nullable|string|max:255',
            'amend'                          => 'nullable|array',
        ]);

        try {
            $challan = $this->reviewService->review($challan, $request->decision, $request->input('lines', []), [
                'remarks' => $request->remarks, 'receiving_date' => $request->receiving_date,
                'is_final_receiving' => $request->boolean('is_final_receiving'), 'amend' => $request->input('amend', []),
            ], $request->user()->id);

            return response()->json([
                'id' => $challan->id, 'status' => $challan->status, 'status_label' => $challan->status_label,
                'grn_no' => $challan->receiving->receiving_no ?? null,
                'message' => match ($request->decision) {
                    'amend'  => 'Amendment sent for approval.',
                    'reject' => 'Consignment rejected.',
                    default  => 'Receiving posted' . ($challan->receiving ? ' — ' . $challan->receiving->receiving_no : '') . '.',
                },
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
