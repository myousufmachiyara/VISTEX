<?php

namespace App\Services;

use App\Models\PurchaseReceiving;
use App\Models\PurchaseReceivingItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderObjection;
use App\Models\LocationStockLedger;
use Illuminate\Support\Facades\DB;

class PurchaseReceivingService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    // Records the physical receipt. Quantity received is real and locked
    // in immediately (increments PO item's quantity_received), but stock
    // value and accounting are deferred until a category in-charge or
    // superadmin approves it.
    public function create(array $data, array $items, ?int $userId = null): PurchaseReceiving
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $po = PurchaseOrder::with('vendor', 'items.product.category')->findOrFail($data['purchase_order_id']);

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity_received'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter a quantity for at least one item.');
            }

            $receiving = PurchaseReceiving::create([
                'receiving_no'       => $this->numberService->next('purchase_receiving', 'purchase_receivings', 'receiving_no', 'PR'),
                'purchase_order_id'  => $po->id,
                'location_id'        => $data['location_id'],
                'receiving_date'     => $data['receiving_date'],
                'vendor_challan_no'  => $data['vendor_challan_no'],
                'remarks'            => $data['remarks'] ?? null,
                'attachments'        => $data['attachments'],
                'amount'             => 0,
                'status'             => 'PendingApproval',
                'created_by'         => $userId,
                'updated_by'         => $userId,
            ]);

            $subtotal = 0;

            foreach ($items as $item) {
                $poItem = $po->items->firstWhere('id', $item['purchase_order_item_id']);
                if (!$poItem) {
                    throw new \Exception('Invalid item — not part of this Purchase Order.');
                }

                $outstanding = round((float) $poItem->quantity - (float) $poItem->quantity_received, 3);
                $qty = round((float) $item['quantity_received'], 3);

                if ($qty > $outstanding + 0.001) {
                    throw new \Exception(
                        "Cannot receive {$qty} of {$poItem->product->name} — only {$outstanding} remains outstanding."
                    );
                }

                $rate   = (float) $poItem->rate;
                $amount = round($qty * $rate, 2);
                $subtotal += $amount;

                PurchaseReceivingItem::create([
                    'purchase_receiving_id'   => $receiving->id,
                    'purchase_order_item_id'  => $poItem->id,
                    'product_id'              => $poItem->product_id,
                    'quantity_received'       => $qty,
                    'rate'                     => $rate,
                    'amount'                   => $amount,
                ]);

                // Quantity received is real, locked immediately — this
                // tracks physical receipt, independent of approval.
                $poItem->increment('quantity_received', $qty);
            }

            $gstAmount = 0;
            if ($po->gst_applicable) {
                $gstAmount = round($subtotal * ((float) $po->gst_rate / 100), 2);
            }
            $totalAmount = round($subtotal + $gstAmount, 2);

            $receiving->update(['amount' => $totalAmount]);

            $this->refreshPoStatus($po);

            return $receiving->load('items.product', 'location', 'purchaseOrder.vendor');
        });
    }

    // Category in-charge or superadmin approves — THIS is what actually
    // hits stock (LocationStockLedger) and posts the accounting voucher.
    public function approve(PurchaseReceiving $receiving, int $approverId): PurchaseReceiving
    {
        return DB::transaction(function () use ($receiving, $approverId) {

            if ($receiving->status !== 'PendingApproval') {
                throw new \Exception('This receiving has already been ' . strtolower($receiving->status) . '.');
            }

            $po = $receiving->purchaseOrder()->with('vendor')->first();
            $stockTotalsByAccount = [];

            foreach ($receiving->items as $item) {
                $product = $item->product()->with('category')->first();

                LocationStockLedger::create([
                    'doc_no'          => $receiving->receiving_no,
                    'location_id'     => $receiving->location_id,
                    'product_id'      => $item->product_id,
                    'status'          => 'fresh',
                    'quantity'        => $item->quantity_received,
                    'amount'          => $item->amount,
                    'reference_type'  => 'PurchaseReceiving',
                    'reference_id'    => $receiving->id,
                    'entry_date'      => $receiving->receiving_date,
                ]);

                $stockAccountId = $product->category->stock_account_id
                    ?? $this->mappingService->accountId('stock_in_hand');
                $stockTotalsByAccount[$stockAccountId] = ($stockTotalsByAccount[$stockAccountId] ?? 0) + (float) $item->amount;
            }

            $subtotal = (float) $receiving->items->sum('amount');
            $gstAmount = $po->gst_applicable ? round($subtotal * ((float) $po->gst_rate / 100), 2) : 0;

            $this->postVoucher($receiving, $po, $stockTotalsByAccount, $gstAmount, (float) $receiving->amount, $approverId);

            $receiving->update([
                'status'       => 'Approved',
                'approved_by'  => $approverId,
                'approved_at'  => now(),
                'updated_by'   => $approverId,
            ]);

            return $receiving->fresh();
        });
    }

    public function reject(PurchaseReceiving $receiving, int $approverId, string $reason): PurchaseReceiving
    {
        return DB::transaction(function () use ($receiving, $approverId, $reason) {

            if ($receiving->status !== 'PendingApproval') {
                throw new \Exception('This receiving has already been ' . strtolower($receiving->status) . '.');
            }

            foreach ($receiving->items as $item) {
                PurchaseOrderItem::where('id', $item->purchase_order_item_id)
                    ->decrement('quantity_received', $item->quantity_received);
            }

            $receiving->update([
                'status'            => 'Rejected',
                'rejection_reason'  => $reason,
                'updated_by'        => $approverId,
            ]);

            $po = $receiving->purchaseOrder;
            if ($po) $this->refreshPoStatus($po);

            return $receiving->fresh();
        });
    }

    public function delete(PurchaseReceiving $receiving): void
    {
        DB::transaction(function () use ($receiving) {
            if ($receiving->status === 'Approved') {
                throw new \Exception('Cannot delete an approved receiving — it has posted accounting entries.');
            }

            foreach ($receiving->items as $item) {
                PurchaseOrderItem::where('id', $item->purchase_order_item_id)
                    ->decrement('quantity_received', $item->quantity_received);
            }

            $po = $receiving->purchaseOrder;
            $receiving->items()->delete();
            $receiving->delete();

            if ($po) $this->refreshPoStatus($po);
        });
    }

    public function raiseObjection(int $purchaseOrderId, string $remarks, int $userId): PurchaseOrderObjection
    {
        $po = PurchaseOrder::findOrFail($purchaseOrderId);

        return PurchaseOrderObjection::create([
            'purchase_order_id' => $po->id,
            'raised_by'         => $userId,
            'remarks'           => $remarks,
            'status'            => 'Open',
        ]);
    }

    public function historyForPo(int $purchaseOrderId)
    {
        return PurchaseReceiving::where('purchase_order_id', $purchaseOrderId)
            ->with('items.product')
            ->orderByDesc('receiving_date')
            ->get()
            ->map(fn($r) => [
                'receiving_no' => $r->receiving_no,
                'date'         => $r->receiving_date->format('d-M-Y'),
                'status'       => $r->status,
                'items'        => $r->items->map(fn($i) => ($i->product->name ?? '') . ' (' . $i->quantity_received . ')')->implode(', '),
            ]);
    }

    // Dr Stock in Hand (per category) + Dr Purchase Tax (if GST)
    // / Cr Accounts Payable (tagged to vendor)
    private function postVoucher(PurchaseReceiving $receiving, PurchaseOrder $po, array $stockTotalsByAccount, float $gstAmount, float $totalAmount, ?int $userId): void
    {
        $lines = [];
        foreach ($stockTotalsByAccount as $accountId => $amount) {
            $lines[] = ['account_id' => $accountId, 'debit' => $amount, 'credit' => 0];
        }

        if ($gstAmount > 0) {
            $purchaseTaxAccountId = $this->mappingService->accountId('purchase_tax');
            if ($purchaseTaxAccountId) {
                $lines[] = ['account_id' => $purchaseTaxAccountId, 'debit' => $gstAmount, 'credit' => 0];
            }
        }

        $apAccountId = $this->mappingService->accountId('accounts_payable');
        if (!$apAccountId) {
            throw new \Exception('Accounts Payable mapping is not configured — check Account Mappings.');
        }

        $lines[] = [
            'account_id' => $apAccountId,
            'debit'      => 0,
            'credit'     => $totalAmount,
            'party_type' => 'vendor',
            'party_id'   => $po->vendor_id,
        ];

        $this->voucherService->post(
            'system',
            $receiving->receiving_date instanceof \Carbon\Carbon ? $receiving->receiving_date->format('Y-m-d') : $receiving->receiving_date,
            $lines,
            "Purchase Receiving {$receiving->receiving_no} — {$po->order_no}",
            'PurchaseReceiving',
            $receiving->id,
            $userId
        );
    }

    private function refreshPoStatus(PurchaseOrder $po): void
    {
        $po->refresh();
        $totalOrdered  = $po->items->sum('quantity');
        $totalReceived = $po->items->sum('quantity_received');

        $status = $totalReceived <= 0
            ? 'Pending'
            : ($totalReceived < $totalOrdered ? 'PartiallyReceived' : 'Received');

        $po->update(['status' => $status]);
    }
}