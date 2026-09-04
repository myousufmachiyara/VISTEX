<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\YarnIssue;
use App\Models\YarnIssueItem;
use App\Models\LocationStockLedger;
use App\Models\Location;
use App\Models\Product;
use App\Models\YarnInProcessLedger;
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

            $po = \App\Models\PurchaseOrder::findOrFail($data['purchase_order_id']);

            if ($po->type !== 'weaving') {
                throw new \Exception('Yarn can only be issued against a Weaving-type Purchase Order.');
            }
            if (!in_array($po->status, ['Approved', 'PartiallyReceived'])) {
                throw new \Exception('This Purchase Order is not in a state that allows yarn issuance.');
            }

            $defaultLocationId = \App\Models\Location::whereNull('vendor_id')->value('id');

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter a quantity for at least one yarn.');
            }

            $alreadyIssued = (float) $po->yarn_issued_total;
            $thisIssueTotal = array_sum(array_column($items, 'quantity'));
            $required = (float) $po->total_yarn_weight_consumed;

            if ($required > 0 && ($alreadyIssued + $thisIssueTotal) > $required + 0.001) {
                $remaining = round($required - $alreadyIssued, 3);
                throw new \Exception("Cannot issue {$thisIssueTotal} — PO {$po->order_no} allows only {$remaining} more yarn.");
            }

            $issue = \App\Models\YarnIssue::create([
                'issue_no'          => $this->numberService->next('yarn_issue', 'yarn_issues', 'issue_no', 'YI'),
                'purchase_order_id' => $po->id,
                'issue_date'        => $data['issue_date'],
                'remarks'           => $data['remarks'] ?? null,
                'attachments'       => $data['attachments'] ?? null,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $product = \App\Models\Product::findOrFail($item['product_id']);

                $available = \App\Models\LocationStockLedger::balance($defaultLocationId, $product->id, 'fresh');
                if ($qty > $available + 0.001) {
                    throw new \Exception("Insufficient stock of {$product->name} — available: " . round($available, 3) . ", requested: {$qty}.");
                }

                $rate = $product->weightedAverageCost($defaultLocationId);
                $amount = round($qty * $rate, 2);
                $totalAmount += $amount;

                \App\Models\YarnIssueItem::create([
                    'yarn_issue_id' => $issue->id,
                    'product_id'    => $product->id,
                    'quantity'      => $qty,
                    'rate'          => $rate,
                    'amount'        => $amount,
                ]);

                \App\Models\LocationStockLedger::create([
                    'doc_no' => $issue->issue_no, 'location_id' => $defaultLocationId, 'product_id' => $product->id,
                    'status' => 'fresh', 'quantity' => -$qty, 'amount' => -$amount,
                    'reference_type' => 'YarnIssue', 'reference_id' => $issue->id, 'entry_date' => $data['issue_date'],
                ]);

                \App\Models\YarnInProcessLedger::create([
                    'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'product_id' => $product->id,
                    'quantity' => $qty, 'amount' => $amount,
                    'reference_type' => 'YarnIssue', 'reference_id' => $issue->id, 'entry_date' => $data['issue_date'],
                ]);
            }

            $this->postVoucher($issue, $po, $totalAmount, $userId);
            // First yarn issue against this PO moves it from Approved -> Issued
            if ($po->status === 'Approved') {
                $po->update(['status' => 'Issued']);
            }

            return $issue->load('items.product', 'purchaseOrder.vendor');
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
    private function postVoucher(YarnIssue $issue, PurchaseOrder $po, float $totalAmount, ?int $userId): void
    {
        $yipAccountId = $this->mappingService->accountId('yarn_in_process');
        $stockAccountId = $this->mappingService->accountId('stock_in_hand');

        if (!$yipAccountId || !$stockAccountId) {
            throw new \Exception('Required account mappings (yarn_in_process, stock_in_hand) are missing.');
        }

        $lines = [
            ['account_id' => $yipAccountId, 'debit' => $totalAmount, 'credit' => 0],
            ['account_id' => $stockAccountId, 'debit' => 0, 'credit' => $totalAmount],
        ];

        $this->voucherService->post(
            'system',
            $issue->issue_date->format('Y-m-d'),
            $lines,
            "Yarn Issue {$issue->issue_no} — {$po->order_no}",
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