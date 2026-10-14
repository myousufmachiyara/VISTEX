<?php

namespace App\Services;

use App\Models\{Location, LocationStockLedger, Product, StockMovement, StockMovementItem};
use Illuminate\Support\Facades\DB;

/**
 * Stock transfer with gate pass.
 *   create   → Pending
 *   approve  → Approved (gate pass issued); vendor → warehouse is dispatched at once
 *   dispatch → Dispatched: gate verified the exit; stock leaves the source (oldest lot first) and is in transit
 *   receive  → Received: destination in-charge confirms; a mill destination gives its own lot #
 */
class StockMovementService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private NotificationService $notifications,
    ) {}

    public function create(array $data, array $items, ?int $userId = null): StockMovement
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0 && !empty($i['product_id'])));
            if (empty($items)) throw new \Exception('Enter at least one item to move.');

            $type = $data['movement_type'];
            $from = Location::findOrFail($data['from_location_id']);
            $to = Location::findOrFail($data['to_location_id']);
            $this->assertLocations($type, $from, $to);

            // Stock check: what's at the source minus what other open movements already hold
            $want = [];
            foreach ($items as $i) {
                $k = $i['product_id'] . '|' . ($i['source_lot_no'] ?? '');
                $want[$k] = ($want[$k] ?? 0) + (float) $i['quantity'];
            }
            foreach ($want as $k => $qty) {
                [$productId, $lot] = explode('|', $k);
                $lot = $lot === '' ? null : $lot;
                $free = LocationStockLedger::balance($from->id, (int) $productId, 'fresh', $lot) - $this->held($from->id, (int) $productId, $lot);
                if ($qty > $free + 0.001) {
                    $name = Product::find($productId)->name ?? 'item';
                    throw new \Exception("Not enough {$name} at {$from->name}" . ($lot ? " (lot {$lot})" : '') . ' — free stock ' . round(max(0, $free), 3) . ", requested {$qty}.");
                }
            }

            $movement = StockMovement::create([
                'movement_no'       => $this->numberService->next('stock_movement', 'stock_movements', 'movement_no', 'SM'),
                'movement_type'     => $type,
                'from_location_id'  => $from->id,
                'to_location_id'    => $to->id,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'movement_date'     => $data['movement_date'],
                'status'            => StockMovement::PENDING,
                'remarks'           => $data['remarks'] ?? null,
                'attachments'       => $data['attachments'] ?? null,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            foreach ($items as $item) {
                StockMovementItem::create([
                    'stock_movement_id' => $movement->id,
                    'product_id'        => $item['product_id'],
                    'source_lot_no'     => ($item['source_lot_no'] ?? '') !== '' ? $item['source_lot_no'] : null,
                    'quantity'          => round((float) $item['quantity'], 3),
                    'amount'            => 0, // valued at dispatch
                ]);
            }

            $this->notifyLocation($from, 'stock_movement_pending', 'Stock Movement to Approve',
                "{$movement->movement_no}: {$from->name} → {$to->name} needs your approval.", $movement->id);

            return $movement->load('items.product', 'fromLocation', 'toLocation');
        });
    }

    private function assertLocations(string $type, Location $from, Location $to): void
    {
        if ($from->id === $to->id) throw new \Exception('From and To locations must be different.');
        $fromVendor = (bool) $from->vendor_id; $toVendor = (bool) $to->vendor_id;
        $ok = match ($type) {
            'warehouse_to_warehouse' => !$fromVendor && !$toVendor,
            'warehouse_to_vendor'    => !$fromVendor && $toVendor,
            'vendor_to_warehouse'    => $fromVendor && !$toVendor,
            default                  => false,
        };
        if (!$ok) throw new \Exception('The selected locations do not match the movement type ' . (StockMovement::TYPES[$type] ?? $type) . '.');
    }

    // Quantity already promised to open (not yet dispatched) movements from this location
    private function held(int $locationId, int $productId, ?string $lot, ?int $exceptId = null): float
    {
        return (float) StockMovementItem::where('product_id', $productId)
            ->when($lot !== null, fn($q) => $q->where('source_lot_no', $lot))
            ->whereHas('stockMovement', fn($q) => $q->where('from_location_id', $locationId)
                ->whereIn('status', [StockMovement::PENDING, StockMovement::APPROVED])
                ->when($exceptId, fn($q2) => $q2->where('id', '!=', $exceptId)))
            ->sum('quantity');
    }

    public function approve(StockMovement $movement, int $userId): StockMovement
    {
        return DB::transaction(function () use ($movement, $userId) {
            $movement = StockMovement::lockForUpdate()->findOrFail($movement->id);
            if ($movement->status !== StockMovement::PENDING) throw new \Exception("This movement is already {$movement->status_label}.");

            $movement->update(['status' => StockMovement::APPROVED, 'approved_by' => $userId, 'approved_at' => now(), 'updated_by' => $userId]);

            // Leaving a vendor's premises: there is no gate of ours to pass, so it is on its way now
            if (!$movement->needsGateExit()) return $this->dispatch($movement, [], $userId);

            $this->notifications->notifyRole('gatekeeper', 'gate_pass_issued', 'Gate Pass Issued',
                "{$movement->movement_no}: {$movement->fromLocation->name} → {$movement->toLocation->name} may leave after gate check.", 'stock_movement', $movement->id);
            return $movement->fresh();
        });
    }

    /** Gate check passed: stock leaves the source and is in transit to the destination. */
    public function dispatch(StockMovement $movement, array $gate, int $userId): StockMovement
    {
        return DB::transaction(function () use ($movement, $gate, $userId) {
            $movement = StockMovement::with('items.product', 'fromLocation', 'toLocation')->lockForUpdate()->findOrFail($movement->id);
            if ($movement->status !== StockMovement::APPROVED) {
                throw new \Exception($movement->status === StockMovement::PENDING ? 'This gate pass has not been approved yet.' : "This movement is already {$movement->status_label}.");
            }

            foreach ($movement->items as $item) {
                $avg = $item->product->weightedAverageCost($movement->from_location_id);
                $total = 0;
                foreach (LocationStockLedger::allocate($movement->from_location_id, $item->product_id, (float) $item->quantity, $item->source_lot_no) as $part) {
                    // Each lot carries its own cost (a lot is one PO's price); unlotted stock uses the location average
                    $rate = LocationStockLedger::lotRate($movement->from_location_id, $item->product_id, $part['lot_no'], $avg);
                    $amount = round($part['quantity'] * $rate, 2);
                    $total += $amount;
                    $this->post($movement, $movement->from_location_id, $item->product_id, 'fresh', $part['lot_no'], -$part['quantity'], -$amount);
                    $this->post($movement, $movement->to_location_id, $item->product_id, 'in_transit', $part['lot_no'], $part['quantity'], $amount);
                }
                $item->update(['amount' => round($total, 2)]);
            }

            $clean = fn($v) => ($v = trim((string) $v)) === '' ? null : $v;
            $vehicle = $clean($gate['vehicle_no'] ?? null);
            $movement->update([
                'status'         => StockMovement::DISPATCHED,
                'vehicle_no'     => $vehicle ? strtoupper($vehicle) : null,
                'driver_name'    => $clean($gate['driver_name'] ?? null),
                'driver_contact' => $clean($gate['driver_contact'] ?? null),
                'gate_images'    => $gate['gate_images'] ?? null,
                'gate_remarks'   => $clean($gate['gate_remarks'] ?? null),
                'dispatched_by'  => $userId,
                'dispatched_at'  => now(),
                'updated_by'     => $userId,
            ]);

            $this->notifyLocation($movement->toLocation, 'stock_movement_dispatched', 'Stock On The Way',
                "{$movement->movement_no} left {$movement->fromLocation->name}. Receive it when it arrives.", $movement->id);

            return $movement->fresh('items.product');
        });
    }

    /** Destination confirms arrival. A mill destination's lot # is required and tags the stock there. */
    public function receive(StockMovement $movement, array $data, int $userId): StockMovement
    {
        return DB::transaction(function () use ($movement, $data, $userId) {
            $movement = StockMovement::lockForUpdate()->findOrFail($movement->id);
            if ($movement->status !== StockMovement::DISPATCHED) {
                throw new \Exception($movement->status === StockMovement::RECEIVED ? 'This movement has already been received.' : 'Only goods that have left through the gate can be received.');
            }

            $millLot = trim((string) ($data['lot_no'] ?? ''));
            if ($movement->needsMillLot() && $millLot === '') throw new \Exception("Enter the mill's lot # for this delivery.");

            $transit = LocationStockLedger::where('reference_type', 'StockMovement')->where('reference_id', $movement->id)
                ->where('location_id', $movement->to_location_id)->where('status', 'in_transit')->get();

            foreach ($transit as $row) {
                $lot = $millLot !== '' ? $millLot : $row->lot_no;
                $this->post($movement, $movement->to_location_id, $row->product_id, 'in_transit', $row->lot_no, -(float) $row->quantity, -(float) $row->amount);
                $this->post($movement, $movement->to_location_id, $row->product_id, 'fresh', $lot, (float) $row->quantity, (float) $row->amount);
            }

            $movement->update([
                'status'          => StockMovement::RECEIVED,
                'lot_no'          => $millLot !== '' ? $millLot : $movement->lot_no,
                'received_by'     => $userId,
                'received_at'     => now(),
                'receive_remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
                'updated_by'      => $userId,
            ]);
            return $movement->fresh('items.product');
        });
    }

    public function reject(StockMovement $movement, int $userId, string $reason): StockMovement
    {
        if (!in_array($movement->status, [StockMovement::PENDING, StockMovement::APPROVED], true)) {
            throw new \Exception("Goods have already left — this movement is {$movement->status_label}.");
        }
        $movement->update(['status' => StockMovement::REJECTED, 'rejection_reason' => $reason, 'updated_by' => $userId]);
        return $movement->fresh();
    }

    private function post(StockMovement $m, int $locationId, int $productId, string $status, ?string $lot, float $qty, float $amount): void
    {
        LocationStockLedger::create([
            'doc_no' => $m->movement_no, 'location_id' => $locationId, 'product_id' => $productId,
            'status' => $status, 'lot_no' => $lot, 'quantity' => round($qty, 3), 'amount' => round($amount, 2),
            'reference_type' => 'StockMovement', 'reference_id' => $m->id, 'entry_date' => $m->movement_date,
        ]);
    }

    private function notifyLocation(?Location $loc, string $type, string $title, string $body, int $id): void
    {
        if ($loc && $loc->in_charge_user_id) $this->notifications->notifyUsers([$loc->in_charge_user_id], $type, $title, $body, 'stock_movement', $id);
    }
}
