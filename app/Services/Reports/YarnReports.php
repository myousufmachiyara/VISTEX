<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class YarnReports extends BaseReports
{
    public static function group(): string { return 'yarn'; }
    public static function title(): string { return 'Yarn Reports'; }

    public static function catalog(): array
    {
        return [
            'po'              => ['title' => 'Daily Yarn P.O',                        'filter' => 'range', 'desc' => 'Yarn purchase orders placed in the period.'],
            'arrival'         => ['title' => 'Daily Yarn Arrival',                    'filter' => 'range', 'desc' => 'Yarn accepted into the warehouse (approved GRNs).'],
            'issuance'        => ['title' => 'Daily Yarn Issuance',                   'filter' => 'range', 'desc' => 'Yarn issued to weaving mills against Weaving POs.'],
            'return_to_mill'  => ['title' => 'Daily Yarn Return To Mills',            'filter' => 'range', 'desc' => 'Rejected yarn returned to the spinning mill.'],
            'return_from_vendor' => ['title' => 'Daily Yarn Return From Supplier/Vendor', 'filter' => 'range', 'desc' => 'Unused yarn returned by weaving mills (GIN).'],
            'sale'            => ['title' => 'Yarn Sale Order',                       'filter' => 'range', 'desc' => 'Yarn sold to customers.'],
            'stock'           => ['title' => 'Daily Yarn Stock Details',              'filter' => 'asof',  'desc' => 'Yarn in our warehouses on a date, by Yarn PO.'],
        ];
    }

    private static function weightCols(): array
    {
        return [
            self::col('bags', 'Bags', 'qty', true),
            self::col('lbs', 'Lbs', 'weight', true),
            self::col('kg', 'Kg', 'weight', true),
            self::col('rate_lbs', 'Rate Lbs', 'rate'),
            self::col('rate_kg', 'Rate Kg', 'rate'),
            self::col('amount', 'Amount', 'money', true),
        ];
    }

    // bags / lbs / kg / rates / amount for one line
    private function weights(float $qty, ?string $unit, float $rate, $perPack): array
    {
        $lbs = $this->toLbs($qty, $unit);
        $rLbs = $this->ratePerLbs($rate, $unit);
        return [
            'bags' => $this->bags($qty, $perPack),
            'lbs' => round($lbs, 3), 'kg' => round($this->lbsToKg($lbs), 3),
            'rate_lbs' => round($rLbs, 2), 'rate_kg' => round($rLbs * self::LBS_PER_KG, 2),
            'amount' => round($qty * $rate, 2),
        ];
    }

    public function run(string $key, array $f): array
    {
        return match ($key) {
            'po' => $this->po($f),
            'arrival' => $this->arrival($f),
            'issuance' => $this->issuance($f),
            'return_to_mill' => $this->returnToMill($f),
            'return_from_vendor' => $this->pending(array_merge([
                self::col('date', 'Date', 'date'), self::col('yarn_po', 'Yarn P.O'), self::col('mill', 'Mill Name'),
                self::col('quality', 'Yarn Quality'), self::col('gin', 'GIN')], self::weightCols()),
                'There is no document yet for yarn coming back from a weaving mill (GIN). This report will fill once that flow is built.'),
            'sale' => $this->pending(array_merge([
                self::col('date', 'Date', 'date'), self::col('yarn_po', 'Yarn P.O'), self::col('mill', 'Mill Name'),
                self::col('quality', 'Yarn Quality')], self::weightCols()),
                'Yarn Sale Orders are part of the Sale Orders module, which is still locked pending client discussion.'),
            'stock' => $this->stock($f),
        };
    }

    // 1. Daily Yarn P.O
    private function po(array $f): array
    {
        $rows = DB::table('purchase_order_items as i')
            ->join('purchase_orders as po', 'po.id', '=', 'i.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->leftJoin('measurement_units as u', 'u.id', '=', DB::raw('COALESCE(i.measurement_unit, p.measurement_unit)'))
            ->where('po.type', 'purchase')->whereIn('po.product_category_id', $this->categoryIds('yarn'))
            ->whereNull('po.deleted_at')->whereNotIn('po.status', ['Draft', 'Rejected'])
            ->whereBetween('po.order_date', [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('po.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('i.product_id', $v))
            ->orderBy('po.order_date')->orderBy('po.order_no')
            ->get(['po.id as po_id', 'po.order_date', 'po.order_no', 'po.status', 'v.name as vendor', 'p.name as product',
                   'i.quantity', 'i.rate', 'i.qty_per_pack', 'u.shortcode as unit'])
            ->map(fn($r) => array_merge([
                'date' => $r->order_date, 'yarn_po' => $r->order_no, 'yarn_po_link' => route('purchase_orders.show', $r->po_id),
                'mill' => $r->vendor, 'quality' => $r->product,
            ], $this->weights((float) $r->quantity, $r->unit, (float) $r->rate, $r->qty_per_pack), ['status' => $r->status]))->all();

        return ['columns' => array_merge([
            self::col('date', 'Date', 'date'), self::col('yarn_po', 'Yarn P.O', 'link'), self::col('mill', 'Mill Name'),
            self::col('quality', 'Yarn Quality')], self::weightCols(), [self::col('status', 'Status')]), 'rows' => $rows,
            'notes' => ['Draft and rejected POs are excluded.']];
    }

    // 2. Daily Yarn Arrival
    private function arrival(array $f): array
    {
        $rows = $this->grnLines($f, 'yarn')->map(function ($r) {
            $accepted = (float) $r->quantity_received - (float) $r->quantity_rejected;
            return array_merge([
                'date' => $r->receiving_date, 'yarn_po' => $r->order_no, 'yarn_po_link' => route('purchase_orders.show', $r->po_id),
                'mill' => $r->vendor, 'quality' => $r->product,
                'grn' => $r->receiving_no, 'grn_link' => route('purchase_receivings.show', $r->receiving_id),
            ], $this->weights($accepted, $r->unit, (float) $r->rate, $r->qty_per_pack),
               ['rejected_lbs' => round($this->toLbs((float) $r->quantity_rejected, $r->unit), 3)]);
        })->all();

        return ['columns' => array_merge([
            self::col('date', 'Date', 'date'), self::col('yarn_po', 'Yarn P.O', 'link'), self::col('mill', 'Mill Name'),
            self::col('quality', 'Yarn Quality'), self::col('grn', 'GRN', 'link')], self::weightCols(),
            [self::col('rejected_lbs', 'Rejected Lbs', 'weight', true)]), 'rows' => $rows,
            'notes' => ['Quantities are what was accepted after inspection; rejected quantity is shown separately.']];
    }

    // 3. Daily Yarn Issuance
    private function issuance(array $f): array
    {
        $lines = DB::table('issuance_items as ii')
            ->join('issuances as s', 's.id', '=', 'ii.issuance_id')
            ->join('products as p', 'p.id', '=', 'ii.product_id')
            ->leftJoin('purchase_orders as wpo', 'wpo.id', '=', 's.purchase_order_id')
            ->leftJoin('vendors as v', 'v.id', '=', 's.vendor_id')
            ->leftJoin('measurement_units as u', 'u.id', '=', 'p.measurement_unit')
            ->where('s.issue_type', 'yarn_weaving')->whereNull('s.deleted_at')
            ->whereBetween('s.issue_date', [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('s.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('ii.product_id', $v))
            ->orderBy('s.issue_date')->orderBy('s.issue_no')
            ->get(['s.id as issuance_id', 's.issue_date', 's.issue_no', 'wpo.id as wpo_id', 'wpo.order_no as weaving_po', 'v.name as vendor',
                   'ii.product_id', 'p.name as product', 'ii.lot_no', 'ii.quantity', 'ii.rate', 'u.shortcode as unit']);

        $lots = $lines->pluck('lot_no')->all();
        $pos = $this->poByNumber($lots);
        $packs = $this->packSizes($lots);

        $rows = $lines->map(fn($r) => array_merge([
            'date' => $r->issue_date, 'issue_no' => $r->issue_no, 'issue_no_link' => route('issuances.show', $r->issuance_id),
            'weaving_po' => $r->weaving_po, 'weaving_po_link' => $r->wpo_id ? route('purchase_orders.show', $r->wpo_id) : null,
            'vendor' => $r->vendor,
            'yarn_po' => $r->lot_no && isset($pos[$r->lot_no]) ? $r->lot_no : ($r->lot_no ?: '—'),
            'mill' => $pos[$r->lot_no]->vendor ?? '—',
            'quality' => $r->product,
        ], $this->weights((float) $r->quantity, $r->unit, (float) $r->rate, $packs[$r->lot_no . '|' . $r->product_id] ?? null)))->all();

        $unknown = $lines->filter(fn($r) => !$r->lot_no || !isset($pos[$r->lot_no]))->count();
        return ['columns' => array_merge([
            self::col('date', 'Date', 'date'), self::col('issue_no', 'Issue #', 'link'), self::col('weaving_po', 'Weaving P.O', 'link'),
            self::col('vendor', 'Supplier/Vendor'), self::col('yarn_po', 'Yarn P.O'), self::col('mill', 'Mill Name'),
            self::col('quality', 'Yarn Quality')], self::weightCols()), 'rows' => $rows,
            'notes' => array_filter([
                'Rate is the warehouse average cost at the time of issue.',
                $unknown ? "{$unknown} line(s) were issued without picking a lot, so their Yarn P.O / Mill Name is unknown. Pick the lot on the issuance form to fill these columns." : null,
            ])];
    }

    // 4. Daily Yarn Return To Mills
    private function returnToMill(array $f): array
    {
        $rows = $this->returnLines($f, 'yarn')->map(fn($r) => array_merge([
            'date' => $r->return_date, 'return_no' => $r->return_no,
            'yarn_po' => $r->order_no, 'yarn_po_link' => route('purchase_orders.show', $r->po_id),
            'mill' => $r->vendor, 'quality' => $r->product,
            'grn' => $r->receiving_no, 'grn_link' => route('purchase_receivings.show', $r->receiving_id),
        ], $this->weights((float) $r->quantity_returned, $r->unit, (float) $r->rate, $r->qty_per_pack)))->all();

        return ['columns' => array_merge([
            self::col('date', 'Date', 'date'), self::col('return_no', 'Return #'), self::col('yarn_po', 'Yarn P.O', 'link'),
            self::col('mill', 'Mill Name'), self::col('quality', 'Yarn Quality'), self::col('grn', 'GRN', 'link')], self::weightCols()),
            'rows' => $rows, 'notes' => []];
    }

    // 7. Daily Yarn Stock Details
    private function stock(array $f): array
    {
        $locations = $this->ownLocationIds($f['location_id']);
        $lines = DB::table('location_stock_ledger as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('measurement_units as u', 'u.id', '=', 'p.measurement_unit')
            ->whereIn('p.category_id', $this->categoryIds('yarn'))
            ->where('l.status', 'fresh')->whereIn('l.location_id', $locations)
            ->where('l.entry_date', '<=', $f['asof'])
            ->when($f['product_id'], fn($q, $v) => $q->where('l.product_id', $v))
            ->groupBy('l.lot_no', 'l.product_id', 'p.name', 'p.attributes', 'u.shortcode')
            ->selectRaw('l.lot_no, l.product_id, p.name as product, p.attributes, u.shortcode as unit,
                         SUM(l.quantity) as qty, SUM(l.amount) as amount, MAX(l.entry_date) as last_date, MIN(l.entry_date) as first_date')
            ->get();
        $lines = $this->netUnlotted($lines, fn($l) => $l->product_id);

        $pos = $this->poByNumber($lines->pluck('lot_no')->all());
        $packs = $this->packSizes($lines->pluck('lot_no')->all());

        $rows = $lines->filter(fn($r) => !$f['vendor_id'] || (isset($pos[$r->lot_no]) && $this->vendorMatches($pos[$r->lot_no], $f['vendor_id'])))
            ->map(function ($r) use ($pos, $packs) {
                $qty = (float) $r->qty;
                $rate = $qty > 0 ? (float) $r->amount / $qty : 0;
                return array_merge([
                    'date' => $r->last_date,
                    'yarn_po' => $r->lot_no ?: '—', 'yarn_po_link' => isset($pos[$r->lot_no]) ? route('purchase_orders.show', $pos[$r->lot_no]->id) : null,
                    'mill' => $pos[$r->lot_no]->vendor ?? '—',
                    'brand' => $this->attr($r->attributes, 'brand') ?? '—',
                    'quality' => $r->product,
                ], $this->weights($qty, $r->unit, $rate, $packs[$r->lot_no . '|' . $r->product_id] ?? null));
            })->values()->all();

        // Opening stock typed on the product (not tied to a lot or warehouse)
        $openingNote = null;
        if (!$f['location_id'] && !$f['vendor_id']) {
            $opening = DB::table('products as p')->leftJoin('measurement_units as u', 'u.id', '=', 'p.measurement_unit')
                ->whereIn('p.category_id', $this->categoryIds('yarn'))->where('p.opening_stock', '>', 0)->whereNull('p.deleted_at')
                ->when($f['product_id'], fn($q, $v) => $q->where('p.id', $v))
                ->get(['p.name', 'p.attributes', 'p.opening_stock', 'u.shortcode as unit']);
            foreach ($opening as $o) {
                $rows[] = array_merge(['date' => null, 'yarn_po' => 'Opening Stock', 'mill' => '—',
                    'brand' => $this->attr($o->attributes, 'brand') ?? '—', 'quality' => $o->name],
                    $this->weights((float) $o->opening_stock, $o->unit, 0, null));
            }
            if ($opening->isNotEmpty()) $openingNote = 'Rows marked "Opening Stock" come from the Opening Stock typed on the product. They have no cost, and can\'t be issued until received through a GRN.';
        }

        return ['columns' => array_merge([
            self::col('date', 'Last Receipt', 'date'), self::col('yarn_po', 'Yarn P.O', 'link'), self::col('mill', 'Mill Name'),
            self::col('brand', 'Brand'), self::col('quality', 'Yarn Quality')], self::weightCols()),
            'rows' => $rows, 'notes' => array_filter([
                'Fresh yarn in our own warehouses on ' . date('d-M-Y', strtotime($f['asof'])) . ', after issuances. Yarn already issued to weaving mills is not included.',
                'Rate is the average cost of that lot; Amount is its stock value.',
                $openingNote,
            ])];
    }

}
