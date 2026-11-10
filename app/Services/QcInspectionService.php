<?php

namespace App\Services;

use App\Models\{LocationStockLedger, Pdc, PurchaseReceiving, QcInspection, QcInspectionItem, QcStatusLog};
use Illuminate\Support\Facades\DB;

/**
 * QC of processed fabric received on a Processing PO (one inspection per GRN).
 *   start → Open (prefilled from the GRN: final grade = received grade)
 *   save  → rolls, measured meters, width, line status + remarks (required); gross kg, GSM, remarks (optional);
 *           defects, final A/B/C, reprocess, shortage. Every status change is logged (qc_status_logs).
 *   close → every line must have a status other than Hold; stock regraded at the GRN rate;
 *           reprocess meters put on hold ("reprocess" status); shortage written off and charged back
 *           to the mill (debit note + PDC reduced)
 */
class QcInspectionService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private VoucherService $vouchers,
        private AccountMappingService $mapping,
    ) {}

    public function start(PurchaseReceiving $receiving, ?int $userId = null): QcInspection
    {
        return DB::transaction(function () use ($receiving, $userId) {
            $receiving = PurchaseReceiving::with('items', 'purchaseOrder')->lockForUpdate()->findOrFail($receiving->id);
            if ($qc = QcInspection::where('purchase_receiving_id', $receiving->id)->first()) return $qc;

            $po = $receiving->purchaseOrder;
            if (!$po || $po->type !== 'processing') throw new \Exception('QC applies to fabric received on a Processing PO.');
            if ($receiving->status !== 'Approved') throw new \Exception('The receiving must be approved before QC.');

            $location = LocationStockLedger::where('reference_type', 'PurchaseReceiving')->where('reference_id', $receiving->id)
                ->where('status', 'fresh')->whereNotNull('grade')->value('location_id');
            $width = (float) preg_replace('/[^0-9.]/', '', (string) ($po->fabric_specs['finished_width'] ?? '')) ?: null;
            $gsm = (float) preg_replace('/[^0-9.]/', '', (string) ($po->fabric_specs['finished_gsm'] ?? '')) ?: null;

            $qc = QcInspection::create([
                'qc_no' => $this->numbers->next('qc_inspection', 'qc_inspections', 'qc_no', 'QC'),
                'purchase_receiving_id' => $receiving->id, 'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id,
                'location_id' => $location, 'inspection_date' => now()->toDateString(), 'status' => QcInspection::OPEN,
                'created_by' => $userId, 'updated_by' => $userId,
            ]);

            foreach ($receiving->items as $item) {
                $a = (float) $item->qty_a; $b = (float) $item->qty_b; $c = (float) $item->qty_c;
                if ($a + $b + $c <= 0) continue;
                QcInspectionItem::create([
                    'qc_inspection_id' => $qc->id, 'purchase_receiving_item_id' => $item->id, 'product_id' => $item->product_id,
                    'lot_no' => $po->stock_lot_no, 'received_a' => $a, 'received_b' => $b, 'received_c' => $c,
                    'rate' => round((float) $item->amount / ($a + $b + $c), 4),
                    'measured_meters' => round($a + $b + $c, 3), // total received; inspector corrects if the kanta differs
                    'width_inch' => $width, 'expected_gsm' => $gsm,
                    'final_a' => $a, 'final_b' => $b, 'final_c' => $c,
                ]);
            }
            $receiving->update(['qc_status' => 'InProgress']);
            return $qc->load('items');
        });
    }

    /** Save the inspection while it is open. $items keyed by qc_inspection_items.id */
    public function save(QcInspection $qc, array $data, array $items, ?int $userId = null): QcInspection
    {
        return DB::transaction(function () use ($qc, $data, $items, $userId) {
            $qc = QcInspection::with('items.product')->lockForUpdate()->findOrFail($qc->id);
            if (!$qc->isOpen()) throw new \Exception('This QC is closed.');

            $num = fn($v, $dp = 3) => ($v === null || $v === '') ? null : max(0, round((float) $v, $dp));
            $text = fn($v) => trim((string) ($v ?? '')) ?: null;
            $missing = [];
            foreach ($qc->items as $line) {
                $row = $items[$line->id] ?? null;
                if (!$row) continue;
                $name = $line->product->name ?? "Line {$line->id}";

                $status = $row['status'] ?? null;
                if ($status !== null && $status !== '' && !array_key_exists($status, QcInspectionItem::STATUSES)) {
                    throw new \Exception("{$name}: unknown status \"{$status}\".");
                }
                $vals = [
                    'rolls' => ($row['rolls'] ?? '') === '' ? null : max(0, (int) $row['rolls']),
                    'measured_meters' => $num($row['measured_meters'] ?? null),
                    'width_inch' => $num($row['width_inch'] ?? null, 2),
                    'status' => $status ?: null,
                    'status_remarks' => $text($row['status_remarks'] ?? null),
                ];
                foreach (['rolls' => 'Rolls', 'measured_meters' => 'Measured meters', 'width_inch' => 'Width', 'status' => 'Status', 'status_remarks' => 'Status remarks'] as $k => $label) {
                    if ($vals[$k] === null || ($k !== 'status' && $k !== 'status_remarks' && (float) $vals[$k] <= 0)) $missing[] = "{$name}: {$label}";
                }

                $before = ['status' => $line->status, 'status_remarks' => $line->status_remarks];
                $line->update($vals + [
                    'gross_kg' => $num($row['gross_kg'] ?? null),
                    'expected_gsm' => $num($row['expected_gsm'] ?? ($row['gsm'] ?? null), 2),
                    'final_a' => $num($row['final_a'] ?? 0) ?? 0, 'final_b' => $num($row['final_b'] ?? 0) ?? 0,
                    'final_c' => $num($row['final_c'] ?? 0) ?? 0, 'reprocess_qty' => $num($row['reprocess_qty'] ?? 0) ?? 0,
                    'shortage_qty' => $num($row['shortage_qty'] ?? 0) ?? 0,
                    'defects' => array_values(array_intersect((array) ($row['defects'] ?? []), array_keys(QcInspectionItem::DEFECTS))) ?: null,
                    'defect_notes' => $text($row['defect_notes'] ?? null),
                ]);

                // Status log: a new entry whenever the status or its remarks change
                if ($vals['status'] && ($before['status'] !== $vals['status'] || $before['status_remarks'] !== $vals['status_remarks'])) {
                    QcStatusLog::create([
                        'qc_inspection_id' => $qc->id, 'qc_inspection_item_id' => $line->id,
                        'from_status' => $before['status'], 'to_status' => $vals['status'],
                        'remarks' => $vals['status_remarks'], 'changed_by' => $userId,
                    ]);
                }
            }
            if ($missing) throw new \Exception('Required: ' . implode(', ', $missing) . '.');

            $qc->update([
                'inspection_date' => $data['inspection_date'] ?? $qc->inspection_date,
                'remarks' => $text($data['remarks'] ?? null),
                'updated_by' => $userId,
            ]);
            return $qc->fresh('items');
        });
    }

    public function close(QcInspection $qc, int $userId): QcInspection
    {
        return DB::transaction(function () use ($qc, $userId) {
            $qc = QcInspection::with('items.product.category', 'receiving', 'purchaseOrder')->lockForUpdate()->findOrFail($qc->id);
            if (!$qc->isOpen()) throw new \Exception('This QC is already closed.');
            if (!$qc->location_id) throw new \Exception('Could not find where this fabric was stocked.');

            foreach ($qc->items as $l) {
                if (!$l->status) throw new \Exception("{$l->product->name}: set a QC status before closing.");
                if ($l->status === 'Hold') throw new \Exception("{$l->product->name}: status is Hold — resolve it before closing.");
                if (abs($l->accounted - $l->received_total) > 0.001) {
                    throw new \Exception("{$l->product->name}: A + B + C + reprocess + shortage must equal the {$l->received_total} m received (now {$l->accounted} m).");
                }
            }
            $po = $qc->purchaseOrder;
            $post = fn(array $row) => LocationStockLedger::create($row + [
                'doc_no' => $qc->qc_no, 'location_id' => $qc->location_id, 'lot_no' => $po->stock_lot_no,
                'reference_type' => 'QcInspection', 'reference_id' => $qc->id, 'entry_date' => $qc->inspection_date,
            ]);

            $credits = []; // stock account => value of shortage leaving stock
            $shortageValue = 0.0;

            foreach ($qc->items as $l) {
                $stockAcc = $l->product->category->stock_account_id ?? $this->mapping->accountId('stock_in_hand');

                // Regrade: grades losing meters first (they also feed reprocess and shortage)
                $deltas = ['A' => $l->final_a - $l->received_a, 'B' => $l->final_b - $l->received_b, 'C' => $l->final_c - $l->received_c];
                foreach ($deltas as $grade => $d) {
                    if ($d >= -0.0005) continue;
                    $have = (float) LocationStockLedger::where('location_id', $qc->location_id)->where('product_id', $l->product_id)
                        ->where('lot_no', $po->stock_lot_no)->where('status', 'fresh')->where('grade', $grade)->sum('quantity');
                    if ($have + 0.001 < -$d) {
                        throw new \Exception("{$l->product->name}: only {$have} m of {$grade} quality is still in stock, cannot take " . round(-$d, 3) . ' m.');
                    }
                    $post(['product_id' => $l->product_id, 'status' => 'fresh', 'grade' => $grade, 'quantity' => round($d, 3), 'amount' => round($d * $l->rate, 2)]);
                }
                foreach ($deltas as $grade => $d) {
                    if ($d > 0.0005) $post(['product_id' => $l->product_id, 'status' => 'fresh', 'grade' => $grade, 'quantity' => round($d, 3), 'amount' => round($d * $l->rate, 2)]);
                }

                // Defective meters on hold, waiting to go back to the mill (reprocess)
                if ($l->reprocess_qty > 0) {
                    $post(['product_id' => $l->product_id, 'status' => 'reprocess', 'grade' => null,
                           'quantity' => $l->reprocess_qty, 'amount' => round($l->reprocess_qty * $l->rate, 2),
                           'remarks' => $l->status_remarks ?: ($l->defect_labels ?: null)]);
                }

                // Shortage is already out of stock by the regrade; its value is charged to the mill
                if ($l->shortage_qty > 0) {
                    $v = round($l->shortage_qty * $l->rate, 2);
                    $shortageValue += $v;
                    $credits[$stockAcc] = ($credits[$stockAcc] ?? 0) + $v;
                }
            }

            // Debit note: the mill owes us for the shortage
            $chargeBack = round($shortageValue, 2);
            $pdcReduced = 0.0;
            if ($chargeBack > 0) {
                $ap = $this->mapping->accountId('accounts_payable');
                if (!$ap) throw new \Exception('Accounts Payable mapping is not configured.');
                $lines = [['account_id' => $ap, 'debit' => $chargeBack, 'credit' => 0, 'party_type' => 'vendor', 'party_id' => $qc->vendor_id]];
                foreach ($credits as $acc => $amt) if ($amt > 0) $lines[] = ['account_id' => $acc, 'debit' => 0, 'credit' => round($amt, 2)];
                $this->vouchers->post('system', $qc->inspection_date->format('Y-m-d'), $lines,
                    "QC {$qc->qc_no} — shortage claim on mill for {$po->order_no}", 'QcInspection', $qc->id, $userId);

                // Pay the mill less: lower the processing-charge PDC by what is not yet covered by cheques
                $pdc = Pdc::where('reference_type', 'PurchaseReceiving')->where('reference_id', $qc->purchase_receiving_id)->lockForUpdate()->first();
                if ($pdc) {
                    $pdcReduced = round(min($chargeBack, max(0, (float) $pdc->pending_amount)), 2);
                    if ($pdcReduced > 0) $pdc->update(['amount' => round((float) $pdc->amount - $pdcReduced, 2), 'updated_by' => $userId]);
                }
            }

            $qc->update([
                'status' => QcInspection::CLOSED, 'shortage_amount' => round($shortageValue, 2), 'pdc_reduced_by' => $pdcReduced,
                'closed_by' => $userId, 'closed_at' => now(), 'updated_by' => $userId,
            ]);
            $qc->receiving->update(['qc_status' => 'Closed']);
            return $qc->fresh('items');
        });
    }
}
