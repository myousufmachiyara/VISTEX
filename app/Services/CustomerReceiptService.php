<?php

namespace App\Services;

use App\Models\{ChartOfAccounts, Customer, CustomerInvoice, CustomerReceipt, CustomerReceiptAllocation};
use Illuminate\Support\Facades\DB;

/**
 * Money received from a customer into a cash / bank account, allocated to invoices.
 * Posting: Dr Cash/Bank (receipt × receipt rate) / Cr Receivable — allocations at each invoice's own rate,
 * anything unallocated (on account) at the receipt rate; the difference is exchange gain / loss.
 */
class CustomerReceiptService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private VoucherService $vouchers,
        private AccountMappingService $mapping,
    ) {}

    /** $allocations: [['customer_invoice_id', 'amount'], ...] in the invoices' (= receipt's) currency */
    public function create(array $data, array $allocations, int $userId): CustomerReceipt
    {
        return DB::transaction(function () use ($data, $allocations, $userId) {
            $customer = Customer::findOrFail($data['customer_id']);
            $account = ChartOfAccounts::findOrFail($data['account_id']);
            if (!in_array($account->account_type, ['cash', 'bank'], true)) throw new \Exception('Receive into a cash or bank account.');
            $currency = strtoupper($data['currency'] ?? 'PKR');
            $rate = $currency === 'PKR' ? 1 : round((float) ($data['exchange_rate'] ?? 0), 4);
            if ($rate <= 0) throw new \Exception('Enter the exchange rate for the amount received.');
            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) throw new \Exception('Enter the amount received.');
            // Post-dated cheque: held in Cheques in Hand until it clears into the bank account chosen
            $pdc = ($data['method'] ?? '') === 'pdc';
            if ($pdc && empty($data['cheque_date'])) throw new \Exception('Enter the cheque date for a post-dated cheque.');
            $debitAccount = $pdc ? $this->mapping->accountId('cheques_in_hand') : $account->id;
            if (!$debitAccount) throw new \Exception('Cheques in Hand account mapping is missing.');

            $rows = []; $allocated = 0; $arCredit = 0;
            foreach ($allocations as $a) {
                $amt = round((float) ($a['amount'] ?? 0), 2);
                if ($amt <= 0) continue;
                $inv = CustomerInvoice::lockForUpdate()->findOrFail($a['customer_invoice_id']);
                if ((int) $inv->customer_id !== $customer->id || $inv->status === 'Cancelled') throw new \Exception("{$inv->invoice_no} is not an open invoice of {$customer->name}.");
                if ($inv->currency !== $currency) throw new \Exception("{$inv->invoice_no} is in {$inv->currency}; this receipt is in {$currency}.");
                if ($amt > $inv->balance + 0.004) throw new \Exception("{$inv->invoice_no}: balance is {$inv->balance}, you allocated {$amt}.");
                $rows[] = [$inv, $amt];
                $allocated += $amt;
                $arCredit += round($amt * $inv->exchange_rate, 2);   // clears the receivable at the invoice's booked value
            }
            if ($allocated > $amount + 0.004) throw new \Exception("Allocated {$allocated} is more than the {$amount} received.");
            $unallocated = round($amount - $allocated, 2);
            $amountPkr = round($amount * $rate, 2);
            $arCredit = round($arCredit + $unallocated * $rate, 2);
            $diff = round($amountPkr - $arCredit, 2); // + gain, − loss

            $receipt = CustomerReceipt::create([
                'receipt_no' => $this->numbers->next('customer_receipt', 'customer_receipts', 'receipt_no', 'RCV'),
                'customer_id' => $customer->id, 'receipt_date' => $data['receipt_date'], 'account_id' => $account->id,
                'method' => $data['method'] ?? 'bank', 'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'currency' => $currency, 'exchange_rate' => $rate, 'amount' => $amount, 'amount_pkr' => $amountPkr,
                'unallocated' => $unallocated, 'exchange_difference' => $diff, 'remarks' => $data['remarks'] ?? null, 'created_by' => $userId,
                'status' => $pdc ? 'Pending' : 'Cleared', 'cheque_date' => $pdc ? $data['cheque_date'] : null,
                'cleared_at' => $pdc ? null : $data['receipt_date'],
            ]);
            foreach ($rows as [$inv, $amt]) {
                CustomerReceiptAllocation::create(['customer_receipt_id' => $receipt->id, 'customer_invoice_id' => $inv->id, 'amount' => $amt]);
                CustomerInvoiceService::refreshStatus($inv);
            }

            $ar = $this->mapping->accountId('accounts_receivable');
            $fx = $this->mapping->accountId('exchange_gain_loss');
            if (!$ar || (abs($diff) > 0.004 && !$fx)) throw new \Exception('Receivable / Exchange Gain-Loss account mapping is missing.');
            $lines = [
                ['account_id' => $debitAccount, 'debit' => $amountPkr, 'credit' => 0],
                ['account_id' => $ar, 'debit' => 0, 'credit' => $arCredit, 'party_type' => 'customer', 'party_id' => $customer->id],
            ];
            if ($diff > 0.004) $lines[] = ['account_id' => $fx, 'debit' => 0, 'credit' => $diff];
            if ($diff < -0.004) $lines[] = ['account_id' => $fx, 'debit' => -$diff, 'credit' => 0];
            $this->vouchers->post('receipt', $receipt->receipt_date->format('Y-m-d'), $lines,
                "Receipt {$receipt->receipt_no} — {$customer->name}" . ($currency !== 'PKR' ? " ({$currency} {$amount} @ {$rate})" : ''), 'CustomerReceipt', $receipt->id, $userId);

            return $receipt->fresh('allocations.invoice');
        });
    }

    public function delete(CustomerReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $this->unwind($receipt);
            $this->vouchers->deleteByReference('CustomerReceiptClear', $receipt->id);
            $receipt->delete();
        });
    }

    /** Post-dated cheque cleared: Cheques in Hand → bank. */
    public function clear(CustomerReceipt $receipt, string $date, int $userId): CustomerReceipt
    {
        return DB::transaction(function () use ($receipt, $date, $userId) {
            $receipt = CustomerReceipt::lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status !== 'Pending') throw new \Exception('Only a post-dated cheque waiting to clear can be cleared.');
            $cih = $this->mapping->accountId('cheques_in_hand');
            $this->vouchers->post('contra', $date, [
                ['account_id' => $receipt->account_id, 'debit' => (float) $receipt->amount_pkr, 'credit' => 0],
                ['account_id' => $cih, 'debit' => 0, 'credit' => (float) $receipt->amount_pkr],
            ], "Cheque cleared — {$receipt->receipt_no}" . ($receipt->reference ? " ({$receipt->reference})" : ''), 'CustomerReceiptClear', $receipt->id, $userId);
            $receipt->update(['status' => 'Cleared', 'cleared_at' => $date]);
            return $receipt->fresh();
        });
    }

    /** Post-dated cheque bounced: the receipt is undone and its invoices are open again. */
    public function bounce(CustomerReceipt $receipt, string $reason): CustomerReceipt
    {
        return DB::transaction(function () use ($receipt, $reason) {
            $receipt = CustomerReceipt::lockForUpdate()->findOrFail($receipt->id);
            if ($receipt->status !== 'Pending') throw new \Exception('Only a post-dated cheque waiting to clear can bounce.');
            $this->unwind($receipt);
            $receipt->update(['status' => 'Bounced', 'bounce_reason' => $reason, 'unallocated' => 0, 'exchange_difference' => 0]);
            return $receipt->fresh();
        });
    }

    // Remove the receipt's posting and allocations (incl. ones applied later) and reopen its invoices
    private function unwind(CustomerReceipt $receipt): void
    {
        $allocs = $receipt->allocations()->with('invoice')->get();
        foreach ($allocs as $a) $this->vouchers->deleteByReference('CustomerReceiptAllocation', $a->id);
        $receipt->allocations()->delete();
        foreach ($allocs->pluck('invoice')->unique('id') as $inv) CustomerInvoiceService::refreshStatus($inv);
        $this->vouchers->deleteByReference('CustomerReceipt', $receipt->id);
    }
}
