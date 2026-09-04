<?php

namespace App\Http\Controllers;

use App\Models\Challan;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\ChartOfAccounts;
use App\Services\ChallanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChallanController extends Controller
{
    public function __construct(private ChallanService $service) {}

    public function index(Request $request)
    {
        $challans = Challan::with('purchaseOrder.vendor', 'purchaseOrder.category', 'vendor', 'receivedBy')
            ->forCategoryIncharge(auth()->user())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->where('entry_type', $request->type))
            ->orderByDesc('received_date')
            ->get();

        return view('challans.index', compact('challans'));
    }

    public function pending()
    {
        $challans = Challan::awaitingInspection()
            ->with('purchaseOrder.vendor', 'purchaseOrder.category', 'vendor')
            ->forCategoryIncharge(auth()->user())
            ->orderBy('received_date')
            ->get();

        return view('challans.pending', compact('challans'));
    }

    public function create()
    {
        $vendors = Vendor::active()->orderBy('name')->get(['id', 'name']);
        $expenseAccounts = ChartOfAccounts::active()->where('account_type', 'expense')->orderBy('name')->get(['id', 'name']);

        return view('challans.create', compact('vendors', 'expenseAccounts'));
    }

    // AJAX: vendors that have at least one PO of the given type (any status
    // relevant to Challan — including Pending, shown view-only client-side)
    public function vendorsForType(Request $request)
    {
        $request->validate(['type' => 'required|in:purchase,weaving,processing']);

        $statuses = $request->type === 'purchase'
            ? ['Pending', 'Approved', 'PartiallyReceived']
            : ['Pending', 'Issued', 'PartiallyReceived'];

        $vendorIds = PurchaseOrder::where('type', $request->type)
            ->whereIn('status', $statuses)
            ->distinct()
            ->pluck('vendor_id');

        $vendors = Vendor::whereIn('id', $vendorIds)->orderBy('name')->get(['id', 'name']);

        return response()->json($vendors);
    }

    // AJAX: POs for a given type+vendor — Pending POs included but flagged
    // not selectable, per "show unapproved POs, view-only" requirement
    public function posForVendor(Request $request)
    {
        $request->validate([
            'type'      => 'required|in:purchase,weaving,processing',
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
            'id'         => $o->id,
            'order_no'   => $o->order_no,
            'status'     => $o->status,
            'selectable' => $o->status !== 'Pending',
        ]));
    }

    // AJAX: expected items for the selected PO (purchase/processing only —
    // weaving has no line items, shown differently on the create form)
    public function poItems($poId)
    {
        $po = PurchaseOrder::with('items.product')->findOrFail($poId);

        if ($po->type === 'weaving') {
            return response()->json([[
                'product_name' => $po->item_name ?? 'Greige (per CPO)',
                'quantity'     => (float) $po->total_meters_required,
            ]]);
        }

        return response()->json($po->items->map(fn($i) => [
            'product_name' => $i->product->name ?? ($i->description ?? $i->pattern_code ?? ''),
            'quantity'     => (float) $i->quantity,
        ]));
    }

    public function store(Request $request)
    {
        if ($request->entry_type === 'direct') {
            return $this->storeDirect($request);
        }

        $request->validate([
            'purchase_order_id'  => 'required|exists:purchase_orders,id',
            'vendor_challan_no'  => 'nullable|string|max:50',
            'received_date'      => 'required|date',
            'challan_images'      => 'required|array|min:1',
            'challan_images.*'    => 'file|image|max:5120',
            'remarks'            => 'nullable|string',
        ]);

        try {
            $images = [];
            foreach ($request->file('challan_images') as $file) {
                $images[] = $file->store('challan_images', 'public');
            }

            $challan = $this->service->create([
                'purchase_order_id'  => $request->purchase_order_id,
                'vendor_challan_no'  => $request->vendor_challan_no,
                'received_date'      => $request->received_date,
                'challan_images'     => $images,
                'remarks'            => $request->remarks,
            ], auth()->id());

            Log::info('[Challan] Created', ['id' => $challan->id, 'by' => auth()->id()]);

            return redirect()->route('challans.create')->with('success', $challan->challan_no . ' logged successfully.');

        } catch (\Exception $e) {
            Log::error('[Challan] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function storeDirect(Request $request)
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
            ], $request->direct_items, auth()->id());

            Log::info('[Challan] Direct entry created', ['id' => $challan->id, 'by' => auth()->id()]);

            return redirect()->route('challans.create')->with('success', $challan->challan_no . ' logged — pending approval.');

        } catch (\Exception $e) {
            Log::error('[Challan] Direct store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $challan = Challan::with(
            'purchaseOrder.vendor', 'purchaseOrder.category', 'purchaseOrder.items.product',
            'vendor', 'receivedBy', 'directItems.expenseAccount'
        )->findOrFail($id);

        return view('challans.show', compact('challan'));
    }

    public function approveDirect($id)
    {
        try {
            $challan = Challan::findOrFail($id);
            $this->service->approveDirect($challan, auth()->id());

            Log::info('[Challan] Direct approved', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('challans.index')->with('success', 'Direct receiving approved and posted.');

        } catch (\Exception $e) {
            Log::error('[Challan] approveDirect failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectDirect(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $challan = Challan::findOrFail($id);
            $this->service->rejectDirect($challan, auth()->id(), $request->reason);

            return back()->with('success', 'Direct entry rejected.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}