<?php

namespace App\Http\Controllers;

use App\Models\{PurchaseReceiving, QcInspection};
use App\Services\QcInspectionService;
use Illuminate\Http\Request;

class QcInspectionController extends Controller
{
    public function __construct(private QcInspectionService $service) {}

    public function index(Request $request)
    {
        // Processed fabric received and approved, QC not started yet
        $awaiting = PurchaseReceiving::with('purchaseOrder.vendor', 'items')
            ->where('status', 'Approved')->where('qc_status', 'Pending')
            ->orderBy('receiving_date')->get();

        $inspections = QcInspection::with('purchaseOrder', 'vendor', 'receiving', 'items')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('inspection_date')->orderByDesc('id')->get();

        return view('qc_inspections.index', compact('awaiting', 'inspections'));
    }

    public function start($receivingId)
    {
        try {
            $qc = $this->service->start(PurchaseReceiving::findOrFail($receivingId), auth()->id());
            return redirect()->route('qc_inspections.show', $qc->id);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $qc = QcInspection::with('items.product', 'purchaseOrder.vendor', 'receiving.challan', 'location', 'creator', 'closer')->findOrFail($id);
        return view('qc_inspections.show', compact('qc'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'inspection_date' => 'required|date',
            'claim_amount'    => 'nullable|numeric|min:0',
            'claim_reason'    => 'nullable|string|max:255',
            'remarks'         => 'nullable|string',
            'items'           => 'array',
            'items.*.rolls'   => 'nullable|integer|min:0',
            'items.*.gross_kg' => 'nullable|numeric|min:0', 'items.*.tare_kg' => 'nullable|numeric|min:0',
            'items.*.measured_meters' => 'nullable|numeric|min:0',
            'items.*.width_inch' => 'nullable|numeric|min:0', 'items.*.expected_gsm' => 'nullable|numeric|min:0',
            'items.*.final_a' => 'nullable|numeric|min:0', 'items.*.final_b' => 'nullable|numeric|min:0', 'items.*.final_c' => 'nullable|numeric|min:0',
            'items.*.reprocess_qty' => 'nullable|numeric|min:0', 'items.*.shortage_qty' => 'nullable|numeric|min:0',
            'items.*.defects' => 'nullable|array', 'items.*.defect_notes' => 'nullable|string|max:1000',
        ]);

        $qc = QcInspection::findOrFail($id);
        try {
            $this->service->save($qc, $request->only('inspection_date', 'claim_amount', 'claim_reason', 'remarks'), $request->input('items', []), auth()->id());
            if ($request->input('action') === 'close') {
                abort_unless(auth()->user()->can('qc_inspections.edit'), 403, 'You cannot close QC.');
                $this->service->close($qc, auth()->id());
                return redirect()->route('qc_inspections.show', $qc->id)->with('success', "QC {$qc->qc_no} closed. Stock regraded" . ($qc->fresh()->claim_amount > 0 || $qc->fresh()->shortage_amount > 0 ? ' and claim posted to the mill.' : '.'));
            }
            return redirect()->route('qc_inspections.show', $qc->id)->with('success', 'QC saved.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function print($id)
    {
        $qc = QcInspection::with('items.product', 'purchaseOrder.vendor', 'receiving.challan', 'location', 'creator', 'closer')->findOrFail($id);
        return view('qc_inspections.print', compact('qc'));
    }
}
