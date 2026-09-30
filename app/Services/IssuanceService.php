<?php

namespace App\Services;

use App\Models\{Issuance, IssuanceItem, Location, LocationStockLedger, Product, PurchaseOrder, PurchaseReceiving, YarnInProcessLedger};
use Illuminate\Support\Facades\DB;

/**
 * Issuance = material leaving our warehouse against a document.
 *
 *  yarn_weaving       stock out of warehouse -> Yarn-in-Process at the weaving mill
 *                     Dr Yarn in Process / Cr Stock. Capped per yarn at the PO's warp/weft requirement.
 *  greige_processing  stock transfer: our warehouse -> processing mill's location (still our stock, no voucher)
 *
 * First issuance moves the PO Approved -> Issued, which is what makes a
 * weaving/processing PO receivable at the gate.
 */
class IssuanceService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private VoucherService $voucherService,
        private AccountMappingService $mappingService,
    ) {}

    public function create(array $data, array $items, ?int $userId = null): Issuance
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            [$type, $po] = $this->validateHeader($data);

            $issuance = Issuance::create([
                'issue_no'                => $this->numberService->next('issuance', 'issuances', 'issue_no', 'ISS'),
                'issue_type'              => $type,
                'purchase_order_id'       => $po->id,
                'vendor_id'               => $po->vendor_id,
                'source_location_id'      => $data['source_location_id'],
                'destination_location_id' => $data['destination_location_id'] ?? null,
                'issue_date'              => $data['issue_date'],
                'remarks'                 => $data['remarks'] ?? null,
                'attachments'             => $data['attachments'] ?? null,
                'created_by'              => $userId,
                'updated_by'              => $userId,
            ]);

            $this->apply($issuance, $po, $items, $userId);

            if ($po->status === PurchaseOrder::STATUS_APPROVED) {
                $po->update(['status' => PurchaseOrder::STATUS_ISSUED]);
            }

            return $issuance->load('items.product', 'purchaseOrder.vendor');
        });
    }

    public function update(Issuance $issuance, array $data, array $items, ?int $userId = null): Issuance
    {
        return DB::transaction(function () use ($issuance, $data, $items, $userId) {
            $this->assertReversible($issuance);
            $data['issue_type'] = $issuance->issue_type;
            $data['purchase_order_id'] = $issuance->purchase_order_id;
            [, $po] = $this->validateHeader($data);

            $this->reverse($issuance);
            $issuance->update([
                'source_location_id'      => $data['source_location_id'],
                'destination_location_id' => $data['destination_location_id'] ?? null,
                'issue_date'              => $data['issue_date'],
                'remarks'                 => $data['remarks'] ?? null,
                'attachments'             => $data['attachments'] ?? $issuance->attachments,
                'updated_by'              => $userId,
            ]);
            $this->apply($issuance->fresh(), $po, $items, $userId);

            return $issuance->fresh(['items.product', 'purchaseOrder.vendor']);
        });
    }

    public function delete(Issuance $issuance): void
    {
        DB::transaction(function () use ($issuance) {
            $this->assertReversible($issuance);
            $this->reverse($issuance);
            $po = $issuance->purchaseOrder;
            $issuance->delete();

            // Last issuance gone: PO is no longer "Issued"
            if ($po && $po->status === PurchaseOrder::STATUS_ISSUED && !$po->issuances()->exists()) {
                $po->update(['status' => PurchaseOrder::STATUS_APPROVED]);
            }
        });
    }

    // ── Per-PO figures used by the create form ─────────────────────────
    public function yarnRequirement(PurchaseOrder $po, ?int $excludeIssuanceId = null): array
    {
        $required = [];
        if ($po->warp_product_id) $required[$po->warp_product_id] = ($required[$po->warp_product_id] ?? 0) + (float) $po->warp_required_lbs;
        if ($po->weft_product_id) $required[$po->weft_product_id] = ($required[$po->weft_product_id] ?? 0) + (float) $po->weft_required_lbs;

        $issued = IssuanceItem::join('issuances', 'issuances.id', '=', 'issuance_items.issuance_id')
            ->where('issuances.purchase_order_id', $po->id)->whereNull('issuances.deleted_at')
            ->when($excludeIssuanceId, fn($q) => $q->where('issuances.id', '!=', $excludeIssuanceId))
            ->groupBy('issuance_items.product_id')
            ->selectRaw('issuance_items.product_id, SUM(issuance_items.quantity) as qty')
            ->pluck('qty', 'product_id');

        $out = [];
        foreach ($required as $productId => $req) {
            $iss = (float) ($issued[$productId] ?? 0);
            $out[$productId] = ['required' => round($req, 3), 'issued' => round($iss, 3), 'remaining' => round($req - $iss, 3)];
        }
        return $out;
    }

    // ── internals ──────────────────────────────────────────────────────

    private function validateHeader(array $data): array
    {
        $type = $data['issue_type'] ?? null;
        $def = Issuance::TYPES[$type] ?? null;
        if (!$def) throw new \Exception('Unknown issuance type.');
        if (!$def['active']) throw new \Exception("{$def['label']} is not enabled yet — pending client discussion.");

        $po = PurchaseOrder::with('vendor')->findOrFail($data['purchase_order_id'] ?? 0);
        if ($po->type !== $def['po_type']) throw new \Exception("{$def['label']} must be issued against a {$def['against']}.");
        if (!in_array($po->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ISSUED, PurchaseOrder::STATUS_PARTIAL])) {
            throw new \Exception("{$po->order_no} is {$po->status_label} — material can only be issued against an approved, open PO.");
        }

        $source = Location::find($data['source_location_id'] ?? 0);
        if (!$source || !$source->isOwnWarehouse()) throw new \Exception('Select one of our warehouses to issue from.');

        if ($type === 'greige_processing') {
            $dest = Location::find($data['destination_location_id'] ?? 0);
            if (!$dest || (int) $dest->vendor_id !== (int) $po->vendor_id) {
                throw new \Exception("Select the processing mill location of {$po->vendor->name} to send greige to.");
            }
        }
        return [$type, $po];
    }

    private function apply(Issuance $issuance, PurchaseOrder $po, array $items, ?int $userId): void
    {
        $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
        if (empty($items)) throw new \Exception('Enter a quantity for at least one item.');

        $sourceId = (int) $issuance->source_location_id;
        $isYarn = $issuance->issue_type === 'yarn_weaving';
        $requirement = $isYarn ? $this->yarnRequirement($po, $issuance->id) : [];

        // Combine duplicate product+lot rows before checking stock
        $grouped = [];
        foreach ($items as $i) {
            $key = $i['product_id'] . '|' . trim((string) ($i['lot_no'] ?? ''));
            $grouped[$key] = [
                'product_id' => (int) $i['product_id'], 'lot_no' => trim((string) ($i['lot_no'] ?? '')) ?: null,
                'purchase_order_item_id' => $i['purchase_order_item_id'] ?? null,
                'quantity' => round(($grouped[$key]['quantity'] ?? 0) + (float) $i['quantity'], 3),
            ];
        }

        $perProduct = [];
        foreach ($grouped as $g) $perProduct[$g['product_id']] = ($perProduct[$g['product_id']] ?? 0) + $g['quantity'];

        if ($isYarn) {
            foreach ($perProduct as $productId => $qty) {
                if (!isset($requirement[$productId])) {
                    throw new \Exception('Only the warp/weft yarn specified on ' . $po->order_no . ' can be issued against it.');
                }
                $r = $requirement[$productId];
                if ($r['required'] > 0 && $qty > $r['remaining'] + 0.001) {
                    $name = Product::find($productId)->name ?? 'yarn';
                    throw new \Exception("Cannot issue {$qty} of {$name} — {$po->order_no} needs {$r['required']}, {$r['issued']} already issued, {$r['remaining']} remaining. Amend the PO to issue more.");
                }
            }
        }

        $totalQty = 0; $totalAmount = 0; $stockCredits = [];

        foreach ($grouped as $g) {
            $product = Product::with('category')->findOrFail($g['product_id']);
            $available = LocationStockLedger::balance($sourceId, $product->id, 'fresh', $g['lot_no']);
            if ($g['quantity'] > $available + 0.001) {
                $where = $g['lot_no'] ? " (lot {$g['lot_no']})" : '';
                throw new \Exception("Insufficient stock of {$product->name}{$where} — available " . round($available, 3) . ", requested {$g['quantity']}.");
            }

            $rate = $product->weightedAverageCost($sourceId);
            $amount = round($g['quantity'] * $rate, 2);
            $totalQty += $g['quantity']; $totalAmount += $amount;

            IssuanceItem::create([
                'issuance_id' => $issuance->id, 'product_id' => $product->id, 'lot_no' => $g['lot_no'],
                'purchase_order_item_id' => $g['purchase_order_item_id'], 'quantity' => $g['quantity'], 'rate' => $rate, 'amount' => $amount,
            ]);

            LocationStockLedger::create([
                'doc_no' => $issuance->issue_no, 'location_id' => $sourceId, 'product_id' => $product->id,
                'status' => 'fresh', 'lot_no' => $g['lot_no'], 'quantity' => -$g['quantity'], 'amount' => -$amount,
                'reference_type' => 'Issuance', 'reference_id' => $issuance->id, 'entry_date' => $issuance->issue_date,
            ]);

            if ($isYarn) {
                YarnInProcessLedger::create([
                    'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'product_id' => $product->id,
                    'quantity' => $g['quantity'], 'amount' => $amount,
                    'reference_type' => 'Issuance', 'reference_id' => $issuance->id, 'entry_date' => $issuance->issue_date,
                ]);
                $acc = $product->category->stock_account_id ?? $this->mappingService->accountId('stock_in_hand');
                $stockCredits[$acc] = ($stockCredits[$acc] ?? 0) + $amount;
            } else {
                // Same goods, new place: lot defaults to the issuance number so the mill can track it
                LocationStockLedger::create([
                    'doc_no' => $issuance->issue_no, 'location_id' => $issuance->destination_location_id, 'product_id' => $product->id,
                    'status' => 'fresh', 'lot_no' => $g['lot_no'] ?? $issuance->issue_no, 'quantity' => $g['quantity'], 'amount' => $amount,
                    'reference_type' => 'Issuance', 'reference_id' => $issuance->id, 'entry_date' => $issuance->issue_date,
                    'remarks' => "At mill for {$po->order_no}",
                ]);
            }
        }

        $issuance->update(['total_quantity' => round($totalQty, 3), 'total_amount' => round($totalAmount, 2)]);

        if ($isYarn && $totalAmount > 0) {
            $yip = $this->mappingService->accountId('yarn_in_process');
            if (!$yip) throw new \Exception('Account mapping "yarn_in_process" is missing.');
            $lines = [['account_id' => $yip, 'debit' => round($totalAmount, 2), 'credit' => 0]];
            foreach ($stockCredits as $acc => $amt) {
                if (!$acc) throw new \Exception('Account mapping "stock_in_hand" is missing.');
                $lines[] = ['account_id' => $acc, 'debit' => 0, 'credit' => round($amt, 2)];
            }
            $this->voucherService->post('system', $issuance->issue_date->format('Y-m-d'), $lines,
                "Yarn issued {$issuance->issue_no} — {$po->order_no}", 'Issuance', $issuance->id, $userId);
        }
    }

    private function reverse(Issuance $issuance): void
    {
        LocationStockLedger::where('reference_type', 'Issuance')->where('reference_id', $issuance->id)->delete();
        YarnInProcessLedger::where('reference_type', 'Issuance')->where('reference_id', $issuance->id)->delete();
        $this->voucherService->deleteByReference('Issuance', $issuance->id);
        $issuance->items()->delete();
    }

    private function assertReversible(Issuance $issuance): void
    {
        $received = PurchaseReceiving::where('purchase_order_id', $issuance->purchase_order_id)
            ->whereIn('status', ['Approved', 'PendingApproval'])->exists();
        if ($received) {
            throw new \Exception('Cannot change this issuance — goods have already been received against ' . ($issuance->purchaseOrder->order_no ?? 'the PO') . '.');
        }

        if ($issuance->issue_type === 'greige_processing') {
            foreach ($issuance->items as $item) {
                $lot = $item->lot_no ?? $issuance->issue_no;
                $left = LocationStockLedger::balance($issuance->destination_location_id, $item->product_id, 'fresh', $lot);
                if ($left < (float) $item->quantity - 0.001) {
                    throw new \Exception("Cannot change this issuance — the mill has already used part of lot {$lot}.");
                }
            }
        }
    }
}
