<?php

namespace App\Services;

use App\Models\{Broker, Customer, Vendor};
use Illuminate\Support\Facades\DB;

/**
 * Party statement (customer, vendor / mill, broker) from the ledger: every voucher line posted against the party,
 * with opening balance and running balance. Customers: positive = they owe us. Vendors / brokers: positive = we owe them.
 */
class StatementService
{
    public const TYPES = ['customer' => 'Customer', 'vendor' => 'Vendor / Mill', 'broker' => 'Broker'];

    public function parties(string $type)
    {
        return match ($type) {
            'customer' => Customer::orderBy('name')->get(['id', 'name']),
            'vendor'   => Vendor::orderBy('name')->get(['id', 'name']),
            'broker'   => Broker::orderBy('name')->get(['id', 'name']),
            default    => collect(),
        };
    }

    public function build(string $type, int $partyId, string $from, string $to): array
    {
        $sign = $type === 'customer' ? 1 : -1; // customer: debit − credit; vendor/broker: credit − debit
        $base = DB::table('voucher_entries as e')->join('vouchers as v', 'v.id', '=', 'e.voucher_id')->whereNull('v.deleted_at')
            ->where('e.party_type', $type)->where('e.party_id', $partyId);

        $opening = $sign * (float) (clone $base)->where('v.voucher_date', '<', $from)->selectRaw('COALESCE(SUM(e.debit) - SUM(e.credit), 0) AS b')->value('b');
        $rows = (clone $base)->whereBetween('v.voucher_date', [$from, $to])->orderBy('v.voucher_date')->orderBy('v.id')->orderBy('e.id')
            ->get(['v.voucher_date', 'v.voucher_no', 'v.narration', 'v.reference_type', 'v.reference_id', 'e.debit', 'e.credit']);

        $run = $opening; $out = [];
        foreach ($rows as $r) {
            $run += $sign * ((float) $r->debit - (float) $r->credit);
            $out[] = ['date' => $r->voucher_date, 'voucher_no' => $r->voucher_no, 'document' => $this->documentNo($r->reference_type, $r->reference_id),
                      'narration' => $r->narration, 'debit' => (float) $r->debit, 'credit' => (float) $r->credit, 'balance' => round($run, 2)];
        }
        return [
            'opening' => round($opening, 2), 'rows' => $out, 'closing' => round($run, 2),
            'debits' => round(array_sum(array_column($out, 'debit')), 2), 'credits' => round(array_sum(array_column($out, 'credit')), 2),
            'party' => $this->parties($type)->firstWhere('id', $partyId),
        ];
    }

    private function documentNo(?string $type, $id): ?string
    {
        if (!$type || !$id) return null;
        if ($type === 'CustomerReceiptAllocation') return DB::table('customer_receipt_allocations as a')->join('customer_receipts as r', 'r.id', '=', 'a.customer_receipt_id')->where('a.id', $id)->value('r.receipt_no');
        if ($type === 'CustomerCreditApplication') return DB::table('customer_credit_applications as a')->join('customer_credit_notes as c', 'c.id', '=', 'a.customer_credit_note_id')->where('a.id', $id)->value('c.credit_no');
        [$table, $col] = match ($type) {
            'CustomerInvoice', 'CustomerInvoiceBrokerage' => ['customer_invoices', 'invoice_no'],
            'CustomerReceipt', 'CustomerReceiptClear'     => ['customer_receipts', 'receipt_no'],
            'CustomerCreditNote'                          => ['customer_credit_notes', 'credit_no'],
            'CustomerReturn'                              => ['customer_returns', 'return_no'],
            'PurchaseReceiving'                           => ['purchase_receivings', 'receiving_no'],
            'QcInspection'                                => ['qc_inspections', 'qc_no'],
            'StockMovement'                               => ['stock_movements', 'movement_no'],
            default                                       => [null, null],
        };
        return $table ? DB::table($table)->where('id', $id)->value($col) : null;
    }
}
