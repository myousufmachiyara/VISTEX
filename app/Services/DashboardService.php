<?php

namespace App\Services;

use App\Models\{Challan, User};
use Illuminate\Support\Facades\{DB, Log};

/** Numbers for the home dashboard. Each part is only built for users who may see it. */
class DashboardService
{
    public function build(User $u): array
    {
        $money = $u->canAny(['reports.processing', 'customer_invoices.index']);
        return [
            'money'      => $money ? $this->safe(fn() => $this->money(), null) : null,
            'queues'     => $this->safe(fn() => $this->queues($u), []),
            'production' => $u->canAny(['production_orders.index', 'purchase_orders.index']) ? $this->safe(fn() => $this->production(), null) : null,
            'orders'     => $u->can('jobs.index') ? $this->safe(fn() => $this->orders(), []) : null,
            'trend'      => $money ? $this->safe(fn() => $this->trend(), null) : null,
            'stock'      => $money ? $this->safe(fn() => $this->stockValue(), null) : null,
        ];
    }

    /** A panel whose tables are missing (migrations not run yet) is left out instead of breaking the home page. */
    private function safe(callable $fn, $fallback)
    {
        try {
            return $fn();
        } catch (\Illuminate\Database\QueryException $e) {
            Log::warning('Dashboard panel skipped: ' . $e->getMessage());
            return $fallback;
        }
    }

    private function money(): array
    {
        $start = now()->startOfMonth()->toDateString();
        $open = DB::table('customer_invoices')->whereIn('status', ['Unpaid', 'PartiallyPaid'])->get(['total', 'paid_amount', 'exchange_rate', 'due_date']);
        $bal = fn($i) => ((float) $i->total - (float) $i->paid_amount) * (float) $i->exchange_rate;
        $overdue = $open->filter(fn($i) => $i->due_date && $i->due_date < now()->toDateString());

        $ap = DB::table('account_mappings')->where('role_key', 'accounts_payable')->value('account_id');
        $payable = (float) DB::table('voucher_entries as e')->join('vouchers as v', 'v.id', '=', 'e.voucher_id')->whereNull('v.deleted_at')
            ->where('e.account_id', $ap)->selectRaw('SUM(e.credit) - SUM(e.debit) AS b')->value('b');

        $pdcs = DB::table('pdcs')->where('party_type', 'vendor')->where('due_date', '<=', now()->addDays(7)->toDateString())->get(['id', 'amount']);
        $cleared = DB::table('pdc_cheques')->whereIn('pdc_id', $pdcs->pluck('id')->all() ?: [0])->where('status', 'Cleared')->sum('amount');

        return [
            'sales_month'   => round((float) DB::table('customer_invoices')->where('status', '!=', 'Cancelled')->where('invoice_date', '>=', $start)->sum('total_pkr'), 2),
            'received_month'=> round((float) DB::table('customer_receipts')->where('receipt_date', '>=', $start)->where('status', '!=', 'Bounced')->sum('amount_pkr'), 2),
            'receivable'    => round($open->sum($bal), 2),
            'overdue'       => round($overdue->sum($bal), 2),
            'overdue_count' => $overdue->count(),
            'payable'       => round($payable, 2),
            'pdc_week'      => round(max(0, (float) $pdcs->sum('amount') - (float) $cleared), 2),
        ];
    }

