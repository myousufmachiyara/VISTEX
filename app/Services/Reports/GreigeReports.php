<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class GreigeReports extends BaseReports
{
    public static function group(): string { return 'greige'; }
    public static function title(): string { return 'Fabric (Greige) Reports'; }

    public static function catalog(): array
    {
        return [
            'po'              => ['title' => 'Daily Greige P.O',               'filter' => 'range', 'desc' => 'Greige bought ready-made from suppliers.'],
            'conversion_po'   => ['title' => 'Daily Conversion P.O',           'filter' => 'range', 'desc' => 'Weaving (conversion) POs placed with weaving mills.'],
            'arrival'         => ['title' => 'Daily Greige Arrival',           'filter' => 'range', 'desc' => 'Greige accepted into the warehouse (approved GRNs).'],
            'rejection'       => ['title' => 'Daily Greige Rejection',         'filter' => 'range', 'desc' => 'Greige rejected at inspection.'],
            'refresh'         => ['title' => 'Daily Greige Refresh',           'filter' => 'range', 'desc' => 'Rejected greige reworked and moved back to fresh stock.'],
            'return'          => ['title' => 'Daily Greige Return',            'filter' => 'range', 'desc' => 'Rejected greige returned to the supplier.'],
            'fresh_stock'     => ['title' => 'Daily Greige Fresh Fabric Stock', 'filter' => 'asof', 'desc' => 'Fresh greige on a date, by P.O.'],
            'rejection_stock' => ['title' => 'Daily Greige Rejection Stock',   'filter' => 'asof', 'desc' => 'Rejected greige still held, by P.O.'],
        ];
    }

    private static function head(bool $grn = false): array
    {
        return array_values(array_filter([
            self::col('date', 'Date', 'date'), self::col('po_no', 'P.O #', 'link'), self::col('po_type', 'P.O Type'),
            self::col('vendor', 'Supplier/Vendor'), self::col('quality', 'Fabric Quality'),
            $grn ? self::col('grn', 'GRN', 'link') : null,
        ]));
    }

    private static function mtrCols(): array
    {
        return [
            self::col('mtr', 'Mtr', 'qty', true), self::col('kg', 'Kg', 'weight', true),
            self::col('rate', 'Rate P/Mtr', 'rate'), self::col('amount', 'Amount', 'money', true),
        ];
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) { 'weaving' => 'Weaving', 'processing' => 'Processing', 'purchase' => 'Greige', default => '—' };
    }

    // greige weight is only known for woven fabric (GSM-based kg per meter on the Weaving PO)
    private function kg(float $mtr, $gsmKg): ?float
    {
        return (float) $gsmKg > 0 ? round($mtr * (float) $gsmKg, 3) : null;
    }

    public function run(string $key, array $f): array
    {
        return match ($key) {
            'po' => $this->po($f),
            'conversion_po' => $this->conversionPo($f),
            'arrival' => $this->arrival($f),
            'rejection' => $this->rejection($f),
            'refresh' => $this->pending(array_merge(self::head(true), self::mtrCols()),
                'There is no "refresh" step yet for moving rejected greige back to fresh stock. This report will fill once that flow is built.'),
            'return' => $this->returns($f),
            'fresh_stock' => $this->stock($f, 'fresh'),
            'rejection_stock' => $this->stock($f, 'rejected'),
        };
    }

    // 1. Daily Greige P.O (ready-made greige purchases)
    private function po(array $f): array
    {
        $rows = DB::table('purchase_order_items as i')
            ->join('purchase_orders as po', 'po.id', '=', 'i.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->where('po.type', 'purchase')->whereIn('po.product_category_id', $this->categoryIds('greige'))
            ->whereNull('po.deleted_at')->whereNotIn('po.status', ['Draft', 'Rejected'])
            ->whereBetween('po.order_date', [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('po.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('i.product_id', $v))
            ->orderBy('po.order_date')->orderBy('po.order_no')
            ->get(['po.id as po_id', 'po.order_date', 'po.order_no', 'po.status', 'v.name as vendor', 'p.name as product', 'i.quantity', 'i.rate'])
            ->map(fn($r) => [
                'date' => $r->order_date, 'po_no' => $r->order_no, 'po_no_link' => route('purchase_orders.show', $r->po_id),
                'po_type' => 'Greige', 'vendor' => $r->vendor, 'quality' => $r->product,
                'mtr' => (float) $r->quantity, 'rate' => (float) $r->rate, 'amount' => round((float) $r->quantity * (float) $r->rate, 2),
                'status' => $r->status,
            ])->all();

        return ['columns' => array_merge(self::head(), [
            self::col('mtr', 'Mtr', 'qty', true), self::col('rate', 'Rate P/Mtr', 'rate'), self::col('amount', 'Amount', 'money', true),
            self::col('status', 'Status')]), 'rows' => $rows, 'notes' => ['Draft and rejected POs are excluded.']];
    }

    // 2. Daily Conversion P.O (weaving)
    private function conversionPo(array $f): array
    {
        $rows = DB::table('purchase_orders as po')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->leftJoin('products as g', 'g.id', '=', 'po.greige_product_id')
            ->where('po.type', 'weaving')->whereNull('po.deleted_at')->whereNotIn('po.status', ['Draft', 'Rejected'])
            ->whereBetween('po.order_date', [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('po.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('po.greige_product_id', $v))
            ->orderBy('po.order_date')->orderBy('po.order_no')
            ->get(['po.id', 'po.order_date', 'po.order_no', 'po.status', 'v.name as vendor', 'g.name as product', 'po.item_name',
                   'po.total_meters_required', 'po.total_yarn_required', 'po.rate_per_pick', 'po.weaving_per_meter', 'po.subtotal'])
            ->map(fn($r) => [
                'date' => $r->order_date, 'po_no' => $r->order_no, 'po_no_link' => route('purchase_orders.show', $r->id),
                'po_type' => 'Weaving', 'vendor' => $r->vendor, 'quality' => $r->product ?? $r->item_name,
                'mtr' => (float) $r->total_meters_required, 'weight' => round((float) $r->total_yarn_required, 3),
                'rate_pick' => (float) $r->rate_per_pick, 'rate' => (float) $r->weaving_per_meter, 'amount' => (float) $r->subtotal,
                'status' => $r->status,
            ])->all();

        return ['columns' => array_merge(self::head(), [
            self::col('mtr', 'Mtr', 'qty', true), self::col('weight', 'Total Weight (Lbs)', 'weight', true),
            self::col('rate_pick', 'Rate P/Pick', 'rate'), self::col('rate', 'Rate P/Mtr', 'rate'),
            self::col('amount', 'Conversion Amount', 'money', true), self::col('status', 'Status')]), 'rows' => $rows,
            'notes' => ['Total Weight is the total yarn required for the PO. Rate P/Mtr is the conversion charge (pick rate + sizing).']];
    }

    // approved GRN lines for greige: ready-made purchases + weaving receipts
    private function greigeGrnLines(array $f)
    {
        return DB::table('purchase_receiving_items as gi')
            ->join('purchase_receivings as r', 'r.id', '=', 'gi.purchase_receiving_id')
            ->join('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
            ->leftJoin('products as p', 'p.id', '=', 'gi.product_id')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->where('r.status', 'Approved')->whereNull('r.deleted_at')
            ->where(fn($q) => $q->where('po.type', 'weaving')
                ->orWhere(fn($q2) => $q2->where('po.type', 'purchase')->whereIn('po.product_category_id', $this->categoryIds('greige'))))
            ->whereBetween('r.receiving_date', [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('po.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('gi.product_id', $v))
            ->orderBy('r.receiving_date')->orderBy('r.receiving_no')
            ->get(['r.id as receiving_id', 'r.receiving_date', 'r.receiving_no', 'r.challan_id', 'po.id as po_id', 'po.order_no', 'po.type', 'po.gsm_kg',
                   'v.name as vendor', 'p.name as product', 'po.item_name', 'gi.quantity_received', 'gi.quantity_rejected', 'gi.rate']);
    }

    private function grnRow($r, float $mtr): array
    {
        return [
            'date' => $r->receiving_date, 'po_no' => $r->order_no, 'po_no_link' => route('purchase_orders.show', $r->po_id),
            'po_type' => $this->typeLabel($r->type), 'vendor' => $r->vendor, 'quality' => $r->product ?? $r->item_name,
            'grn' => $r->receiving_no, 'grn_link' => route('purchase_receivings.show', $r->receiving_id),
            'mtr' => round($mtr, 3), 'kg' => $this->kg($mtr, $r->gsm_kg),
            'rate' => round((float) $r->rate, 2), 'amount' => round($mtr * (float) $r->rate, 2),
        ];
    }

    // 3. Daily Greige Arrival
    private function arrival(array $f): array
    {
        $rows = $this->greigeGrnLines($f)
            ->map(fn($r) => $this->grnRow($r, (float) $r->quantity_received - (float) $r->quantity_rejected))
            ->filter(fn($r) => $r['mtr'] > 0)->values()->all();

        return ['columns' => array_merge(self::head(true), self::mtrCols()), 'rows' => $rows,
            'notes' => ['For Weaving POs, Rate P/Mtr is the full greige cost per meter (yarn consumed + conversion). Kg is shown where the Weaving PO has a GSM.']];
    }

    // 4. Daily Greige Rejection — GRN rejections (ready-made greige) + gate-inspection rejections (weaving)
    private function rejection(array $f): array
    {
        $rows = $this->greigeGrnLines($f)->filter(fn($r) => (float) $r->quantity_rejected > 0)
            ->map(fn($r) => array_merge($this->grnRow($r, (float) $r->quantity_rejected), ['reason' => 'Rejected at inspection']))
            ->values()->all();

        // Weaving consignments: the rejected meters are recorded on the challan (they go back to the mill at the gate)
        $weaving = DB::table('challan_items as ci')
            ->join('challans as c', 'c.id', '=', 'ci.challan_id')
            ->join('purchase_orders as po', 'po.id', '=', 'c.purchase_order_id')
            ->leftJoin('products as p', 'p.id', '=', 'po.greige_product_id')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->leftJoin('purchase_receivings as r', fn($j) => $j->on('r.challan_id', '=', 'c.id')->whereNull('r.deleted_at'))
            ->where('po.type', 'weaving')->whereNull('c.deleted_at')->where('ci.rejected_qty', '>', 0)
            ->whereIn('c.status', ['AcceptedWithObjection', 'Rejected'])
            ->whereBetween(DB::raw('DATE(c.reviewed_at)'), [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('po.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('po.greige_product_id', $v))
            ->get(['c.id as challan_id', 'c.challan_no', 'c.reviewed_at', 'c.decision_remarks', 'po.id as po_id', 'po.order_no', 'po.gsm_kg',
                   'po.weaving_per_meter', 'v.name as vendor', 'p.name as product', 'po.item_name', 'ci.rejected_qty',
                   'r.id as receiving_id', 'r.receiving_no']);

        foreach ($weaving as $w) {
            $mtr = (float) $w->rejected_qty;
            $rows[] = [
                'date' => substr($w->reviewed_at, 0, 10), 'po_no' => $w->order_no, 'po_no_link' => route('purchase_orders.show', $w->po_id),
                'po_type' => 'Weaving', 'vendor' => $w->vendor, 'quality' => $w->product ?? $w->item_name,
                'grn' => $w->receiving_no ?? $w->challan_no,
                'grn_link' => $w->receiving_id ? route('purchase_receivings.show', $w->receiving_id) : route('challans.show', $w->challan_id),
                'mtr' => round($mtr, 3), 'kg' => $this->kg($mtr, $w->gsm_kg),
                'rate' => round((float) $w->weaving_per_meter, 2), 'amount' => round($mtr * (float) $w->weaving_per_meter, 2),
                'reason' => $w->decision_remarks ?: 'Rejected at inspection',
            ];
        }
        usort($rows, fn($a, $b) => strcmp($a['date'], $b['date']));

        return ['columns' => array_merge(self::head(true), self::mtrCols(), [self::col('reason', 'Reason')]), 'rows' => $rows,
            'notes' => ['Weaving rejections are taken from the inspection decision; where the whole consignment was rejected there is no GRN, so the challan # is shown.',
                        'For Weaving rejections, Rate P/Mtr is the conversion rate (no yarn cost was booked for rejected meters).']];
    }

    // 6. Daily Greige Return
    private function returns(array $f): array
    {
        $rows = $this->returnLines($f, 'greige')->map(fn($r) => [
            'date' => $r->return_date, 'po_no' => $r->order_no, 'po_no_link' => route('purchase_orders.show', $r->po_id),
            'po_type' => $this->typeLabel($r->type), 'vendor' => $r->vendor, 'quality' => $r->product,
            'grn' => $r->receiving_no, 'grn_link' => route('purchase_receivings.show', $r->receiving_id),
            'return_no' => $r->return_no,
            'mtr' => (float) $r->quantity_returned, 'kg' => $this->kg((float) $r->quantity_returned, $r->gsm_kg),
            'rate' => (float) $r->rate, 'amount' => round((float) $r->quantity_returned * (float) $r->rate, 2),
        ])->all();

        return ['columns' => array_merge(self::head(true), [self::col('return_no', 'Return #')], self::mtrCols()), 'rows' => $rows,
            'notes' => ['Rejected weaving fabric goes back to the mill at the gate, so it does not appear here.']];
    }

    // 7 / 8. Greige stock (fresh or rejected) on a date, by P.O
    private function stock(array $f, string $status): array
    {
        $where = $f['location_scope'] ?? 'own';
        $lines = DB::table('location_stock_ledger as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('locations as loc', 'loc.id', '=', 'l.location_id')
            ->whereIn('p.category_id', $this->categoryIds('greige'))
            ->where('l.status', $status)->where('l.entry_date', '<=', $f['asof'])
            ->when($f['location_id'], fn($q, $v) => $q->where('l.location_id', $v))
            ->when(!$f['location_id'] && $where === 'own', fn($q) => $q->whereNull('loc.vendor_id'))
            ->when(!$f['location_id'] && $where === 'mills', fn($q) => $q->whereNotNull('loc.vendor_id'))
            ->when($f['product_id'], fn($q, $v) => $q->where('l.product_id', $v))
            ->groupBy('l.lot_no', 'l.product_id', 'p.name', 'l.location_id', 'loc.name')
            ->selectRaw('l.lot_no, l.product_id, l.location_id, p.name as product, loc.name as location,
                         SUM(l.quantity) as qty, SUM(l.amount) as amount, MAX(l.entry_date) as last_date, MIN(l.entry_date) as first_date')
            ->get();
        $lines = $this->netUnlotted($lines, fn($l) => $l->product_id . '|' . $l->location_id);

        $pos = $this->poByNumber($lines->pluck('lot_no')->all());
        // lots issued to processing mills are numbered by the issuance; find their PO
        $issued = DB::table('issuances as s')->join('purchase_orders as po', 'po.id', '=', 's.purchase_order_id')
            ->leftJoin('vendors as v', 'v.id', '=', 'po.vendor_id')
            ->whereIn('s.issue_no', $lines->pluck('lot_no')->filter()->all() ?: ['-'])
            ->get(['s.issue_no', 'po.id', 'po.order_no', 'po.type', 'po.gsm_kg', 'v.name as vendor'])->keyBy('issue_no');

        $rows = [];
        foreach ($lines as $r) {
            $po = $pos[$r->lot_no] ?? $issued[$r->lot_no] ?? null;
            if ($f['vendor_id'] && (!$po || !$this->vendorMatches($po, $f['vendor_id']))) continue;
            $qty = (float) $r->qty;
            $rate = $qty > 0 ? (float) $r->amount / $qty : 0;
            $rows[] = [
                'date' => $r->last_date,
                'po_no' => $po->order_no ?? ($r->lot_no ?: '—'), 'po_no_link' => $po ? route('purchase_orders.show', $po->id) : null,
                'po_type' => $this->typeLabel($po->type ?? null), 'vendor' => $po->vendor ?? '—', 'quality' => $r->product,
                'location' => $r->location . (isset($issued[$r->lot_no]) ? " (lot {$r->lot_no})" : ''),
                'mtr' => round($qty, 3), 'kg' => $this->kg($qty, $po->gsm_kg ?? null),
                'rate' => round($rate, 2), 'amount' => round((float) $r->amount, 2),
            ];
        }

        $notes = [ucfirst($status) . ' greige on ' . date('d-M-Y', strtotime($f['asof'])) . '.'];
        $notes[] = $status === 'fresh'
            ? 'Use "Where" to include greige sitting at processing mills (issued against a Processing PO).'
            : 'Rejected stock drops off this list when it is returned to the supplier (Purchase Return).';

        return ['columns' => array_merge([
            self::col('date', 'Last Movement', 'date'), self::col('po_no', 'P.O #', 'link'), self::col('po_type', 'P.O Type'),
            self::col('vendor', 'Supplier/Vendor'), self::col('quality', 'Fabric Quality'), self::col('location', 'Location'),
            self::col('mtr', 'Mtr', 'qty', true), self::col('kg', 'Kg', 'weight', true), self::col('rate', 'Rate P/Mtr', 'rate'),
            self::col('amount', 'Value', 'money', true)]), 'rows' => $rows, 'notes' => $notes];
    }
}
