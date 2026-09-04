<?php

namespace App\Services;

use App\Models\Challan;
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

    // Purchasing POs must be Approved; Weaving/Processing POs must be Issued
    private function assertPoIsReceivable(PurchaseOrder $po): void
    {
        if ($po->type === 'purchase' && $po->status !== 'Approved') {
            throw new \Exception('This Purchase Order is not yet Approved.');
        }

        if (in_array($po->type, ['weaving', 'processing']) && $po->status !== 'Issued') {
            throw new \Exception('This Purchase Order has not been Issued yet.');
        }
    }

    // Called by Stage 5 (Receiving) once an action (Approve/Reject/Return/Amendment) is taken
    public function markProcessed(Challan $challan): void
    {
        $challan->update(['status' => 'Processed']);
    }
}