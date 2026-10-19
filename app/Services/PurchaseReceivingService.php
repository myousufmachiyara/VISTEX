<?php
namespace App\Services;

use App\Models\{PurchaseReceiving, PurchaseReceivingItem, PurchaseOrder, PurchaseOrderItem, Challan, LocationStockLedger, Location, ProductCategory, YarnInProcessLedger, Product};
use Illuminate\Support\Facades\DB;

class PurchaseReceivingService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private VoucherService $voucherService,
        private AccountMappingService $mappingService,
        private PdcService $pdcService,
        private ChallanService $challanService
    ) {}

    public function create(array $data, array $items, ?int $userId = null): PurchaseReceiving
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $challan = Challan::with('purchaseOrder.items.product')->findOrFail($data['challan_id']);
            $po = $challan->purchaseOrder;

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity_received'] ?? 0) > 0));
            if (empty($items)) throw new \Exception('Enter a received quantity for at least one item.');

            $receiving = PurchaseReceiving::create([
                'receiving_no' => $this->numberService->next('purchase_receiving', 'purchase_receivings', 'receiving_no', 'GRN'),
                'purchase_order_id' => $po->id, 'challan_id' => $challan->id,
                'receiving_date' => $data['receiving_date'], 'status' => 'PendingApproval',
                'remarks' => $data['remarks'] ?? null, 'created_by' => $userId, 'updated_by' => $userId,
            ]);

            $subtotal = 0; $unresolvedLines = [];

            foreach ($items as $item) {
                $poItem = $po->items->firstWhere('id', $item['purchase_order_item_id']);
                if (!$poItem) throw new \Exception('Invalid item — not part of this Purchase Order.');

                // Only ACCEPTED quantity counts against the PO. Rejected goods go back
                // to the vendor, so that quantity is still owed to us.
                $outstanding = round((float) $poItem->quantity - (float) $poItem->quantity_received, 3);
                $qty = round((float) $item['quantity_received'], 3);
                $rejectedQty = round((float) ($item['quantity_rejected'] ?? 0), 3);
                if ($rejectedQty > $qty + 0.0001) throw new \Exception('Rejected quantity cannot exceed received quantity.');
                $acceptedQty = round($qty - $rejectedQty, 3);

                if ($acceptedQty > $outstanding + 0.001) {
                    $label = $poItem->product->name ?? $poItem->pattern_code ?? 'item';
                    throw new \Exception("Cannot accept {$acceptedQty} of {$label} — only {$outstanding} remains outstanding on the PO. Request a PO amendment to receive more.");
                }

                $productId = $poItem->product_id ?? optional($poItem->jobItem)->product_id ?? null;

                if (!$productId) {
                    $poItem->increment('quantity_received', $acceptedQty);
                    $unresolvedLines[] = $poItem->pattern_code ?? $poItem->description ?? "Item #{$poItem->id}";
                    continue;
                }

                $rate = (float) $poItem->rate;
                $amount = round($acceptedQty * $rate, 2);
                $subtotal += $amount;

                PurchaseReceivingItem::create([
                    'purchase_receiving_id' => $receiving->id, 'purchase_order_item_id' => $poItem->id,
                    'product_id' => $productId, 'quantity_received' => $qty, 'quantity_rejected' => $rejectedQty,
                    'rate' => $rate, 'amount' => $amount,
                ]);

                $poItem->increment('quantity_received', $acceptedQty);
            }

            if (!empty($unresolvedLines)) {
                $receiving->update(['remarks' => trim(($receiving->remarks ?? '') . ' [Unmapped lines, not stocked: ' . implode(', ', $unresolvedLines) . ']')]);
            }

            $gstAmount = $po->gst_applicable ? round($subtotal * ((float) $po->gst_rate / 100), 2) : 0;
            $receiving->update(['amount' => round($subtotal + $gstAmount, 2)]);

            $this->refreshPoStatus($po);
            return $receiving->load('items.product', 'purchaseOrder.vendor');
        });
    }

    public function createWeaving(array $data, ?int $userId = null): PurchaseReceiving
    {
        return DB::transaction(function () use ($data, $userId) {
            $challan = Challan::findOrFail($data['challan_id']);
            $po = PurchaseOrder::with('warpProduct', 'weftProduct', 'vendor')->findOrFail($data['purchase_order_id']);

            if ($po->type !== 'weaving') throw new \Exception('This action only applies to Weaving-type Purchase Orders.');

            $qty = round((float) $data['quantity_received'], 3);
            if ($qty <= 0) throw new \Exception('Enter a received quantity greater than zero.');

            $isFinal = (bool) ($data['is_final_receiving'] ?? false);

            $alreadyReceived = (float) PurchaseReceiving::where('purchase_order_id', $po->id)
                ->whereIn('status', ['Approved', 'PendingApproval'])
                ->join('purchase_receiving_items', 'purchase_receivings.id', '=', 'purchase_receiving_items.purchase_receiving_id')
                ->sum('purchase_receiving_items.quantity_received');
            $outstandingMeters = round((float) $po->total_meters_required - $alreadyReceived, 3);
            if ($qty > $outstandingMeters + 0.001) {
                throw new \Exception("Cannot accept {$qty} m — only {$outstandingMeters} m remains outstanding on {$po->order_no}. Request a PO amendment to receive more.");
            }

            $yarnCostTotal = 0; $yarnConsumedRows = [];

            $yarnLines = [
                ['product' => $po->warpProduct, 'per_unit' => (float) $po->warp_consumption],
                ['product' => $po->weftProduct, 'per_unit' => (float) $po->weft_consumption],
            ];

            foreach ($yarnLines as $yl) {
                if (!$yl['product']) continue;
                $balance = YarnInProcessLedger::balanceForCpoProduct($po->id, $yl['product']->id);
                $avgRate = $balance['quantity'] > 0 ? $balance['amount'] / $balance['quantity'] : 0;

                if ($isFinal) {
                    $qtyConsumed = round($balance['quantity'], 3); $amtConsumed = round($balance['amount'], 2);
                } else {
                    $qtyConsumed = round($yl['per_unit'] * $qty, 3);
                    $qtyConsumed = min($qtyConsumed, $balance['quantity']);
                    $amtConsumed = round($qtyConsumed * $avgRate, 2);
                }
                if ($qtyConsumed <= 0) continue;

                $yarnConsumedRows[] = ['product_id' => $yl['product']->id, 'quantity' => $qtyConsumed, 'rate' => $avgRate, 'amount' => $amtConsumed];
                $yarnCostTotal += $amtConsumed;
            }

            // Conversion charge per meter = pick rate x picks + sizing (weaving_per_meter).
            // The old code read `weaving_rate`, a column that was renamed, so it always charged 0.
            $weavingChargeTotal = round($qty * (float) $po->weaving_per_meter, 2);
            $totalAmount = round($yarnCostTotal + $weavingChargeTotal, 2);

            $receiving = PurchaseReceiving::create([
                'receiving_no' => $this->numberService->next('purchase_receiving', 'purchase_receivings', 'receiving_no', 'GRN'),
                'purchase_order_id' => $po->id, 'challan_id' => $challan->id,
                'receiving_date' => $data['receiving_date'], 'status' => 'PendingApproval', 'amount' => $totalAmount,
                'is_final_receiving' => $isFinal,
                'yarn_consumed_meta' => $yarnConsumedRows,
                'yarn_cost_amount' => $yarnCostTotal, 'weaving_charge_amount' => $weavingChargeTotal,
                'remarks' => $data['remarks'] ?? null, 'created_by' => $userId, 'updated_by' => $userId,
            ]);

            PurchaseReceivingItem::create([
                'purchase_receiving_id' => $receiving->id, 'purchase_order_item_id' => null,
                'product_id' => $po->greige_product_id, 'quantity_received' => $qty, 'quantity_rejected' => 0,
                'rate' => $qty > 0 ? round($totalAmount / $qty, 4) : 0, 'amount' => $totalAmount,
            ]);

            return $receiving->load('items.product', 'purchaseOrder.vendor');
        });
    }

    public function approve(PurchaseReceiving $receiving, int $approverId): PurchaseReceiving
    {
        return DB::transaction(function () use ($receiving, $approverId) {
            if ($receiving->status !== 'PendingApproval') throw new \Exception('This receiving has already been ' . strtolower($receiving->status) . '.');
            $po = $receiving->purchaseOrder()->with('vendor', 'category')->first();

            if ($po->type === 'weaving') return $this->approveWeaving($receiving, $po, $approverId);
            if ($po->type === 'processing') return app(ProcessingReceivingService::class)->approve($receiving, $approverId);

            $defaultLocationId = $this->receivingLocationId($po);
            $stockTotalsByAccount = [];

            foreach ($receiving->items as $item) {
                if ($item->quantity_accepted <= 0) continue;

                LocationStockLedger::create([
                    'doc_no' => $receiving->receiving_no, 'location_id' => $defaultLocationId, 'product_id' => $item->product_id,
                    'status' => 'fresh', 'lot_no' => $po->order_no, 'quantity' => $item->quantity_accepted, 'amount' => $item->amount,
                    'reference_type' => 'PurchaseReceiving', 'reference_id' => $receiving->id, 'entry_date' => $receiving->receiving_date,
                ]);

                if ($item->quantity_rejected > 0) {
                    LocationStockLedger::create([
                        'doc_no' => $receiving->receiving_no, 'location_id' => $defaultLocationId, 'product_id' => $item->product_id,
                        'status' => 'rejected', 'lot_no' => $po->order_no, 'quantity' => $item->quantity_rejected, 'amount' => 0,
                        'reference_type' => 'PurchaseReceivingRejection', 'reference_id' => $receiving->id, 'entry_date' => $receiving->receiving_date,
                        'remarks' => 'Awaiting return to vendor',
                    ]);
                }

                $stockAccountId = $po->category->stock_account_id ?? $this->mappingService->accountId('stock_in_hand');
                $stockTotalsByAccount[$stockAccountId] = ($stockTotalsByAccount[$stockAccountId] ?? 0) + (float) $item->amount;
            }

            $subtotal = (float) $receiving->items->sum('amount');
            $gstAmount = $po->gst_applicable ? round($subtotal * ((float) $po->gst_rate / 100), 2) : 0;
            $totalAmount = round($subtotal + $gstAmount, 2);

            $this->postVoucher($receiving, $po, $stockTotalsByAccount, $gstAmount, $totalAmount, $approverId);
            $this->createPdcForReceiving($receiving, $po, $totalAmount, $approverId);

            $receiving->update(['status' => 'Approved', 'approved_by' => $approverId, 'approved_at' => now(), 'updated_by' => $approverId]);
            $this->challanService->markProcessed($receiving->challan);

            return $receiving->fresh();
        });
    }

    private function approveWeaving(PurchaseReceiving $receiving, PurchaseOrder $po, int $approverId): PurchaseReceiving
    {
        $defaultLocationId = $this->receivingLocationId($po);
        $item = $receiving->items->first();

        foreach ($receiving->yarn_consumed_meta ?? [] as $row) {
            YarnInProcessLedger::create([
                'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'product_id' => $row['product_id'],
                'quantity' => -$row['quantity'], 'amount' => -$row['amount'],
                'reference_type' => 'PurchaseReceiving', 'reference_id' => $receiving->id, 'entry_date' => $receiving->receiving_date,
            ]);
        }

        LocationStockLedger::create([
            'doc_no' => $receiving->receiving_no, 'location_id' => $defaultLocationId, 'product_id' => $item->product_id,
            'status' => 'fresh', 'lot_no' => $po->order_no, 'quantity' => $item->quantity_received, 'amount' => $item->amount,
            'reference_type' => 'PurchaseReceiving', 'reference_id' => $receiving->id, 'entry_date' => $receiving->receiving_date,
        ]);

        $stockAccountId = $po->category->stock_account_id ?? $this->mappingService->accountId('stock_in_hand');
        $yipAccountId = $this->mappingService->accountId('yarn_in_process');
        $apAccountId = $this->mappingService->accountId('accounts_payable');
        if (!$stockAccountId || !$yipAccountId || !$apAccountId) throw new \Exception('Required account mappings missing.');

        $weavingCharge = (float) $receiving->weaving_charge_amount;
        $gstAmount = ($po->gst_applicable && $weavingCharge > 0) ? round($weavingCharge * ((float) $po->gst_rate / 100), 2) : 0;
        $purchaseTaxAccountId = $gstAmount > 0 ? $this->mappingService->accountId('purchase_tax') : null;
        if ($gstAmount > 0 && !$purchaseTaxAccountId) $gstAmount = 0; // no tax account mapped — keep voucher balanced without GST

        $lines = [];
        if ($receiving->amount > 0) $lines[] = ['account_id' => $stockAccountId, 'debit' => (float) $receiving->amount, 'credit' => 0];
        if ($gstAmount > 0) $lines[] = ['account_id' => $purchaseTaxAccountId, 'debit' => $gstAmount, 'credit' => 0];
        if ($receiving->yarn_cost_amount > 0) $lines[] = ['account_id' => $yipAccountId, 'debit' => 0, 'credit' => (float) $receiving->yarn_cost_amount];
        if ($weavingCharge + $gstAmount > 0) {
            $lines[] = ['account_id' => $apAccountId, 'debit' => 0, 'credit' => round($weavingCharge + $gstAmount, 2), 'party_type' => 'vendor', 'party_id' => $po->vendor_id];
        }

        if (count($lines) >= 2) {
            $this->voucherService->post('system', $receiving->receiving_date->format('Y-m-d'), $lines,
                "Weaving Receiving {$receiving->receiving_no} — {$po->order_no}", 'PurchaseReceiving', $receiving->id, $approverId);
        }

        $this->createPdcForReceiving($receiving, $po, round($weavingCharge + $gstAmount, 2), $approverId);

        $receiving->update(['status' => 'Approved', 'approved_by' => $approverId, 'approved_at' => now(), 'updated_by' => $approverId]);
        $this->challanService->markProcessed($receiving->challan);

        $totalReceived = PurchaseReceiving::where('purchase_order_id', $po->id)->where('status', 'Approved')
            ->join('purchase_receiving_items', 'purchase_receivings.id', '=', 'purchase_receiving_items.purchase_receiving_id')
            ->sum('purchase_receiving_items.quantity_received');
        $done = $receiving->is_final_receiving || $totalReceived >= (float) $po->total_meters_required - 0.001;
        $po->update(['status' => $done ? 'Received' : 'PartiallyReceived', 'is_final_receiving_done' => (bool) $receiving->is_final_receiving]);

        return $receiving->fresh();
    }

    public function reject(PurchaseReceiving $receiving, int $approverId, string $reason): PurchaseReceiving
    {
        return DB::transaction(function () use ($receiving, $approverId, $reason) {
            if ($receiving->status !== 'PendingApproval') throw new \Exception('This receiving has already been ' . strtolower($receiving->status) . '.');

            foreach ($receiving->items as $item) {
                if ($item->purchase_order_item_id) {
                    PurchaseOrderItem::where('id', $item->purchase_order_item_id)->decrement('quantity_received', $item->quantity_accepted);
                }
            }

            $receiving->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'updated_by' => $approverId]);
            $po = $receiving->purchaseOrder;
            if ($po && $po->type !== 'weaving') $this->refreshPoStatus($po);
            $this->challanService->markProcessed($receiving->challan);

            return $receiving->fresh();
        });
    }

    /**
     * Revert an approved purchase GRN back to Pending Approval, so it can be corrected and approved again.
     * Undoes stock (fresh + rejected), the purchase voucher and the payment PDC.
     * Refused once the goods have been used/moved, returned, or a cheque has been prepared.
     */
    public function unapprove(PurchaseReceiving $receiving, int $userId, string $reason): PurchaseReceiving
    {
        return DB::transaction(function () use ($receiving, $userId, $reason) {
            $receiving = PurchaseReceiving::with('items.product', 'purchaseOrder')->lockForUpdate()->findOrFail($receiving->id);
            $po = $receiving->purchaseOrder;
            if ($receiving->status !== 'Approved') throw new \Exception("{$receiving->receiving_no} is not approved.");
            if (!$po || $po->type !== 'purchase') {
                throw new \Exception('Only purchase GRNs can be reverted. Weaving and processing receivings post yarn/greige consumption at the mill and must be corrected with a new receiving.');
            }
            if (trim($reason) === '') throw new \Exception('Give a reason for reverting the approval.');
            if (\App\Models\PurchaseReturn::where('purchase_receiving_id', $receiving->id)->exists()
                || $receiving->items->contains(fn($i) => (float) $i->quantity_returned > 0)) {
                throw new \Exception("Goods on {$receiving->receiving_no} have already been returned to the vendor — cancel the purchase return first.");
            }
            $pdc = \App\Models\Pdc::where('reference_type', 'PurchaseReceiving')->where('reference_id', $receiving->id)->first();
            if ($pdc && $pdc->cheques()->exists()) {
                throw new \Exception("Cheques are already prepared against {$pdc->pdc_no} for this GRN — remove them before reverting.");
            }

            // Stock must still be where this GRN put it (not issued, sold or moved)
            $rows = LocationStockLedger::whereIn('reference_type', ['PurchaseReceiving', 'PurchaseReceivingRejection'])
                ->where('reference_id', $receiving->id)->get();
            foreach ($rows as $row) {
                $left = LocationStockLedger::balance($row->location_id, $row->product_id, $row->status, $row->lot_no);
                if ($left < (float) $row->quantity - 0.001) {
                    $name = $row->product->name ?? 'item';
                    $fmt = fn($v) => rtrim(rtrim(number_format($v, 3), '0'), '.');
                    throw new \Exception("Cannot revert: only {$fmt(max(0, $left))} of the {$fmt($row->quantity)} {$name} (lot {$row->lot_no}) received on {$receiving->receiving_no} is still in stock — the rest has been issued, sold or moved.");
                }
            }

            LocationStockLedger::whereIn('reference_type', ['PurchaseReceiving', 'PurchaseReceivingRejection'])->where('reference_id', $receiving->id)->delete();
            $this->voucherService->deleteByReference('PurchaseReceiving', $receiving->id);
            $pdc?->delete();

            $user = \App\Models\User::find($userId);
            $note = '[Approval reverted by ' . ($user->name ?? "user #{$userId}") . ' on ' . now()->format('d-M-Y') . ': ' . trim($reason) . ']';
            $receiving->update([
                'status' => 'PendingApproval', 'approved_by' => null, 'approved_at' => null, 'updated_by' => $userId,
                'remarks' => trim(($receiving->remarks ?? '') . ' ' . $note),
            ]);
            return $receiving->fresh(['items.product', 'purchaseOrder']);
        });
    }

    /**
     * Correct a purchase GRN that is waiting for approval: date, remarks, and per line the received / rejected quantity.
     * $items = [receiving_item_id => ['quantity_received' => .., 'quantity_rejected' => ..]]
     */
    public function updatePending(PurchaseReceiving $receiving, array $data, array $items, int $userId): PurchaseReceiving
    {
        return DB::transaction(function () use ($receiving, $data, $items, $userId) {
            $receiving = PurchaseReceiving::with('items.product', 'items.purchaseOrderItem', 'purchaseOrder', 'challan.items')->lockForUpdate()->findOrFail($receiving->id);
            $po = $receiving->purchaseOrder;
            if ($receiving->status !== 'PendingApproval') throw new \Exception("{$receiving->receiving_no} is {$receiving->status} — revert the approval first to edit it.");
            if (!$po || $po->type !== 'purchase') throw new \Exception('Only purchase GRNs can be edited here.');
            $fmt = fn($v) => rtrim(rtrim(number_format($v, 3), '0'), '.');

            foreach ($receiving->items as $item) {
                if (!isset($items[$item->id])) continue;
                $qty = round(max(0, (float) ($items[$item->id]['quantity_received'] ?? $item->quantity_received)), 3);
                $rej = round(max(0, (float) ($items[$item->id]['quantity_rejected'] ?? $item->quantity_rejected)), 3);
                if ($rej > $qty + 0.0001) throw new \Exception('Rejected quantity cannot be more than received for ' . ($item->product->name ?? 'an item') . '.');
                $oldAccepted = (float) $item->quantity_received - (float) $item->quantity_rejected;
                $newAccepted = round($qty - $rej, 3);

                if ($poi = $item->purchaseOrderItem) {
                    $poi->refresh();
                    $outstanding = round((float) $poi->quantity - (float) $poi->quantity_received + $oldAccepted, 3);
                    if ($newAccepted > $outstanding + 0.001) {
                        throw new \Exception('Cannot accept ' . $fmt($newAccepted) . ' of ' . ($item->product->name ?? 'item') . " — only {$fmt($outstanding)} is open on {$po->order_no}.");
                    }
                    $poi->update(['quantity_received' => round((float) $poi->quantity_received - $oldAccepted + $newAccepted, 3)]);
                }
                $item->update(['quantity_received' => $qty, 'quantity_rejected' => $rej, 'amount' => round($newAccepted * (float) $item->rate, 2)]);

                // keep the inspection record on the challan in step
                $ci = $receiving->challan?->items->firstWhere('purchase_order_item_id', $item->purchase_order_item_id);
                $ci?->update(['accepted_qty' => $newAccepted, 'rejected_qty' => $rej]);
            }

            $subtotal = (float) $receiving->items()->sum('amount');
            $gst = $po->gst_applicable ? round($subtotal * ((float) $po->gst_rate / 100), 2) : 0;
            $receiving->update([
                'receiving_date' => $data['receiving_date'] ?? $receiving->receiving_date,
                'remarks' => array_key_exists('remarks', $data) ? $data['remarks'] : $receiving->remarks,
                'amount' => round($subtotal + $gst, 2), 'updated_by' => $userId,
            ]);
            $this->refreshPoStatus($po);
            return $receiving->fresh(['items.product', 'purchaseOrder']);
        });
    }

    private function createPdcForReceiving(PurchaseReceiving $receiving, PurchaseOrder $po, float $amount, ?int $userId): void
    {
        if (!in_array($po->payment_term_type, ['cash', 'credit', 'pdc']) || $amount <= 0) return;

        $dueDate = $po->payment_term_type === 'cash'
            ? $receiving->receiving_date->toDateString()
            : $receiving->receiving_date->copy()->addDays((int) ($po->payment_term_days ?? 0))->toDateString();

        $this->pdcService->createPending('vendor', $po->vendor_id, $amount, $dueDate, 'PurchaseReceiving', $receiving->id, $userId);
    }
    private function postVoucher(PurchaseReceiving $receiving, PurchaseOrder $po, array $stockTotalsByAccount, float $gstAmount, float $totalAmount, ?int $userId): void
    {
        $lines = [];

        // Stock in — one line per distinct stock account touched by this receiving
        foreach ($stockTotalsByAccount as $accountId => $amount) {
            $lines[] = ['account_id' => $accountId, 'debit' => $amount, 'credit' => 0];
        }

        // Input tax, if applicable
        if ($gstAmount > 0) {
            $purchaseTaxAccountId = $this->mappingService->accountId('purchase_tax');
            if ($purchaseTaxAccountId) {
                $lines[] = ['account_id' => $purchaseTaxAccountId, 'debit' => $gstAmount, 'credit' => 0];
            }
        }

        // Broker commission — prorated to this receiving's share of the PO's
        // total value. Posted as an expense (Dr) plus a payable tagged to the
        // specific broker (Cr, via party_type/party_id — same mechanism that
        // already gives Vendor/Customer their own running ledger balance,
        // without needing a separate Chart of Accounts row per broker).
        $brokerAmountThisReceiving = 0;
        if ($po->broker_id && $po->broker_commission_amount > 0 && $po->subtotal > 0) {
            $receivingSubtotal = (float) $receiving->items->sum('amount');
            $brokerAmountThisReceiving = round($po->broker_commission_amount * ($receivingSubtotal / $po->subtotal), 2);

            if ($brokerAmountThisReceiving > 0) {
                $brokerExpenseAccountId = $this->mappingService->accountId('broker_commission');
                $apAccountId = $this->mappingService->accountId('accounts_payable');

                if (!$brokerExpenseAccountId || !$apAccountId) {
                    throw new \Exception('Broker commission expense or Accounts Payable mapping is not configured.');
                }

                $lines[] = ['account_id' => $brokerExpenseAccountId, 'debit' => $brokerAmountThisReceiving, 'credit' => 0];
                $lines[] = [
                    'account_id' => $apAccountId, 'debit' => 0, 'credit' => $brokerAmountThisReceiving,
                    'party_type' => 'broker', 'party_id' => $po->broker_id,
                ];
            }
        }

        // Vendor payable — the main line, always present
        $apAccountId = $this->mappingService->accountId('accounts_payable');
        if (!$apAccountId) {
            throw new \Exception('Accounts Payable mapping is not configured.');
        }

        $lines[] = [
            'account_id' => $apAccountId, 'debit' => 0, 'credit' => $totalAmount,
            'party_type' => 'vendor', 'party_id' => $po->vendor_id,
        ];

        if (count($lines) < 2) {
            return;
        }

        $this->voucherService->post(
            'system',
            $receiving->receiving_date->format('Y-m-d'),
            $lines,
            "Purchase Receiving {$receiving->receiving_no} — {$po->order_no}",
            'PurchaseReceiving',
            $receiving->id,
            $userId
        );
    }

    // Recompute Partially/Received for item-based POs (also used after an amendment changes quantities)
    public function refreshPoStatus(PurchaseOrder $po): void
    {
        $po->refresh();
        $po->load('items');
        if ($po->type === 'weaving' || $po->items->isEmpty()) return;

        $totalOrdered = (float) $po->items->sum('quantity');
        $totalReceived = (float) $po->items->sum('quantity_received');

        if ($totalReceived <= 0.0001) {
            // Nothing received: fall back to the pre-receiving status
            $status = in_array($po->status, ['PartiallyReceived', 'Received'])
                ? ($po->issuances()->exists() ? 'Issued' : 'Approved')
                : $po->status;
        } else {
            $status = $totalReceived < $totalOrdered - 0.001 ? 'PartiallyReceived' : 'Received';
        }
        if ($status !== $po->status) $po->update(['status' => $status]);
    }

    // Goods land in the PO's drop-off warehouse; fall back to the default own warehouse
    private function receivingLocationId(PurchaseOrder $po): ?int
    {
        $dropOff = $po->drop_off_location_id ? Location::find($po->drop_off_location_id) : null;
        if ($dropOff && $dropOff->isOwnWarehouse()) return $dropOff->id;
        return Location::defaultId() ?? Location::whereNull('vendor_id')->value('id');
    }
}