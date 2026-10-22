<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Processing payables, lot costing, customer order margin and receivables aging.
 *  - Mill payables: what each Processing / Reprocess PO billed, what QC charged back, what the PDC still owes
 *  - Lot costing: greige + processing + reprocessing − claims → cost per meter; where the lot is now; what it sold for
 */
class ProcessingReports extends BaseReports
{
    public static function group(): string { return 'processing'; }
    public static function title(): string { return 'Processing & Costing Reports'; }

    public static function catalog(): array
    {
        return [
            'mill_stock_summary' => ['title' => 'Stock at Mills — Summary',      'filter' => 'asof',  'desc' => 'Total stock at each vendor mill on a date: waiting, issued for processing, reprocess, rejected, on the way, yarn.'],
            'mill_stock'         => ['title' => 'Stock at Mills — Details',      'filter' => 'asof',  'desc' => 'Every item and lot at vendor mills on a date, with its stage, PO and value.'],
            'mill_payables'      => ['title' => 'Mill Payables (Processing)',    'filter' => 'range', 'desc' => 'Per Processing / Reprocess PO: charges billed, QC claims, PDC and cheques cleared.'],
            'vendor_balances'    => ['title' => 'Vendor Balances',               'filter' => 'asof',  'desc' => 'What we owe each vendor / mill, and PDC still to be paid.'],
            'lot_costing'        => ['title' => 'Lot Costing (greige → SKU)',    'filter' => 'range', 'desc' => 'Cost per meter of each processing lot, where it is now, and what it sold for.'],
            'order_margin'       => ['title' => 'Customer Order Margin',         'filter' => 'range', 'desc' => 'Per customer order: dispatched, invoiced, cost of goods and margin.'],
            'receivables_aging'  => ['title' => 'Receivables Aging',             'filter' => 'asof',  'desc' => 'Open customer invoices by days past due (PKR at invoice rate).'],
        ];
    }

    public function run(string $key, array $f): array
    {
        return match ($key) {
            'mill_stock_summary' => $this->millStock($f, true),
            'mill_stock' => $this->millStock($f, false),
            'mill_payables' => $this->millPayables($f),
            'vendor_balances' => $this->vendorBalances($f),
            'lot_costing' => $this->lotCosting($f),
            'order_margin' => $this->orderMargin($f),
            'receivables_aging' => $this->receivablesAging($f),
        };
    }

    // ── Stock at vendor mills ───────────────────────────────────────

    public const MILL_STAGES = [
        'fresh' => 'At mill — not yet issued', 'in_process' => 'Issued for processing', 'reprocess' => 'Reprocess',
        'rejected' => 'Rejected', 'in_transit' => 'On the way to the mill', 'yarn' => 'Yarn at weaving mill',
    ];

