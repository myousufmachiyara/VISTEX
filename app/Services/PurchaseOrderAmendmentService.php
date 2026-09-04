<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAmendment;
use Illuminate\Support\Facades\DB;

class PurchaseOrderAmendmentService
{
    // Fields that CAN be amended — deliberately restrictive, not
    // every column (e.g. vendor_id, type should never be amendable —
    // that would need a whole new PO, not an amendment)
    private const AMENDABLE_FIELDS = [
        'expected_date', 'total_meters_required', 'rate_per_pick', 'weaving_rate',
        'payment_term_type', 'payment_term_days', 'remarks',
    ];

    public function propose(PurchaseOrder $po, array $newValues, string $reason, int $userId): PurchaseOrderAmendment
    {
        return DB::transaction(function () use ($po, $newValues, $reason, $userId) {

            $filtered = array_intersect_key($newValues, array_flip(self::AMENDABLE_FIELDS));
            if (empty($filtered)) {
                throw new \Exception('No amendable fields were changed.');
            }

            $previousValues = [];
            foreach (array_keys($filtered) as $field) {
                $previousValues[$field] = $po->{$field};
            }

            $nextAmendmentNo = ($po->amendments()->max('amendment_no') ?? 0) + 1;

            return PurchaseOrderAmendment::create([
                'purchase_order_id' => $po->id,
                'amendment_no'      => $nextAmendmentNo,
                'previous_values'   => $previousValues,
                'new_values'        => $filtered,
                'reason'            => $reason,
                'requested_by'      => $userId,
                'status'            => 'Pending',
            ]);
        });
    }

    // Superadmin approval — applies the change to the ORIGINAL PO row's
    // "current effective" tracking. Per your spec, the original row's
    // historical values stay, but revision_no bumps and the PO reflects
    // the amended values going forward via getEffective().
    public function approve(PurchaseOrderAmendment $amendment, int $approverId): PurchaseOrderAmendment
    {
        return DB::transaction(function () use ($amendment, $approverId) {

            if ($amendment->status !== 'Pending') {
                throw new \Exception('This amendment has already been ' . strtolower($amendment->status) . '.');
            }

            $po = $amendment->purchaseOrder;

            // Apply new values directly to the PO row — the "original"
            // record for audit purposes lives in previous_values on this
            // amendment row, and on any prior amendment rows before it.
            $po->update(array_merge($amendment->new_values, [
                'revision_no' => $po->revision_no + 1,
                'updated_by'  => $approverId,
            ]));

            $amendment->update(['status' => 'Approved', 'approved_by' => $approverId, 'approved_at' => now()]);

            return $amendment->fresh();
        });
    }

    public function reject(PurchaseOrderAmendment $amendment, int $approverId, string $reason): PurchaseOrderAmendment
    {
        if ($amendment->status !== 'Pending') {
            throw new \Exception('This amendment has already been ' . strtolower($amendment->status) . '.');
        }

        $amendment->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'approved_by' => $approverId, 'approved_at' => now()]);
        return $amendment->fresh();
    }
}