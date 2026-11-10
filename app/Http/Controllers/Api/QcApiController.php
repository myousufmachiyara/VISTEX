<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{PurchaseReceiving, QcInspection, QcInspectionItem};
use App\Services\QcInspectionService;
use Illuminate\Http\Request;

/** QC of processed fabric from the mobile app: rolls, meters, width, line status (+ log), grading, defects, close. */
class QcApiController extends Controller
{
    public function __construct(private QcInspectionService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('qc_inspections.index'), 403, 'You do not have access to QC.');
        $awaiting = PurchaseReceiving::with('purchaseOrder.vendor', 'items')->where('status', 'Approved')->where('qc_status', 'Pending')
            ->orderBy('receiving_date')->get()->map(fn($r) => [
                'receiving_id' => $r->id, 'receiving_no' => $r->receiving_no, 'date' => $r->receiving_date->format('d-M-Y'),
                'lot' => $r->purchaseOrder?->stock_lot_no, 'mill' => $r->purchaseOrder->vendor->name ?? '',
                'meters' => round((float) $r->items->sum(fn($i) => (float) $i->qty_a + (float) $i->qty_b + (float) $i->qty_c), 3),
            ]);
        $qc = fn($q) => ['id' => $q->id, 'qc_no' => $q->qc_no, 'date' => $q->inspection_date->format('d-M-Y'), 'status' => $q->status,
            'lot' => $q->purchaseOrder?->stock_lot_no, 'mill' => $q->vendor->name ?? '', 'receiving_no' => $q->receiving->receiving_no ?? ''];
        return response()->json([
            'awaiting' => $awaiting->values(),
            'open' => QcInspection::with('purchaseOrder', 'vendor', 'receiving')->where('status', QcInspection::OPEN)->orderBy('inspection_date')->get()->map($qc)->values(),
            'closed' => QcInspection::with('purchaseOrder', 'vendor', 'receiving')->where('status', QcInspection::CLOSED)->orderByDesc('closed_at')->limit(20)->get()->map($qc)->values(),
            'can_create' => $request->user()->can('qc_inspections.create'),
            'can_close' => $request->user()->can('qc_inspections.edit'),
        ]);
    }

    public function start(Request $request, $receivingId)
    {
        abort_unless($request->user()->can('qc_inspections.create'), 403, 'You cannot record QC.');
        try {
            $qc = $this->service->start(PurchaseReceiving::findOrFail($receivingId), $request->user()->id);
            return response()->json($this->payload($qc, $request), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, $id)
    {
        abort_unless($request->user()->can('qc_inspections.index'), 403, 'You do not have access to QC.');
        return response()->json($this->payload(QcInspection::findOrFail($id), $request));
    }

    // Save; with close=true also closes (needs qc_inspections.edit)
    public function update(Request $request, $id)
    {
        abort_unless($request->user()->can('qc_inspections.create'), 403, 'You cannot record QC.');
        $request->validate([
            'inspection_date' => 'required|date', 'remarks' => 'nullable|string', 'items' => 'required|array', 'close' => 'nullable|boolean',
            // Required per line (also enforced in the service)
            'items.*.rolls' => 'required|integer|min:1', 'items.*.measured_meters' => 'required|numeric|gt:0',
            'items.*.width_inch' => 'required|numeric|gt:0',
            'items.*.status' => 'required|in:' . implode(',', array_keys(QcInspectionItem::STATUSES)),
            'items.*.status_remarks' => 'required|string|max:1000',
            'items.*.gross_kg' => 'nullable|numeric|min:0', 'items.*.expected_gsm' => 'nullable|numeric|min:0',
        ]);
        $qc = QcInspection::findOrFail($id);
        try {
            $this->service->save($qc, $request->only('inspection_date', 'remarks'), $request->input('items', []), $request->user()->id);
            if ($request->boolean('close')) {
                abort_unless($request->user()->can('qc_inspections.edit'), 403, 'You cannot close QC.');
                $this->service->close($qc, $request->user()->id);
            }
            return response()->json($this->payload($qc->fresh(), $request));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function payload(QcInspection $qc, Request $request): array
    {
        $qc->load('items.product', 'purchaseOrder.vendor', 'receiving.challan', 'location', 'statusLogs.user', 'statusLogs.item.product');
        return [
            'id' => $qc->id, 'qc_no' => $qc->qc_no, 'status' => $qc->status, 'inspection_date' => $qc->inspection_date->format('Y-m-d'),
            'lot' => $qc->purchaseOrder?->stock_lot_no, 'mill' => $qc->purchaseOrder->vendor->name ?? '', 'receiving_no' => $qc->receiving->receiving_no ?? '',
            'challan_no' => $qc->receiving->challan->challan_no ?? null, 'location' => $qc->location->name ?? '',
            'remarks' => $qc->remarks,
            'shortage_amount' => (float) $qc->shortage_amount, 'pdc_reduced_by' => (float) $qc->pdc_reduced_by,
            'can_edit' => $qc->isOpen() && $request->user()->can('qc_inspections.create'),
            'can_close' => $qc->isOpen() && $request->user()->can('qc_inspections.edit'),
            'status_options' => array_keys(QcInspectionItem::STATUSES),
            'defect_options' => collect(QcInspectionItem::DEFECTS)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'items' => $qc->items->map(fn($l) => [
                'id' => $l->id, 'product' => $l->product->name ?? '', 'lot_no' => $l->lot_no, 'rate' => $l->rate,
                'received_a' => $l->received_a, 'received_b' => $l->received_b, 'received_c' => $l->received_c, 'received_total' => $l->received_total,
                'rolls' => $l->rolls, 'measured_meters' => $l->measured_meters ?? $l->received_total, 'width_inch' => $l->width_inch,
                'status' => $l->status, 'status_remarks' => $l->status_remarks,
                'gross_kg' => $l->gross_kg, 'gsm' => $l->expected_gsm, 'expected_gsm' => $l->expected_gsm,
                'final_a' => $l->final_a, 'final_b' => $l->final_b, 'final_c' => $l->final_c, 'reprocess_qty' => $l->reprocess_qty, 'shortage_qty' => $l->shortage_qty,
                'defects' => $l->defects ?? [], 'defect_notes' => $l->defect_notes,
            ])->values(),
            'status_log' => $qc->statusLogs->map(fn($g) => [
                'product' => $g->item->product->name ?? '', 'from' => $g->from_status, 'to' => $g->to_status,
                'remarks' => $g->remarks, 'by' => $g->user->name ?? '', 'at' => $g->created_at->format('d-M-Y H:i'),
            ])->values(),
        ];
    }
}
