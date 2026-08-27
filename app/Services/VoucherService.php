<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherEntry;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function __construct(private DocumentNumberService $numberService) {}

    // $lines: [ ['account_id'=>.., 'debit'=>.., 'credit'=>.., 'party_type'=>null, 'party_id'=>null, 'narration'=>null], ... ]
    // $type: 'journal' | 'payment' | 'receipt' | 'contra' | 'system'
    // $referenceType/$referenceId: set ONLY for system-generated vouchers,
    // tying this voucher to the business document that triggered it.
    public function post(
        string $type,
        string $voucherDate,
        array $lines,
        ?string $narration = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): Voucher {
        return DB::transaction(function () use ($type, $voucherDate, $lines, $narration, $referenceType, $referenceId, $userId) {

            $lines = array_values(array_filter($lines, fn($l) => (float) ($l['debit'] ?? 0) > 0 || (float) ($l['credit'] ?? 0) > 0));

            if (count($lines) < 2) {
                throw new \Exception('A voucher needs at least two lines.');
            }

            $totalDebit  = round(array_sum(array_column($lines, 'debit')), 2);
            $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception("Voucher does not balance: Debit {$totalDebit} != Credit {$totalCredit}.");
            }

            $voucher = Voucher::create([
                'voucher_no'      => $this->numberService->next('voucher_' . $type, 'vouchers', 'voucher_no'),
                'type'            => $type,
                'voucher_date'    => $voucherDate,
                'narration'       => $narration,
                'reference_type'  => $referenceType,
                'reference_id'    => $referenceId,
                'created_by'      => $userId,
                'updated_by'      => $userId,
            ]);

            foreach ($lines as $line) {
                VoucherEntry::create([
                    'voucher_id' => $voucher->id,
                    'account_id' => $line['account_id'],
                    'debit'      => (float) ($line['debit'] ?? 0),
                    'credit'     => (float) ($line['credit'] ?? 0),
                    'party_type' => $line['party_type'] ?? null,
                    'party_id'   => $line['party_id'] ?? null,
                    'narration'  => $line['narration'] ?? null,
                ]);
            }

            return $voucher->load('entries.account');
        });
    }

    // Used by any module's delete()/reject() to cleanly reverse a
    // system-generated voucher tied to that document.
    public function deleteByReference(string $referenceType, int $referenceId): void
    {
        $voucher = Voucher::where('reference_type', $referenceType)->where('reference_id', $referenceId)->first();

        if ($voucher) {
            $voucher->entries()->delete();
            $voucher->delete();
        }
    }
}