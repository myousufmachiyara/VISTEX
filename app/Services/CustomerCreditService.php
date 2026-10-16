<?php

namespace App\Services;

use App\Models\{CustomerCreditApplication, CustomerCreditNote, CustomerInvoice, CustomerReceipt, CustomerReceiptAllocation};
use Illuminate\Support\Facades\DB;

/**
 * Applying a customer's credit to open invoices after the fact:
 *   money on account (a receipt's unallocated part) or a credit note's unapplied part.
 * The receivable was already credited at the credit's own rate; the invoice sits at its rate,
 * so the difference is posted as exchange gain / loss when they are matched.
 */
class CustomerCreditService
{
    public function __construct(private VoucherService $vouchers, private AccountMappingService $mapping) {}

    /** $rows: [['customer_invoice_id', 'amount'], ...] in the receipt's currency */
    public function applyReceipt(CustomerReceipt $receipt, array $rows, int $userId): CustomerReceipt
    {
        return DB::transaction(function () use ($receipt, $rows, $userId) {
            $receipt = CustomerReceipt::lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status === 'Bounced') throw new \Exception('This cheque bounced; there is nothing to apply.');
            $left = (float) $receipt->unallocated; $diffTotal = 0;
            foreach ($this->clean($rows) as [$inv, $amt]) {
                $this->check($inv, $receipt->customer_id, $receipt->currency, $amt);
                if ($amt > $left + 0.004) throw new \Exception("Only {$receipt->currency} {$left} is left on {$receipt->receipt_no} to apply.");
                $diff = round($amt * ($receipt->exchange_rate - $inv->exchange_rate), 2);
                $a = CustomerReceiptAllocation::create(['customer_receipt_id' => $receipt->id, 'customer_invoice_id' => $inv->id, 'amount' => $amt,
                    'exchange_difference' => $diff, 'applied_later' => true]);
                $this->postDifference($diff, $receipt->customer_id, 'CustomerReceiptAllocation', $a->id,
                    "Applied {$receipt->receipt_no} to {$inv->invoice_no}", $userId);
                $left = round($left - $amt, 2); $diffTotal += $diff;
                CustomerInvoiceService::refreshStatus($inv);
            }
            $receipt->update(['unallocated' => $left, 'exchange_difference' => round($receipt->exchange_difference + $diffTotal, 2)]);
            return $receipt->fresh('allocations.invoice');
        });
    }

    public function applyCreditNote(CustomerCreditNote $note, array $rows, int $userId): CustomerCreditNote
    {
        return DB::transaction(function () use ($note, $rows, $userId) {
            $note = CustomerCreditNote::lockForUpdate()->findOrFail($note->id);
            $left = (float) $note->unapplied;
            foreach ($this->clean($rows) as [$inv, $amt]) {
                $this->check($inv, $note->customer_id, $note->currency, $amt);
                if ($amt > $left + 0.004) throw new \Exception("Only {$note->currency} {$left} is left on {$note->credit_no} to apply.");
                $diff = round($amt * ($note->exchange_rate - $inv->exchange_rate), 2);
                $a = CustomerCreditApplication::create(['customer_credit_note_id' => $note->id, 'customer_invoice_id' => $inv->id, 'amount' => $amt, 'exchange_difference' => $diff]);
                $this->postDifference($diff, $note->customer_id, 'CustomerCreditApplication', $a->id, "Applied {$note->credit_no} to {$inv->invoice_no}", $userId);
                $left = round($left - $amt, 2);
                CustomerInvoiceService::refreshStatus($inv);
            }
            $note->update(['unapplied' => $left]);
            return $note->fresh('applications.invoice');
        });
    }

    /** Credits a customer still has, per currency: receipts on account + unapplied credit notes. */
    public function available(int $customerId): array
    {
        $r = CustomerReceipt::where('customer_id', $customerId)->where('unallocated', '>', 0.004)->where('status', '!=', 'Bounced')
            ->get()->map(fn($x) => ['type' => 'receipt', 'id' => $x->id, 'no' => $x->receipt_no, 'date' => $x->receipt_date->format('d-M-Y'),
                'currency' => $x->currency, 'amount' => (float) $x->unallocated]);
        $c = CustomerCreditNote::where('customer_id', $customerId)->where('unapplied', '>', 0.004)
            ->get()->map(fn($x) => ['type' => 'credit_note', 'id' => $x->id, 'no' => $x->credit_no, 'date' => $x->credit_date->format('d-M-Y'),
                'currency' => $x->currency, 'amount' => (float) $x->unapplied]);
        return $r->concat($c)->values()->all();
    }

    private function clean(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $amt = round((float) ($row['amount'] ?? 0), 2);
            if ($amt <= 0) continue;
            $out[] = [CustomerInvoice::lockForUpdate()->findOrFail($row['customer_invoice_id']), $amt];
        }
        if (!$out) throw new \Exception('Enter an amount to apply to at least one invoice.');
        return $out;
    }

    private function check(CustomerInvoice $inv, int $customerId, string $currency, float $amt): void
    {
        if ((int) $inv->customer_id !== $customerId || !in_array($inv->status, ['Unpaid', 'PartiallyPaid'], true)) throw new \Exception("{$inv->invoice_no} is not an open invoice of this customer.");
        if ($inv->currency !== $currency) throw new \Exception("{$inv->invoice_no} is in {$inv->currency}; the credit is in {$currency}.");
        if ($amt > $inv->balance + 0.004) throw new \Exception("{$inv->invoice_no}: balance is {$inv->balance}, you entered {$amt}.");
    }

    // + diff: receivable over-credited at a higher rate → Dr receivable / Cr exchange gain; − diff the other way round
    private function postDifference(float $diff, int $customerId, string $refType, int $refId, string $narration, int $userId): void
    {
        if (abs($diff) <= 0.004) return;
        $ar = $this->mapping->accountId('accounts_receivable'); $fx = $this->mapping->accountId('exchange_gain_loss');
        if (!$ar || !$fx) throw new \Exception('Receivable / Exchange Gain-Loss account mapping is missing.');
        $party = ['party_type' => 'customer', 'party_id' => $customerId];
        $lines = $diff > 0
            ? [['account_id' => $ar, 'debit' => $diff, 'credit' => 0] + $party, ['account_id' => $fx, 'debit' => 0, 'credit' => $diff]]
            : [['account_id' => $fx, 'debit' => -$diff, 'credit' => 0], ['account_id' => $ar, 'debit' => 0, 'credit' => -$diff] + $party];
        $this->vouchers->post('journal', now()->toDateString(), $lines, $narration . ' — exchange difference', $refType, $refId, $userId);
    }
}
