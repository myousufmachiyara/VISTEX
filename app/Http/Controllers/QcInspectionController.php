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
        $qc = QcInspection::with('items.product', 'purchaseOrder.vendor', 'receiving.challan', 'location', 'creator', 'closer', 'statusLogs.user', 'statusLogs.item.product')->findOrFail($id);
        return view('qc_inspections.show', compact('qc'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'inspection_date' => 'required|date',
            'remarks'         => 'nullable|string',
            'items'           => 'required|array',
            'items.*.rolls'   => 'required|integer|min:1',
            'items.*.measured_meters' => 'required|numeric|gt:0',
            'items.*.width_inch'      => 'required|numeric|gt:0',
            'items.*.status'          => 'required|in:' . implode(',', array_keys(\App\Models\QcInspectionItem::STATUSES)),
            'items.*.status_remarks'  => 'required|string|max:1000',
            'items.*.gross_kg' => 'nullable|numeric|min:0', 'items.*.expected_gsm' => 'nullable|numeric|min:0',
            'items.*.final_a' => 'nullable|numeric|min:0', 'items.*.final_b' => 'nullable|numeric|min:0', 'items.*.final_c' => 'nullable|numeric|min:0',
            'items.*.reprocess_qty' => 'nullable|numeric|min:0', 'items.*.shortage_qty' => 'nullable|numeric|min:0',
            'items.*.defects' => 'nullable|array', 'items.*.defect_notes' => 'nullable|string|max:1000',
        ], [
            'items.*.rolls.required' => 'Rolls is required on every line.',
            'items.*.measured_meters.required' => 'Measured meters is required on every line.',
            'items.*.width_inch.required' => 'Width is required on every line.',
            'items.*.status.required' => 'Status is required on every line.',
            'items.*.status_remarks.required' => 'Status remarks are required on every line.',
        ]);

        $qc = QcInspection::findOrFail($id);
        try {
            $this->service->save($qc, $request->only('inspection_date', 'remarks'), $request->input('items', []), auth()->id());
            if ($request->input('action') === 'close') {
                abort_unless(auth()->user()->can('qc_inspections.edit'), 403, 'You cannot close QC.');
                $this->service->close($qc, auth()->id());
                return redirect()->route('qc_inspections.show', $qc->id)->with('success', "QC {$qc->qc_no} closed. Stock regraded" . ($qc->fresh()->shortage_amount > 0 ? ' and shortage claimed from the mill.' : '.'));
            }
            return redirect()->route('qc_inspections.show', $qc->id)->with('success', 'QC saved.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function print($id)
    {
        $qc = QcInspection::with('items.product', 'purchaseOrder.vendor', 'receiving.challan', 'location', 'creator', 'closer', 'statusLogs.user', 'statusLogs.item.product')->findOrFail($id);
        return view('qc_inspections.print', compact('qc'));
    }
}
