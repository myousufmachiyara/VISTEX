<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stock reports for Packaging Material (or any category picked in the filter).
 * Stock = Opening Stock typed on the product + fresh movements in our own warehouses.
 * Opening Stock isn't tied to a warehouse, so it's left out when one warehouse is picked.
 */
class PackagingReports extends BaseReports
{
    public static function group(): string { return 'packaging'; }
    public static function title(): string { return 'Packaging Material Reports'; }

    public static function catalog(): array
    {
        return [
            'stock_status'    => ['title' => 'Stock Status Report',            'filter' => 'asof',  'desc' => 'What is in stock now, against reorder level.'],
            'opening_closing' => ['title' => 'Opening & Closing Balance',      'filter' => 'range', 'desc' => 'Opening, in, out and closing per item for a period.'],
            'movement'        => ['title' => 'Movement / In-Out Report',       'filter' => 'range', 'desc' => 'Every stock in and out, with running balance.'],
            'consumption'     => ['title' => 'Consumption / Usage Report',     'filter' => 'range', 'desc' => 'How much of each item was used, and how long stock lasts.'],
            'low_stock'       => ['title' => 'Low Stock / Reorder Point Report', 'filter' => 'none', 'desc' => 'Items at or below their reorder level.'],
            'slow_moving'     => ['title' => 'Slow Moving / Dead Stock Report', 'filter' => 'days', 'desc' => 'Stock that has not moved recently.'],
            'inventory_value' => ['title' => 'Inventory Value Report',         'filter' => 'asof',  'desc' => 'Stock value at average cost, per item and warehouse.'],
        ];
    }

    // Reference types that only move stock between our own places — not usage
    private const TRANSFERS = ['StockMovement'];

    private const DOC_LABELS = [
        'PurchaseReceiving' => 'Purchase Receipt (GRN)', 'Challan' => 'Purchase without PO', 'PurchaseReturn' => 'Purchase Return',
        'Issuance' => 'Issuance', 'StockMovement' => 'Stock Transfer', 'ProcessingIssue' => 'Processing Issue',
        'GreigeReceive' => 'Greige Receive', 'PurchaseReceivingRejection' => 'Rejected at Receipt',
    ];

    public function run(string $key, array $f): array
    {
        return match ($key) {
            'stock_status' => $this->stockStatus($f),
            'opening_closing' => $this->openingClosing($f),
            'movement' => $this->movement($f),
            'consumption' => $this->consumption($f),
            'low_stock' => $this->lowStock($f),
            'slow_moving' => $this->slowMoving($f),
            'inventory_value' => $this->inventoryValue($f),
        };
    }

    // ── shared ──────────────────────────────────────────────────────

    private function products(array $f)
    {
        return DB::table('products as p')
            ->leftJoin('measurement_units as u', 'u.id', '=', 'p.measurement_unit')
            ->whereIn('p.category_id', $f['category_id'] ? [$f['category_id']] : $this->categoryIds('packaging'))
            ->whereNull('p.deleted_at')
            ->when($f['product_id'], fn($q, $v) => $q->where('p.id', $v))
            ->orderBy('p.name')
            ->get(['p.id', 'p.name', 'p.sku', 'p.opening_stock', 'p.reorder_level', 'p.is_active', 'u.shortcode as unit'])
            ->keyBy('id');
    }

    private function ledger(array $f, array $productIds)
    {
        return DB::table('location_stock_ledger as l')
            ->whereIn('l.product_id', $productIds ?: [0])->where('l.status', 'fresh')
            ->whereIn('l.location_id', $this->ownLocationIds($f['location_id']));
    }

    // product_id => [qty, amount] up to and including a date (null = all time)
    private function balances(array $f, array $productIds, ?string $upTo): array
    {
        $rows = $this->ledger($f, $productIds)->when($upTo, fn($q) => $q->where('l.entry_date', '<=', $upTo))
            ->groupBy('l.product_id')->selectRaw('l.product_id, SUM(l.quantity) as qty, SUM(l.amount) as amount')
            ->get()->keyBy('product_id');
        $out = [];
        foreach ($productIds as $id) $out[$id] = ['qty' => (float) ($rows[$id]->qty ?? 0), 'amount' => (float) ($rows[$id]->amount ?? 0)];
        return $out;
    }

