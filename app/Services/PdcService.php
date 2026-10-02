<?php
namespace App\Services;

use App\Models\{Pdc, PdcCheque};
use Illuminate\Support\Facades\DB;

class PdcService
{
    public function __construct(
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    public function createPending(string $partyType, int $partyId, float $amount, string $dueDate, ?string $referenceType, ?int $referenceId, ?int $userId): Pdc
    {
        return Pdc::create([
            'pdc_no'         => app(DocumentNumberService::class)->next('pdc', 'pdcs', 'pdc_no', 'PDC'),
            'party_type'     => $partyType, 'party_id' => $partyId,
            'reference_type' => $referenceType, 'reference_id' => $referenceId,
            'amount'         => $amount, 'due_date' => $dueDate,
            'created_by'     => $userId,
        ]);
    }

    // Add a single cheque against a PDC (kept for existing callers)
    public function addCheque(Pdc $pdc, array $data, ?int $userId = null): PdcCheque
    {
        return $this->addCheques($pdc, [$data], $userId)[0];
    }

    /**
     * Add several cheques against one PDC in a single, all-or-nothing step.
     * Rows: amount, bank_account_id, cheque_no, cheque_date (optional, defaults
     * to the PDC due date), unsigned_cheque_image.
     * The batch total must not exceed what is still unallocated on the PDC.
     */
    public function addCheques(Pdc $pdc, array $rows, ?int $userId = null): array
    {
        if (empty($rows)) throw new \Exception('Add at least one cheque.');

        return DB::transaction(function () use ($pdc, $rows, $userId) {
            // Lock the PDC so two people can't over-allocate it at the same moment
            $pdc = Pdc::whereKey($pdc->id)->lockForUpdate()->firstOrFail();
            $remaining = $pdc->pending_amount;

            $total = 0; $seen = [];
            foreach ($rows as $i => $row) {
                $n = $i + 1;
                $amount = round((float) ($row['amount'] ?? 0), 2);
                if ($amount <= 0) throw new \Exception("Cheque {$n}: amount must be greater than zero.");
                $total += $amount;

                $key = ($row['bank_account_id'] ?? '') . '|' . strtolower(trim((string) ($row['cheque_no'] ?? '')));
                if (isset($seen[$key])) throw new \Exception("Cheque {$n}: cheque # {$row['cheque_no']} is entered twice for the same bank.");
                $seen[$key] = true;

                $exists = PdcCheque::where('bank_account_id', $row['bank_account_id'])
                    ->where('cheque_no', trim((string) $row['cheque_no']))
                    ->where('status', '!=', 'Bounced')->exists();
                if ($exists) throw new \Exception("Cheque {$n}: cheque # {$row['cheque_no']} already exists for this bank.");
            }

            $total = round($total, 2);
            if ($total > $remaining + 0.01) {
                throw new \Exception('These cheques total ' . number_format($total, 2) . ' but only ' . number_format($remaining, 2) . ' is unallocated on ' . $pdc->pdc_no . '.');
            }

            $seq = (int) ($pdc->cheques()->withTrashed()->max('sequence_no') ?? 0);
            $created = [];
            foreach ($rows as $row) {
                $created[] = PdcCheque::create([
                    'pdc_id'                => $pdc->id,
                    'sequence_no'           => ++$seq,
                    'amount'                => round((float) $row['amount'], 2),
                    'status'                => 'Created',
                    'bank_account_id'       => $row['bank_account_id'],
                    'cheque_no'             => trim((string) $row['cheque_no']),
                    'cheque_date'           => !empty($row['cheque_date']) ? $row['cheque_date'] : $pdc->due_date,
                    'unsigned_cheque_image' => $row['unsigned_cheque_image'],
                    'created_by'            => $userId,
                    'updated_by'            => $userId,
                ]);
            }
            return $created;
        });
    }

    public function markSigned(PdcCheque $cheque, string $signedImage, ?int $userId = null): PdcCheque
    {
        $this->assertStatus($cheque, 'Created', 'signed');
        $cheque->update(['signed_cheque_image' => $signedImage, 'status' => 'Signed', 'updated_by' => $userId]);
        return $cheque->fresh();
    }

    public function markIssued(PdcCheque $cheque, array $data, ?int $userId = null): PdcCheque
    {
        $this->assertStatus($cheque, 'Signed', 'issued');

        $method = $data['issue_method'];
        if ($method === 'handed_to_vendor') {
            if (empty($data['receiver_name']) || empty($data['receipt_signed_image'])) {
                throw new \Exception('Receiver name and signed receipt image are required.');
            }
        } elseif ($method === 'bank_deposit') {
            if (empty($data['bank_slip_image'])) {
                throw new \Exception('A bank deposit slip image is required.');
            }
        } else {
            throw new \Exception('Invalid issue method.');
        }

        $cheque->update([
            'issue_method' => $method,
            'receiver_name' => $data['receiver_name'] ?? null,
            'receiver_contact' => $data['receiver_contact'] ?? null,
            'receiver_cnic' => $data['receiver_cnic'] ?? null,
            'receipt_signed_image' => $data['receipt_signed_image'] ?? null,
            'bank_slip_image' => $data['bank_slip_image'] ?? null,
            'issued_date' => $data['issued_date'] ?? now()->toDateString(),
            'status' => 'Issued',
            'updated_by' => $userId,
        ]);

        return $cheque->fresh();
    }

    public function markCleared(PdcCheque $cheque, ?string $clearedDate = null, ?int $userId = null): PdcCheque
    {
        return DB::transaction(function () use ($cheque, $clearedDate, $userId) {
            $this->assertStatus($cheque, 'Issued', 'cleared');

            $pdc = $cheque->pdc;
            $apAccountId = $this->mappingService->accountId(
                $pdc->party_type === 'broker' ? 'accounts_payable' : 'accounts_payable'
            );
            if (!$cheque->bank_account_id || !$apAccountId) {
                throw new \Exception('Bank account or Accounts Payable mapping missing.');
            }

            $lines = [
                ['account_id' => $apAccountId, 'debit' => (float) $cheque->amount, 'credit' => 0, 'party_type' => $pdc->party_type, 'party_id' => $pdc->party_id],
                ['account_id' => $cheque->bank_account_id, 'debit' => 0, 'credit' => (float) $cheque->amount],
            ];

            $this->voucherService->post('system', $clearedDate ?? now()->toDateString(), $lines,
                "PDC {$pdc->pdc_no} cheque #{$cheque->sequence_no} cleared — {$cheque->cheque_no}",
                'PdcCheque', $cheque->id, $userId);

            $cheque->update(['status' => 'Cleared', 'cleared_date' => $clearedDate ?? now()->toDateString(), 'updated_by' => $userId]);

            return $cheque->fresh();
        });
    }

    public function markBounced(PdcCheque $cheque, string $reason, ?int $userId = null): PdcCheque
    {
        $this->assertStatus($cheque, 'Issued', 'bounced');
        $cheque->update(['status' => 'Bounced', 'bounced_date' => now()->toDateString(), 'bounced_reason' => $reason, 'updated_by' => $userId]);
        return $cheque->fresh();
    }

    private function assertStatus(PdcCheque $cheque, string $expected, string $action): void
    {
        if ($cheque->status !== $expected) {
            throw new \Exception("Cannot mark as {$action} — this cheque is currently {$cheque->status}, expected {$expected}.");
        }
    }

    // For fix #2 — status-wise totals across all cheques + unallocated
    public function summary(): array
    {
        $unallocated = 0;
        \App\Models\Pdc::with('cheques')->get()->each(function ($pdc) use (&$unallocated) {
            $unallocated += $pdc->pending_amount;
        });

        $byStatus = \App\Models\PdcCheque::selectRaw('status, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('status')->get()->keyBy('status');

        return [
            'pending'  => round($unallocated, 2),
            'created'  => (float) ($byStatus->get('Created')->total ?? 0),
            'signed'   => (float) ($byStatus->get('Signed')->total ?? 0),
            'issued'   => (float) ($byStatus->get('Issued')->total ?? 0),
            'cleared'  => (float) ($byStatus->get('Cleared')->total ?? 0),
            'bounced'  => (float) ($byStatus->get('Bounced')->total ?? 0),
        ];
    }
}