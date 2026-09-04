<?php

namespace App\Http\Controllers;

use App\Models\ProcessingIssue;
use App\Models\PurchaseOrder;
use App\Models\LocationStockLedger;
use App\Services\ProcessingIssueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessingIssueController extends Controller
{
    public function __construct(private ProcessingIssueService $service) {}

    public function index()
    {
        $user = auth()->user();

        $issues = ProcessingIssue::with('purchaseOrder.vendor', 'location', 'items.product')
            ->when(!$user->hasRole('superadmin'), function ($q) use ($user) {
                $q->whereHas('location', fn($q2) => $q2->where('in_charge_user_id', $user->id));
            })
            ->orderByDesc('issue_date')
            ->get();

        return view('processing_issues.index', compact('issues'));
    }

    public function create()
    {
        $user = auth()->user();

        // Only the vendor location this employee is in-charge of (or all, for superadmin)
        $orders = PurchaseOrder::where('type', 'processing')->where('status', 'Issued')
            ->when(!$user->hasRole('superadmin'), function ($q) use ($user) {
                // Processing POs whose vendor has a location this user manages
                $q->whereHas('vendor.locations', fn($q2) => $q2->where('in_charge_user_id', $user->id));
            })
            ->with('vendor', 'items')
            ->orderByDesc('order_date')
            ->get();

        return view('processing_issues.create', compact('orders'));
    }

    // AJAX: for the selected PO's vendor, which locations does this user manage + what lots/products are available there
    public function poDetails($poId)
    {
        $po = PurchaseOrder::with('items.product', 'vendor.locations')->findOrFail($poId);
        $user = auth()->user();

        $locations = $user->hasRole('superadmin')
            ? $po->vendor->locations
            : $po->vendor->locations->where('in_charge_user_id', $user->id);

        return response()->json([
            'locations' => $locations->map(fn($l) => ['id' => $l->id, 'name' => $l->name])->values(),
            'po_items'  => $po->items->map(fn($i) => [
                'purchase_order_item_id' => $i->id,
                'pattern_code'           => $i->pattern_code,
                'description'            => $i->description,
                'outstanding'            => round((float) $i->quantity - (float) $i->quantity_received, 3),
            ]),
        ]);
    }

    // AJAX: lots + products available at the chosen location
    public function availableStock(Request $request)
    {
        $request->validate(['location_id' => 'required|exists:locations,id']);

        $rows = LocationStockLedger::where('location_id', $request->location_id)
            ->where('status', 'fresh')
            ->whereNotNull('lot_no')
            ->groupBy('lot_no', 'product_id')
            ->selectRaw('lot_no, product_id, SUM(quantity) as qty')
            ->having('qty', '>', 0.001)
            ->with('product')
            ->get();

        return response()->json($rows->map(fn($r) => [
            'lot_no'       => $r->lot_no,
            'product_id'   => $r->product_id,
            'product_name' => $r->product->name ?? '',
            'available'    => (float) $r->qty,
        ]));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'                => 'required|exists:purchase_orders,id',
            'location_id'                       => 'required|exists:locations,id',
            'lot_no'                            => 'required|string|max:100',
            'issue_date'                        => 'required|date',
            'remarks'                           => 'nullable|string',
            'items'                              => 'required|array|min:1',
            'items.*.product_id'                => 'required|exists:products,id',
            'items.*.purchase_order_item_id'    => 'nullable|exists:purchase_order_items,id',
            'items.*.quantity'                  => 'required|numeric|min:0.001',
        ]);

        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('processing_issue_attachments', 'public');
                }
            }

            $issue = $this->service->create(
                array_merge($request->all(), ['attachments' => $attachments ?: null]),
                $request->items,
                auth()->id()
            );

            Log::info('[ProcessingIssue] Created', ['id' => $issue->id, 'by' => auth()->id()]);

            return redirect()->route('processing_issues.index')->with('success', $issue->issue_no . ' recorded successfully.');

        } catch (\Exception $e) {
            Log::error('[ProcessingIssue] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $issue = ProcessingIssue::findOrFail($id);
            $this->service->delete($issue);
            return redirect()->route('processing_issues.index')->with('success', 'Deleted successfully.');
        } catch (\Exception $e) {
            Log::error('[ProcessingIssue] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
}