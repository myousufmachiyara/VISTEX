<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReceiving;
use App\Models\Challan;
use App\Models\PurchaseOrder;
use App\Services\PurchaseReceivingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseReceivingController extends Controller
{
    public function __construct(private PurchaseReceivingService $service) {}

    public function index(Request $request)
    {
        $receivings = PurchaseReceiving::with('purchaseOrder.vendor', 'purchaseOrder.category', 'challan')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('receiving_date')
            ->get();

        return view('purchase_receivings.index', compact('receivings'));
    }

    // Entry point is always a specific Challan
    public function create($challanId)
    {
        $challan = Challan::with('purchaseOrder.items.product', 'purchaseOrder.category')->findOrFail($challanId);

        if ($challan->status !== 'AwaitingInspection') {
            return redirect()->route('challans.show', $challanId)->with('error', 'This challan has already been processed.');
        }

        $po = $challan->purchaseOrder;

        if ($po->type === 'weaving') {
            return $this->createWeavingReceiving($challan, $po);
        }

        $outstanding = $po->items->map(function ($item) {
            return [
                'purchase_order_item_id' => $item->id,
                'product_id'             => $item->product_id,
                'product_name'           => $item->product->name ?? ($item->description ?? $item->pattern_code ?? ''),
                'pattern_code'           => $item->pattern_code,
                'ordered'                => (float) $item->quantity,
                'already_received'       => (float) $item->quantity_received,
                'outstanding'            => round((float) $item->quantity - (float) $item->quantity_received, 3),
            ];
        })->filter(fn($row) => $row['outstanding'] > 0.001)->values();

        $issuedSummary = null;
        if ($po->type === 'processing') {
            $issuedSummary = $po->processingIssues()
                ->join('processing_issue_items', 'processing_issues.id', '=', 'processing_issue_items.processing_issue_id')
                ->selectRaw('SUM(processing_issue_items.quantity) as total_issued')
                ->value('total_issued') ?? 0;
        }

        return view('purchase_receivings.create', compact('challan', 'outstanding', 'issuedSummary'));
    }

    private function createWeavingReceiving(Challan $challan, PurchaseOrder $po)
    {
        $yarnBalance = [];
        $seenProductIds = [];

        foreach ([$po->warpProduct, $po->weftProduct] as $product) {
            if (!$product || in_array($product->id, $seenProductIds)) continue;
            $seenProductIds[] = $product->id;

            $bal = \App\Models\YarnInProcessLedger::balanceForCpoProduct($po->id, $product->id);
            $yarnBalance[] = ['product_name' => $product->name, 'quantity' => $bal['quantity'], 'amount' => $bal['amount']];
        }

        $totalOrderedMeters = (float) $po->total_meters_required;
        $totalReceivedMeters = \App\Models\PurchaseReceiving::where('purchase_order_id', $po->id)
            ->where('status', 'Approved')
            ->join('purchase_receiving_items', 'purchase_receivings.id', '=', 'purchase_receiving_items.purchase_receiving_id')
            ->where('purchase_receiving_items.product_id', $po->greige_product_id)
            ->sum('purchase_receiving_items.quantity_received');

        $outstandingMeters = round($totalOrderedMeters - $totalReceivedMeters, 3);

        return view('purchase_receivings.create_weaving', compact('challan', 'po', 'yarnBalance', 'outstandingMeters', 'totalReceivedMeters'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'challan_id'                                => 'required|exists:challans,id',
            'receiving_date'                             => 'required|date',
            'remarks'                                    => 'nullable|string',
            'items'                                       => 'required|array|min:1',
            'items.*.purchase_order_item_id'            => 'required|integer|exists:purchase_order_items,id',
            'items.*.product_id'                        => 'required|integer|exists:products,id',
            'items.*.quantity_received'                 => 'required|numeric|min:0',
            'items.*.quantity_rejected'                 => 'nullable|numeric|min:0',
        ]);

        try {
            $receiving = $this->service->create($request->all(), $request->items, auth()->id());

            Log::info('[PurchaseReceiving] Created', ['id' => $receiving->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_receivings.show', $receiving->id)
                ->with('success', $receiving->receiving_no . ' recorded — pending your approval.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReceiving] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function storeWeaving(Request $request)
    {
        $request->validate([
            'challan_id'          => 'required|exists:challans,id',
            'purchase_order_id'   => 'required|exists:purchase_orders,id',
            'receiving_date'      => 'required|date',
            'quantity_received'   => 'required|numeric|min:0.001',
            'is_final_receiving'  => 'nullable|boolean',
            'remarks'             => 'nullable|string',
        ]);

        try {
            $receiving = $this->service->createWeaving($request->all(), auth()->id());

            Log::info('[PurchaseReceiving:Weaving] Created', ['id' => $receiving->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_receivings.show', $receiving->id)
                ->with('success', $receiving->receiving_no . ' recorded — pending your approval.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReceiving:Weaving] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
    
    public function show($id)
    {
        $receiving = PurchaseReceiving::with([
            'purchaseOrder.vendor', 'purchaseOrder.category', 'challan',
            'items.product', 'items.purchaseOrderItem', 'approver',
        ])->findOrFail($id);

        return view('purchase_receivings.show', compact('receiving'));
    }

    public function approve($id)
    {
        $receiving = PurchaseReceiving::with('purchaseOrder')->findOrFail($id);

        if (!$receiving->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only this category\'s in-charge or a superadmin can approve this receiving.');
        }

        try {
            $this->service->approve($receiving, auth()->id());
            Log::info('[PurchaseReceiving] Approved', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'Receiving approved — stock, vendor ledger, and PDC updated.');
        } catch (\Exception $e) {
            Log::error('[PurchaseReceiving] Approve failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $receiving = PurchaseReceiving::with('purchaseOrder')->findOrFail($id);

        if (!$receiving->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only this category\'s in-charge or a superadmin can reject this receiving.');
        }

        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->service->reject($receiving, auth()->id(), $request->reason);
            return back()->with('success', 'Receiving rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}