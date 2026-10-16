<?php

namespace App\Services;

use App\Models\{JobItem, Location, LocationStockLedger, Product, ProductCategory, ProductionConsumption, ProductionOrder, ProductionOrderLine, PurchaseReceiving, PutUpPackaging};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cutting & packing at our warehouse.
 *   cut  : processed fabric (lot, grade) → Cut Pcs of a put-up (pieces × meters per piece)
 *          + leftover meters (Leftover) + damaged meters (Rejection). Fabric must have closed QC.
 *   pack : Cut Pcs → packed SKU (pattern × put-up), using the put-up's packaging list (BOM).
 * Every output keeps the lot # and grade; cost moves with the stock (+ packaging cost on packing).
 */
class ProductionService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private VoucherService $vouchers,
        private AccountMappingService $mapping,
    ) {}

    /** Fabric that can be cut at a location: processed fabric, fresh, by lot and grade, QC closed. */
    public function cuttable(int $locationId): array
    {
        return $this->stockOf($locationId, 'processed')
            ->map(fn($r) => $r + ['qc_pending' => $this->qcPending($r['lot_no'])])->values()->all();
    }

    /** Cut pieces waiting to be packed at a location. */
    public function packable(int $locationId): array
    {
        return $this->stockOf($locationId, 'cut_pcs')->map(function ($r) {
            $p = Product::find($r['product_id']);
            $putUp = Product::find($this->attr($p, 'put_up_product_id'));
            return $r + ['put_up_product_id' => $putUp?->id, 'put_up' => $putUp?->name,
                         'bom' => $putUp ? $this->bom($putUp->id) : []];
        })->values()->all();
    }

    private function stockOf(int $locationId, string $categoryCode)
    {
        $cat = ProductCategory::where('code', $categoryCode)->value('id');
        return LocationStockLedger::query()->join('products as p', 'p.id', '=', 'location_stock_ledger.product_id')
            ->where('location_stock_ledger.location_id', $locationId)->where('location_stock_ledger.status', 'fresh')->where('p.category_id', $cat)
            ->groupBy('location_stock_ledger.product_id', 'p.name', 'p.attributes', 'location_stock_ledger.lot_no', 'location_stock_ledger.grade')
            ->selectRaw('location_stock_ledger.product_id, p.name AS product, p.attributes AS attrs, location_stock_ledger.lot_no, location_stock_ledger.grade, SUM(location_stock_ledger.quantity) AS qty')
            ->havingRaw('SUM(location_stock_ledger.quantity) > 0.0005')->orderBy('p.name')->orderBy('location_stock_ledger.lot_no')->get()
            ->map(fn($r) => ['product_id' => (int) $r->product_id, 'product' => $r->product, 'lot_no' => $r->lot_no,
                             'pattern' => json_decode((string) $r->attrs, true)['design_code'] ?? null,
                             'grade' => $r->grade, 'qty' => round((float) $r->qty, 3)]);
    }

    /** Packaging one piece of a put-up uses: [['product_id','name','qty_per_piece'], ...] */
    public function bom(int $putUpId): array
    {
        return PutUpPackaging::where('put_up_product_id', $putUpId)->with('material')->get()
            ->map(fn($b) => ['product_id' => $b->material_product_id, 'name' => $b->material->name ?? '', 'qty_per_piece' => (float) $b->qty_per_piece])->all();
    }

    public function saveBom(int $putUpId, array $rows): void
    {
        DB::transaction(function () use ($putUpId, $rows) {
            $put = Product::with('category')->findOrFail($putUpId);
            if (($put->category->code ?? '') !== 'sku' || !$this->attr($put, 'meters_per_unit')) throw new \Exception('Choose a put-up.');
            $packCat = ProductCategory::where('code', 'packaging')->value('id');
            $keep = [];
            foreach ($rows as $r) {
                $qty = round((float) ($r['qty_per_piece'] ?? 0), 4);
                if (empty($r['material_product_id']) || $qty <= 0) continue;
                $m = Product::findOrFail($r['material_product_id']);
                if ((int) $m->category_id !== (int) $packCat) throw new \Exception("{$m->name} is not a packaging material.");
                PutUpPackaging::updateOrCreate(['put_up_product_id' => $putUpId, 'material_product_id' => $m->id], ['qty_per_piece' => $qty]);
                $keep[] = $m->id;
            }
            PutUpPackaging::where('put_up_product_id', $putUpId)->whereNotIn('material_product_id', $keep ?: [0])->delete();
        });
    }

    /**
     * $lines: [['product_id','lot_no','grade','put_up_product_id','pieces','leftover_qty','rejected_qty','job_item_id'], ...]
     * $data: location_id, order_date, job_id, remarks, pack_now
     */
    public function cut(array $data, array $lines, int $userId): ProductionOrder
    {
        return DB::transaction(function () use ($data, $lines, $userId) {
            $loc = Location::findOrFail($data['location_id']);
            if ($loc->vendor_id) throw new \Exception('Cutting is done at one of our warehouses.');
            $lines = array_values(array_filter($lines, fn($l) => (float) ($l['pieces'] ?? 0) > 0));
            if (empty($lines)) throw new \Exception('Enter the pieces cut for at least one line.');

            $order = $this->newOrder('cutting', $data, $userId);
            $packLines = [];
            $need = [];
            foreach ($lines as $l) {
                $fabric = Product::with('category')->findOrFail($l['product_id']);
                if (($fabric->category->code ?? '') !== 'processed') throw new \Exception("{$fabric->name} is not processed fabric.");
                $putUp = Product::findOrFail($l['put_up_product_id']);
                $mpu = (float) $this->attr($putUp, 'meters_per_unit');
                if ($mpu <= 0) throw new \Exception("{$putUp->name} is not a put-up (no meters per piece).");
                $lot = $l['lot_no'] ?? null; $grade = ($l['grade'] ?? '') ?: null;
                if ($lot && $this->qcPending($lot)) throw new \Exception("Lot {$lot} has QC still open — close QC before cutting.");

                $pieces = round((float) $l['pieces'], 3);
                $leftover = max(0, round((float) ($l['leftover_qty'] ?? 0), 3));
                $rejected = max(0, round((float) ($l['rejected_qty'] ?? 0), 3));
                $cutMeters = round($pieces * $mpu, 3);
                $total = round($cutMeters + $leftover + $rejected, 3);

                $k = "{$fabric->id}|{$lot}|{$grade}";
                $need[$k] = ($need[$k] ?? 0) + $total;
                $have = LocationStockLedger::balance($loc->id, $fabric->id, 'fresh', $lot, $grade);
                if ($need[$k] > $have + 0.001) {
                    throw new \Exception("{$fabric->name}" . ($lot ? " lot {$lot}" : '') . ($grade ? " grade {$grade}" : '') . ": needs {$need[$k]} m, only " . round($have, 3) . ' m in stock.');
                }
                $jobItem = $this->checkJobItem($l['job_item_id'] ?? null, $fabric, $putUp);

                $rate = LocationStockLedger::lotRate($loc->id, $fabric->id, $lot, $fabric->weightedAverageCost($loc->id), 'fresh', $grade);
                $totalAmt = round($total * $rate, 2);
                $leftAmt = round($leftover * $rate, 2); $rejAmt = round($rejected * $rate, 2);
                $cutAmt = round($totalAmt - $leftAmt - $rejAmt, 2);

                $cutPcs = $this->variant('cut_pcs', 'CP', $fabric, $putUp);
                $line = ProductionOrderLine::create([
                    'production_order_id' => $order->id, 'job_item_id' => $jobItem?->id,
                    'input_product_id' => $fabric->id, 'lot_no' => $lot, 'grade' => $grade, 'input_qty' => $total,
                    'put_up_product_id' => $putUp->id, 'output_product_id' => $cutPcs->id, 'output_qty' => $pieces,
                    'leftover_qty' => $leftover, 'rejected_qty' => $rejected, 'input_amount' => $totalAmt, 'output_amount' => $cutAmt,
                ]);

                $post = fn($pid, $status, $g, $qty, $amt) => $this->post($order, $loc->id, $pid, $status, $lot, $g, $qty, $amt);
                $post($fabric->id, 'fresh', $grade, -$total, -$totalAmt);
                $post($cutPcs->id, 'fresh', $grade, $pieces, $cutAmt);
                if ($leftover > 0) $post($this->leftoverProduct('leftover', 'LO', $fabric)->id, 'fresh', null, $leftover, $leftAmt);
                if ($rejected > 0) $post($this->leftoverProduct('rejection', 'RJ', $fabric)->id, 'fresh', null, $rejected, $rejAmt);

                $packLines[] = ['product_id' => $cutPcs->id, 'lot_no' => $lot, 'grade' => $grade, 'pieces' => $pieces, 'job_item_id' => $jobItem?->id];
            }
            $this->postVoucher($order, $userId);

            if (!empty($data['pack_now'])) {
                $this->pack(['location_id' => $loc->id, 'order_date' => $order->order_date->format('Y-m-d'), 'job_id' => $order->job_id,
                             'remarks' => "Packed on cutting {$order->order_no}"], $packLines, $userId);
            }
            return $order->fresh('lines');
        });
    }

    /** $lines: [['product_id' (cut pcs), 'lot_no', 'grade', 'pieces', 'job_item_id'], ...] */
    public function pack(array $data, array $lines, int $userId): ProductionOrder
    {
        return DB::transaction(function () use ($data, $lines, $userId) {
            $loc = Location::findOrFail($data['location_id']);
            $lines = array_values(array_filter($lines, fn($l) => (float) ($l['pieces'] ?? 0) > 0));
            if (empty($lines)) throw new \Exception('Enter the pieces packed for at least one line.');

            $order = $this->newOrder('packing', $data, $userId);
            $needCut = []; $needMat = [];
            foreach ($lines as $l) {
                $cut = Product::with('category')->findOrFail($l['product_id']);
                if (($cut->category->code ?? '') !== 'cut_pcs') throw new \Exception("{$cut->name} is not cut pieces.");
                $putUp = Product::findOrFail($this->attr($cut, 'put_up_product_id'));
                $fabric = Product::find($this->attr($cut, 'source_product_id')) ?? $cut;
                $lot = $l['lot_no'] ?? null; $grade = ($l['grade'] ?? '') ?: null;
                $pieces = round((float) $l['pieces'], 3);

                $k = "{$cut->id}|{$lot}|{$grade}";
                $needCut[$k] = ($needCut[$k] ?? 0) + $pieces;
                $have = LocationStockLedger::balance($loc->id, $cut->id, 'fresh', $lot, $grade);
                if ($needCut[$k] > $have + 0.001) throw new \Exception("{$cut->name}: needs {$needCut[$k]} pcs, only " . round($have, 3) . ' cut.');
                $jobItem = $this->checkJobItem($l['job_item_id'] ?? null, $fabric, $putUp);

                $rate = LocationStockLedger::lotRate($loc->id, $cut->id, $lot, $cut->weightedAverageCost($loc->id), 'fresh', $grade);
                $cutAmt = round($pieces * $rate, 2);
                $sku = $this->variant('sku', 'FG', $fabric, $putUp);

                $line = ProductionOrderLine::create([
                    'production_order_id' => $order->id, 'job_item_id' => $jobItem?->id,
                    'input_product_id' => $cut->id, 'lot_no' => $lot, 'grade' => $grade, 'input_qty' => $pieces,
                    'put_up_product_id' => $putUp->id, 'output_product_id' => $sku->id, 'output_qty' => $pieces, 'input_amount' => $cutAmt,
                ]);

                // Packaging materials from the put-up's list, oldest lot first
                $packAmt = 0;
                foreach ($this->bom($putUp->id) as $b) {
                    $qty = round($pieces * $b['qty_per_piece'], 4);
                    if ($qty <= 0) continue;
                    $mat = Product::find($b['product_id']);
                    $needMat[$mat->id] = ($needMat[$mat->id] ?? 0) + $qty;
                    $haveMat = LocationStockLedger::balance($loc->id, $mat->id);
                    if ($needMat[$mat->id] > $haveMat + 0.0001) throw new \Exception("Not enough {$mat->name} at {$loc->name}: needs {$needMat[$mat->id]}, have " . round($haveMat, 3) . '.');
                    $avg = $mat->weightedAverageCost($loc->id);
                    $amt = 0;
                    foreach (LocationStockLedger::allocate($loc->id, $mat->id, $qty) as $part) {
                        $a = round($part['quantity'] * LocationStockLedger::lotRate($loc->id, $mat->id, $part['lot_no'], $avg), 2);
                        $this->post($order, $loc->id, $mat->id, 'fresh', $part['lot_no'], null, -$part['quantity'], -$a);
                        $amt += $a;
                    }
                    ProductionConsumption::create(['production_order_id' => $order->id, 'production_order_line_id' => $line->id,
                        'product_id' => $mat->id, 'quantity' => $qty, 'amount' => round($amt, 2)]);
                    $packAmt += $amt;
                }

                $this->post($order, $loc->id, $cut->id, 'fresh', $lot, $grade, -$pieces, -$cutAmt);
                $this->post($order, $loc->id, $sku->id, 'fresh', $lot, $grade, $pieces, round($cutAmt + $packAmt, 2));
                $line->update(['packaging_amount' => round($packAmt, 2), 'output_amount' => round($cutAmt + $packAmt, 2)]);
            }
            $this->postVoucher($order, $userId);
            return $order->fresh('lines.consumptions');
        });
    }

    /** Pieces packed against a customer order line. */
    public static function packedFor(int $jobItemId): float
    {
        return (float) ProductionOrderLine::where('job_item_id', $jobItemId)->whereHas('order', fn($q) => $q->where('type', 'packing'))->sum('output_qty');
    }

    public function qcPending(?string $lot): bool
    {
        if (!$lot) return false;
        return PurchaseReceiving::whereIn('qc_status', ['Pending', 'InProgress'])->where('status', 'Approved')
            ->whereHas('purchaseOrder', fn($q) => $q->where('order_no', $lot)->orWhere('reprocess_lot_no', $lot))->exists();
    }

    private function newOrder(string $type, array $data, int $userId): ProductionOrder
    {
        return ProductionOrder::create([
            'order_no' => $this->numbers->next($type === 'cutting' ? 'cutting_order' : 'packing_order', 'production_orders', 'order_no', $type === 'cutting' ? 'CUT' : 'PCK'),
            'type' => $type, 'location_id' => $data['location_id'], 'job_id' => $data['job_id'] ?? null,
            'order_date' => $data['order_date'] ?? now()->toDateString(), 'remarks' => $data['remarks'] ?? null, 'created_by' => $userId,
        ]);
    }

    private function checkJobItem($jobItemId, Product $fabric, Product $putUp): ?JobItem
    {
        if (!$jobItemId) return null;
        $item = JobItem::findOrFail($jobItemId);
        $pattern = $this->pattern($fabric);
        if ($item->pattern_code && strcasecmp(trim($item->pattern_code), $pattern) !== 0) {
            throw new \Exception("Customer order line is for pattern {$item->pattern_code}, not {$pattern}.");
        }
        if ($item->product_id && (int) $item->product_id !== (int) $putUp->id) {
            throw new \Exception("Customer order line wants a different put-up than {$putUp->name}.");
        }
        return $item;
    }

    private function attr(Product $p, string $key)
    {
        $a = $p->getAttribute('attributes');
        if (is_string($a)) $a = json_decode($a, true);
        return $a[$key] ?? null;
    }

    private function pattern(Product $fabric): string
    {
        return (string) ($this->attr($fabric, 'design_code') ?: preg_replace('/^PF-/', '', $fabric->sku));
    }

    /** Cut Pcs (CP-…) or packed SKU (FG-…) for a pattern × put-up, created on first use. */
    private function variant(string $categoryCode, string $prefix, Product $fabric, Product $putUp): Product
    {
        $pattern = $this->pattern($fabric);
        $sku = $prefix . '-' . strtoupper(Str::slug($pattern)) . '-' . preg_replace('/^PU-/', '', $putUp->sku);
        if ($p = Product::withTrashed()->where('sku', $sku)->first()) return $p;
        $cat = ProductCategory::where('code', $categoryCode)->firstOrFail();
        return Product::create([
            'category_id' => $cat->id, 'sku' => $sku, 'name' => trim($fabric->name) . ' — ' . $putUp->name,
            'measurement_unit' => $putUp->measurement_unit,
            'attributes' => array_filter([
                'design_code' => $pattern, 'color' => $this->attr($fabric, 'color'),
                'put_up_product_id' => $putUp->id, 'put_up_sku' => $putUp->sku,
                'meters_per_unit' => $this->attr($putUp, 'meters_per_unit'), 'source_product_id' => $fabric->id,
            ]),
            'is_active' => true, 'track_lots' => true,
        ]);
    }

    /** Leftover (LO-…) or rejection (RJ-…) meters of a pattern. */
    private function leftoverProduct(string $categoryCode, string $prefix, Product $fabric): Product
    {
        $pattern = $this->pattern($fabric);
        $sku = $prefix . '-' . strtoupper(Str::slug($pattern));
        if ($p = Product::withTrashed()->where('sku', $sku)->first()) return $p;
        return Product::create([
            'category_id' => ProductCategory::where('code', $categoryCode)->firstOrFail()->id, 'sku' => $sku,
            'name' => trim($fabric->name) . ($categoryCode === 'leftover' ? ' — Leftover' : ' — Rejected'),
            'measurement_unit' => $fabric->measurement_unit,
            'attributes' => ['design_code' => $pattern, 'source_product_id' => $fabric->id],
            'is_active' => true, 'track_lots' => true,
        ]);
    }

    private function post(ProductionOrder $o, int $locationId, int $productId, string $status, ?string $lot, ?string $grade, float $qty, float $amount): void
    {
        LocationStockLedger::create([
            'doc_no' => $o->order_no, 'location_id' => $locationId, 'product_id' => $productId, 'status' => $status,
            'lot_no' => $lot, 'grade' => $grade, 'quantity' => round($qty, 4), 'amount' => round($amount, 2),
            'reference_type' => 'ProductionOrder', 'reference_id' => $o->id, 'entry_date' => $o->order_date,
        ]);
    }

    /** Value moves between stock accounts (processed → cut pcs / leftover / rejection; cut pcs + packaging → SKU). */
    private function postVoucher(ProductionOrder $o, int $userId): void
    {
        $net = [];
        $rows = LocationStockLedger::where('reference_type', 'ProductionOrder')->where('reference_id', $o->id)->with('product.category')->get();
        foreach ($rows as $r) {
            $acc = $r->product->category->stock_account_id ?? $this->mapping->accountId('stock_in_hand');
            if ($acc) $net[$acc] = ($net[$acc] ?? 0) + (float) $r->amount;
        }
        $lines = [];
        foreach ($net as $acc => $amt) {
            if ($amt > 0.004) $lines[] = ['account_id' => $acc, 'debit' => round($amt, 2), 'credit' => 0];
            if ($amt < -0.004) $lines[] = ['account_id' => $acc, 'debit' => 0, 'credit' => round(-$amt, 2)];
        }
        if (count($lines) >= 2) {
            $this->vouchers->post('system', $o->order_date->format('Y-m-d'), $lines, "{$o->type_label} {$o->order_no}", 'ProductionOrder', $o->id, $userId);
        }
    }
}
