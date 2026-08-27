<?php

namespace App\Services;

use App\Models\ConversionPurchaseOrder;
use App\Models\YarnIssue;
use App\Models\YarnIssueItem;
use App\Models\YarnInProcessLedger;
use App\Models\Location;
use App\Models\LocationStockLedger;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class YarnIssueService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    // $items: [ ['product_id'=>.., 'quantity'=>..], ... ] — warp and/or weft yarn
    public function create(array $data, array $items, ?int $userId = null): YarnIssue
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $cpo = ConversionPurchaseOrder::findOrFail($data['cpo_id']);
            $defaultLocationId = Location::where('vendor_id', null)->where('is_default', true)->value('id')
                ?? Location::whereNull('vendor_id')->value('id');

            if (!$defaultLocationId) {
                throw new \Exception('No default warehouse location configured.');
            }

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter a quantity for at least one yarn.');
            }

            // Hard block: total ever issued (existing + this) cannot exceed CPO's required yarn weight
            $alreadyIssued = $cpo->yarn_issued_total;
            $thisIssueTotal = array_sum(array_column($items, 'quantity'));
            $required = (float) $cpo->total_yarn_weight_consumed;

            if ($required > 0 && ($alreadyIssued + $thisIssueTotal) > $required + 0.001) {
                $remaining = round($required - $alreadyIssued, 3);
                throw new \Exception(
                    "Cannot issue {$thisIssueTotal} — CPO {$cpo->cpo_no} allows only " . round($remaining, 3) . " more yarn."
                );
            }

            $issue = YarnIssue::create([
                'issue_no'    => $this->numberService->next('yarn_issue', 'yarn_issues', 'issue_no', 'YI'),
                'cpo_id'      => $cpo->id,
                'issue_date'  => $data['issue_date'],
                'remarks'     => $data['remarks'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $product = Product::findOrFail($item['product_id']);

                $available = LocationStockLedger::balance($defaultLocationId, $product->id, 'fresh');
                if ($qty > $available + 0.001) {
                    throw new \Exception("Insufficient stock of {$product->name} — available: " . round($available, 3) . ", requested: {$qty}.");
                }

                $rate = $product->weightedAverageCost($defaultLocationId);
                $amount = round($qty * $rate, 2);
                $totalAmount += $amount;

                YarnIssueItem::create([
                    'yarn_issue_id' => $issue->id,
                    'product_id'    => $product->id,
                    'quantity'      => $qty,
                    'rate'          => $rate,
                    'amount'        => $amount,
                ]);

                // Leaves our warehouse
                LocationStockLedger::create([
                    'doc_no'          => $issue->issue_no,
                    'location_id'     => $defaultLocationId,
                    'product_id'      => $product->id,
                    'status'          => 'fresh',
                    'quantity'        => -$qty,
                    'amount'          => -$amount,
                    'reference_type'  => 'YarnIssue',
                    'reference_id'    => $issue->id,
                    'entry_date'      => $data['issue_date'],
                ]);

                // Now sits "in process" at the weaving mill
                YarnInProcessLedger::create([
                    'cpo_id'          => $cpo->id,
                    'vendor_id'       => $cpo->vendor_id,
                    'product_id'      => $product->id,
                    'quantity'        => $qty,
                    'amount'          => $amount,
                    'reference_type'  => 'YarnIssue',
                    'reference_id'    => $issue->id,
                    'entry_date'      => $data['issue_date'],
                ]);
            }

            $this->postVoucher($issue, $cpo, $totalAmount, $userId);

            return $issue->load('items.product', 'cpo.vendor');
        });
    }

    public function delete(YarnIssue $issue): void
    {
        DB::transaction(function () use ($issue) {
            if (\App\Models\GreigeReceive::where('cpo_id', $issue->cpo_id)->exists()) {
                throw new \Exception('Cannot delete — greige has already been received against this CPO.');
            }

            LocationStockLedger::where('reference_type', 'YarnIssue')->where('reference_id', $issue->id)->delete();
            YarnInProcessLedger::where('reference_type', 'YarnIssue')->where('reference_id', $issue->id)->delete();
            $this->voucherService->deleteByReference('YarnIssue', $issue->id);

            $issue->items()->delete();
            $issue->delete();
        });
    }

    // Dr Yarn in Process (asset) / Cr Stock in Hand — Yarn
    private function postVoucher(YarnIssue $issue, ConversionPurchaseOrder $cpo, float $totalAmount, ?int $userId): void
    {
        if ($totalAmount <= 0) return;

        $yipAccountId = $this->mappingService->accountId('yarn_in_process');
        $stockAccountId = $this->mappingService->accountId('stock_in_hand');

        if (!$yipAccountId || !$stockAccountId) {
            throw new \Exception('Yarn in Process or Stock in Hand mapping is not configured — check Account Mappings.');
        }

        $lines = [
            ['account_id' => $yipAccountId, 'debit' => $totalAmount, 'credit' => 0],
            ['account_id' => $stockAccountId, 'debit' => 0, 'credit' => $totalAmount],
        ];

        $this->voucherService->post(
            'system',
            $issue->issue_date instanceof \Carbon\Carbon ? $issue->issue_date->format('Y-m-d') : $issue->issue_date,
            $lines,
            "Yarn Issue {$issue->issue_no} — {$cpo->cpo_no} to {$cpo->vendor->name}",
            'YarnIssue',
            $issue->id,
            $userId
        );
    }

    public function update(YarnIssue $issue, array $data, array $items, ?int $userId = null): YarnIssue
    {
        return DB::transaction(function () use ($issue, $data, $items, $userId) {

            $cpo = ConversionPurchaseOrder::findOrFail($issue->cpo_id);
            $defaultLocationId = Location::whereNull('vendor_id')->value('id');

            // Reverse everything this issue previously did
            LocationStockLedger::where('reference_type', 'YarnIssue')->where('reference_id', $issue->id)->delete();
            YarnInProcessLedger::where('reference_type', 'YarnIssue')->where('reference_id', $issue->id)->delete();
            $this->voucherService->deleteByReference('YarnIssue', $issue->id);
            $issue->items()->delete();

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter a quantity for at least one yarn.');
            }

            // Re-check the hard cap, excluding this issue's own (now-zeroed) contribution
            $alreadyIssued = $cpo->yarn_issued_total; // already excludes this issue since items were just deleted
            $thisIssueTotal = array_sum(array_column($items, 'quantity'));
            $required = (float) $cpo->total_yarn_weight_consumed;

            if ($required > 0 && ($alreadyIssued + $thisIssueTotal) > $required + 0.001) {
                $remaining = round($required - $alreadyIssued, 3);
                throw new \Exception("Cannot issue {$thisIssueTotal} — CPO {$cpo->cpo_no} allows only " . round($remaining, 3) . " more yarn.");
            }

            $issue->update([
                'issue_date'  => $data['issue_date'],
                'remarks'     => $data['remarks'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                'updated_by'  => $userId,
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $product = Product::findOrFail($item['product_id']);

                $available = LocationStockLedger::balance($defaultLocationId, $product->id, 'fresh');
                if ($qty > $available + 0.001) {
                    throw new \Exception("Insufficient stock of {$product->name} — available: " . round($available, 3) . ", requested: {$qty}.");
                }

                $rate = $product->weightedAverageCost($defaultLocationId);
                $amount = round($qty * $rate, 2);
                $totalAmount += $amount;

                YarnIssueItem::create([
                    'yarn_issue_id' => $issue->id,
                    'product_id'    => $product->id,
                    'quantity'      => $qty,
                    'rate'          => $rate,
                    'amount'        => $amount,
                ]);

                LocationStockLedger::create([
                    'doc_no' => $issue->issue_no, 'location_id' => $defaultLocationId, 'product_id' => $product->id,
                    'status' => 'fresh', 'quantity' => -$qty, 'amount' => -$amount,
                    'reference_type' => 'YarnIssue', 'reference_id' => $issue->id, 'entry_date' => $data['issue_date'],
                ]);

                YarnInProcessLedger::create([
                    'cpo_id' => $cpo->id, 'vendor_id' => $cpo->vendor_id, 'product_id' => $product->id,
                    'quantity' => $qty, 'amount' => $amount,
                    'reference_type' => 'YarnIssue', 'reference_id' => $issue->id, 'entry_date' => $data['issue_date'],
                ]);
            }

            $this->postVoucher($issue, $cpo, $totalAmount, $userId);

            return $issue->load('items.product', 'cpo.vendor');
        });
    }
}