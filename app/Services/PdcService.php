<?php

namespace App\Services;

use App\Models\Pdc;
use App\Services\VoucherService;
use App\Services\AccountMappingService;
use Illuminate\Support\Facades\DB;

class PdcService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    // Called internally by PO Receiving approval — creates the Pending PDC automatically.
    // $partyType/$partyId: who this PDC is payable to (vendor) — kept generic for future receivable use.
    public function createPending(string $partyType, int $partyId, float $amount, string $dueDate, ?string $referenceType, ?int $referenceId, ?int $userId): Pdc
    {
        return Pdc::create([
            'pdc_no'          => $this->numberService->next('pdc', 'pdcs', 'pdc_no', 'PDC'),
            'party_type'      => $partyType,
            'party_id'        => $partyId,
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'amount'          => $amount,
            'due_date'        => $dueDate,
            'status'          => 'Pending',
            'created_by'      => $userId,
            'updated_by'      => $userId,
        ]);
    }

    public function markCreated(Pdc $pdc, array $data, ?int $userId = null): Pdc
    {
        $this->assertStatus($pdc, 'Pending', 'created');

        $pdc->update([
            'bank_account_id'         => $data['bank_account_id'],
            'cheque_no'                => $data['cheque_no'],
            'unsigned_cheque_image'    => $data['unsigned_cheque_image'],
            'status'                   => 'Created',
            'updated_by'                => $userId,
        ]);

        return $pdc->fresh();
    }

    public function markSigned(Pdc $pdc, string $signedImage, ?int $userId = null): Pdc
    {
        $this->assertStatus($pdc, 'Created', 'signed');

        $pdc->update([
            'signed_cheque_image' => $signedImage,
            'status'               => 'Signed',
            'updated_by'            => $userId,
        ]);

        return $pdc->fresh();
    }

    public function markIssued(Pdc $pdc, array $data, ?int $userId = null): Pdc
    {
        $this->assertStatus($pdc, 'Signed', 'issued');

        $method = $data['issue_method']; // handed_to_vendor | bank_deposit

        if ($method === 'handed_to_vendor') {
            if (empty($data['receiver_name']) || empty($data['receipt_signed_image'])) {
                throw new \Exception('Receiver name and a signed receipt image are required when handing a cheque directly to the vendor.');
            }
        } elseif ($method === 'bank_deposit') {
            if (empty($data['bank_slip_image'])) {
                throw new \Exception('A bank deposit slip image is required.');
            }
        } else {
            throw new \Exception('Invalid issue method.');
        }

        $pdc->update([
            'issue_method'          => $method,
            'receiver_name'         => $data['receiver_name'] ?? null,
            'receiver_contact'      => $data['receiver_contact'] ?? null,
            'receiver_cnic'         => $data['receiver_cnic'] ?? null,
            'receipt_signed_image'  => $data['receipt_signed_image'] ?? null,
            'bank_slip_image'       => $data['bank_slip_image'] ?? null,
            'issued_date'           => $data['issued_date'] ?? now()->toDateString(),
            'status'                 => 'Issued',
            'updated_by'             => $userId,
        ]);

        return $pdc->fresh();
    }

    // THIS is what actually hits vendor ledger + bank balance
    public function markCleared(Pdc $pdc, ?string $clearedDate = null, ?int $userId = null): Pdc
    {
        return DB::transaction(function () use ($pdc, $clearedDate, $userId) {
            $this->assertStatus($pdc, 'Issued', 'cleared');

            $apAccountId = $this->mappingService->accountId('accounts_payable');
            if (!$pdc->bank_account_id || !$apAccountId) {
                throw new \Exception('Bank account or Accounts Payable mapping missing — cannot post clearing voucher.');
            }

            $lines = [
                [
                    'account_id' => $apAccountId,
                    'debit'      => (float) $pdc->amount,
                    'credit'     => 0,
                    'party_type' => $pdc->party_type,
                    'party_id'   => $pdc->party_id,
                ],
                ['account_id' => $pdc->bank_account_id, 'debit' => 0, 'credit' => (float) $pdc->amount],
            ];

            $this->voucherService->post(
                'system',
                $clearedDate ?? now()->toDateString(),
                $lines,
                "PDC {$pdc->pdc_no} cleared — cheque #{$pdc->cheque_no}",
                'Pdc',
                $pdc->id,
                $userId
            );

            $pdc->update([
                'status'        => 'Cleared',
                'cleared_date'  => $clearedDate ?? now()->toDateString(),
                'updated_by'    => $userId,
            ]);

            return $pdc->fresh();
        });
    }

    // Bounced — reverses nothing accounting-wise (nothing was posted until
    // Cleared), but flags this cheque needs to be replaced/re-issued.
    public function markBounced(Pdc $pdc, string $reason, ?int $userId = null): Pdc
    {
        $this->assertStatus($pdc, 'Issued', 'bounced');

        $pdc->update([
            'status'          => 'Bounced',
            'bounced_date'    => now()->toDateString(),
            'bounced_reason'  => $reason,
            'updated_by'      => $userId,
        ]);

        return $pdc->fresh();
    }

    private function assertStatus(Pdc $pdc, string $expected, string $action): void
    {
        if ($pdc->status !== $expected) {
            throw new \Exception("Cannot mark as {$action} — this PDC is currently {$pdc->status}, expected {$expected}.");
        }
    }
}