    /** "Waiting on you": counts with a link, only for what the user can act on. */
    private function queues(User $u): array
    {
        $super = $u->hasRole('superadmin');
        $q = [];
        $add = function (bool $show, string $label, $n, string $url, string $tone = 'warning') use (&$q) {
            if (!$show) return;
            if ($n instanceof \Closure) $n = $this->safe($n, 0);
            if ($n > 0) $q[] = compact('label', 'n', 'url', 'tone');
        };
        $add($u->can('purchase_orders.edit'), 'Purchase orders to approve', fn() => DB::table('purchase_orders')->where('status', 'Pending')->whereNull('deleted_at')->count(), route('purchase_orders.index'));
        $add($u->can('jobs.edit'), 'Customer orders to approve', fn() => DB::table('job_orders')->where('status', 'Pending')->whereNull('deleted_at')->count(), route('jobs.index'));
        $add($u->can('sale_orders.edit'), 'Sale orders to approve', fn() => DB::table('sale_orders')->where('status', 'Pending')->whereNull('deleted_at')->count(), route('sale_orders.index', ['status' => 'Pending']));
        $add($u->can('challans.index'), 'Challans to inspect', fn() => Challan::awaitingInspection()->forCategoryIncharge($u)->count(), route('challans.pending'), 'danger');
        $add($u->can('qc_inspections.index'), 'Processed fabric waiting for QC', fn() => DB::table('purchase_receivings')->where('status', 'Approved')->whereIn('qc_status', ['Pending', 'InProgress'])->count(), route('qc_inspections.index'));

        $mine = fn($col) => DB::table('stock_movements as m')->join('locations as l', 'l.id', '=', "m.{$col}")->whereNull('m.deleted_at')
            ->when(!$super, fn($x) => $x->where('l.in_charge_user_id', $u->id));
        $add($u->can('stock_movements.index'), 'Gate passes to approve', fn() => $mine('from_location_id')->where('m.status', 'Pending')->count(), route('stock_movements.index', ['status' => 'Pending']));
        $add($u->can('stock_movements.gate'), 'Waiting at the gate', fn() => DB::table('stock_movements')->where('status', 'Approved')->whereNull('deleted_at')->count(), route('stock_movements.gate'), 'info');
        $add($u->can('stock_movements.index'), 'Stock in transit to receive', fn() => $mine('to_location_id')->where('m.status', 'Dispatched')->count(), route('stock_movements.index', ['status' => 'Dispatched']), 'info');

        $hold = (float) $this->safe(fn() => DB::table('location_stock_ledger')->where('status', 'reprocess')->sum('quantity'), 0);
        if ($u->can('purchase_orders.create') && $hold > 0.0005) $q[] = ['label' => 'Meters on hold for reprocess', 'n' => round($hold, 1), 'url' => route('reprocess.index'), 'tone' => 'secondary'];
        $add($u->can('customer_invoices.create'), 'Deliveries not invoiced', fn() => DB::table('stock_movement_items as i')->join('stock_movements as m', 'm.id', '=', 'i.stock_movement_id')
            ->where('m.movement_type', 'warehouse_to_customer')->where('m.status', 'Received')->whereColumn('i.invoiced_qty', '<', 'i.quantity')
            ->distinct()->count('m.id'), route('customer_invoices.create'), 'primary');
        $add($u->can('customer_receipts.edit'), 'Customer cheques to deposit (due)', fn() => DB::table('customer_receipts')->where('status', 'Pending')->where('cheque_date', '<=', now()->toDateString())->count(), route('customer_receipts.index'), 'warning');
        $add($u->can('customer_invoices.index'), 'Overdue invoices', fn() => DB::table('customer_invoices')->whereIn('status', ['Unpaid', 'PartiallyPaid'])->where('due_date', '<', now()->toDateString())->count(), route('customer_invoices.index'), 'danger');
        return $q;
    }

    private function production(): array
    {
        $start = now()->startOfMonth()->toDateString();
        $received = (float) DB::table('purchase_receiving_items as i')->join('purchase_receivings as r', 'r.id', '=', 'i.purchase_receiving_id')
            ->join('purchase_orders as p', 'p.id', '=', 'r.purchase_order_id')->where('p.type', 'processing')->whereNull('p.reprocess_of_po_id')
            ->where('r.status', 'Approved')->where('r.receiving_date', '>=', $start)
            ->selectRaw('SUM(COALESCE(i.qty_a,0)+COALESCE(i.qty_b,0)+COALESCE(i.qty_c,0)) AS m')->value('m');
        $line = fn($type) => DB::table('production_order_lines as l')->join('production_orders as o', 'o.id', '=', 'l.production_order_id')
            ->where('o.type', $type)->where('o.order_date', '>=', $start);
        $dispatched = (float) DB::table('stock_movement_items as i')->join('stock_movements as m', 'm.id', '=', 'i.stock_movement_id')
            ->whereNotNull('m.job_id')->where('m.status', 'Received')->where('m.received_at', '>=', $start)->sum('i.quantity');
        $atMills = DB::table('location_stock_ledger as l')->join('products as p', 'p.id', '=', 'l.product_id')->join('product_categories as c', 'c.id', '=', 'p.category_id')
            ->where('l.status', 'in_process')->groupBy('c.code')->selectRaw('c.code, SUM(l.quantity) AS q')->pluck('q', 'code');
        return [
            'received_m' => round($received, 1),
            'cut_m'      => round((float) (clone $line('cutting'))->sum('l.input_qty'), 1),
            'packed_pcs' => round((float) $line('packing')->sum('l.output_qty')),
            'dispatched_pcs' => round($dispatched),
            'greige_at_mills' => round((float) ($atMills['greige'] ?? 0), 1),
            'reprocess_at_mills' => round((float) ($atMills['processed'] ?? 0), 1),
        ];
    }

