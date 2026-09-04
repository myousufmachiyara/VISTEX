<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseReceiving;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(private DocumentNumberService $numberService) {}

    // $items: [ ['purchase_receiving_item_id'=>.., 'quantity_returned'=>..], ... ]
    public function create(array $data, array $items, ?int $userId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $receiving = PurchaseReceiving::with('items')->findOrFail($data['purchase_receiving_id']);

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity_returned'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter a quantity for at least one item being returned.');
            }

            $return = PurchaseReturn::create([
                'return_no'               => $this->numberService->next('purchase_return', 'purchase_returns', 'return_no', 'RET'),
                'purchase_receiving_id'   => $receiving->id,
                'return_date'              => $data['return_date'],
                'remarks'                  => $data['remarks'] ?? null,
                'proof_images'             => $data['proof_images'] ?? null,
                'created_by'                => $userId,
            ]);

            foreach ($items as $item) {
                $receivingItem = $receiving->items->firstWhere('id', $item['purchase_receiving_item_id']);
                if (!$receivingItem) throw new \Exception('Invalid item — not part of this receiving.');

                $pendingReturn = round((float) $receivingItem->quantity_rejected - (float) $receivingItem->quantity_returned, 3);
                $qty = round((float) $item['quantity_returned'], 3);

                if ($qty > $pendingReturn + 0.001) {
                    throw new \Exception("Cannot return {$qty} — only {$pendingReturn} is pending return for this item.");
                }

                PurchaseReturnItem::create([
                    'purchase_return_id'          => $return->id,
                    'purchase_receiving_item_id' => $receivingItem->id,
                    'quantity_returned'           => $qty,
                ]);

                $receivingItem->increment('quantity_returned', $qty);
            }

            return $return->load('items.purchaseReceivingItem.product');
        });
    }
}