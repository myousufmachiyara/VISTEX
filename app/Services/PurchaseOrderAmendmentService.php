<?php

namespace App\Services;

use App\Models\{Challan, PurchaseOrder, PurchaseOrderAmendment, PurchaseOrderItem};
use Illuminate\Support\Facades\DB;

class PurchaseOrderAmendmentService
{
    // Header fields that CAN be amended — vendor, type, category never are (that needs a new PO)
    private const AMENDABLE_FIELDS = [
        'expected_date', 'total_meters_required', 'rate_per_pick',
        'payment_term_type', 'payment_term_days', 'remarks',
    ];

    public function __construct(
        private NotificationService $notificationService,
        private CpoFormulaService $formulaService,
    ) {}

    /**
     * $newValues may contain header fields and/or
     *   'items' => [ ['id' => poItemId, 'quantity' => .., 'rate' => ..], ... ]
     * Only values that actually differ from the PO are kept.
     */
    public function propose(PurchaseOrder $po, array $newValues, string $reason, int $userId, ?int $challanId = null): PurchaseOrderAmendment
    {
        return DB::transaction(function () use ($po, $newValues, $reason, $userId, $challanId) {
            if (!in_array($po->status, ['Approved', 'Issued', 'PartiallyReceived', 'Received'])) {
                throw new \Exception('Amendments can only be raised against an approved PO.');
            }
            if ($po->amendments()->where('status', 'Pending')->exists()) {
                throw new \Exception("{$po->order_no} already has an amendment awaiting approval.");
            }

            $new = []; $previous = [];

            foreach (self::AMENDABLE_FIELDS as $field) {
                if (!array_key_exists($field, $newValues) || $newValues[$field] === null || $newValues[$field] === '') continue;
                $current = $po->{$field} instanceof \DateTimeInterface ? $po->{$field}->format('Y-m-d') : $po->{$field};
                if ((string) $current == (string) $newValues[$field]) continue;
                if (is_numeric($current) && is_numeric($newValues[$field]) && abs((float) $current - (float) $newValues[$field]) < 0.0001) continue;
                $new[$field] = $newValues[$field];
                $previous[$field] = $current;
            }

            if (!empty($newValues['items']) && is_array($newValues['items'])) {
                $poItems = $po->items()->with('product')->get()->keyBy('id');
                $newItems = []; $prevItems = [];
                foreach ($newValues['items'] as $row) {
                    $item = $poItems->get((int) ($row['id'] ?? 0));
                    if (!$item) throw new \Exception('Amendment references an item that is not on this PO.');

                    $change = ['id' => $item->id];
                    if (isset($row['quantity']) && $row['quantity'] !== '' && abs((float) $row['quantity'] - (float) $item->quantity) > 0.0001) {
                        if ((float) $row['quantity'] < (float) $item->quantity_received - 0.0001) {
                            throw new \Exception("Quantity for {$this->itemLabel($item)} cannot go below what is already received ({$item->quantity_received}).");
                        }
                        $change['quantity'] = round((float) $row['quantity'], 3);
                    }
                    if (isset($row['rate']) && $row['rate'] !== '' && abs((float) $row['rate'] - (float) $item->rate) > 0.0001) {
                        $change['rate'] = round((float) $row['rate'], 2);
                    }
                    if (count($change) > 1) {
                        $newItems[] = $change;
                        $prevItems[] = ['id' => $item->id, 'label' => $this->itemLabel($item), 'quantity' => (float) $item->quantity, 'rate' => (float) $item->rate];
                    }
                }
                if ($newItems) { $new['items'] = $newItems; $previous['items'] = $prevItems; }
            }

            if (empty($new)) throw new \Exception('Nothing was changed — enter the new quantity, rate or terms to amend.');

            $amendment = PurchaseOrderAmendment::create([
                'purchase_order_id' => $po->id,
                'challan_id'        => $challanId,
                'amendment_no'      => ($po->amendments()->max('amendment_no') ?? 0) + 1,
                'previous_values'   => $previous,
                'new_values'        => $new,
                'reason'            => $reason,
                'requested_by'      => $userId,
                'status'            => 'Pending',
            ]);

            $this->notificationService->notifyRole(
                'superadmin', 'po_amendment', 'PO Amendment Requested',
                "Amendment #{$amendment->amendment_no} on {$po->order_no}: {$reason}", 'purchase_order', $po->id
            );

            return $amendment;
        });
    }

