<?php

namespace App\Services;

use App\Models\ConversionPurchaseOrder;
use App\Models\GreigeReceive;
use App\Models\GreigeReceiveYarnConsumed;
use App\Models\GreigeReceiveOutput;
use App\Models\YarnInProcessLedger;
use App\Models\Location;
use App\Models\LocationStockLedger;
use Illuminate\Support\Facades\DB;

class GreigeReceiveService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    // Records the receipt. Yarn-consumed calc + greige stock + accounting
    // are ALL deferred until Approve — matching the same PendingApproval
    // pattern as Purchase Receiving.
    public function create(array $data, array $outputs, ?int $userId = null): GreigeReceive
    {
        return DB::transaction(function () use ($data, $outputs, $userId) {

            $cpo = ConversionPurchaseOrder::with('warpProduct', 'weftProduct', 'vendor')->findOrFail($data['cpo_id']);

            $outputs = array_values(array_filter($outputs, fn($o) => (float) ($o['quantity_output'] ?? 0) > 0));
            if (empty($outputs)) {
                throw new \Exception('Enter a quantity for at least one greige output.');
            }

            $receive = GreigeReceive::create([
                'receive_no'          => $this->numberService->next('greige_receive', 'greige_receives', 'receive_no', 'GR'),
                'cpo_id'               => $cpo->id,
                'receive_date'         => $data['receive_date'],
                'vendor_challan_no'    => $data['vendor_challan_no'],
                'is_final_receiving'   => (bool) ($data['is_final_receiving'] ?? false),
                'status'               => 'PendingApproval',
                'remarks'              => $data['remarks'] ?? null,
                'attachments'          => $data['attachments'],
                'created_by'           => $userId,
                'updated_by'           => $userId,
            ]);

            $totalGreigeQty = 0;
            $weavingChargeTotal = 0;

            foreach ($outputs as $output) {
                $qty  = (float) $output['quantity_output'];
                $rate = (float) $output['weaving_rate'];
                $charge = round($qty * $rate, 2);
                $weavingChargeTotal += $charge;
                $totalGreigeQty += $qty;

                GreigeReceiveOutput::create([
                    'greige_receive_id' => $receive->id,
                    'greige_product_id' => $output['greige_product_id'],
                    'quantity_output'   => $qty,
                    'weaving_rate'      => $rate,
                    'weaving_charge'    => $charge,
                ]);
            }

            $receive->update(['weaving_charge_amount' => $weavingChargeTotal]);

            return $receive->load('outputs.greigeProduct', 'cpo.vendor');
        });
    }

    // Category in-charge (Greige) or superadmin approves — THIS calculates
    // yarn cost consumed, hits stock, and posts the accounting voucher.
    public function approve(GreigeReceive $receive, int $approverId): GreigeReceive
    {
        return DB::transaction(function () use ($receive, $approverId) {

            if ($receive->status !== 'PendingApproval') {
                throw new \Exception('This receiving has already been ' . strtolower($receive->status) . '.');
            }

            $cpo = ConversionPurchaseOrder::with('warpProduct', 'weftProduct', 'vendor')->findOrFail($receive->cpo_id);
            $defaultLocationId = Location::whereNull('vendor_id')->value('id');

            if (!$defaultLocationId) {
                throw new \Exception('No default warehouse location configured.');
            }

            $totalGreigeQty = (float) $receive->outputs->sum('quantity_output');

            // ── Yarn cost consumed, per warp/weft product ──────────────
            $yarnCostTotal = 0;
            $yarnLines = [
                ['product' => $cpo->warpProduct, 'per_unit' => (float) $cpo->warp_consumption],
                ['product' => $cpo->weftProduct, 'per_unit' => (float) $cpo->weft_consumption],
            ];

            foreach ($yarnLines as $yl) {
                if (!$yl['product']) continue;

                $balance = YarnInProcessLedger::balanceForCpoProduct($cpo->id, $yl['product']->id);
                $avgRate = $balance['quantity'] > 0 ? $balance['amount'] / $balance['quantity'] : 0;

                if ($receive->is_final_receiving) {
                    // True-up: consume exactly what's left, clearing the balance to zero
                    $qtyConsumed = round($balance['quantity'], 3);
                    $amtConsumed = round($balance['amount'], 2);
                } else {
                    $qtyConsumed = round($yl['per_unit'] * $totalGreigeQty, 3);
                    $qtyConsumed = min($qtyConsumed, $balance['quantity']); // never consume more than what's there
                    $amtConsumed = round($qtyConsumed * $avgRate, 2);
                }

                if ($qtyConsumed <= 0) continue;

                GreigeReceiveYarnConsumed::create([
                    'greige_receive_id' => $receive->id,
                    'product_id'        => $yl['product']->id,
                    'quantity'          => $qtyConsumed,
                    'rate'              => $avgRate,
                    'amount'            => $amtConsumed,
                ]);

                YarnInProcessLedger::create([
                    'cpo_id'          => $cpo->id,
                    'vendor_id'       => $cpo->vendor_id,
                    'product_id'      => $yl['product']->id,
                    'quantity'        => -$qtyConsumed,
                    'amount'          => -$amtConsumed,
                    'reference_type'  => 'GreigeReceive',
                    'reference_id'    => $receive->id,
                    'entry_date'      => $receive->receive_date,
                ]);

                $yarnCostTotal += $amtConsumed;
            }

            // ── Greige output stock, valued at yarn cost + weaving charge ──
            $weavingChargeTotal = (float) $receive->weaving_charge_amount;
            $totalAmount = round($yarnCostTotal + $weavingChargeTotal, 2);

            foreach ($receive->outputs as $output) {
                $proportion = $totalGreigeQty > 0 ? $output->quantity_output / $totalGreigeQty : 0;
                $stockValue = round($totalAmount * $proportion, 2);

                LocationStockLedger::create([
                    'doc_no'          => $receive->receive_no,
                    'location_id'     => $defaultLocationId,
                    'product_id'      => $output->greige_product_id,
                    'status'          => 'fresh',
                    'quantity'        => $output->quantity_output,
                    'amount'          => $stockValue,
                    'reference_type'  => 'GreigeReceive',
                    'reference_id'    => $receive->id,
                    'entry_date'      => $receive->receive_date,
                ]);
            }

            $receive->update([
                'yarn_cost_amount' => $yarnCostTotal,
                'total_amount'      => $totalAmount,
                'status'            => 'Approved',
                'approved_by'       => $approverId,
                'approved_at'       => now(),
                'updated_by'        => $approverId,
            ]);

            $this->postVoucher($receive->fresh(), $cpo, $yarnCostTotal, $weavingChargeTotal, $approverId);

            return $receive->fresh();
        });
    }

    public function reject(GreigeReceive $receive, int $approverId, string $reason): GreigeReceive
    {
        if ($receive->status !== 'PendingApproval') {
            throw new \Exception('This receiving has already been ' . strtolower($receive->status) . '.');
        }

        // Nothing was posted at creation, so rejection is just a status flip
        $receive->update([
            'status'            => 'Rejected',
            'rejection_reason'  => $reason,
            'updated_by'        => $approverId,
        ]);

        return $receive;
    }

    public function delete(GreigeReceive $receive): void
    {
        DB::transaction(function () use ($receive) {
            if ($receive->status === 'Approved') {
                throw new \Exception('Cannot delete an approved receiving — it has posted accounting entries.');
            }

            $receive->outputs()->delete();
            $receive->yarnConsumed()->delete();
            $receive->delete();
        });
    }

    // Dr Stock in Hand — Greige / Cr Yarn in Process (yarn portion) / Cr AP (weaving charge portion)
    private function postVoucher(GreigeReceive $receive, ConversionPurchaseOrder $cpo, float $yarnCost, float $weavingCharge, ?int $userId): void
    {
        $greigeCategory = \App\Models\ProductCategory::where('code', 'greige')->first();
        $stockAccountId = $greigeCategory?->stock_account_id ?? $this->mappingService->accountId('stock_in_hand');
        $yipAccountId   = $this->mappingService->accountId('yarn_in_process');
        $apAccountId    = $this->mappingService->accountId('accounts_payable');

        if (!$stockAccountId || !$yipAccountId || !$apAccountId) {
            throw new \Exception('Required account mappings (stock, yarn in process, or payable) are not configured.');
        }

        $lines = [];
        $total = round($yarnCost + $weavingCharge, 2);

        if ($total > 0) {
            $lines[] = ['account_id' => $stockAccountId, 'debit' => $total, 'credit' => 0];
        }
        if ($yarnCost > 0) {
            $lines[] = ['account_id' => $yipAccountId, 'debit' => 0, 'credit' => $yarnCost];
        }
        if ($weavingCharge > 0) {
            $lines[] = [
                'account_id' => $apAccountId, 'debit' => 0, 'credit' => $weavingCharge,
                'party_type' => 'vendor', 'party_id' => $cpo->vendor_id,
            ];
        }

        if (empty($lines) || count($lines) < 2) return;

        $this->voucherService->post(
            'system',
            $receive->receive_date instanceof \Carbon\Carbon ? $receive->receive_date->format('Y-m-d') : $receive->receive_date,
            $lines,
            "Greige Receive {$receive->receive_no} — {$cpo->cpo_no}, Challan: {$receive->vendor_challan_no}",
            'GreigeReceive',
            $receive->id,
            $userId
        );
    }
}