    /** Approved customer orders not fully dispatched, soonest due first. */
    private function orders(): array
    {
        $jobs = DB::table('job_orders as j')->leftJoin('customers as c', 'c.id', '=', 'j.customer_id')->where('j.status', 'Approved')->whereNull('j.deleted_at')
            ->orderByRaw('j.expected_date IS NULL, j.expected_date')->limit(30)->get(['j.id', 'j.job_no', 'j.expected_date', 'c.name as customer']);
        $out = [];
        foreach ($jobs as $j) {
            $items = DB::table('job_order_items')->where('job_id', $j->id)->get(['id', 'quantity', 'quantity_fulfilled']);
            $ordered = (float) $items->sum('quantity'); $sent = (float) $items->sum('quantity_fulfilled');
            if ($ordered <= 0 || $sent + 0.001 >= $ordered) continue;
            $packedPcs = (float) DB::table('production_order_lines as l')->join('production_orders as o', 'o.id', '=', 'l.production_order_id')
                ->where('o.type', 'packing')->whereIn('l.job_item_id', $items->pluck('id'))->sum('l.output_qty');
            $out[] = ['id' => $j->id, 'job_no' => $j->job_no, 'customer' => $j->customer, 'due' => $j->expected_date,
                'ordered_m' => round($ordered), 'sent_m' => round($sent), 'pct' => round($sent / $ordered * 100), 'packed_pcs' => (int) $packedPcs,
                'late' => $j->expected_date && $j->expected_date < now()->toDateString()];
            if (count($out) >= 10) break;
        }
        return $out;
    }

    /** Last 6 months: invoiced (PKR) and cost of goods sold. */
    private function trend(): array
    {
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonthsNoOverflow($i);
            $from = $m->toDateString(); $to = $m->copy()->endOfMonth()->toDateString();
            $out[] = [
                'label' => $m->format('M Y'),
                'sales' => round((float) DB::table('customer_invoices')->where('status', '!=', 'Cancelled')->whereBetween('invoice_date', [$from, $to])->sum('total_pkr'), 2),
                'cogs'  => round((float) DB::table('stock_movements')->where('movement_type', 'warehouse_to_customer')->where('status', 'Received')
                    ->whereBetween(DB::raw('DATE(received_at)'), [$from, $to])->sum('cogs_amount'), 2),
            ];
        }
        return $out;
    }

    /** Stock value by category: our warehouses, at mills, in transit. */
    private function stockValue(): array
    {
        return DB::table('location_stock_ledger as l')->join('products as p', 'p.id', '=', 'l.product_id')->join('product_categories as c', 'c.id', '=', 'p.category_id')
            ->join('locations as loc', 'loc.id', '=', 'l.location_id')
            ->groupBy('c.name')->selectRaw("c.name,
                SUM(CASE WHEN loc.vendor_id IS NULL AND l.status IN ('fresh','reprocess') THEN l.amount ELSE 0 END) AS own,
                SUM(CASE WHEN loc.vendor_id IS NOT NULL AND l.status IN ('fresh','in_process') THEN l.amount ELSE 0 END) AS mills,
                SUM(CASE WHEN l.status = 'in_transit' THEN l.amount ELSE 0 END) AS transit")
            ->get()->map(fn($r) => ['category' => $r->name, 'own' => round((float) $r->own, 2), 'mills' => round((float) $r->mills, 2), 'transit' => round((float) $r->transit, 2)])
            ->filter(fn($r) => abs($r['own']) + abs($r['mills']) + abs($r['transit']) > 0.5)->values()->all();
    }
}
