<?php

namespace App\Services;

use App\Models\{CustomerCreditApplication, CustomerCreditNote, CustomerInvoiceItem, CustomerReturn, CustomerReturnItem, JobItem, Location, LocationStockLedger, SaleOrderItem, StockMovement, StockMovementItem};
use Illuminate\Support\Facades\DB;

/**
 * Goods coming back from a customer.
 *   Stock: back into the warehouse under the lots it left from, at the cost it left at — fresh (re-sellable) or rejected.
 *   Cost of goods sold reversed. Sale order / customer order quantities reduced.
 *   Part not yet invoiced: simply no longer invoiceable. Part already invoiced: credit note at the invoice price + tax,
 *   applied to that invoice (anything beyond its balance stays on account).
 */
class CustomerReturnService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private VoucherService $vouchers,
        private AccountMappingService $mapping,
        private SaleOrderService $saleOrders,
    ) {}

    /** Delivered lines of a customer that can still come back. */
    public function returnable(int $customerId): array
    {
        return StockMovementItem::whereColumn('returned_qty', '<', 'quantity')
            ->whereHas('stockMovement', fn($q) => $q->where('movement_type', StockMovement::SALE)->where('status', StockMovement::RECEIVED)->where('customer_id', $customerId))
            ->with('product.measurementUnit', 'stockMovement.saleOrder', 'stockMovement.job')->get()
            ->map(fn($mi) => ['stock_movement_item_id' => $mi->id, 'delivery_no' => $mi->stockMovement->movement_no,
                'date' => $mi->stockMovement->movement_date->format('d-M-Y'), 'order' => $mi->stockMovement->saleOrder->so_no ?? $mi->stockMovement->job->job_no ?? '',
                'product' => $mi->product->name ?? '', 'unit' => $mi->product->measurementUnit->shortcode ?? '',
                'delivered' => (float) $mi->quantity, 'returned' => (float) $mi->returned_qty, 'invoiced' => (float) $mi->invoiced_qty,
                'can_return' => round((float) $mi->quantity - (float) $mi->returned_qty, 3)])->values()->all();
    }

    /** $lines: [['stock_movement_item_id', 'quantity', 'condition' => fresh|rejected], ...] */
    public function create(array $data, array $lines, int $userId): CustomerReturn
    {
        return DB::transaction(function () use ($data, $lines, $userId) {
            $loc = Location::findOrFail($data['location_id']);
            if ($loc->vendor_id) throw new \Exception('Returned goods come back into one of our warehouses.');
            $ret = CustomerReturn::create([
                'return_no' => $this->numbers->next('customer_return', 'customer_returns', 'return_no', 'CRT'),
                'customer_id' => $data['customer_id'], 'location_id' => $loc->id, 'return_date' => $data['return_date'],
                'reason' => $data['reason'] ?? null, 'remarks' => $data['remarks'] ?? null, 'created_by' => $userId,
            ]);
            $toCredit = []; // invoice_id => [[invoice item, qty], ...]
            $costByCategory = []; $any = false;

            foreach ($lines as $l) {
                $qty = round((float) ($l['quantity'] ?? 0), 3);
                if ($qty <= 0) continue;
                $mi = StockMovementItem::with('product.category', 'stockMovement')->lockForUpdate()->findOrFail($l['stock_movement_item_id']);
                $m = $mi->stockMovement;
                if (!$m->isSale() || $m->status !== StockMovement::RECEIVED || (int) $m->customer_id !== (int) $ret->customer_id) throw new \Exception('A line is not a delivery to this customer.');
                $can = round((float) $mi->quantity - (float) $mi->returned_qty, 3);
                if ($qty > $can + 0.001) throw new \Exception("{$mi->product->name} ({$m->movement_no}): only {$can} can still come back.");
                $condition = ($l['condition'] ?? 'fresh') === 'rejected' ? 'rejected' : 'fresh';
                $any = true;

                // Back under the lots it left from (latest first), at their cost
                $left = $qty; $cost = 0;
                $out = LocationStockLedger::where('reference_type', 'StockMovement')->where('reference_id', $m->id)->where('product_id', $mi->product_id)
                    ->where('quantity', '<', 0)->orderByDesc('id')->get();
                $alreadyBack = (float) $mi->returned_qty;
                foreach ($out as $row) {
                    $rowQty = -(float) $row->quantity; $rate = $rowQty > 0 ? -(float) $row->amount / $rowQty : 0;
                    $skip = min($alreadyBack, $rowQty); $alreadyBack -= $skip; $room = $rowQty - $skip;
                    if ($room <= 0.0005 || $left <= 0.0005) continue;
                    $take = round(min($room, $left), 3); $amt = round($take * $rate, 2);
                    LocationStockLedger::create(['doc_no' => $ret->return_no, 'location_id' => $loc->id, 'product_id' => $mi->product_id,
                        'status' => $condition, 'lot_no' => $row->lot_no, 'grade' => $condition === 'fresh' ? $row->grade : null,
                        'quantity' => $take, 'amount' => $amt, 'reference_type' => 'CustomerReturn', 'reference_id' => $ret->id, 'entry_date' => $ret->return_date]);
                    $cost += $amt; $left = round($left - $take, 3);
                    $lotUsed = $row->lot_no; $gradeUsed = $row->grade;
                }
                $cat = $mi->product->category;
                $k = ($cat->stock_account_id ?? $this->mapping->accountId('stock_in_hand')) . '|' . ($cat->cogs_account_id ?? $this->mapping->accountId('cogs'));
                $costByCategory[$k] = ($costByCategory[$k] ?? 0) + $cost;

                // Not-yet-invoiced part first; the rest was invoiced and needs a credit note
                $uninvoiced = max(0, round((float) $mi->quantity - (float) $mi->returned_qty - (float) $mi->invoiced_qty, 3));
                $fromUninvoiced = min($qty, $uninvoiced); $credit = round($qty - $fromUninvoiced, 3);
                $credited = 0;
                if ($credit > 0) {
                    $items = CustomerInvoiceItem::where('stock_movement_item_id', $mi->id)->whereColumn('credited_qty', '<', 'delivered_qty')
                        ->whereHas('invoice', fn($q) => $q->where('status', '!=', 'Cancelled'))->orderByDesc('id')->get();
                    $need = $credit;
                    foreach ($items as $it) {
                        if ($need <= 0.0005) break;
                        $take = round(min($need, (float) $it->delivered_qty - (float) $it->credited_qty), 3);
                        $toCredit[$it->customer_invoice_id][] = [$it, $take];
                        $need = round($need - $take, 3); $credited += $take;
                    }
                }
                CustomerReturnItem::create(['customer_return_id' => $ret->id, 'stock_movement_item_id' => $mi->id, 'product_id' => $mi->product_id,
                    'lot_no' => $lotUsed ?? $mi->source_lot_no, 'grade' => $gradeUsed ?? $mi->source_grade, 'quantity' => $qty, 'condition' => $condition,
                    'cost_amount' => round($cost, 2), 'uninvoiced_qty' => $fromUninvoiced, 'credited_qty' => $credited]);
                $mi->increment('returned_qty', $qty);

                // Order quantities
                if ($mi->sale_order_item_id) {
                    SaleOrderItem::whereKey($mi->sale_order_item_id)->decrement('delivered_qty', $qty);
                    $this->saleOrders->refreshStatus($m->saleOrder()->first());
                }
                if ($mi->job_item_id) {
                    $mpu = (float) (($mi->product->getAttribute('attributes') ?? [])['meters_per_unit'] ?? 0);
                    JobItem::whereKey($mi->job_item_id)->decrement('quantity_fulfilled', round($qty * ($mpu ?: 1), 3));
                }
                unset($lotUsed, $gradeUsed);
            }
            if (!$any) throw new \Exception('Enter the quantity coming back for at least one line.');

            // Cost of goods sold reversed: Dr stock / Cr COGS
            $vl = [];
            foreach ($costByCategory as $k => $amt) {
                if ($amt <= 0) continue;
                [$stock, $cogs] = explode('|', $k);
                $vl[] = ['account_id' => (int) $stock, 'debit' => round($amt, 2), 'credit' => 0];
                $vl[] = ['account_id' => (int) $cogs, 'debit' => 0, 'credit' => round($amt, 2)];
            }
            if (count($vl) >= 2) $this->vouchers->post('system', $ret->return_date->format('Y-m-d'), $vl, "Customer return {$ret->return_no} — cost of goods reversed", 'CustomerReturn', $ret->id, $userId);
            $ret->update(['cost_amount' => round(array_sum($costByCategory), 2)]);

            foreach ($toCredit as $invoiceId => $parts) $this->creditNote($ret, $invoiceId, $parts, $userId);
            return $ret->fresh('items', 'creditNotes');
        });
    }

    /** Credit note for invoiced goods that came back, at the invoice's price, discount and tax. */
    private function creditNote(CustomerReturn $ret, int $invoiceId, array $parts, int $userId): CustomerCreditNote
    {
        $invoice = \App\Models\CustomerInvoice::lockForUpdate()->findOrFail($invoiceId);
        $sub = 0; $tax = 0;
        foreach ($parts as [$it, $qty]) {
            $factor = (float) $it->delivered_qty > 0 ? (float) $it->quantity / (float) $it->delivered_qty : 1;
            $amt = round($qty * $factor * (float) $it->rate * (1 - (float) $it->discount_pct / 100), 2);
            $sub += $amt; $tax += round($amt * (float) $it->tax_rate / 100, 2);
            $it->increment('credited_qty', $qty);
        }
        $total = round($sub + $tax, 2); $rate = (float) $invoice->exchange_rate;
        $subPkr = round($sub * $rate, 2); $totalPkr = round($total * $rate, 2); $taxPkr = round($totalPkr - $subPkr, 2);
        $note = CustomerCreditNote::create([
            'credit_no' => $this->numbers->next('customer_credit_note', 'customer_credit_notes', 'credit_no', 'CN'),
            'customer_id' => $invoice->customer_id, 'customer_invoice_id' => $invoice->id, 'customer_return_id' => $ret->id,
            'credit_date' => $ret->return_date, 'currency' => $invoice->currency, 'exchange_rate' => $rate,
            'subtotal' => round($sub, 2), 'tax_amount' => round($tax, 2), 'total' => $total, 'total_pkr' => $totalPkr,
            'unapplied' => $total, 'reason' => $ret->reason ?: "Return {$ret->return_no}", 'created_by' => $userId,
        ]);
        $ar = $this->mapping->accountId('accounts_receivable'); $sr = $this->mapping->accountId('sales_returns'); $stp = $this->mapping->accountId('sales_tax_payable');
        if (!$ar || !$sr || ($taxPkr > 0 && !$stp)) throw new \Exception('Receivable / Sales Returns / Sales Tax Payable account mapping is missing.');
        $this->vouchers->post('system', $ret->return_date->format('Y-m-d'), array_values(array_filter([
            ['account_id' => $sr, 'debit' => $subPkr, 'credit' => 0],
            $taxPkr > 0 ? ['account_id' => $stp, 'debit' => $taxPkr, 'credit' => 0] : null,
            ['account_id' => $ar, 'debit' => 0, 'credit' => $totalPkr, 'party_type' => 'customer', 'party_id' => $invoice->customer_id],
        ])), "Credit note {$note->credit_no} on {$invoice->invoice_no}", 'CustomerCreditNote', $note->id, $userId);

        // Applied to its own invoice as far as the balance goes; the rest stays on account
        $apply = round(min($total, max(0, $invoice->balance)), 2);
        if ($apply > 0) {
            CustomerCreditApplication::create(['customer_credit_note_id' => $note->id, 'customer_invoice_id' => $invoice->id, 'amount' => $apply]);
            $note->update(['unapplied' => round($total - $apply, 2)]);
            CustomerInvoiceService::refreshStatus($invoice);
        }
        return $note;
    }
}
