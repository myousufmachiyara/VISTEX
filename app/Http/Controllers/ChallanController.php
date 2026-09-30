<?php

namespace App\Http\Controllers;

use App\Models\{Challan, ChartOfAccounts, Location, MeasurementUnit, ProductCategory, PurchaseOrder, Vendor};
use App\Services\{ChallanReviewService, ChallanService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChallanController extends Controller
{
    public function __construct(
        private ChallanService $service,
        private ChallanReviewService $reviewService,
    ) {}

    public function index(Request $request)
    {
        $challans = Challan::with('purchaseOrder.vendor', 'purchaseOrder.category', 'category', 'vendor', 'receivedBy')
            ->visibleTo(auth()->user())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->where('entry_type', $request->type))
            ->orderByDesc('received_date')->orderByDesc('id')
            ->get();

        return view('challans.index', compact('challans'));
    }

    // Category incharge's inspection queue
    public function pending()
    {
        $challans = Challan::whereIn('status', [Challan::AWAITING, Challan::AMENDING])
            ->with('purchaseOrder.vendor', 'purchaseOrder.category', 'category', 'receivedBy', 'amendment')
            ->forCategoryIncharge(auth()->user())
            ->orderBy('received_date')
            ->get();

        return view('challans.pending', compact('challans'));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $orders = PurchaseOrder::openForReceiving()
            ->where(fn($q) => $q->where('type', 'purchase')->orWhereIn('status', ['Issued', 'PartiallyReceived']))
            ->with('vendor', 'category')
            ->orderByDesc('order_date')->get();

        $categories = ProductCategory::orderBy('name')->get(['id', 'name']);
        $preselect = $request->integer('purchase_order_id') ?: null;

        return view('challans.create', compact('orders', 'categories', 'preselect'));
    }

    // AJAX: outstanding lines of the selected PO for the gate count
    public function poItems($poId)
    {
        $po = PurchaseOrder::with('items.product', 'greigeProduct')->findOrFail($poId);

        if ($po->type === 'weaving') {
            return response()->json([[
                'purchase_order_item_id' => null, 'product_id' => $po->greige_product_id,
                'product_name' => $po->greigeProduct->name ?? $po->item_name ?? 'Greige (meters)',
                'quantity' => round((float) $po->total_meters_required, 3),
            ]]);
        }

        return response()->json($po->items->map(fn($i) => [
            'purchase_order_item_id' => $i->id, 'product_id' => $i->product_id,
            'product_name' => $i->product->name ?? trim(($i->pattern_code ?? '') . ' ' . ($i->description ?? '')),
            'quantity' => $i->outstanding_qty,
        ])->filter(fn($r) => $r['quantity'] > 0.001)->values());
    }

    public function store(Request $request)
    {
        if ($request->entry_type === 'direct') return $this->storeDirect($request);

        $request->validate([
            'purchase_order_id'              => 'required|exists:purchase_orders,id',
            'vendor_challan_no'              => 'nullable|string|max:50',
            'received_date'                  => 'required|date',
            'challan_images'                 => 'required|array|min:1',
            'challan_images.*'               => 'file|image|max:5120',
            'has_objection'                  => 'nullable|boolean',
            'objection_remarks'              => 'nullable|required_if:has_objection,1|string|max:1000',
            'remarks'                        => 'nullable|string',
            'items'                          => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id'             => 'nullable|exists:products,id',
            'items.*.expected_qty'           => 'required|numeric|min:0',
            'items.*.received_qty'           => 'required|numeric|min:0',
        ]);

        try {
            $images = [];
            foreach ($request->file('challan_images') as $file) $images[] = $file->store('challan_images', 'public');

            $challan = $this->service->create([
                'purchase_order_id' => $request->purchase_order_id,
                'vendor_challan_no' => $request->vendor_challan_no,
                'received_date'     => $request->received_date,
                'challan_images'    => $images,
                'has_objection'     => $request->boolean('has_objection'),
                'objection_remarks' => $request->objection_remarks,
                'remarks'           => $request->remarks,
            ], $request->items, auth()->id());

            Log::info('[Challan] Created', ['id' => $challan->id, 'by' => auth()->id()]);
            return redirect()->route('challans.show', $challan->id)->with('success', $challan->challan_no . ' logged — sent to the category incharge for inspection.');
        } catch (\Exception $e) {
            Log::error('[Challan] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // No-PO purchase at the gate (same payload shape as the mobile app)
    private function storeDirect(Request $request)
    {
        $request->validate([
            'category_id'                 => 'required|exists:product_categories,id',
            'vendor_name'                 => 'required|string|max:191',
            'received_date'               => 'required|date',
            'challan_images'              => 'required|array|min:1',
            'challan_images.*'            => 'file|image|max:5120',
            'remarks'                     => 'nullable|string',
            'direct_items'                => 'required|array|min:1',
            'direct_items.*.description'  => 'required|string|max:255',
            'direct_items.*.quantity'     => 'required|numeric|min:0.001',
            'direct_items.*.unit'         => 'nullable|string|max:30',
            'direct_items.*.unit_price'   => 'required|numeric|min:0',
        ]);

        try {
            $images = [];
            foreach ($request->file('challan_images') as $file) $images[] = $file->store('challan_images', 'public');

            $challan = $this->service->createDirect([
                'category_id'    => $request->category_id,
                'vendor_name'    => $request->vendor_name,
                'received_date'  => $request->received_date,
                'challan_images' => $images,
                'remarks'        => $request->remarks,
            ], $request->direct_items, auth()->id());

            return redirect()->route('challans.show', $challan->id)->with('success', $challan->challan_no . ' logged — sent to the category incharge for review.');
        } catch (\Exception $e) {
            Log::error('[Challan] Direct store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $challan = Challan::with(
            'purchaseOrder.vendor', 'purchaseOrder.category', 'purchaseOrder.items.product', 'category',
            'vendor', 'receivedBy', 'reviewedBy', 'items.product', 'items.purchaseOrderItem', 'directItems.expenseAccount',
            'receiving', 'amendment', 'objection'
        )->findOrFail($id);

        return view('challans.show', compact('challan'));
    }

    // ── Incharge review of a PO challan ────────────────────────────────
    public function reviewForm($id)
    {
        $challan = Challan::with('purchaseOrder')->findOrFail($id);
        abort_unless($challan->entry_type === 'po', 404);
        $this->authorizeReview($challan);

        if ($challan->status !== Challan::AWAITING) {
            return redirect()->route('challans.show', $id)->with('error', "This challan is {$challan->status_label} and cannot be reviewed now.");
        }

        return view('challans.review', ['challan' => $challan, 'data' => $this->reviewService->reviewData($challan)]);
    }

    public function review(Request $request, $id)
    {
        $challan = Challan::with('purchaseOrder')->findOrFail($id);
        $this->authorizeReview($challan);

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
            'amend.expected_date'            => 'nullable|date',
            'amend.total_meters_required'    => 'nullable|numeric|min:0.001',
            'amend.rate_per_pick'            => 'nullable|numeric|min:0',
            'amend.payment_term_type'        => 'nullable|in:cash,credit,pdc,other',
            'amend.payment_term_days'        => 'nullable|integer|min:1',
        ]);

        try {
            $challan = $this->reviewService->review($challan, $request->decision, $request->input('lines', []), [
                'remarks' => $request->remarks, 'receiving_date' => $request->receiving_date,
                'is_final_receiving' => $request->boolean('is_final_receiving'), 'amend' => $request->input('amend', []),
            ], auth()->id());

            Log::info('[Challan] Reviewed', ['id' => $id, 'decision' => $request->decision, 'by' => auth()->id()]);
            $msg = match ($request->decision) {
                'amend'  => 'Amendment sent for superadmin approval. The challan returns to your queue once it is decided.',
                'reject' => 'Consignment rejected. The gatekeeper and PO creator have been notified.',
                default  => 'Receiving posted — ' . ($challan->receiving->receiving_no ?? '') . '.',
            };
            return redirect()->route('challans.show', $id)->with('success', $msg);
        } catch (\Exception $e) {
            Log::error('[Challan] Review failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ── No-PO review (unchanged behaviour, imports fixed) ──────────────
    public function reviewDirectForm($id)
    {
        $challan = Challan::with('directItems', 'category.inchargeUsers', 'receivedBy')->findOrFail($id);
        abort_unless($challan->entry_type === 'direct', 404);
        $this->authorizeReview($challan);

        if ($challan->status !== Challan::AWAITING) {
            return redirect()->route('challans.show', $id)->with('error', 'This challan has already been reviewed.');
        }

        return view('challans.review_direct', [
            'challan'         => $challan,
            'categories'      => ProductCategory::orderBy('name')->get(['id', 'name']),
            'units'           => MeasurementUnit::orderBy('name')->get(),
            'vendors'         => Vendor::active()->orderBy('name')->get(['id', 'name']),
            'accounts'        => ChartOfAccounts::active()->orderBy('name')->get(['id', 'name']),
            'expenseAccounts' => ChartOfAccounts::active()->where('account_type', 'expense')->orderBy('name')->get(['id', 'name']),
            'locations'       => Location::whereNull('vendor_id')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reviewDirect(Request $request, $id)
    {
        $request->validate([
            'location_id'                 => 'required|exists:locations,id',
            'payable_vendor_id'           => 'nullable|exists:vendors,id',
            'payable_account_id'          => 'nullable|exists:chart_of_accounts,id',
            'paid_from_account_id'        => 'nullable|exists:chart_of_accounts,id',
            'items'                       => 'required|array|min:1',
            'items.*.id'                  => 'required|exists:challan_direct_items,id',
            'items.*.treatment'           => 'required|in:stock,expense',
            'items.*.product_category_id' => 'required_if:items.*.treatment,stock|nullable|exists:product_categories,id',
            'items.*.product_id'          => 'nullable|string',
            'items.*.measurement_unit_id' => 'nullable|exists:measurement_units,id',
            'items.*.expense_account_id'  => 'required_if:items.*.treatment,expense|nullable|exists:chart_of_accounts,id',
        ]);

        try {
            $challan = Challan::with('directItems')->findOrFail($id);
            $this->authorizeReview($challan);

            $this->service->reviewDirect(
                $challan,
                $request->only(['location_id', 'payable_vendor_id', 'payable_account_id', 'paid_from_account_id']),
                $request->items, auth()->id()
            );

            return redirect()->route('challans.show', $id)->with('success', 'Challan reviewed — stock and ledger posted.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function rejectDirect(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        try {
            $challan = Challan::findOrFail($id);
            $this->authorizeReview($challan);
            $this->service->rejectDirect($challan, auth()->id(), $request->reason);
            return back()->with('success', 'Direct entry rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function authorizeReview(Challan $challan): void
    {
        abort_unless($challan->canBeReviewedBy(auth()->user()), 403, 'Only the category incharge can review this challan.');
    }
}
