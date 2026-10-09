<?php

namespace App\Services\Reports;

use App\Models\{Location, ProductCategory};
use Illuminate\Support\Facades\DB;

/**
 * Shared plumbing for the report families.
 *
 * Each family lists its reports in catalog() and builds one in run().
 * run() returns:
 *   columns  [ ['key','label','type' => text|date|qty|weight|money|rate,'total' => bool] ]
 *   rows     [ [key => value, ...] ]
 *   notes    [ string ]  shown above the table
 *   pending  string|null — set when the business flow behind the report isn't built yet
 */
abstract class BaseReports
{
    public const LBS_PER_KG = 2.2046226218;

    abstract public static function group(): string;
    abstract public static function title(): string;
    abstract public static function catalog(): array;
    abstract public function run(string $key, array $f): array;

    // ── units ───────────────────────────────────────────────────────

    // Quantity in lbs. Yarn is bought in lbs or kg; anything else is assumed lbs.
    protected function toLbs(float $qty, ?string $unit): float
    {
        return strtolower((string) $unit) === 'kg' ? $qty * self::LBS_PER_KG : $qty;
    }

    // Rate per lb from a rate per the item's unit
    protected function ratePerLbs(float $rate, ?string $unit): float
    {
        return strtolower((string) $unit) === 'kg' ? $rate / self::LBS_PER_KG : $rate;
    }

    protected function lbsToKg(float $lbs): float { return $lbs / self::LBS_PER_KG; }

    // Bags only make sense when the PO line was entered in packs
    protected function bags(float $qty, $perPack): ?float
    {
        $perPack = (float) $perPack;
        return $perPack > 1 ? round($qty / $perPack, 2) : null;
    }

    // ── lookups ─────────────────────────────────────────────────────

    protected function categoryIds(string $code): array
    {
        return ProductCategory::where('code', $code)->pluck('id')->all() ?: [0];
    }

    protected function ownLocationIds(?int $only = null): array
    {
        if ($only) return [$only];
        return Location::whereNull('vendor_id')->pluck('id')->all() ?: [0];
    }

    // order_no => vendor, type, gsm_kg — to describe a stock lot by its PO
    protected function poByNumber(array $orderNos): array
    {
        $orderNos = array_values(array_filter(array_unique($orderNos)));
        if (!$orderNos) return [];
        return DB::table('purchase_orders as po')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->whereIn('po.order_no', $orderNos)
            ->get(['po.id', 'po.order_no', 'po.type', 'po.gsm_kg', 'v.name as vendor'])
            ->keyBy('order_no')->all();
    }

    // order_no|product_id => qty_per_pack from that PO's line
    protected function packSizes(array $orderNos): array
    {
        $orderNos = array_values(array_filter(array_unique($orderNos)));
        if (!$orderNos) return [];
        $out = [];
        foreach (DB::table('purchase_order_items as i')->join('purchase_orders as po', 'po.id', '=', 'i.purchase_order_id')
            ->whereIn('po.order_no', $orderNos)->get(['po.order_no', 'i.product_id', 'i.qty_per_pack']) as $r) {
            $out[$r->order_no . '|' . $r->product_id] = $r->qty_per_pack;
        }
        return $out;
    }

    protected function attr($json, string $key): ?string
    {
        $a = is_array($json) ? $json : (json_decode((string) $json, true) ?: []);
        return isset($a[$key]) && $a[$key] !== '' ? (string) $a[$key] : null;
    }

    protected function pending(array $columns, string $why): array
    {
        return ['columns' => $columns, 'rows' => [], 'notes' => [], 'pending' => $why];
    }

    protected static function col(string $key, string $label, string $type = 'text', bool $total = false): array
    {
        return compact('key', 'label', 'type', 'total');
    }

