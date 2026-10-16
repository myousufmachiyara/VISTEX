<?php

namespace App\Services;

use App\Models\{LocationStockLedger, Pdc, PurchaseReceiving, QcInspection, QcInspectionItem};
use Illuminate\Support\Facades\DB;

/**
 * QC of processed fabric received on a Processing PO (one inspection per GRN).
 *   start → Open (prefilled from the GRN: final grade = received grade)
 *   save  → kanta weight, measured meters, defects, final A/B/C, reprocess, shortage, claim
 *   close → stock regraded at the GRN rate; reprocess meters put on hold ("reprocess" status);
 *           shortage written off and, with any B/C claim, charged back to the mill (debit note + PDC reduced)
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
            $qc = QcInspection::with('items')->lockForUpdate()->findOrFail($qc->id);
            if (!$qc->isOpen()) throw new \Exception('This QC is closed.');

            $num = fn($v, $dp = 3) => ($v === null || $v === '') ? null : max(0, round((float) $v, $dp));
            foreach ($qc->items as $line) {
                $row = $items[$line->id] ?? null;
                if (!$row) continue;
                $line->update([
                    'rolls' => ($row['rolls'] ?? '') === '' ? null : max(0, (int) $row['rolls']),
                    'gross_kg' => $num($row['gross_kg'] ?? null), 'tare_kg' => $num($row['tare_kg'] ?? null),
                    'measured_meters' => $num($row['measured_meters'] ?? null),
                    'width_inch' => $num($row['width_inch'] ?? null, 2), 'expected_gsm' => $num($row['expected_gsm'] ?? null, 2),
                    'final_a' => $num($row['final_a'] ?? 0) ?? 0, 'final_b' => $num($row['final_b'] ?? 0) ?? 0,
                    'final_c' => $num($row['final_c'] ?? 0) ?? 0, 'reprocess_qty' => $num($row['reprocess_qty'] ?? 0) ?? 0,
                    'shortage_qty' => $num($row['shortage_qty'] ?? 0) ?? 0,
                    'defects' => array_values(array_intersect((array) ($row['defects'] ?? []), array_keys(QcInspectionItem::DEFECTS))) ?: null,
                    'defect_notes' => trim((string) ($row['defect_notes'] ?? '')) ?: null,
                ]);
            }

            $qc->update([
                'inspection_date' => $data['inspection_date'] ?? $qc->inspection_date,
                'claim_amount' => max(0, round((float) ($data['claim_amount'] ?? 0), 2)),
                'claim_reason' => trim((string) ($data['claim_reason'] ?? '')) ?: null,
                'remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
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
                if (abs($l->accounted - $l->received_total) > 0.001) {
                    throw new \Exception("{$l->product->name}: A + B + C + reprocess + shortage must equal the {$l->received_total} m received (now {$l->accounted} m).");
                }
            }
            $po = $qc->purchaseOrder;
            $post = fn(array $row) => LocationStockLedger::create($row + [
                'doc_no' => $qc->qc_no, 'location_id' => $qc->location_id, 'lot_no' => $po->stock_lot_no,
                'reference_type' => 'QcInspection', 'reference_id' => $qc->id, 'entry_date' => $qc->inspection_date,
            ]);

            $credits = []; // stock account => amount leaving stock (shortage + claim)
            $shortageValue = 0.0;
            $claimLeft = (float) $qc->claim_amount;
            $bcMeters = $qc->items->sum(fn($l) => $l->final_b + $l->final_c);
            $allMeters = $qc->items->sum(fn($l) => $l->final_a + $l->final_b + $l->final_c);
            if ($claimLeft > 0 && $allMeters <= 0) throw new \Exception('A claim needs fabric left in stock to adjust.');
            $stockValue = round($qc->items->sum(fn($l) => ($l->final_a + $l->final_b + $l->final_c) * $l->rate), 2);
            if ($claimLeft > $stockValue + 0.01) throw new \Exception("The quality claim ({$claimLeft}) is more than the value of the fabric kept ({$stockValue}).");

            foreach ($qc->items as $idx => $l) {
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
                           'remarks' => $l->defect_labels ?: null]);
                }

                // Shortage is already out of stock by the regrade; its value is charged to the mill
                if ($l->shortage_qty > 0) {
                    $v = round($l->shortage_qty * $l->rate, 2);
                    $shortageValue += $v;
                    $credits[$stockAcc] = ($credits[$stockAcc] ?? 0) + $v;
                }

                // B/C quality claim lowers the value of this lot (B and C meters first)
                if ($claimLeft > 0.004) {
                    $isLast = $idx === $qc->items->keys()->last();
                    $weight = $bcMeters > 0 ? ($l->final_b + $l->final_c) / $bcMeters : ($l->final_a + $l->final_b + $l->final_c) / $allMeters;
                    $share = $isLast ? round($claimLeft, 2) : round((float) $qc->claim_amount * $weight, 2);
                    $share = min($share, $claimLeft);
                    if ($share > 0) {
                        $grades = $bcMeters > 0 ? ['B' => $l->final_b, 'C' => $l->final_c] : ['A' => $l->final_a, 'B' => $l->final_b, 'C' => $l->final_c];
                        $grades = array_filter($grades, fn($q) => $q > 0);
                        $tot = array_sum($grades); $left = $share; $n = 0;
                        foreach ($grades as $grade => $q) {
                            $amt = ++$n === count($grades) ? round($left, 2) : round($share * $q / $tot, 2);
                            $left -= $amt;
                            $post(['product_id' => $l->product_id, 'status' => 'fresh', 'grade' => $grade, 'quantity' => 0, 'amount' => -$amt,
                                   'remarks' => 'Quality claim on mill']);
                        }
                        $credits[$stockAcc] = ($credits[$stockAcc] ?? 0) + $share;
                        $claimLeft = round($claimLeft - $share, 2);
                    }
                }
            }

            // Debit note: the mill owes us for shortage + quality claim
            $chargeBack = round($shortageValue + (float) $qc->claim_amount, 2);
            $pdcReduced = 0.0;
            if ($chargeBack > 0) {
                $ap = $this->mapping->accountId('accounts_payable');
                if (!$ap) throw new \Exception('Accounts Payable mapping is not configured.');
                $lines = [['account_id' => $ap, 'debit' => $chargeBack, 'credit' => 0, 'party_type' => 'vendor', 'party_id' => $qc->vendor_id]];
                foreach ($credits as $acc => $amt) if ($amt > 0) $lines[] = ['account_id' => $acc, 'debit' => 0, 'credit' => round($amt, 2)];
                $this->vouchers->post('system', $qc->inspection_date->format('Y-m-d'), $lines,
                    "QC {$qc->qc_no} — claim on mill for {$po->order_no}" . ($qc->claim_reason ? " ({$qc->claim_reason})" : ''), 'QcInspection', $qc->id, $userId);

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