    /** Everything sitting at vendor (mill) locations on a date, plus yarn issued to weaving mills and not yet woven. */
    private function millStock(array $f, bool $summary): array
    {
        $asof = $f['asof'];
        $lines = DB::table('location_stock_ledger as l')
            ->join('locations as loc', 'loc.id', '=', 'l.location_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->leftJoin('product_categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('measurement_units as u', 'u.id', '=', 'p.measurement_unit')
            ->leftJoin('vendors as v', 'v.id', '=', 'loc.vendor_id')
            ->whereNotNull('loc.vendor_id')->where('l.entry_date', '<=', $asof)
            ->whereIn('l.status', ['fresh', 'in_process', 'reprocess', 'rejected', 'in_transit'])
            ->when($f['vendor_id'], fn($q, $v) => $q->where('loc.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('l.product_id', $v))
            ->groupBy('loc.vendor_id', 'v.name', 'l.location_id', 'loc.name', 'l.status', 'l.product_id', 'p.name', 'c.name', 'u.shortcode', 'l.lot_no')
            ->havingRaw('ABS(SUM(l.quantity)) > 0.0005')
            ->selectRaw('loc.vendor_id, v.name as mill, l.location_id, loc.name as location, l.status, l.product_id, p.name as item, c.name as category,
                         u.shortcode as unit, l.lot_no, SUM(l.quantity) as qty, SUM(l.amount) as amount, MAX(l.entry_date) as last_date')
            ->get();

        // Processing PO behind each lot: issuances for "issued", the stock movement that took it to the mill otherwise
        $lots = $lines->pluck('lot_no')->filter()->unique()->values()->all() ?: ['-'];
        $byIssue = DB::table('location_stock_ledger as l')->join('issuances as s', fn($j) => $j->on('s.id', '=', 'l.reference_id')->where('l.reference_type', 'Issuance'))
            ->join('purchase_orders as po', 'po.id', '=', 's.purchase_order_id')->whereIn('l.lot_no', $lots)->where('l.quantity', '>', 0)
            ->get(['l.location_id', 'l.product_id', 'l.lot_no', 'po.id', 'po.order_no'])
            ->groupBy(fn($r) => "{$r->location_id}|{$r->product_id}|{$r->lot_no}");
        $byMove = DB::table('stock_movements as m')->join('purchase_orders as po', 'po.id', '=', 'm.purchase_order_id')->whereNull('m.deleted_at')
            ->whereIn('m.lot_no', $lots)->get(['m.lot_no', 'po.id', 'po.order_no'])->keyBy('lot_no');

        $rows = [];
        foreach ($lines as $l) {
            $po = ($byIssue["{$l->location_id}|{$l->product_id}|{$l->lot_no}"] ?? collect())->unique('id');
            $poNo = $po->pluck('order_no')->implode(', ') ?: ($byMove[$l->lot_no]->order_no ?? '');
            $poId = $po->first()->id ?? ($byMove[$l->lot_no]->id ?? null);
            $rows[] = ['mill' => $l->mill ?: '—', 'vendor_id' => $l->vendor_id, 'location' => $l->location, 'stage' => self::MILL_STAGES[$l->status], 'stage_key' => $l->status,
                'category' => $l->category, 'item' => $l->item, 'lot' => $l->lot_no ?: '—', 'po_no' => $poNo ?: '—',
                'po_no_link' => $poId ? route('purchase_orders.show', $poId) : null,
                'qty' => round((float) $l->qty, 3), 'unit' => $l->unit, 'amount' => round((float) $l->amount, 2), 'date' => $l->last_date];
        }

        // Yarn issued to weaving mills and not yet consumed by greige receipts
        $yarn = DB::table('yarn_in_process_ledger as y')
            ->join('products as p', 'p.id', '=', 'y.product_id')
            ->leftJoin('measurement_units as u', 'u.id', '=', 'p.measurement_unit')
            ->leftJoin('vendors as v', 'v.id', '=', 'y.vendor_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'y.purchase_order_id')
            ->where('y.entry_date', '<=', $asof)
            ->when($f['vendor_id'], fn($q, $v) => $q->where('y.vendor_id', $v))
            ->when($f['product_id'], fn($q, $v) => $q->where('y.product_id', $v))
            ->groupBy('y.vendor_id', 'v.name', 'y.purchase_order_id', 'po.order_no', 'y.product_id', 'p.name', 'u.shortcode')
            ->havingRaw('SUM(y.quantity) > 0.0005')
            ->selectRaw('y.vendor_id, v.name as mill, y.purchase_order_id, po.order_no, p.name as item, u.shortcode as unit,
                         SUM(y.quantity) as qty, SUM(y.amount) as amount, MAX(y.entry_date) as last_date')
            ->get();
        foreach ($yarn as $y) {
            $rows[] = ['mill' => $y->mill ?: '—', 'vendor_id' => $y->vendor_id, 'location' => 'Weaving mill', 'stage' => self::MILL_STAGES['yarn'], 'stage_key' => 'yarn',
                'category' => 'Yarn', 'item' => $y->item, 'lot' => '—', 'po_no' => $y->order_no ?: '—',
                'po_no_link' => $y->purchase_order_id ? route('purchase_orders.show', $y->purchase_order_id) : null,
                'qty' => round((float) $y->qty, 3), 'unit' => $y->unit, 'amount' => round((float) $y->amount, 2), 'date' => $y->last_date];
        }

        $notes = ['Stock at vendor (mill) locations on ' . date('d-M-Y', strtotime($asof)) . ', plus yarn issued to weaving mills and not yet woven into greige.',
                  'Quantities are in each item\'s own unit (greige / fabric in meters, yarn in its purchase unit).'];

        if (!$summary) {
            usort($rows, fn($a, $b) => [$a['mill'], array_search($a['stage_key'], array_keys(self::MILL_STAGES)), $a['item'], $a['lot']]
                                      <=> [$b['mill'], array_search($b['stage_key'], array_keys(self::MILL_STAGES)), $b['item'], $b['lot']]);
            return ['columns' => [
                self::col('mill', 'Mill / Vendor'), self::col('location', 'Location'), self::col('stage', 'Stage'), self::col('category', 'Category'),
                self::col('item', 'Item'), self::col('lot', 'Lot #'), self::col('po_no', 'P.O #', 'link'), self::col('qty', 'Quantity', 'qty', true),
                self::col('unit', 'Unit'), self::col('amount', 'Value', 'money', true), self::col('date', 'Last Movement', 'date')],
                'rows' => $rows, 'notes' => $notes];
        }

        // Summary: one row per mill, a quantity column per stage, total value
        $out = [];
        foreach (collect($rows)->groupBy('mill') as $mill => $g) {
            $r = ['mill' => $mill, 'mill_link' => route('reports.show', ['processing', 'mill_stock', 'asof' => $asof, 'vendor_id' => $g->first()['vendor_id']])];
            foreach (array_keys(self::MILL_STAGES) as $k) {
                $v = $g->where('stage_key', $k)->sum('qty');
                $r[$k] = abs($v) > 0.0005 ? round($v, 3) : null;
            }
            $r['lots'] = $g->where('stage_key', '!=', 'yarn')->pluck('lot')->filter(fn($x) => $x !== '—')->unique()->count() ?: null;
            $r['amount'] = round($g->sum('amount'), 2);
            $out[] = $r;
        }
        usort($out, fn($a, $b) => $b['amount'] <=> $a['amount']);
        $notes[] = 'Click a mill to see its items and lots.';
        return ['columns' => [
            self::col('mill', 'Mill / Vendor', 'link'),
            self::col('fresh', 'At mill, not issued', 'qty', true), self::col('in_process', 'Issued for processing', 'qty', true),
            self::col('reprocess', 'Reprocess', 'qty', true), self::col('rejected', 'Rejected', 'qty', true),
            self::col('in_transit', 'On the way', 'qty', true), self::col('yarn', 'Yarn at weaving mill', 'qty', true),
            self::col('lots', 'Lots', 'qty', true), self::col('amount', 'Total Value', 'money', true)],
            'rows' => $out, 'notes' => $notes];
    }

    // ── Mill payables ───────────────────────────────────────────────

    private function millPayables(array $f): array
    {
        $grns = DB::table('purchase_receivings as r')->join('purchase_orders as p', 'p.id', '=', 'r.purchase_order_id')
            ->leftJoin('vendors as v', 'v.id', '=', 'p.vendor_id')
            ->where('p.type', 'processing')->where('r.status', 'Approved')->whereNull('r.deleted_at')
            ->whereBetween('r.receiving_date', [$f['from'], $f['to']])
            ->when($f['vendor_id'], fn($q, $id) => $q->where('p.vendor_id', $id))
            ->get(['r.id', 'r.purchase_order_id', 'r.processing_charge_amount', 'p.order_no', 'p.reprocess_lot_no', 'p.reprocess_of_po_id', 'p.is_foc',
                   'p.gst_applicable', 'p.gst_rate', 'v.name as mill']);
        $ids = $grns->pluck('id')->all() ?: [0];

        $meters = DB::table('purchase_receiving_items')->whereIn('purchase_receiving_id', $ids)
            ->groupBy('purchase_receiving_id')->selectRaw('purchase_receiving_id, SUM(COALESCE(qty_a,0)+COALESCE(qty_b,0)+COALESCE(qty_c,0)) AS m')->pluck('m', 'purchase_receiving_id');
        $claims = DB::table('qc_inspections')->whereIn('purchase_receiving_id', $ids)->where('status', 'Closed')
            ->selectRaw('purchase_receiving_id, claim_amount + shortage_amount AS c')->pluck('c', 'purchase_receiving_id');
        $pdcs = DB::table('pdcs')->where('reference_type', 'PurchaseReceiving')->whereIn('reference_id', $ids)->get(['id', 'reference_id', 'amount'])->keyBy('reference_id');
        $cleared = DB::table('pdc_cheques')->whereIn('pdc_id', $pdcs->pluck('id')->all() ?: [0])->where('status', 'Cleared')
            ->groupBy('pdc_id')->selectRaw('pdc_id, SUM(amount) AS a')->pluck('a', 'pdc_id');

        $rows = $grns->groupBy('purchase_order_id')->map(function ($list) use ($meters, $claims, $pdcs, $cleared) {
            $p = $list->first();
            $charge = $list->sum('processing_charge_amount');
            $gst = $p->gst_applicable ? round($charge * $p->gst_rate / 100, 2) : 0;
            $claim = $list->sum(fn($g) => (float) ($claims[$g->id] ?? 0));
            $pdc = $list->sum(fn($g) => (float) ($pdcs[$g->id]->amount ?? 0));
            $paid = $list->sum(fn($g) => isset($pdcs[$g->id]) ? (float) ($cleared[$pdcs[$g->id]->id] ?? 0) : 0);
            return [
                'po' => $p->order_no, 'kind' => $p->reprocess_of_po_id ? ($p->is_foc ? 'Reprocess (FOC)' : 'Reprocess') : 'Processing',
                'lot' => $p->reprocess_lot_no ?: $p->order_no, 'mill' => $p->mill, 'grns' => $list->count(),
                'meters' => round($list->sum(fn($g) => (float) ($meters[$g->id] ?? 0)), 3),
                'charge' => round($charge, 2), 'gst' => $gst, 'billed' => round($charge + $gst, 2), 'claims' => round($claim, 2),
                'net' => round($charge + $gst - $claim, 2), 'pdc' => round($pdc, 2), 'paid' => round($paid, 2), 'pending' => round($pdc - $paid, 2),
            ];
        })->sortBy('po')->values()->all();

        return ['columns' => [
            self::col('po', 'PO #'), self::col('kind', 'Kind'), self::col('lot', 'Lot #'), self::col('mill', 'Mill'),
            self::col('grns', 'GRNs', 'qty', true), self::col('meters', 'Meters', 'qty', true),
            self::col('charge', 'Charge', 'money', true), self::col('gst', 'GST', 'money', true), self::col('billed', 'Billed', 'money', true),
            self::col('claims', 'QC claims', 'money', true), self::col('net', 'Net payable', 'money', true),
            self::col('pdc', 'PDC', 'money', true), self::col('paid', 'Cleared', 'money', true), self::col('pending', 'Still to pay', 'money', true),
        ], 'rows' => $rows, 'notes' => [
            'GRNs approved in the period. QC claims = quality claim + shortage charged back on closed QC.',
            'The PDC is already lowered by claims where cheques had not been made out yet.',
        ]];
    }

    // ── Vendor balances ─────────────────────────────────────────────

    private function vendorBalances(array $f): array
    {
        $ap = DB::table('account_mappings')->where('role_key', 'accounts_payable')->value('account_id');
        $rows = DB::table('voucher_entries as e')->join('vouchers as v', 'v.id', '=', 'e.voucher_id')->join('vendors as ven', 'ven.id', '=', 'e.party_id')
            ->where('e.party_type', 'vendor')->where('e.account_id', $ap)->where('v.voucher_date', '<=', $f['asof'])->whereNull('v.deleted_at')
            ->when($f['vendor_id'], fn($q, $id) => $q->where('e.party_id', $id))
            ->groupBy('e.party_id', 'ven.name')->selectRaw('e.party_id, ven.name, SUM(e.credit) AS billed, SUM(e.debit) AS debits')->get();
        $pdcs = DB::table('pdcs')->where('party_type', 'vendor')->get(['id', 'party_id', 'amount']);
        $alloc = DB::table('pdc_cheques')->groupBy('pdc_id')->selectRaw("pdc_id, SUM(amount) AS a, SUM(CASE WHEN status = 'Cleared' THEN amount ELSE 0 END) AS c")->get()->keyBy('pdc_id');

        $out = $rows->map(function ($r) use ($pdcs, $alloc) {
            $mine = $pdcs->where('party_id', $r->party_id);
            $notCleared = $mine->sum(fn($p) => (float) $p->amount - (float) ($alloc[$p->id]->c ?? 0));
            $noCheque = $mine->sum(fn($p) => max(0, (float) $p->amount - (float) ($alloc[$p->id]->a ?? 0)));
            return ['vendor' => $r->name, 'billed' => round((float) $r->billed, 2), 'debits' => round((float) $r->debits, 2),
                    'balance' => round((float) $r->billed - (float) $r->debits, 2), 'pdc_open' => round($notCleared, 2), 'no_cheque' => round($noCheque, 2)];
        })->sortByDesc('balance')->values()->all();

        return ['columns' => [
            self::col('vendor', 'Vendor / Mill'), self::col('billed', 'Billed (Cr)', 'money', true), self::col('debits', 'Paid / claims (Dr)', 'money', true),
            self::col('balance', 'We owe', 'money', true), self::col('pdc_open', 'PDC not cleared', 'money', true), self::col('no_cheque', 'PDC without cheque', 'money', true),
        ], 'rows' => $out, 'notes' => ['From Accounts Payable postings up to the date. PDC columns are as of today.']];
    }

    // ── Lot costing ─────────────────────────────────────────────────

    private function lotCosting(array $f): array
    {
        $pos = DB::table('purchase_orders as p')->leftJoin('vendors as v', 'v.id', '=', 'p.vendor_id')
            ->where('p.type', 'processing')->whereNull('p.reprocess_of_po_id')->whereNull('p.deleted_at')
            ->whereExists(fn($q) => $q->from('purchase_receivings as r')->whereColumn('r.purchase_order_id', 'p.id')->where('r.status', 'Approved')
                ->whereBetween('r.receiving_date', [$f['from'], $f['to']]))
            ->when($f['vendor_id'], fn($q, $id) => $q->where('p.vendor_id', $id))
            ->get(['p.id', 'p.order_no', 'v.name as mill']);

        $rows = [];
        foreach ($pos as $po) {
            $lot = $po->order_no;
            $poIds = DB::table('purchase_orders')->where('id', $po->id)->orWhere('reprocess_of_po_id', $po->id)->pluck('id');
            $grns = DB::table('purchase_receivings')->whereIn('purchase_order_id', $poIds)->where('status', 'Approved')->whereNull('deleted_at')
                ->get(['id', 'purchase_order_id', 'greige_cost_amount', 'processing_charge_amount', 'greige_consumed_meta']);
            $orig = $grns->where('purchase_order_id', $po->id);
            $greigeM = $orig->sum(fn($g) => collect(json_decode((string) $g->greige_consumed_meta, true) ?: [])->sum('quantity'));
            $received = (float) DB::table('purchase_receiving_items')->whereIn('purchase_receiving_id', $orig->pluck('id')->all() ?: [0])
                ->selectRaw('SUM(COALESCE(qty_a,0)+COALESCE(qty_b,0)+COALESCE(qty_c,0)) AS m')->value('m');
            $claims = (float) DB::table('qc_inspections')->whereIn('purchase_receiving_id', $grns->pluck('id')->all() ?: [0])->where('status', 'Closed')
                ->selectRaw('SUM(claim_amount + shortage_amount) AS c')->value('c');
            $greige = $orig->sum('greige_cost_amount');
            $processing = $orig->sum('processing_charge_amount');
            $reprocess = $grns->where('purchase_order_id', '!=', $po->id)->sum('processing_charge_amount');
            $packaging = (float) DB::table('production_order_lines')->where('lot_no', $lot)->sum('packaging_amount');
            $net = $greige + $processing + $reprocess - $claims;

            // Where the lot's value sits now, and what has been sold
            $stock = (float) DB::table('location_stock_ledger')->where('lot_no', $lot)->whereIn('status', ['fresh', 'reprocess', 'in_process', 'in_transit'])
                ->whereIn('product_id', DB::table('products as pr')->join('product_categories as c', 'c.id', '=', 'pr.category_id')
                    ->whereIn('c.code', ['processed', 'cut_pcs', 'sku', 'leftover', 'rejection'])->select('pr.id'))->sum('amount');
            [$cogs, $revenue] = $this->soldFromLot($lot);

            $rows[] = [
                'lot' => $lot, 'mill' => $po->mill, 'greige_m' => round($greigeM, 3), 'received_m' => round($received, 3),
                'greige' => round($greige, 2), 'processing' => round($processing, 2), 'reprocess' => round($reprocess, 2), 'claims' => round($claims, 2),
                'net' => round($net, 2), 'per_m' => $received > 0 ? round($net / $received, 2) : null, 'packaging' => round($packaging, 2),
                'in_stock' => round($stock, 2), 'cogs' => round($cogs, 2), 'revenue' => round($revenue, 2), 'margin' => round($revenue - $cogs, 2),
            ];
        }

        return ['columns' => [
            self::col('lot', 'Lot (Processing PO)'), self::col('mill', 'Mill'), self::col('greige_m', 'Greige used m', 'qty', true), self::col('received_m', 'Received m', 'qty', true),
            self::col('greige', 'Greige cost', 'money', true), self::col('processing', 'Processing', 'money', true), self::col('reprocess', 'Reprocessing', 'money', true),
            self::col('claims', 'Claims (−)', 'money', true), self::col('net', 'Fabric cost', 'money', true), self::col('per_m', 'Cost / m', 'rate'),
            self::col('packaging', 'Packaging', 'money', true), self::col('in_stock', 'Value in stock', 'money', true),
            self::col('cogs', 'Cost sold', 'money', true), self::col('revenue', 'Invoiced (PKR)', 'money', true), self::col('margin', 'Margin', 'money', true),
        ], 'rows' => $rows, 'notes' => [
            'Lots with a receiving in the period. Reprocessing charges and QC claims of the lot are included.',
            'Value in stock covers processed fabric, cut pcs, packed SKUs, leftover and rejection of the lot, at any location.',
            'Invoiced = invoice lines of deliveries from this lot (PKR at the invoice rate); margin = invoiced − cost of what was sold.',
        ]];
    }

    /** [cost of goods sold, revenue PKR] for deliveries that took stock from a lot. */
    private function soldFromLot(string $lot): array
    {
        $taken = DB::table('location_stock_ledger as l')->join('stock_movements as m', 'm.id', '=', 'l.reference_id')
            ->where('l.reference_type', 'StockMovement')->where('m.movement_type', 'warehouse_to_customer')->where('l.lot_no', $lot)->where('l.quantity', '<', 0)
            ->get(['m.id as movement_id', 'l.product_id', 'l.quantity', 'l.amount']);
        $cogs = -$taken->sum('amount');
        $revenue = 0;
        foreach ($taken->groupBy(fn($t) => $t->movement_id . '|' . $t->product_id) as $parts) {
            $t = $parts->first();
            $item = DB::table('stock_movement_items')->where('stock_movement_id', $t->movement_id)->where('product_id', $t->product_id)->first();
            if (!$item || (float) $item->quantity <= 0) continue;
            $share = -$parts->sum('quantity') / (float) $item->quantity;
            $revenue += $share * (float) DB::table('customer_invoice_items as ii')->join('customer_invoices as i', 'i.id', '=', 'ii.customer_invoice_id')
                ->where('ii.stock_movement_item_id', $item->id)->where('i.status', '!=', 'Cancelled')->sum(DB::raw('ii.amount * i.exchange_rate'));
        }
        return [$cogs, $revenue];
    }

    // ── Customer order margin ───────────────────────────────────────

    private function orderMargin(array $f): array
    {
        $jobs = DB::table('job_orders as j')->leftJoin('customers as c', 'c.id', '=', 'j.customer_id')
            ->where('j.status', 'Approved')->whereBetween('j.order_date', [$f['from'], $f['to']])->whereNull('j.deleted_at')
            ->get(['j.id', 'j.job_no', 'j.currency', 'j.order_date', 'c.name as customer']);
        $rows = $jobs->map(function ($j) {
            $items = DB::table('job_order_items')->where('job_id', $j->id)->get(['id', 'quantity', 'quantity_fulfilled']);
            $ids = $items->pluck('id')->all() ?: [0];
            $invoiced = (float) DB::table('customer_invoice_items as ii')->join('customer_invoices as i', 'i.id', '=', 'ii.customer_invoice_id')
                ->whereIn('ii.job_item_id', $ids)->where('i.status', '!=', 'Cancelled')->sum(DB::raw('ii.amount * i.exchange_rate'));
            $cogs = (float) DB::table('stock_movements')->where('job_id', $j->id)->where('status', 'Received')->sum('cogs_amount');
            $ordered = (float) $items->sum('quantity'); $sent = (float) $items->sum('quantity_fulfilled');
            return ['job' => $j->job_no, 'customer' => $j->customer, 'date' => $j->order_date, 'currency' => $j->currency,
                'ordered' => round($ordered, 3), 'dispatched' => round($sent, 3), 'pct' => $ordered > 0 ? round($sent / $ordered * 100, 1) : null,
                'invoiced' => round($invoiced, 2), 'cogs' => round($cogs, 2), 'margin' => round($invoiced - $cogs, 2),
                'margin_pct' => $invoiced > 0 ? round(($invoiced - $cogs) / $invoiced * 100, 1) : null];
        })->values()->all();

        return ['columns' => [
            self::col('job', 'Order #'), self::col('customer', 'Customer'), self::col('date', 'Date', 'date'), self::col('currency', 'Cur'),
            self::col('ordered', 'Ordered m', 'qty', true), self::col('dispatched', 'Dispatched m', 'qty', true), self::col('pct', '% sent', 'rate'),
            self::col('invoiced', 'Invoiced (PKR)', 'money', true), self::col('cogs', 'Cost of goods', 'money', true),
            self::col('margin', 'Gross margin', 'money', true), self::col('margin_pct', 'Margin %', 'rate'),
        ], 'rows' => $rows, 'notes' => ['Approved customer orders dated in the period. Cost of goods = cost of dispatched SKUs (fabric + processing + packaging).']];
    }

    // ── Receivables aging ───────────────────────────────────────────

    private function receivablesAging(array $f): array
    {
        $asof = $f['asof'];
        $inv = DB::table('customer_invoices as i')->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->whereIn('i.status', ['Unpaid', 'PartiallyPaid'])->where('i.invoice_date', '<=', $asof)
            ->get(['i.customer_id', 'c.name', 'i.due_date', 'i.total', 'i.paid_amount', 'i.exchange_rate']);
        $onAccount = DB::table('customer_receipts')->where('unallocated', '>', 0)->where('receipt_date', '<=', $asof)->where('status', '!=', 'Bounced')
            ->groupBy('customer_id')->selectRaw('customer_id, SUM(unallocated * exchange_rate) AS a')->pluck('a', 'customer_id');
        foreach (DB::table('customer_credit_notes')->where('unapplied', '>', 0)->where('credit_date', '<=', $asof)
            ->groupBy('customer_id')->selectRaw('customer_id, SUM(unapplied * exchange_rate) AS a')->pluck('a', 'customer_id') as $cid => $amt) {
            $onAccount[$cid] = ($onAccount[$cid] ?? 0) + $amt;
        }

        $rows = $inv->groupBy('customer_id')->map(function ($list) use ($asof, $onAccount) {
            $b = ['current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'over' => 0];
            foreach ($list as $i) {
                $bal = ((float) $i->total - (float) $i->paid_amount) * (float) $i->exchange_rate;
                $days = $i->due_date ? (int) floor((strtotime($asof) - strtotime($i->due_date)) / 86400) : 0;
                $k = $days <= 0 ? 'current' : ($days <= 30 ? 'd30' : ($days <= 60 ? 'd60' : ($days <= 90 ? 'd90' : 'over')));
                $b[$k] += $bal;
            }
            $total = array_sum($b); $adv = (float) ($onAccount[$list->first()->customer_id] ?? 0);
            return ['customer' => $list->first()->name, 'invoices' => $list->count()] + array_map(fn($v) => round($v, 2), $b)
                + ['total' => round($total, 2), 'on_account' => round($adv, 2), 'net' => round($total - $adv, 2)];
        })->sortByDesc('total')->values()->all();

        return ['columns' => [
            self::col('customer', 'Customer'), self::col('invoices', 'Invoices', 'qty', true), self::col('current', 'Not due', 'money', true),
            self::col('d30', '1–30 days', 'money', true), self::col('d60', '31–60', 'money', true), self::col('d90', '61–90', 'money', true),
            self::col('over', '90+', 'money', true), self::col('total', 'Outstanding', 'money', true), self::col('on_account', 'On account', 'money', true),
            self::col('net', 'Net due', 'money', true),
        ], 'rows' => $rows, 'notes' => ['Days past the invoice due date. Foreign-currency balances at the invoice rate. Balances use payments and credit notes recorded to date; on account = unapplied receipts + credit notes.']];
    }
}
