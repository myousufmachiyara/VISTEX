<?php

namespace App\Services;

use App\Models\Challan;
use App\Models\ChallanDirectItem;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class ChallanService
{
    public function __construct(private DocumentNumberService $numberService) {}

    public function create(array $data, ?int $userId = null): Challan
    {
        return DB::transaction(function () use ($data, $userId) {

            $po = PurchaseOrder::findOrFail($data['purchase_order_id']);

            $this->assertPoIsReceivable($po);

            $images = $data['challan_images'] ?? [];
            if (empty($images)) {
                throw new \Exception('At least one photo of the challan is required.');
            }

            return Challan::create([
                'challan_no'          => $this->numberService->next('challan', 'challans', 'challan_no', 'CHL'),
                'entry_type'          => 'po',
                'purchase_order_id'   => $po->id,
                'vendor_challan_no'   => $data['vendor_challan_no'] ?? null,
                'received_date'       => $data['received_date'],
                'challan_images'      => $images,
                'status'              => 'AwaitingInspection',
                'remarks'             => $data['remarks'] ?? null,
                'received_by'         => $userId,
                'created_by'          => $userId,
                'updated_by'          => $userId,
            ]);
        });
    }

    public function createDirect(array $data, array $items, ?int $userId = null): Challan
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter at least one item.');
            }

            $images = $data['challan_images'] ?? [];
            if (empty($images)) {
                throw new \Exception('At least one photo of the challan is required.');
            }

            $challan = Challan::create([
                'challan_no'      => $this->numberService->next('challan', 'challans', 'challan_no', 'CHL'),
                'entry_type'      => 'direct',
                'vendor_id'       => $data['vendor_id'],
                'received_date'   => $data['received_date'],
                'challan_images'  => $images,
                'status'          => 'AwaitingInspection',
                'remarks'         => $data['remarks'] ?? null,
                'received_by'     => $userId,
                'created_by'      => $userId,
                'updated_by'      => $userId,
            ]);

            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $price = (float) $item['unit_price'];

                ChallanDirectItem::create([
                    'challan_id'          => $challan->id,
                    'description'         => $item['description'],
                    'quantity'            => $qty,
                    'unit_price'          => $price,
                    'expense_account_id'  => $item['expense_account_id'],
                    'amount'              => round($qty * $price, 2),
                ]);
            }

            return $challan->load('directItems.expenseAccount', 'vendor');
        });
    }

    public function approveDirect(Challan $challan, int $approverId): Challan
    {
        return DB::transaction(function () use ($challan, $approverId) {

            if ($challan->entry_type !== 'direct') {
                throw new \Exception('This is not a direct (no-PO) entry.');
            }
            if ($challan->status !== 'AwaitingInspection') {
                throw new \Exception('This entry has already been processed.');
            }

            $lines = [];
            foreach ($challan->directItems as $item) {
                $lines[] = ['account_id' => $item->expense_account_id, 'debit' => (float) $item->amount, 'credit' => 0];
            }

            $apAccountId = app(AccountMappingService::class)->accountId('accounts_payable');
            if (!$apAccountId) {
                throw new \Exception('Accounts Payable mapping is not configured.');
            }

            $total = $challan->directItems->sum('amount');
            $lines[] = ['account_id' => $apAccountId, 'debit' => 0, 'credit' => $total, 'party_type' => 'vendor', 'party_id' => $challan->vendor_id];

            app(VoucherService::class)->post(
                'system',
                $challan->received_date->format('Y-m-d'),
                $lines,
                "Direct Receiving {$challan->challan_no} — no PO",
                'Challan',
                $challan->id,
                $approverId
            );

            $challan->update(['status' => 'Processed', 'updated_by' => $approverId]);

            return $challan->fresh();
        });
    }

    public function rejectDirect(Challan $challan, int $approverId, string $reason): Challan
    {
        if ($challan->entry_type !== 'direct') {
            throw new \Exception('This is not a direct (no-PO) entry.');
        }
        if ($challan->status !== 'AwaitingInspection') {
            throw new \Exception('This entry has already been processed.');
        }

        $challan->update(['status' => 'Processed', 'remarks' => trim(($challan->remarks ?? '') . ' [Rejected: ' . $reason . ']'), 'updated_by' => $approverId]);

        return $challan->fresh();
    }

    private function assertPoIsReceivable(PurchaseOrder $po): void
    {
        if ($po->type === 'purchase' && !in_array($po->status, ['Approved', 'PartiallyReceived'])) {
            throw new \Exception('This Purchase Order is not yet Approved, or is already fully received.');
        }

        if (in_array($po->type, ['weaving', 'processing']) && !in_array($po->status, ['Issued', 'PartiallyReceived'])) {
            throw new \Exception('This Purchase Order has not been Issued yet, or is already fully received.');
        }
    }

    public function markProcessed(Challan $challan): void
    {
        $challan->update(['status' => 'Processed']);
    }
}