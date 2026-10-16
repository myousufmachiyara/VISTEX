<?php

namespace App\Services;

use App\Models\{Location, LocationStockLedger, Product, SaleOrder, SaleOrderItem, StockMovement, TaxMaster};
use Illuminate\Support\Facades\DB;

/**
 * Sale orders for yarn / greige / other stock.
 *   create → Pending → approve → Approved
 *   delivery → a gate pass (stock movement, warehouse → customer): warehouse in-charge approves, gate checks it out
 *   at the gate → stock leaves (oldest lot first, or the lot ordered), cost of goods sold posted, order delivered
 * Invoicing the customer follows from delivered quantities.
 */
class SaleOrderService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private VoucherService $vouchers,
        private AccountMappingService $mapping,
        private NotificationService $notifications,
        private StockMovementService $movements,
    ) {}

    public function create(array $data, array $items, int $userId): SaleOrder
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $order = SaleOrder::create($this->header($data, $userId) + [
                'so_no' => $this->numbers->next('sale_order', 'sale_orders', 'so_no', 'SO'),
                'status' => 'Pending', 'created_by' => $userId,
            ]);
            $this->writeItems($order, $items);
            $this->notifications->notifyRole('superadmin', 'sale_order_pending', 'Sale Order Awaiting Approval',
                "{$order->so_no} for {$order->customer->name} needs approval.", 'sale_order', $order->id);
            return $order->fresh('items.product', 'customer');
        });
    }

    public function update(SaleOrder $order, array $data, array $items, int $userId): SaleOrder
    {
        if (!$order->isEditable()) throw new \Exception("{$order->so_no} is {$order->status_label} and can no longer be edited.");
        return DB::transaction(function () use ($order, $data, $items, $userId) {
            $order->update($this->header($data, $userId) + ['status' => 'Pending', 'rejection_reason' => null]); // edited → back for approval
            $order->items()->delete();
            $this->writeItems($order, $items);
            return $order->fresh('items.product', 'customer');
        });
    }

    public function approve(SaleOrder $order, int $userId): SaleOrder
    {
        if ($order->status !== 'Pending') throw new \Exception('Only a Pending sale order can be approved.');
        $order->update(['status' => 'Approved', 'approved_by' => $userId, 'approved_at' => now(), 'updated_by' => $userId]);
        return $order->fresh();
    }

    public function reject(SaleOrder $order, int $userId, string $reason): SaleOrder
    {
        if ($order->status !== 'Pending') throw new \Exception('Only a Pending sale order can be rejected.');
        $order->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'updated_by' => $userId]);
        return $order->fresh();
    }

    /** Short-close: nothing more will be delivered. */
    public function close(SaleOrder $order, int $userId): SaleOrder
    {
        if (!$order->canDeliver()) throw new \Exception("{$order->so_no} is {$order->status_label}.");
        if ($order->deliveries()->whereIn('status', [StockMovement::PENDING, StockMovement::APPROVED])->exists()) {
            throw new \Exception('A delivery for this order is still waiting at the gate. Reject it or let it go first.');
        }
        $order->update(['status' => 'Closed', 'updated_by' => $userId]);
        return $order->fresh();
    }

    /** $lines: [['sale_order_item_id', 'quantity'], ...] — creates the delivery gate pass. */
    public function createDelivery(SaleOrder $order, array $data, array $lines, int $userId): StockMovement
    {
        return DB::transaction(function () use ($order, $data, $lines, $userId) {
            $order = SaleOrder::with('items.product')->lockForUpdate()->findOrFail($order->id);
            if (!$order->canDeliver()) throw new \Exception("{$order->so_no} is {$order->status_label} — deliveries need an approved order.");

            $items = [];
            foreach ($lines as $l) {
                $qty = round((float) ($l['quantity'] ?? 0), 3);
                if ($qty <= 0) continue;
                $item = $order->items->firstWhere('id', (int) ($l['sale_order_item_id'] ?? 0));
                if (!$item) throw new \Exception('A line does not belong to this sale order.');
                if ($qty > $item->open_qty + 0.001) {
                    throw new \Exception("{$item->product->name}: only {$item->open_qty} left to deliver, you entered {$qty}.");
                }
                $items[] = ['product_id' => $item->product_id, 'quantity' => $qty, 'source_lot_no' => $item->lot_no, 'sale_order_item_id' => $item->id];
            }
            if (empty($items)) throw new \Exception('Enter the quantity to deliver for at least one line.');

            return $this->movements->create([
                'movement_type' => StockMovement::SALE, 'from_location_id' => $data['location_id'] ?? $order->location_id,
                'sale_order_id' => $order->id, 'customer_id' => $order->customer_id,
                'movement_date' => $data['delivery_date'] ?? now()->toDateString(),
                'remarks' => trim("Sale {$order->so_no} — " . ($data['remarks'] ?? '')) ?: null,
            ], $items, $userId);
        });
    }

    /** Called when a sale delivery passes the gate: cost of goods sold + delivered quantities. */
    public function delivered(StockMovement $movement, int $userId): void
    {
        $cogs = [];
        $total = 0;
        foreach ($movement->items as $mi) {
            $cat = $mi->product->category;
            $stockAcc = $cat->stock_account_id ?? $this->mapping->accountId('stock_in_hand');
            $cogsAcc = $cat->cogs_account_id ?? $this->mapping->accountId('cogs');
            $k = "{$cogsAcc}|{$stockAcc}";
            $cogs[$k] = ($cogs[$k] ?? 0) + (float) $mi->amount;
            $total += (float) $mi->amount;
            if ($mi->sale_order_item_id) SaleOrderItem::whereKey($mi->sale_order_item_id)->increment('delivered_qty', (float) $mi->quantity);
            if ($mi->job_item_id) {
                // Customer order lines are in meters: pieces × meters per piece
                $mpu = (float) (($mi->product->getAttribute('attributes') ?? [])['meters_per_unit'] ?? 0);
                \App\Models\JobItem::whereKey($mi->job_item_id)->increment('quantity_fulfilled', round((float) $mi->quantity * ($mpu ?: 1), 3));
            }
        }
        $lines = [];
        foreach ($cogs as $k => $amt) {
            if ($amt <= 0) continue;
            [$cogsAcc, $stockAcc] = explode('|', $k);
            $lines[] = ['account_id' => (int) $cogsAcc, 'debit' => round($amt, 2), 'credit' => 0];
            $lines[] = ['account_id' => (int) $stockAcc, 'debit' => 0, 'credit' => round($amt, 2)];
        }
        if (count($lines) >= 2) {
            $this->vouchers->post('system', now()->toDateString(), $lines,
                "Cost of goods sold — {$movement->movement_no} (" . ($movement->saleOrder->so_no ?? $movement->job->job_no ?? '') . ')', 'StockMovement', $movement->id, $userId);
        }
        $movement->update(['cogs_amount' => round($total, 2)]);
        if ($movement->saleOrder) $this->refreshStatus($movement->saleOrder);
    }

    public function refreshStatus(SaleOrder $order): void
    {
        $order->load('items');
        // Delivered orders reopen when goods come back; closed / rejected orders stay as they are
        if (!$order->canDeliver() && $order->status !== 'Delivered') return;
        $ordered = $order->items->sum('quantity'); $done = $order->items->sum('delivered_qty');
        $status = $done <= 0.0005 ? 'Approved' : ($done + 0.001 >= $ordered ? 'Delivered' : 'PartiallyDelivered');
        if ($status !== $order->status) $order->update(['status' => $status]);
    }

    /** Stock that can be sold from a warehouse: product, lot, free qty (fresh, ungraded or any grade). */
    public function sellable(int $locationId): array
    {
        return LocationStockLedger::query()->join('products as p', 'p.id', '=', 'location_stock_ledger.product_id')
            ->join('product_categories as c', 'c.id', '=', 'p.category_id')
            ->where('location_stock_ledger.location_id', $locationId)->where('location_stock_ledger.status', 'fresh')
            ->whereIn('c.code', SaleOrder::CATEGORIES)
            ->groupBy('location_stock_ledger.product_id', 'p.name', 'c.name', 'location_stock_ledger.lot_no')
            ->selectRaw('location_stock_ledger.product_id, p.name AS product, c.name AS category, location_stock_ledger.lot_no, SUM(location_stock_ledger.quantity) AS qty, SUM(location_stock_ledger.amount) AS amount')
            ->havingRaw('SUM(location_stock_ledger.quantity) > 0.0005')->orderBy('c.name')->orderBy('p.name')->get()
            ->map(fn($r) => ['product_id' => (int) $r->product_id, 'product' => $r->product, 'category' => $r->category, 'lot_no' => $r->lot_no,
                             'qty' => round((float) $r->qty, 3), 'cost' => (float) $r->qty > 0 ? round((float) $r->amount / (float) $r->qty, 2) : 0])->all();
    }

    private function header(array $data, int $userId): array
    {
        $loc = Location::findOrFail($data['location_id']);
        if ($loc->vendor_id) throw new \Exception('Sales are dispatched from one of our warehouses.');
        $gst = (bool) ($data['gst_applicable'] ?? false);
        $type = $data['payment_term_type'] ?? 'cash';
        return [
            'customer_id' => $data['customer_id'], 'location_id' => $loc->id,
            'broker_id' => $data['broker_id'] ?? null, 'broker_commission_amount' => !empty($data['broker_id']) ? round((float) ($data['broker_commission_amount'] ?? 0), 2) : 0,
            'customer_po_no' => trim((string) ($data['customer_po_no'] ?? '')) ?: null,
            'order_date' => $data['order_date'], 'delivery_date' => $data['delivery_date'] ?? null,
            'payment_term_type' => $type, 'payment_term_days' => in_array($type, ['credit', 'pdc']) ? ($data['payment_term_days'] ?? null) : null,
            'gst_applicable' => $gst, 'tax_id' => $gst ? ($data['tax_id'] ?? null) : null,
            'remarks' => $data['remarks'] ?? null, 'updated_by' => $userId,
        ];
    }

    private function writeItems(SaleOrder $order, array $items): void
    {
        $items = array_values(array_filter($items, fn($i) => !empty($i['product_id']) && (float) ($i['quantity'] ?? 0) > 0));
        if (empty($items)) throw new \Exception('Add at least one item with a quantity.');
        $subtotal = 0;
        foreach ($items as $i) {
            $p = Product::with('category')->findOrFail($i['product_id']);
            if (!in_array($p->category->code ?? '', SaleOrder::CATEGORIES, true)) {
                throw new \Exception("{$p->name} ({$p->category->name}) is sold through a Customer Order, not a sale order.");
            }
            $qty = round((float) $i['quantity'], 3); $rate = round((float) ($i['rate'] ?? 0), 4);
            if ($rate <= 0) throw new \Exception("Enter a rate for {$p->name}.");
            $amount = round($qty * $rate, 2);
            $subtotal += $amount;
            SaleOrderItem::create([
                'sale_order_id' => $order->id, 'product_id' => $p->id, 'lot_no' => trim((string) ($i['lot_no'] ?? '')) ?: null,
                'description' => trim((string) ($i['description'] ?? '')) ?: null, 'quantity' => $qty, 'rate' => $rate, 'amount' => $amount,
            ]);
        }
        $rate = $order->gst_applicable && $order->tax_id ? (float) (TaxMaster::find($order->tax_id)->rate ?? 0) : 0;
        $gst = round($subtotal * $rate / 100, 2);
        $order->update(['subtotal' => round($subtotal, 2), 'gst_rate' => $rate, 'gst_amount' => $gst, 'total_amount' => round($subtotal + $gst, 2)]);
    }
}
