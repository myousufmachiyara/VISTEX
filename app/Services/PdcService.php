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

    // Add a new cheque against a PDC — total across all cheques must not exceed pdc.amount
    public function addCheque(Pdc $pdc, array $data, ?int $userId = null): PdcCheque
    {
        return DB::transaction(function () use ($pdc, $data, $userId) {
            $amount = (float) $data['amount'];
            $remaining = $pdc->pending_amount;

            if ($amount > $remaining + 0.01) {
                throw new \Exception("Cheque amount ({$amount}) exceeds remaining PDC balance ({$remaining}).");
            }
            if ($amount <= 0) {
                throw new \Exception('Cheque amount must be greater than zero.');
            }

            $nextSeq = ($pdc->cheques()->max('sequence_no') ?? 0) + 1;

            return PdcCheque::create([
                'pdc_id'                => $pdc->id,
                'sequence_no'           => $nextSeq,
                'amount'                => $amount,
                'status'                => 'Created',
                'bank_account_id'       => $data['bank_account_id'],
                'cheque_no'             => $data['cheque_no'],
                'unsigned_cheque_image' => $data['unsigned_cheque_image'],
                'created_by'            => $userId,
                'updated_by'            => $userId,
            ]);
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