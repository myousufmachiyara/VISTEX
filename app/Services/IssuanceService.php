<?php

namespace App\Services;

use App\Models\{Issuance, IssuanceItem, Job, JobItem, Location, LocationStockLedger, Product, PurchaseOrder, PurchaseReceiving, YarnInProcessLedger};
use Illuminate\Support\Facades\DB;

/**
 * Issuance = material leaving our warehouse against a document.
 *
 *  yarn_weaving       stock out of warehouse -> Yarn-in-Process at the weaving mill
 *                     Dr Yarn in Process / Cr Stock. Capped per yarn at the PO's warp/weft requirement.
 *  greige_processing  at the processing mill: greige already there (Stock Movement, mill lot #) is issued to
 *                     the Processing PO — fresh → in_process at the mill, lot kept. No voucher (still our stock).
 *
 *  fabric_cutting     QC-passed processed fabric in our warehouse is issued to the cutting department against
 *                     an open customer job (line-wise): fresh → for_cutting, same location / lot / grade. No voucher
 *                     (still our stock). Cutting (Production) only cuts fabric that is "for_cutting".
 *                     Entered in yards, stocked in meters.
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
            [$type, $po, $job] = $this->validateHeader($data);

            $issuance = Issuance::create([
                'issue_no'                => $this->numberService->next('issuance', 'issuances', 'issue_no', 'ISS'),
                'issue_type'              => $type,
                'purchase_order_id'       => $po?->id,
                'job_id'                  => $job?->id,
                'vendor_id'               => $po?->vendor_id,
                'source_location_id'      => $data['source_location_id'],
                'destination_location_id' => null,
                'issue_date'              => $data['issue_date'],
                'remarks'                 => $data['remarks'] ?? null,
                'attachments'             => $data['attachments'] ?? null,
                ...self::extraFields($data),
                'created_by'              => $userId,
                'updated_by'              => $userId,
            ]);

            $job ? $this->applyCutting($issuance, $job, $items) : $this->apply($issuance, $po, $items, $userId);
            $this->syncBagTotal($issuance);

            if ($po && $po->status === PurchaseOrder::STATUS_APPROVED) {
                $po->update(['status' => PurchaseOrder::STATUS_ISSUED]);
            }

            return $issuance->load('items.product', 'purchaseOrder.vendor', 'job.customer');
        });
    }

    public function update(Issuance $issuance, array $data, array $items, ?int $userId = null): Issuance
    {
        return DB::transaction(function () use ($issuance, $data, $items, $userId) {
            $this->assertReversible($issuance);
            if ($issuance->isWarehouseToMill()) throw new \Exception('This issuance sent greige straight from the warehouse (older method). Delete it and issue again at the mill.');
            $data['issue_type'] = $issuance->issue_type;
            $data['purchase_order_id'] = $issuance->purchase_order_id;
            $data['job_id'] = $issuance->job_id;
            [, $po, $job] = $this->validateHeader($data);

            $this->reverse($issuance);
            $issuance->update([
                'source_location_id'      => $data['source_location_id'],
                'destination_location_id' => null,
                'issue_date'              => $data['issue_date'],
                'remarks'                 => $data['remarks'] ?? null,
                'attachments'             => $data['attachments'] ?? $issuance->attachments,
                ...self::extraFields($data),
                'updated_by'              => $userId,
            ]);
            $job ? $this->applyCutting($issuance->fresh(), $job, $items) : $this->apply($issuance->fresh(), $po, $items, $userId);
            $this->syncBagTotal($issuance);

            return $issuance->fresh(['items.product', 'purchaseOrder.vendor', 'job.customer']);
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

        if ($type === 'fabric_cutting') {
            $job = Job::with('customer')->find($data['job_id'] ?? 0);
            if (!$job) throw new \Exception('Select the customer job.');
            if ($job->status !== 'Approved') throw new \Exception("{$job->job_no} is {$job->status} — fabric is issued for cutting only against an approved, open job.");
            if (!empty($data['customer_id']) && (int) $data['customer_id'] !== (int) $job->customer_id) throw new \Exception("{$job->job_no} belongs to another customer.");
            $source = Location::find($data['source_location_id'] ?? 0);
            if (!$source || !$source->isOwnWarehouse()) throw new \Exception('Select one of our warehouses to issue from.');
            return [$type, null, $job];
        }

        $po = PurchaseOrder::with('vendor')->findOrFail($data['purchase_order_id'] ?? 0);
        if ($po->type !== $def['po_type']) throw new \Exception("{$def['label']} must be issued against a {$def['against']}.");
        if ($po->isReprocess()) throw new \Exception("{$po->order_no} is a reprocess order — its fabric goes into process when the mill receives the gate pass.");
        if (!in_array($po->status, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ISSUED, PurchaseOrder::STATUS_PARTIAL])) {
            throw new \Exception("{$po->order_no} is {$po->status_label} — material can only be issued against an approved, open PO.");
        }

        $source = Location::find($data['source_location_id'] ?? 0);
        if ($type === 'greige_processing') {
            if (!$source || (int) $source->vendor_id !== (int) $po->vendor_id) {
                throw new \Exception("Issue from {$po->vendor->name}'s own location — greige reaches the mill by Stock Movement first.");
            }
        } elseif (!$source || !$source->isOwnWarehouse()) {
            throw new \Exception('Select one of our warehouses to issue from.');
        }
        return [$type, $po, null];
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
                'bags' => self::addBags($grouped[$key]['bags'] ?? null, $i['bags'] ?? null),
            ];
        }

        $perProduct = [];
        foreach ($grouped as $g) $perProduct[$g['product_id']] = ($perProduct[$g['product_id']] ?? 0) + $g['quantity'];

        // "Any lot": take the oldest lots first so every issued line records which lot (PO) it came from
        $grouped = $this->allocateFifo($grouped, $sourceId);

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

            $rate = LocationStockLedger::lotRate($sourceId, $product->id, $g['lot_no'], $product->weightedAverageCost($sourceId));
            $amount = round($g['quantity'] * $rate, 2);
            $totalQty += $g['quantity']; $totalAmount += $amount;

            IssuanceItem::create([
                'issuance_id' => $issuance->id, 'product_id' => $product->id, 'lot_no' => $g['lot_no'],
                'purchase_order_item_id' => $g['purchase_order_item_id'], 'quantity' => $g['quantity'], 'bags' => $g['bags'] ?? null, 'rate' => $rate, 'amount' => $amount,
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
                // Same lot, same mill: no longer free stock — held in process for this Processing PO
                LocationStockLedger::create([
                    'doc_no' => $issuance->issue_no, 'location_id' => $sourceId, 'product_id' => $product->id,
                    'status' => 'in_process', 'lot_no' => $g['lot_no'], 'quantity' => $g['quantity'], 'amount' => $amount,
                    'reference_type' => 'Issuance', 'reference_id' => $issuance->id, 'entry_date' => $issuance->issue_date,
                    'remarks' => "In process for {$po->order_no}",
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

    /**
     * Per job line: required (job qty) and already issued for cutting — both in yards.
     * Job quantities are entered in meters.
     */
    public function jobRequirement(Job $job, ?int $excludeIssuanceId = null): array
    {
        $issued = IssuanceItem::join('issuances', 'issuances.id', '=', 'issuance_items.issuance_id')
            ->where('issuances.job_id', $job->id)->where('issuances.issue_type', 'fabric_cutting')->whereNull('issuances.deleted_at')
            ->when($excludeIssuanceId, fn($q) => $q->where('issuances.id', '!=', $excludeIssuanceId))
            ->groupBy('issuance_items.job_item_id')
            ->selectRaw('issuance_items.job_item_id, SUM(issuance_items.quantity) as qty')
            ->pluck('qty', 'job_item_id');

        $out = [];
        foreach ($job->items as $ji) {
            $out[$ji->id] = [
                'required_yd' => Issuance::toYards((float) $ji->quantity),
                'issued_yd'   => Issuance::toYards((float) ($issued[$ji->id] ?? 0)),
            ];
        }
        return $out;
    }

    /** Required ÷ issued × 100 (both in yards) — e.g. 100 yd required, 110 yd issued = 90.9 %. */
    public static function cuttingPercent(float $requiredYd, float $issuedYd): ?float
    {
        return $issuedYd > 0 ? round($requiredYd / $issuedYd * 100, 1) : null;
    }

    /**
     * $items: [['job_item_id','product_id','lot_no','grade','quantity_yd'], ...]
     * Fabric must be processed, QC closed, fresh in the warehouse. Moves fresh → for_cutting.
     */
    private function applyCutting(Issuance $issuance, Job $job, array $items): void
    {
        $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity_yd'] ?? 0) > 0));
        if (empty($items)) throw new \Exception('Enter the yards to issue for at least one job line.');

        $sourceId = (int) $issuance->source_location_id;
        $jobItems = $job->items->keyBy('id');
        $production = app(ProductionService::class);
        $need = []; $totalQty = 0; $totalAmount = 0;

        foreach ($items as $i) {
            $ji = $jobItems[(int) ($i['job_item_id'] ?? 0)] ?? null;
            if (!$ji) throw new \Exception("Pick the {$job->job_no} line each fabric is issued for.");
            $label = trim(implode(' · ', array_filter([$ji->collection, $ji->design, $ji->color_scheme]))) ?: "line {$ji->id}";

            $product = Product::with('category')->findOrFail($i['product_id'] ?? 0);
            if (($product->category->code ?? '') !== 'processed') throw new \Exception("{$product->name} is not processed fabric.");
            $lot = trim((string) ($i['lot_no'] ?? '')) ?: null;
            $grade = trim((string) ($i['grade'] ?? '')) ?: null;
            if ($lot && $production->qcPending($lot)) throw new \Exception("Lot {$lot} still has QC open — close QC before issuing it for cutting.");

            $yd = round((float) $i['quantity_yd'], 3);
            $m = Issuance::toMeters($yd);

            $k = "{$product->id}|{$lot}|{$grade}";
            $need[$k] = ($need[$k] ?? 0) + $m;
            $have = LocationStockLedger::balance($sourceId, $product->id, 'fresh', $lot, $grade);
            if ($need[$k] > $have + 0.001) {
                $where = ($lot ? " lot {$lot}" : '') . ($grade ? " grade {$grade}" : '');
                throw new \Exception("Not enough {$product->name}{$where} for {$label}: in stock " . Issuance::toYards($have) . ' yd, issuing ' . Issuance::toYards($need[$k]) . ' yd.');
            }

            $rate = LocationStockLedger::lotRate($sourceId, $product->id, $lot, $product->weightedAverageCost($sourceId), 'fresh', $grade);
            $amount = round($m * $rate, 2);
            $totalQty += $m; $totalAmount += $amount;

            IssuanceItem::create([
                'issuance_id' => $issuance->id, 'product_id' => $product->id, 'lot_no' => $lot, 'grade' => $grade,
                'job_item_id' => $ji->id, 'quantity' => $m, 'bags' => null, 'rate' => $rate, 'amount' => $amount,
            ]);

            $row = ['doc_no' => $issuance->issue_no, 'location_id' => $sourceId, 'product_id' => $product->id, 'lot_no' => $lot, 'grade' => $grade,
                    'reference_type' => 'Issuance', 'reference_id' => $issuance->id, 'entry_date' => $issuance->issue_date];
            LocationStockLedger::create($row + ['status' => 'fresh', 'quantity' => -$m, 'amount' => -$amount]);
            LocationStockLedger::create($row + ['status' => 'for_cutting', 'quantity' => $m, 'amount' => $amount,
                'remarks' => "For cutting — {$job->job_no} {$label}"]);
        }

        $issuance->update(['total_quantity' => round($totalQty, 3), 'total_amount' => round($totalAmount, 2)]);
    }

    /**
     * Split lines without a lot across the lots in stock, oldest first.
     * Untracked stock (no lot) is used last. If there isn't enough stock the line
     * is left as-is and the normal stock check reports the shortage.
     */
    private function allocateFifo(array $grouped, int $sourceId): array
    {
        $out = [];
        foreach ($grouped as $g) {
            if ($g['lot_no'] !== null) { $out[] = $g; continue; }

            $buckets = LocationStockLedger::where('location_id', $sourceId)->where('product_id', $g['product_id'])
                ->where('status', 'fresh')->groupBy('lot_no')
                ->selectRaw('lot_no, SUM(quantity) as qty, MIN(entry_date) as first_date')
                ->havingRaw('SUM(quantity) > 0.0005')->get()
                ->sortBy(fn($b) => [$b->lot_no === null ? 1 : 0, $b->first_date, $b->lot_no])->values();

            if ($buckets->sum('qty') + 0.001 < $g['quantity']) { $out[] = $g; continue; }

            $left = $g['quantity'];
            foreach ($buckets as $b) {
                if ($left <= 0.0005) break;
                $take = round(min($left, (float) $b->qty), 3);
                // bags are a record of the line — keep them on its first lot only
                $out[] = array_merge($g, ['lot_no' => $b->lot_no, 'quantity' => $take, 'bags' => $left === $g['quantity'] ? ($g['bags'] ?? null) : null]);
                $left = round($left - $take, 3);
            }
        }

        // merge back any lines that landed on the same product + lot
        $merged = [];
        foreach ($out as $g) {
            $k = $g['product_id'] . '|' . $g['lot_no'];
            if (isset($merged[$k])) {
                $merged[$k]['quantity'] = round($merged[$k]['quantity'] + $g['quantity'], 3);
                $merged[$k]['bags'] = self::addBags($merged[$k]['bags'] ?? null, $g['bags'] ?? null);
            } else $merged[$k] = $g;
        }
        return array_values($merged);
    }

    // Header "No. of Bags" = total of the item lines (record only)
    private function syncBagTotal(Issuance $issuance): void
    {
        $bags = $issuance->items()->whereNotNull('bags')->get();
        $issuance->update(['no_of_bags' => $bags->isEmpty() ? null : round((float) $bags->sum('bags'), 2)]);
    }

    // Bags are optional: null + null stays null, otherwise add
    private static function addBags($a, $b): ?float
    {
        $a = ($a === null || $a === '') ? null : max(0, (float) $a);
        $b = ($b === null || $b === '') ? null : max(0, (float) $b);
        return $a === null && $b === null ? null : round(($a ?? 0) + ($b ?? 0), 2);
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
        // Fabric for cutting: everything issued must still be uncut
        if ($issuance->isForCutting()) {
            $need = [];
            foreach ($issuance->items as $it) {
                $k = "{$it->product_id}|{$it->lot_no}|{$it->grade}";
                $need[$k] = ['item' => $it, 'qty' => ($need[$k]['qty'] ?? 0) + (float) $it->quantity];
            }
            foreach ($need as $n) {
                $it = $n['item'];
                $left = LocationStockLedger::balance($issuance->source_location_id, $it->product_id, 'for_cutting', $it->lot_no, $it->grade);
                if ($left + 0.001 < $n['qty']) {
                    throw new \Exception('Cannot change this issuance — part of ' . ($it->product->name ?? 'the fabric') . ($it->lot_no ? " lot {$it->lot_no}" : '') . ' has already been cut.');
                }
            }
            return;
        }

        $received = PurchaseReceiving::where('purchase_order_id', $issuance->purchase_order_id)
            ->whereIn('status', ['Approved', 'PendingApproval'])->exists();
        if ($received) {
            throw new \Exception('Cannot change this issuance — goods have already been received against ' . ($issuance->purchaseOrder->order_no ?? 'the PO') . '.');
        }

        if ($issuance->isWarehouseToMill()) {
            $lot = $issuance->issue_no;
            foreach ($issuance->items->groupBy('product_id') as $productId => $lines) {
                $left = LocationStockLedger::balance($issuance->destination_location_id, $productId, 'fresh', $lot);
                if ($left < (float) $lines->sum('quantity') - 0.001) {
                    throw new \Exception("Cannot change this issuance — the mill has already used part of lot {$lot}.");
                }
            }
        }
    }

    // Transport, bags (record only — no stock effect) and the two signatory names
    private static function extraFields(array $data): array
    {
        $str = fn($k) => isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;
        // Fabric for cutting is a handover inside the factory: only who issued and who received
        if (($data['issue_type'] ?? null) === 'fabric_cutting') {
            return ['vehicle_no' => null, 'driver_name' => null, 'driver_contact' => null,
                    'prepared_by_name' => $str('prepared_by_name'), 'received_by_name' => $str('received_by_name')];
        }
        return [
            'vehicle_no'       => $str('vehicle_no') ? strtoupper($str('vehicle_no')) : null,
            'driver_name'      => $str('driver_name'),
            'driver_contact'   => $str('driver_contact'),
            'prepared_by_name' => $str('prepared_by_name'),
            'received_by_name' => $str('received_by_name'),
        ];
    }
}