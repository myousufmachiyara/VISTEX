<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAmendment;
use App\Services\PurchaseOrderAmendmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseOrderAmendmentController extends Controller
{
    public function __construct(private PurchaseOrderAmendmentService $service) {}

    public function create($poId)
    {
        $po = PurchaseOrder::findOrFail($poId);

        if (!in_array($po->status, ['Approved', 'Issued', 'PartiallyReceived'])) {
            return back()->with('error', 'Amendments can only be raised against an active PO.');
        }

        return view('purchase_order_amendments.create', compact('po'));
    }

    public function store(Request $request, $poId)
    {
        $po = PurchaseOrder::findOrFail($poId);

        $request->validate([
            'reason'                  => 'required|string|max:1000',
            'expected_date'           => 'nullable|date',
            'total_meters_required'   => 'nullable|numeric|min:0.001',
            'rate_per_pick'           => 'nullable|numeric|min:0',
            'weaving_rate'            => 'nullable|numeric|min:0',
            'payment_term_type'       => 'nullable|in:cash,credit,pdc,other',
            'payment_term_days'       => 'nullable|integer|min:1',
            'remarks'                 => 'nullable|string',
        ]);

        try {
            $amendment = $this->service->propose($po, $request->except(['_token', 'reason']), $request->reason, auth()->id());

            Log::info('[PurchaseOrderAmendment] Proposed', ['id' => $amendment->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_orders.show', $po->id)->with('success', 'Amendment submitted — pending superadmin approval.');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve($id)
    {
        $amendment = PurchaseOrderAmendment::with('purchaseOrder')->findOrFail($id);

        if (!auth()->user()->hasRole('superadmin')) {
            abort(403, 'Only a superadmin can approve an amendment.');
        }

        try {
            $this->service->approve($amendment, auth()->id());
            return back()->with('success', 'Amendment approved and applied.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $amendment = PurchaseOrderAmendment::findOrFail($id);

        if (!auth()->user()->hasRole('superadmin')) {
            abort(403, 'Only a superadmin can reject an amendment.');
        }

        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->service->reject($amendment, auth()->id(), $request->reason);
            return back()->with('success', 'Amendment rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}