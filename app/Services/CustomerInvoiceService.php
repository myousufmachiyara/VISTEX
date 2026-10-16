<?php

namespace App\Services;

use App\Models\{Customer, CustomerInvoice, CustomerInvoiceItem, StockMovement, StockMovementItem};
use Illuminate\Support\Facades\DB;

/**
 * Customer invoices from delivered goods (sale-order deliveries and customer-order dispatches).
 *   Sale order lines bill the delivered quantity at the order rate (PKR) + the order's sales tax.
 *   Customer order lines bill meters (pieces × meters per piece) at the line's unit price, discount and tax,
 *   in the order's currency at the invoice exchange rate.
 * Posting: Dr Accounts Receivable (customer) / Cr Sales Revenue, Cr Sales Tax Payable (PKR).
 */
class CustomerInvoiceService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private VoucherService $vouchers,
        private AccountMappingService $mapping,
    ) {}

    /** Delivered lines of a customer not yet fully invoiced, priced from their order. */
    public function uninvoiced(int $customerId): array
    {
        return StockMovementItem::whereColumn('invoiced_qty', '<', 'quantity')
            ->whereHas('stockMovement', fn($q) => $q->where('movement_type', StockMovement::SALE)->where('status', StockMovement::RECEIVED)->where('customer_id', $customerId))
            ->with('product.measurementUnit', 'saleOrderItem.saleOrder', 'jobItem.job', 'jobItem.tax', 'stockMovement')->get()
            ->map(fn($mi) => $this->price($mi))->filter()->values()->all();
    }

    /** How one delivered line is billed. */
    private function price(StockMovementItem $mi): ?array
    {
        $left = round((float) $mi->quantity - (float) $mi->invoiced_qty, 3);
        $base = ['stock_movement_item_id' => $mi->id, 'delivery_no' => $mi->stockMovement->movement_no,
                 'delivery_date' => $mi->stockMovement->movement_date->format('Y-m-d'), 'product_id' => $mi->product_id,
                 'product' => $mi->product->name ?? '', 'left' => $left];
        if ($soi = $mi->saleOrderItem) {
            $so = $soi->saleOrder;
            return $base + ['order' => $so->so_no, 'currency' => 'PKR', 'exchange_rate' => 1, 'factor' => 1,
                'unit' => $mi->product->measurementUnit->shortcode ?? '', 'rate' => (float) $soi->rate, 'discount_pct' => 0,
                'tax_rate' => (float) $so->gst_rate, 'sale_order_item_id' => $soi->id, 'job_item_id' => null,
                'terms' => [$so->payment_term_type, $so->payment_term_days]];
        }
        if ($ji = $mi->jobItem) {
            $job = $ji->job;
            $mpu = (float) (($mi->product->getAttribute('attributes') ?? [])['meters_per_unit'] ?? 0);
            return $base + ['order' => $job->job_no, 'currency' => $job->currency ?: 'PKR', 'exchange_rate' => (float) ($job->exchange_rate ?: 1),
                'factor' => $mpu ?: 1, 'unit' => $mpu ? 'm' : 'pcs', 'rate' => (float) $ji->unit_price, 'discount_pct' => (float) $ji->discount_pct,
                'tax_rate' => (float) ($ji->tax->rate ?? 0), 'sale_order_item_id' => null, 'job_item_id' => $ji->id,
                'terms' => [$job->payment_term_type, $job->payment_term_days]];
        }
        return null;
    }

    /** $lines: [['stock_movement_item_id', 'quantity' (delivered unit; default what is left)], ...] */
    public function create(int $customerId, array $data, array $lines, int $userId): CustomerInvoice
    {
        return DB::transaction(function () use ($customerId, $data, $lines, $userId) {
            $customer = Customer::findOrFail($customerId);
            $rows = [];
            foreach ($lines as $l) {
                $mi = StockMovementItem::with('product.measurementUnit', 'saleOrderItem.saleOrder', 'jobItem.job', 'jobItem.tax', 'stockMovement')
                    ->lockForUpdate()->find($l['stock_movement_item_id'] ?? 0);
                if (!$mi) continue;
                $m = $mi->stockMovement;
                if (!$m->isSale() || $m->status !== StockMovement::RECEIVED || (int) $m->customer_id !== $customer->id) {
                    throw new \Exception('A line is not a delivery to this customer.');
                }
                $p = $this->price($mi);
                $qty = array_key_exists('quantity', $l) && $l['quantity'] !== null && $l['quantity'] !== '' ? round((float) $l['quantity'], 3) : $p['left'];
                if ($qty <= 0) continue;
                if ($qty > $p['left'] + 0.001) throw new \Exception("{$p['product']} ({$p['delivery_no']}): only {$p['left']} left to invoice.");
                $rows[] = [$mi, $p, $qty];
            }
            if (empty($rows)) throw new \Exception('Pick at least one delivered line to invoice.');
            $currencies = collect($rows)->pluck('1.currency')->unique();
            if ($currencies->count() > 1) throw new \Exception('One invoice is in one currency — invoice the ' . $currencies->implode(' and ') . ' lines separately.');
            $currency = $currencies->first();
            $rate = $currency === 'PKR' ? 1 : round((float) ($data['exchange_rate'] ?? 0) ?: $rows[0][1]['exchange_rate'], 4);
            if ($rate <= 0) throw new \Exception('Enter the exchange rate.');

            $date = $data['invoice_date'] ?? now()->toDateString();
            [$termType, $days] = $rows[0][1]['terms'];
            $due = $data['due_date'] ?? (in_array($termType, ['credit', 'pdc']) && $days ? \Carbon\Carbon::parse($date)->addDays((int) $days)->toDateString() : $date);

            $invoice = CustomerInvoice::create([
                'invoice_no' => $this->numbers->next('customer_invoice', 'customer_invoices', 'invoice_no', 'INV'),
                'customer_id' => $customer->id, 'invoice_date' => $date, 'due_date' => $due, 'currency' => $currency, 'exchange_rate' => $rate,
                'status' => 'Unpaid', 'remarks' => $data['remarks'] ?? null, 'created_by' => $userId,
            ]);
            $sub = 0; $tax = 0;
            foreach ($rows as [$mi, $p, $qty]) {
                $billed = round($qty * $p['factor'], 3);
                $amount = round($billed * $p['rate'] * (1 - $p['discount_pct'] / 100), 2);
                $lineTax = round($amount * $p['tax_rate'] / 100, 2);
                CustomerInvoiceItem::create([
                    'customer_invoice_id' => $invoice->id, 'stock_movement_item_id' => $mi->id, 'product_id' => $mi->product_id,
                    'sale_order_item_id' => $p['sale_order_item_id'], 'job_item_id' => $p['job_item_id'],
                    'description' => "{$p['order']} · {$p['delivery_no']}", 'delivered_qty' => $qty, 'quantity' => $billed, 'unit' => $p['unit'],
                    'rate' => $p['rate'], 'discount_pct' => $p['discount_pct'], 'amount' => $amount, 'tax_rate' => $p['tax_rate'], 'tax_amount' => $lineTax,
                ]);
                $mi->increment('invoiced_qty', $qty);
                $sub += $amount; $tax += $lineTax;
            }
            $total = round($sub + $tax, 2);
            $subPkr = round($sub * $rate, 2); $totalPkr = round($total * $rate, 2); $taxPkr = round($totalPkr - $subPkr, 2);
            $invoice->update(['subtotal' => round($sub, 2), 'tax_amount' => round($tax, 2), 'total' => $total, 'total_pkr' => $totalPkr]);

            $ar = $this->mapping->accountId('accounts_receivable'); $rev = $this->mapping->accountId('sales_revenue');
            $stp = $this->mapping->accountId('sales_tax_payable');
            if (!$ar || !$rev || ($taxPkr > 0 && !$stp)) throw new \Exception('Receivable / Sales Revenue / Sales Tax Payable account mapping is missing.');
            $this->vouchers->post('system', $date, array_filter([
                ['account_id' => $ar, 'debit' => $totalPkr, 'credit' => 0, 'party_type' => 'customer', 'party_id' => $customer->id],
                ['account_id' => $rev, 'debit' => 0, 'credit' => $subPkr],
                $taxPkr > 0 ? ['account_id' => $stp, 'debit' => 0, 'credit' => $taxPkr] : null,
            ]), "Invoice {$invoice->invoice_no} — {$customer->name}" . ($currency !== 'PKR' ? " ({$currency} {$total} @ {$rate})" : ''), 'CustomerInvoice', $invoice->id, $userId);

            return $invoice->fresh('items');
        });
    }

    public function cancel(CustomerInvoice $invoice, int $userId): CustomerInvoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            $invoice = CustomerInvoice::with('items')->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status === 'Cancelled') throw new \Exception('Already cancelled.');
            if ($invoice->paid_amount > 0.004) throw new \Exception('Payments are allocated to this invoice — remove them first.');
            foreach ($invoice->items as $it) StockMovementItem::whereKey($it->stock_movement_item_id)->decrement('invoiced_qty', $it->delivered_qty);
            $this->vouchers->deleteByReference('CustomerInvoice', $invoice->id);
            $invoice->update(['status' => 'Cancelled']);
            return $invoice;
        });
    }

    public static function refreshStatus(CustomerInvoice $invoice): void
    {
        if ($invoice->status === 'Cancelled') return;
        $paid = (float) $invoice->allocations()->sum('amount');
        $status = $paid <= 0.004 ? 'Unpaid' : ($paid + 0.004 >= $invoice->total ? 'Paid' : 'PartiallyPaid');
        $invoice->update(['paid_amount' => round($paid, 2), 'status' => $status]);
    }
}