    public function approve(PurchaseOrderAmendment $amendment, int $approverId): PurchaseOrderAmendment
    {
        return DB::transaction(function () use ($amendment, $approverId) {
            if ($amendment->status !== 'Pending') {
                throw new \Exception('This amendment has already been ' . strtolower($amendment->status) . '.');
            }

            $po = $amendment->purchaseOrder()->lockForUpdate()->first();
            $values = $amendment->new_values;
            $items = $values['items'] ?? [];
            unset($values['items']);

            foreach ($items as $row) {
                $item = PurchaseOrderItem::where('purchase_order_id', $po->id)->findOrFail($row['id']);
                $qty = $row['quantity'] ?? $item->quantity;
                $rate = $row['rate'] ?? $item->rate;
                $item->update(['quantity' => $qty, 'rate' => $rate, 'amount' => round((float) $qty * (float) $rate, 2)]);
            }

            $po->update(array_merge($values, ['revision_no' => $po->revision_no + 1, 'updated_by' => $approverId]));
            $this->recalculateTotals($po->fresh('items'));

            // Quantities may have grown past what's received — reopen the PO if so
            app(PurchaseReceivingService::class)->refreshPoStatus($po->fresh());
            if ($po->type === 'weaving' && $po->fresh()->status === 'Received' && !$po->is_final_receiving_done) {
                $po->update(['status' => 'PartiallyReceived']);
            }

            $amendment->update(['status' => 'Approved', 'approved_by' => $approverId, 'approved_at' => now()]);
            $this->releaseChallan($amendment, 'approved');

            return $amendment->fresh();
        });
    }

    public function reject(PurchaseOrderAmendment $amendment, int $approverId, string $reason): PurchaseOrderAmendment
    {
        return DB::transaction(function () use ($amendment, $approverId, $reason) {
            if ($amendment->status !== 'Pending') {
                throw new \Exception('This amendment has already been ' . strtolower($amendment->status) . '.');
            }
            $amendment->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'approved_by' => $approverId, 'approved_at' => now()]);
            $this->releaseChallan($amendment, 'rejected');
            return $amendment->fresh();
        });
    }

    // A challan parked for this amendment goes back to the incharge's review queue
    private function releaseChallan(PurchaseOrderAmendment $amendment, string $outcome): void
    {
        if (!$amendment->challan_id) return;
        $challan = Challan::find($amendment->challan_id);
        if (!$challan || $challan->status !== Challan::AMENDING) return;

        $challan->update(['status' => Challan::AWAITING, 'decision' => null]);

        $po = $amendment->purchaseOrder;
        $this->notificationService->notifyCategoryIncharges(
            $po->product_category_id, 'amendment_' . $outcome, 'Amendment ' . ucfirst($outcome),
            "Amendment #{$amendment->amendment_no} on {$po->order_no} was {$outcome}. Challan {$challan->challan_no} is back for your review.",
            'challan', $challan->id
        );
    }

    private function recalculateTotals(PurchaseOrder $po): void
    {
        if ($po->type === 'weaving') {
            $calc = $this->formulaService->calculate([
                'reed' => $po->reed, 'reed_count' => $po->reed_count, 'warp_count' => $po->warp_count, 'weft_count' => $po->weft_count,
                'pick' => $po->pick, 'width' => $po->width, 'total_meters_required' => $po->total_meters_required,
                'rate_per_pick' => $po->rate_per_pick, 'sizing_lbs' => $po->sizing_lbs, 'warping' => $po->warping,
                'warp_shrinkage_pct' => $po->warp_conversion_pct, 'weft_shrinkage_pct' => $po->weft_conversion_pct,
                'warp_conversion_pct' => $po->warp_conversion_pct, 'weft_conversion_pct' => $po->weft_conversion_pct,
                'warp_yarn_cost_price' => $po->warp_yarn_cost_price, 'weft_yarn_cost_price' => $po->weft_yarn_cost_price,
                'reed_space' => $po->reed_space,
            ]);
            $calc = $this->formulaService->withGst($calc, (bool) $po->gst_applicable, (float) $po->gst_rate);
            $po->update(array_merge($calc, [
                'subtotal' => $calc['weaving_cost'], 'gst_amount' => $calc['gst_amount'],
                'total_amount' => round($calc['net_amount'] + (float) $po->broker_commission_amount, 2),
            ]));
            return;
        }

        $subtotal = round((float) $po->items->sum('amount'), 2);
        $gst = $po->gst_applicable ? round($subtotal * ((float) $po->gst_rate / 100), 2) : 0;
        $po->update(['subtotal' => $subtotal, 'gst_amount' => $gst, 'total_amount' => round($subtotal + $gst + (float) $po->broker_commission_amount, 2)]);
    }

    private function itemLabel(PurchaseOrderItem $item): string
    {
        return $item->product->name ?? trim(($item->pattern_code ?? '') . ' ' . ($item->description ?? '')) ?: "Item #{$item->id}";
    }
}