    private function opening($p, array $f): float
    {
        return $f['location_id'] ? 0.0 : (float) $p->opening_stock;
    }

    // average cost from receipts (opening stock has no cost)
    private function avgCost(array $bal): float
    {
        return $bal['qty'] > 0.001 ? $bal['amount'] / $bal['qty'] : 0.0;
    }

    private function lastDates(array $f, array $productIds): array
    {
        return $this->ledger($f, $productIds)->groupBy('l.product_id')
            ->selectRaw('l.product_id,
                MAX(CASE WHEN l.quantity > 0 THEN l.entry_date END) as last_in,
                MAX(CASE WHEN l.quantity < 0 AND l.reference_type NOT IN (\'' . implode("','", self::TRANSFERS) . '\') THEN l.entry_date END) as last_out,
                MAX(l.entry_date) as last_move')
            ->get()->keyBy('product_id')->all();
    }

    private function locationNote(array $f): ?string
    {
        return $f['location_id'] ? 'One warehouse selected: Opening Stock typed on the product is not included because it is not tied to a warehouse.' : null;
    }

    // ── 1. Stock Status ─────────────────────────────────────────────
    private function stockStatus(array $f): array
    {
        $products = $this->products($f);
        $bal = $this->balances($f, $products->keys()->all(), $f['asof']);
        $last = $this->lastDates($f, $products->keys()->all());

        $rows = [];
        foreach ($products as $id => $p) {
            $qty = $this->opening($p, $f) + $bal[$id]['qty'];
            if (!$p->is_active && abs($qty) < 0.001) continue;
            $reorder = $p->reorder_level !== null ? (float) $p->reorder_level : null;
            $status = $qty <= 0.001 ? 'Out of Stock' : ($reorder !== null && $qty <= $reorder ? 'Low' : 'OK');
            $rate = $this->avgCost($bal[$id]);
            $rows[] = [
                'item' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit,
                'qty' => round($qty, 3), 'reorder' => $reorder, 'status' => $status,
                'rate' => round($rate, 2), 'value' => round(max($qty, 0) * $rate, 2),
                'last_move' => $last[$id]->last_move ?? null,
            ];
        }

        return ['columns' => [
            self::col('item', 'Item'), self::col('sku', 'Code'), self::col('unit', 'Unit'),
            self::col('qty', 'In Stock', 'qty', true), self::col('reorder', 'Reorder Level', 'qty'), self::col('status', 'Status', 'badge'),
            self::col('rate', 'Avg Cost', 'rate'), self::col('value', 'Value', 'money', true), self::col('last_move', 'Last Movement', 'date')],
            'rows' => $rows, 'notes' => array_filter([
                'Stock on ' . date('d-M-Y', strtotime($f['asof'])) . ' in our own warehouses. "Low" = at or below the reorder level set on the item.',
                $this->locationNote($f)])];
    }

    // ── 2. Opening & Closing ────────────────────────────────────────
    private function openingClosing(array $f): array
    {
        $products = $this->products($f);
        $ids = $products->keys()->all();
        $before = $this->balances($f, $ids, Carbon::parse($f['from'])->subDay()->toDateString());
        $atEnd = $this->balances($f, $ids, $f['to']);
        $moves = $this->ledger($f, $ids)->whereBetween('l.entry_date', [$f['from'], $f['to']])->groupBy('l.product_id')
            ->selectRaw('l.product_id, SUM(CASE WHEN l.quantity > 0 THEN l.quantity ELSE 0 END) as qty_in,
                         SUM(CASE WHEN l.quantity < 0 THEN -l.quantity ELSE 0 END) as qty_out')
            ->get()->keyBy('product_id');

        $rows = [];
        foreach ($products as $id => $p) {
            $open = $this->opening($p, $f) + $before[$id]['qty'];
            $in = (float) ($moves[$id]->qty_in ?? 0);
            $out = (float) ($moves[$id]->qty_out ?? 0);
            $close = $open + $in - $out;
            if (abs($open) < 0.001 && $in < 0.001 && $out < 0.001 && !$f['product_id']) continue;
            $rate = $this->avgCost($atEnd[$id]);
            $rows[] = [
                'item' => $p->name, 'unit' => $p->unit,
                'opening' => round($open, 3), 'in' => round($in, 3), 'out' => round($out, 3), 'closing' => round($close, 3),
                'rate' => round($rate, 2), 'value' => round(max($close, 0) * $rate, 2),
            ];
        }

        return ['columns' => [
            self::col('item', 'Item'), self::col('unit', 'Unit'),
            self::col('opening', 'Opening', 'qty', true), self::col('in', 'In', 'qty', true), self::col('out', 'Out', 'qty', true),
            self::col('closing', 'Closing', 'qty', true), self::col('rate', 'Avg Cost', 'rate'), self::col('value', 'Closing Value', 'money', true)],
            'rows' => $rows, 'notes' => array_filter(['Items with no stock and no movement in the period are hidden.', $this->locationNote($f)])];
    }

    // ── 3. Movement / In-Out ────────────────────────────────────────
    private function movement(array $f): array
    {
        $products = $this->products($f);
        $ids = $products->keys()->all();
        $before = $this->balances($f, $ids, Carbon::parse($f['from'])->subDay()->toDateString());

        $lines = $this->ledger($f, $ids)->join('locations as loc', 'loc.id', '=', 'l.location_id')
            ->whereBetween('l.entry_date', [$f['from'], $f['to']])
            ->orderBy('l.product_id')->orderBy('l.entry_date')->orderBy('l.id')
            ->get(['l.product_id', 'l.entry_date', 'l.doc_no', 'l.reference_type', 'l.lot_no', 'l.quantity', 'l.amount', 'loc.name as location']);

        $running = [];
        $rows = [];
        foreach ($lines as $m) {
            $p = $products[$m->product_id];
            $running[$m->product_id] ??= $this->opening($p, $f) + $before[$m->product_id]['qty'];
            $running[$m->product_id] += (float) $m->quantity;
            $rows[] = [
                'date' => $m->entry_date, 'item' => $p->name, 'doc_no' => $m->doc_no,
                'type' => self::DOC_LABELS[$m->reference_type] ?? $m->reference_type, 'location' => $m->location,
                'in' => $m->quantity > 0 ? round((float) $m->quantity, 3) : null,
                'out' => $m->quantity < 0 ? round(-(float) $m->quantity, 3) : null,
                'balance' => round($running[$m->product_id], 3), 'unit' => $p->unit,
                'value' => round(abs((float) $m->amount), 2),
            ];
        }

        return ['columns' => [
            self::col('date', 'Date', 'date'), self::col('item', 'Item'), self::col('doc_no', 'Document #'), self::col('type', 'Type'),
            self::col('location', 'Warehouse'), self::col('in', 'In', 'qty', true), self::col('out', 'Out', 'qty', true),
            self::col('balance', 'Balance', 'qty'), self::col('unit', 'Unit'), self::col('value', 'Value', 'money')],
            'rows' => $rows, 'notes' => array_filter(['Balance runs per item, starting from its balance before the period.', $this->locationNote($f)])];
    }

    // ── 4. Consumption / Usage ──────────────────────────────────────
    private function consumption(array $f): array
    {
        $products = $this->products($f);
        $ids = $products->keys()->all();
        $days = max(1, Carbon::parse($f['from'])->diffInDays(Carbon::parse($f['to'])) + 1);
        $now = $this->balances($f, $ids, $f['to']);

        $used = $this->ledger($f, $ids)->where('l.quantity', '<', 0)->whereNotIn('l.reference_type', self::TRANSFERS)
            ->whereBetween('l.entry_date', [$f['from'], $f['to']])->groupBy('l.product_id')
            ->selectRaw('l.product_id, SUM(-l.quantity) as qty, SUM(-l.amount) as amount, COUNT(*) as times, MAX(l.entry_date) as last_used')
            ->get()->keyBy('product_id');

        $rows = [];
        foreach ($used as $id => $u) {
            $p = $products[$id];
            $perDay = (float) $u->qty / $days;
            $onHand = $this->opening($p, $f) + $now[$id]['qty'];
            $rows[] = [
                'item' => $p->name, 'unit' => $p->unit, 'qty' => round((float) $u->qty, 3), 'value' => round((float) $u->amount, 2),
                'times' => (int) $u->times, 'per_day' => round($perDay, 3), 'per_month' => round($perDay * 30, 3),
                'on_hand' => round($onHand, 3), 'cover' => $perDay > 0 ? (int) floor(max($onHand, 0) / $perDay) : null,
                'last_used' => $u->last_used,
            ];
        }
        usort($rows, fn($a, $b) => $b['qty'] <=> $a['qty']);

        return ['columns' => [
            self::col('item', 'Item'), self::col('unit', 'Unit'), self::col('qty', 'Used', 'qty', true), self::col('value', 'Value Used', 'money', true),
            self::col('times', 'Times Issued', 'qty'), self::col('per_day', 'Avg / Day', 'qty'), self::col('per_month', 'Avg / Month', 'qty'),
            self::col('on_hand', 'On Hand', 'qty'), self::col('cover', 'Days of Cover', 'qty'), self::col('last_used', 'Last Used', 'date')],
            'rows' => $rows, 'notes' => array_filter([
                "Usage = stock going out of our warehouses ({$days} day period), not counting transfers between our own warehouses.",
                'Days of Cover = how many days the current stock lasts at this rate.',
                'Packaging stock is used in Packaging & Dispatch, which is still locked — until it is built, only purchase returns and other stock-outs appear here.',
                $this->locationNote($f)])];
    }

    // ── 5. Low Stock / Reorder Point ────────────────────────────────
    private function lowStock(array $f): array
    {
        $products = $this->products($f);
        $ids = $products->keys()->all();
        $bal = $this->balances($f, $ids, null);

        // still to come on open POs
        $onOrder = DB::table('purchase_order_items as i')->join('purchase_orders as po', 'po.id', '=', 'i.purchase_order_id')
            ->whereIn('i.product_id', $ids ?: [0])->whereNull('po.deleted_at')
            ->whereIn('po.status', ['Pending', 'Approved', 'Issued', 'PartiallyReceived'])
            ->groupBy('i.product_id')->selectRaw('i.product_id, SUM(GREATEST(i.quantity - i.quantity_received, 0)) as qty')
            ->pluck('qty', 'product_id');
        $lastRate = DB::table('purchase_order_items as i')->join('purchase_orders as po', 'po.id', '=', 'i.purchase_order_id')
            ->whereIn('i.product_id', $ids ?: [0])->whereNull('po.deleted_at')->whereNotIn('po.status', ['Draft', 'Rejected'])
            ->orderBy('po.order_date')->get(['i.product_id', 'i.rate'])->pluck('rate', 'product_id');

        $rows = []; $noLevel = 0;
        foreach ($products as $id => $p) {
            if ($p->reorder_level === null) { if ($p->is_active) $noLevel++; continue; }
            $qty = $this->opening($p, $f) + $bal[$id]['qty'];
            $level = (float) $p->reorder_level;
            if ($qty > $level + 0.001) continue;
            $coming = (float) ($onOrder[$id] ?? 0);
            $rows[] = [
                'item' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit,
                'qty' => round($qty, 3), 'reorder' => $level, 'short' => round($level - $qty, 3),
                'on_order' => round($coming, 3), 'to_order' => round(max(0, $level - $qty - $coming), 3),
                'last_rate' => isset($lastRate[$id]) ? (float) $lastRate[$id] : null,
                'status' => $qty <= 0.001 ? 'Out of Stock' : 'Low',
            ];
        }

        return ['columns' => [
            self::col('item', 'Item'), self::col('sku', 'Code'), self::col('unit', 'Unit'), self::col('qty', 'In Stock', 'qty'),
            self::col('reorder', 'Reorder Level', 'qty'), self::col('short', 'Short By', 'qty'), self::col('on_order', 'On Open POs', 'qty'),
            self::col('to_order', 'Still To Order', 'qty'), self::col('last_rate', 'Last PO Rate', 'rate'), self::col('status', 'Status', 'badge')],
            'rows' => $rows, 'notes' => array_filter([
                'Current stock against the Reorder Level set on each item. "Still To Order" deducts quantities already on open POs.',
                $noLevel ? "{$noLevel} active item(s) have no Reorder Level yet — set it on the item (Products → Edit) to include them." : null,
                $this->locationNote($f)])];
    }

    // ── 6. Slow Moving / Dead Stock ─────────────────────────────────
    private function slowMoving(array $f): array
    {
        $products = $this->products($f);
        $ids = $products->keys()->all();
        $bal = $this->balances($f, $ids, null);
        $last = $this->lastDates($f, $ids);
        $cutoff = now()->subDays($f['days'])->toDateString();

        $rows = [];
        foreach ($products as $id => $p) {
            $qty = $this->opening($p, $f) + $bal[$id]['qty'];
            if ($qty <= 0.001) continue;
            $lastIn = $last[$id]->last_in ?? null;
            $lastOut = $last[$id]->last_out ?? null;
            $lastAny = $last[$id]->last_move ?? null;

            if (!$lastAny || $lastAny < $cutoff) $class = 'Dead';
            elseif (!$lastOut || $lastOut < $cutoff) $class = 'Slow';
            else continue;

            $since = $lastOut ?? $lastIn;
            $rate = $this->avgCost($bal[$id]);
            $rows[] = [
                'item' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit, 'qty' => round($qty, 3),
                'value' => round($qty * $rate, 2), 'last_in' => $lastIn, 'last_out' => $lastOut,
                'idle' => $since ? Carbon::parse($since)->diffInDays(now()) : null, 'status' => $class === 'Dead' ? 'Dead Stock' : 'Slow Moving',
            ];
        }
        usort($rows, fn($a, $b) => ($b['idle'] ?? PHP_INT_MAX) <=> ($a['idle'] ?? PHP_INT_MAX));

        return ['columns' => [
            self::col('item', 'Item'), self::col('sku', 'Code'), self::col('unit', 'Unit'), self::col('qty', 'In Stock', 'qty', true),
            self::col('value', 'Value', 'money', true), self::col('last_in', 'Last Received', 'date'), self::col('last_out', 'Last Used', 'date'),
            self::col('idle', 'Days Idle', 'qty'), self::col('status', 'Status', 'badge')],
            'rows' => $rows, 'notes' => array_filter([
                "Slow Moving = in stock but not used in the last {$f['days']} days. Dead Stock = no movement at all in the last {$f['days']} days (or never moved since opening stock).",
                $this->locationNote($f)])];
    }

    // ── 7. Inventory Value ──────────────────────────────────────────
    private function inventoryValue(array $f): array
    {
        $products = $this->products($f);
        $ids = $products->keys()->all();

        $lines = $this->ledger($f, $ids)->join('locations as loc', 'loc.id', '=', 'l.location_id')
            ->where('l.entry_date', '<=', $f['asof'])
            ->groupBy('l.product_id', 'l.location_id', 'loc.name')->havingRaw('ABS(SUM(l.quantity)) > 0.001')
            ->selectRaw('l.product_id, loc.name as location, SUM(l.quantity) as qty, SUM(l.amount) as amount')
            ->get();

        $rows = [];
        foreach ($lines as $l) {
            $p = $products[$l->product_id];
            $qty = (float) $l->qty;
            $rows[] = ['item' => $p->name, 'sku' => $p->sku, 'location' => $l->location, 'unit' => $p->unit,
                'qty' => round($qty, 3), 'rate' => round($qty > 0 ? (float) $l->amount / $qty : 0, 2), 'value' => round((float) $l->amount, 2)];
        }
        if (!$f['location_id']) {
            foreach ($products as $p) {
                if ((float) $p->opening_stock > 0) {
                    $rows[] = ['item' => $p->name, 'sku' => $p->sku, 'location' => 'Opening Stock (no warehouse)', 'unit' => $p->unit,
                        'qty' => (float) $p->opening_stock, 'rate' => 0, 'value' => 0];
                }
            }
        }
        usort($rows, fn($a, $b) => [$a['item'], $a['location']] <=> [$b['item'], $b['location']]);

        return ['columns' => [
            self::col('item', 'Item'), self::col('sku', 'Code'), self::col('location', 'Warehouse'), self::col('unit', 'Unit'),
            self::col('qty', 'Qty', 'qty', true), self::col('rate', 'Avg Cost', 'rate'), self::col('value', 'Value', 'money', true)],
            'rows' => $rows, 'notes' => [
                'Value at average purchase cost on ' . date('d-M-Y', strtotime($f['asof'])) . '.',
                'Opening Stock typed on items has no cost, so it shows with zero value.']];
    }
}
