<?php

namespace App\Services;

use App\Models\{Job, JobItem, LocationStockLedger, Product, ProductCategory, StockMovement, StockMovementItem};

/**
 * Dispatch of packed SKUs against a Customer Order. Goes out on a delivery gate pass (stock movement, warehouse → customer):
 * warehouse in-charge approves, gatekeeper checks it out; at the gate stock leaves, COGS is posted and the order line is fulfilled.
 */
class DispatchService
{
    public function __construct(private StockMovementService $movements) {}

    /** Per order line: pieces ordered / packed / dispatched / on a gate pass, and packed SKU stock that matches it. */
    public function lines(Job $job): array
    {
        $skuCat = ProductCategory::where('code', 'sku')->value('id');
        return $job->items()->with('product')->get()->map(function (JobItem $i) use ($skuCat) {
            $fg = Product::where('category_id', $skuCat)->where('sku', 'like', 'FG-%')->get()->filter(function ($p) use ($i) {
                $a = $p->getAttribute('attributes') ?? [];
                return strcasecmp((string) ($a['design_code'] ?? ''), (string) $i->pattern_code) === 0
                    && (!$i->product_id || (int) ($a['put_up_product_id'] ?? 0) === (int) $i->product_id);
            });
            $stock = $fg->isEmpty() ? collect() : LocationStockLedger::query()->join('locations as l', 'l.id', '=', 'location_stock_ledger.location_id')
                ->whereNull('l.vendor_id')->whereIn('location_stock_ledger.product_id', $fg->pluck('id'))->where('location_stock_ledger.status', 'fresh')
                ->groupBy('location_stock_ledger.location_id', 'l.name', 'location_stock_ledger.product_id', 'location_stock_ledger.lot_no', 'location_stock_ledger.grade')
                ->selectRaw('location_stock_ledger.location_id, l.name AS location, location_stock_ledger.product_id, location_stock_ledger.lot_no, location_stock_ledger.grade, SUM(location_stock_ledger.quantity) AS qty')
                ->havingRaw('SUM(location_stock_ledger.quantity) > 0.0005')->orderBy('location_stock_ledger.lot_no')->get()
                ->map(fn($r) => ['location_id' => (int) $r->location_id, 'location' => $r->location, 'product_id' => (int) $r->product_id,
                    'product' => $fg->firstWhere('id', $r->product_id)->name, 'lot_no' => $r->lot_no, 'grade' => $r->grade,
                    'qty' => round((float) $r->qty, 3), 'free' => round(max(0, (float) $r->qty - $this->held((int) $r->location_id, (int) $r->product_id, $r->lot_no, $r->grade)), 3)]);
            return [
                'item' => $i, 'ordered' => $i->pieces, 'packed' => ProductionService::packedFor($i->id),
                'dispatched' => $this->qty($i->id, [StockMovement::RECEIVED]), 'on_gate_pass' => $this->qty($i->id, [StockMovement::PENDING, StockMovement::APPROVED]),
                'stock' => $stock->values()->all(),
            ];
        })->all();
    }

    /** $lines: [['job_item_id', 'product_id', 'lot_no', 'grade', 'pieces', 'location_id'], ...] — all from one warehouse. */
    public function create(Job $job, array $data, array $lines, int $userId): StockMovement
    {
        if ($job->status !== 'Approved') throw new \Exception("{$job->job_no} is {$job->status} — only an approved customer order can be dispatched.");
        $items = [];
        foreach ($lines as $l) {
            $pcs = round((float) ($l['pieces'] ?? 0), 3);
            if ($pcs <= 0) continue;
            $item = JobItem::where('job_id', $job->id)->findOrFail($l['job_item_id']);
            $fg = Product::with('category')->findOrFail($l['product_id']);
            if (($fg->category->code ?? '') !== 'sku') throw new \Exception("{$fg->name} is not a packed SKU — pack it before dispatch.");
            $a = $fg->getAttribute('attributes') ?? [];
            if (strcasecmp((string) ($a['design_code'] ?? ''), (string) $item->pattern_code) !== 0 || ($item->product_id && (int) ($a['put_up_product_id'] ?? 0) !== (int) $item->product_id)) {
                throw new \Exception("{$fg->name} is not what order line {$item->pattern_code} asks for.");
            }
            if ($item->pieces !== null) {
                $left = $item->pieces - $this->qty($item->id, [StockMovement::RECEIVED, StockMovement::PENDING, StockMovement::APPROVED])
                    - collect($items)->where('job_item_id', $item->id)->sum('quantity');
                if ($pcs > $left + 0.001) throw new \Exception("{$item->pattern_code}: only " . max(0, $left) . " pcs left to dispatch on the order, you entered {$pcs}.");
            }
            $items[] = ['product_id' => $fg->id, 'quantity' => $pcs, 'source_lot_no' => ($l['lot_no'] ?? '') ?: null,
                        'source_grade' => ($l['grade'] ?? '') ?: null, 'job_item_id' => $item->id];
        }
        if (empty($items)) throw new \Exception('Enter the pieces to dispatch for at least one line.');

        return $this->movements->create([
            'movement_type' => StockMovement::SALE, 'from_location_id' => $data['location_id'], 'job_id' => $job->id,
            'customer_id' => $job->customer_id, 'movement_date' => $data['dispatch_date'] ?? now()->toDateString(),
            'remarks' => trim("Dispatch {$job->job_no} — " . ($data['remarks'] ?? '')) ?: null,
        ], $items, $userId);
    }

    private function qty(int $jobItemId, array $statuses): float
    {
        return (float) StockMovementItem::where('job_item_id', $jobItemId)
            ->whereHas('stockMovement', fn($q) => $q->whereIn('status', $statuses))->sum('quantity');
    }

    private function held(int $locationId, int $productId, ?string $lot, ?string $grade): float
    {
        return (float) StockMovementItem::where('product_id', $productId)->where('source_lot_no', $lot)
            ->when($grade, fn($q) => $q->where('source_grade', $grade))
            ->whereHas('stockMovement', fn($q) => $q->where('from_location_id', $locationId)->whereIn('status', [StockMovement::PENDING, StockMovement::APPROVED]))
            ->sum('quantity');
    }
}