    // approved GRN lines for purchase POs of a category
    protected function grnLines(array $f, string $categoryCode)
    {
        return DB::table('purchase_receiving_items as gi')
            ->join('purchase_receivings as r', 'r.id', '=', 'gi.purchase_receiving_id')
            ->join('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'gi.product_id')
            ->leftJoin('purchase_order_items as i', 'i.id', '=', 'gi.purchase_order_item_id')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->leftJoin('measurement_units as u', 'u.id', '=', DB::raw('COALESCE(i.measurement_unit, p.measurement_unit)'))
            ->where('r.status', 'Approved')->whereNull('r.deleted_at')
            ->where('po.type', 'purchase')->whereIn('po.product_category_id', $this->categoryIds($categoryCode))
            ->whereBetween('r.receiving_date', [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('po.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('gi.product_id', $v))
            ->orderBy('r.receiving_date')->orderBy('r.receiving_no')
            ->get(['r.id as receiving_id', 'r.receiving_date', 'r.receiving_no', 'po.id as po_id', 'po.order_no', 'v.name as vendor',
                   'p.name as product', 'gi.quantity_received', 'gi.quantity_rejected', 'gi.rate', 'i.qty_per_pack', 'u.shortcode as unit']);
    }

    protected function returnLines(array $f, string $categoryCode)
    {
        return DB::table('purchase_return_items as ri')
            ->join('purchase_returns as rt', 'rt.id', '=', 'ri.purchase_return_id')
            ->join('purchase_receiving_items as gi', 'gi.id', '=', 'ri.purchase_receiving_item_id')
            ->join('purchase_receivings as r', 'r.id', '=', 'gi.purchase_receiving_id')
            ->join('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'gi.product_id')
            ->leftJoin('purchase_order_items as i', 'i.id', '=', 'gi.purchase_order_item_id')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->leftJoin('measurement_units as u', 'u.id', '=', DB::raw('COALESCE(i.measurement_unit, p.measurement_unit)'))
            ->whereNull('rt.deleted_at')->whereIn('po.product_category_id', $this->categoryIds($categoryCode))
            ->whereBetween('rt.return_date', [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('po.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('gi.product_id', $v))
            ->orderBy('rt.return_date')
            ->get(['rt.return_date', 'rt.return_no', 'po.id as po_id', 'po.order_no', 'po.type', 'po.gsm_kg', 'v.name as vendor', 'p.name as product',
                   'r.id as receiving_id', 'r.receiving_no', 'ri.quantity_returned', 'gi.rate', 'i.qty_per_pack', 'u.shortcode as unit']);
    }

    protected function vendorMatches($po, $vendorId): bool
    {
        return (int) DB::table('purchase_orders')->where('id', $po->id)->value('vendor_id') === (int) $vendorId;
    }

    /**
     * Issuances made with "Any lot" before FIFO allocation existed took stock
     * out with no lot. For lot-level stock, net those against the oldest lots
     * of the same item/place. Lines need: lot_no, qty, amount, first_date and
     * the grouping fields used by $key. Returns only lines still in stock.
     */
    protected function netUnlotted($lines, callable $key)
    {
        $out = collect();
        foreach ($lines->groupBy($key) as $group) {
            $unlotted = $group->first(fn($l) => $l->lot_no === null);
            $short = $unlotted && (float) $unlotted->qty < 0 ? -(float) $unlotted->qty : 0;
            $lots = $group->filter(fn($l) => $l->lot_no !== null)->sortBy(fn($l) => [$l->first_date, $l->lot_no]);
            foreach ($lots as $l) {
                if ($short > 0.0005 && (float) $l->qty > 0) {
                    $take = min($short, (float) $l->qty);
                    $rate = (float) $l->qty > 0 ? (float) $l->amount / (float) $l->qty : 0;
                    $l->qty = (float) $l->qty - $take;
                    $l->amount = (float) $l->amount - $take * $rate;
                    $short -= $take;
                }
                $out->push($l);
            }
            if ($unlotted && (float) $unlotted->qty > 0) $out->push($unlotted);
        }
        return $out->filter(fn($l) => (float) $l->qty > 0.001)->values();
    }
}
