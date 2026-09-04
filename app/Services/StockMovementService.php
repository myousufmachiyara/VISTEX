<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\LocationStockLedger;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockMovementService
{
    public function __construct(private DocumentNumberService $numberService) {}

    public function create(array $data, array $items, ?int $userId = null): StockMovement
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter at least one item to move.');
            }

            $type = $data['movement_type'];

            if ($type === 'warehouse_to_vendor' && empty($data['lot_no'])) {
                throw new \Exception('Lot # is required when moving stock to a vendor (as assigned by the mill).');
            }

            $movement = StockMovement::create([
                'movement_no'        => $this->numberService->next('stock_movement', 'stock_movements', 'movement_no', 'SM'),
                'movement_type'      => $type,
                'from_location_id'   => $data['from_location_id'],
                'to_location_id'     => $data['to_location_id'],
                'lot_no'             => $data['lot_no'] ?? null,
                'movement_date'      => $data['movement_date'],
                'status'             => 'PendingApproval',
                'remarks'            => $data['remarks'] ?? null,
                'attachments'        => $data['attachments'] ?? null,
                'created_by'         => $userId,
                'updated_by'         => $userId,
            ]);

            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $product = Product::findOrFail($item['product_id']);

                // For vendor_to_warehouse (returning goods, e.g. lot exhausted / job done),
                // verify stock actually exists at the source+lot before allowing the movement.
                $lotFilter = ($type === 'vendor_to_warehouse') ? ($item['source_lot_no'] ?? null) : null;
                $available = LocationStockLedger::balance($data['from_location_id'], $product->id, 'fresh', $lotFilter);

                if ($qty > $available + 0.001) {
                    throw new \Exception("Insufficient stock of {$product->name} at source location" . ($lotFilter ? " (lot {$lotFilter})" : '') . " — available: " . round($available, 3) . ".");
                }

                $rate = $product->weightedAverageCost($data['from_location_id']);
                $amount = round($qty * $rate, 2);

                StockMovementItem::create([
                    'stock_movement_id' => $movement->id,
                    'product_id'        => $product->id,
                    'quantity'          => $qty,
                    'amount'            => $amount,
                ]);
            }

            return $movement->load('items.product', 'fromLocation', 'toLocation');
        });
    }

    // Destination location in-charge approves — THIS is what moves the
    // stock (out of source, into destination, tagged with the new lot#
    // if applicable).
    public function approve(StockMovement $movement, int $approverId): StockMovement
    {
        return DB::transaction(function () use ($movement, $approverId) {

            if ($movement->status !== 'PendingApproval') {
                throw new \Exception('This movement has already been ' . strtolower($movement->status) . '.');
            }

            foreach ($movement->items as $item) {
                // Out of source
                LocationStockLedger::create([
                    'doc_no'          => $movement->movement_no,
                    'location_id'     => $movement->from_location_id,
                    'product_id'      => $item->product_id,
                    'status'          => 'fresh',
                    'lot_no'          => null, // source ledger isn't lot-tagged unless it already was
                    'quantity'        => -$item->quantity,
                    'amount'          => -$item->amount,
                    'reference_type'  => 'StockMovement',
                    'reference_id'    => $movement->id,
                    'entry_date'      => $movement->movement_date,
                ]);

                // Into destination, tagged with the movement's lot# (if any)
                LocationStockLedger::create([
                    'doc_no'          => $movement->movement_no,
                    'location_id'     => $movement->to_location_id,
                    'product_id'      => $item->product_id,
                    'status'          => 'fresh',
                    'lot_no'          => $movement->lot_no,
                    'quantity'        => $item->quantity,
                    'amount'          => $item->amount,
                    'reference_type'  => 'StockMovement',
                    'reference_id'    => $movement->id,
                    'entry_date'      => $movement->movement_date,
                ]);
            }

            $movement->update([
                'status'      => 'Approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
                'updated_by'  => $approverId,
            ]);

            return $movement->fresh();
        });
    }

    public function reject(StockMovement $movement, int $approverId, string $reason): StockMovement
    {
        if ($movement->status !== 'PendingApproval') {
            throw new \Exception('This movement has already been ' . strtolower($movement->status) . '.');
        }

        $movement->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'updated_by' => $approverId]);
        return $movement->fresh();
    }
}