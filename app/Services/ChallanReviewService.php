<?php

namespace App\Services;

use App\Models\{Challan, ChallanItem, PurchaseOrder, PurchaseOrderObjection, PurchaseReceiving, YarnInProcessLedger};
use Illuminate\Support\Facades\DB;

/**
 * Category-incharge inspection of a gate challan logged against a PO.
 *
 *   accept                 -> GRN created and posted (stock, vendor ledger, PDC)
 *   accept_with_objection  -> same, plus an Open objection on the PO; required if anything is rejected
 *   amend                  -> PO amendment raised to superadmin; challan parked until decided
 *   reject                 -> nothing posted; objection logged; gatekeeper + PO creator notified
 */
class ChallanReviewService
{
    public function __construct(
        private PurchaseReceivingService $receivingService,
        private PurchaseOrderAmendmentService $amendmentService,
        private NotificationService $notificationService,
    ) {}

    // Everything a review screen (web or mobile) needs to render
    public function reviewData(Challan $challan): array
    {
        $challan->loadMissing('purchaseOrder.items.product', 'purchaseOrder.vendor', 'purchaseOrder.category',
            'purchaseOrder.greigeProduct', 'purchaseOrder.warpProduct', 'purchaseOrder.weftProduct', 'items.product', 'receivedBy', 'amendment');
        $po = $challan->purchaseOrder;

        $base = [
            'challan' => [
                'id' => $challan->id, 'challan_no' => $challan->challan_no, 'status' => $challan->status,
                'status_label' => $challan->status_label, 'received_date' => $challan->received_date->format('Y-m-d'),
                'vendor_challan_no' => $challan->vendor_challan_no, 'received_by' => $challan->receivedBy->name ?? '',
                'vehicle_no' => $challan->vehicle_no, 'driver_name' => $challan->driver_name, 'driver_contact' => $challan->driver_contact,
                'has_objection' => (bool) $challan->has_objection, 'objection_remarks' => $challan->objection_remarks,
                'objection_voice_url' => $challan->objection_voice_note ? \App\Support\Media::url($challan->objection_voice_note) : null,
                'remarks' => $challan->remarks,
                'images' => collect($challan->challan_images ?? [])->map(fn($p) => \App\Support\Media::url($p))->values(),
                'last_amendment' => $challan->amendment ? [
                    'amendment_no' => $challan->amendment->amendment_no, 'status' => $challan->amendment->status,
                    'rejection_reason' => $challan->amendment->rejection_reason, 'changes' => $challan->amendment->change_lines,
                ] : null,
            ],
            'po' => [
                'id' => $po->id, 'order_no' => $po->order_no, 'type' => $po->type, 'status' => $po->status,
                'vendor_name' => $po->vendor->name ?? '', 'category_name' => $po->category->name ?? '',
                'expected_date' => $po->expected_date?->format('Y-m-d'),
                'payment_term_type' => $po->payment_term_type, 'payment_term_days' => $po->payment_term_days,
            ],
            'decisions' => Challan::DECISIONS,
        ];

        if ($po->type === 'weaving') {
            $received = $this->weavingReceivedMeters($po);
            $yarn = [];
            foreach (collect([$po->warpProduct, $po->weftProduct])->filter()->unique('id') as $product) {
                $bal = YarnInProcessLedger::balanceForCpoProduct($po->id, $product->id);
                $yarn[] = ['product_name' => $product->name, 'balance_at_mill' => round($bal['quantity'], 3)];
            }
            $gateQty = (float) ($challan->items->first()->received_qty ?? 0);
            $base['lines'] = [[
                'challan_item_id' => $challan->items->first()->id ?? null,
                'purchase_order_item_id' => null,
                'description' => $po->greigeProduct->name ?? $po->item_name ?? 'Greige',
                'unit' => 'm',
                'ordered_qty' => (float) $po->total_meters_required,
                'already_received' => round($received, 3),
                'outstanding' => round((float) $po->total_meters_required - $received, 3),
                'gate_qty' => $gateQty,
                'accepted_qty' => (float) ($challan->items->first()->accepted_qty ?? 0) ?: $gateQty,
                'rejected_qty' => (float) ($challan->items->first()->rejected_qty ?? 0),
                'rate' => (float) $po->weaving_per_meter,
            ]];
            $base['weaving'] = ['yarn_at_mill' => $yarn, 'rate_per_pick' => (float) $po->rate_per_pick, 'total_meters_required' => (float) $po->total_meters_required];
            return $base;
        }

        $challanItems = $challan->items->keyBy('purchase_order_item_id');
        $base['lines'] = $po->items->map(function ($poItem) use ($challanItems) {
            $ci = $challanItems->get($poItem->id);
            $gate = (float) ($ci->received_qty ?? 0);
            $accepted = $ci && (float) $ci->accepted_qty > 0 ? (float) $ci->accepted_qty : $gate;
            return [
                'challan_item_id' => $ci->id ?? null,
                'purchase_order_item_id' => $poItem->id,
                'description' => $poItem->product->name ?? trim(($poItem->pattern_code ?? '') . ' ' . ($poItem->description ?? '')),
                'unit' => $poItem->measurementUnit->shortcode ?? '',
                'ordered_qty' => (float) $poItem->quantity,
                'already_received' => (float) $poItem->quantity_received,
                'outstanding' => $poItem->outstanding_qty,
                'gate_qty' => $gate,
                'accepted_qty' => $accepted,
                'rejected_qty' => (float) ($ci->rejected_qty ?? 0),
                'rate' => (float) $poItem->rate,
            ];
        })->filter(fn($l) => $l['challan_item_id'] || $l['outstanding'] > 0.001)->values()->all();

        return $base;
    }

    /**
     * $lines: [ ['purchase_order_item_id' => ?, 'accepted_qty' => .., 'rejected_qty' => .., 'new_quantity' => ?, 'new_rate' => ?], ... ]
     * $opts:  remarks, receiving_date, is_final_receiving, amend (header fields for an amendment)
     */
    public function review(Challan $challan, string $decision, array $lines, array $opts, int $userId): Challan
    {
        if (!array_key_exists($decision, Challan::DECISIONS)) throw new \Exception('Unknown decision.');

        return DB::transaction(function () use ($challan, $decision, $lines, $opts, $userId) {
            $challan = Challan::with('purchaseOrder.items', 'items')->lockForUpdate()->findOrFail($challan->id);
            if ($challan->entry_type !== 'po') throw new \Exception('Use the without-PO review for this challan.');
            if ($challan->status !== Challan::AWAITING) throw new \Exception("{$challan->challan_no} is not awaiting inspection (status: {$challan->status_label}).");

            $po = $challan->purchaseOrder;
            $remarks = trim((string) ($opts['remarks'] ?? ''));
            $lines = $this->normaliseLines($po, $lines);
            $anyRejected = collect($lines)->contains(fn($l) => $l['rejected_qty'] > 0.0001);

            if (in_array($decision, ['accept_with_objection', 'amend', 'reject']) && $remarks === '') {
                throw new \Exception('Add remarks explaining the ' . ($decision === 'amend' ? 'amendment' : ($decision === 'reject' ? 'rejection' : 'objection')) . '.');
            }
            if ($decision === 'accept' && $anyRejected) {
                throw new \Exception('Some quantity is rejected — choose "Accept with objection" and describe the problem.');
            }

            $this->saveLineVerdicts($challan, $po, $lines, $decision);

            return match ($decision) {
                'reject' => $this->doReject($challan, $po, $remarks, $userId),
                'amend'  => $this->doAmend($challan, $po, $lines, $opts, $remarks, $userId),
                default  => $this->doAccept($challan, $po, $lines, $opts, $remarks, $decision, $userId),
            };
        });
    }

    private function doAccept(Challan $challan, PurchaseOrder $po, array $lines, array $opts, string $remarks, string $decision, int $userId): Challan
    {
        $date = $opts['receiving_date'] ?? $challan->received_date->format('Y-m-d');
        $totalAccepted = collect($lines)->sum('accepted_qty');
        if ($totalAccepted <= 0.0001 && !$this->isFinalWeaving($po, $opts)) {
            throw new \Exception('Nothing accepted. Use "Reject consignment" if the whole delivery is being returned.');
        }

        if ($po->type === 'weaving') {
            $receiving = $this->receivingService->createWeaving([
                'challan_id' => $challan->id, 'purchase_order_id' => $po->id, 'receiving_date' => $date,
                'quantity_received' => $totalAccepted, 'is_final_receiving' => (bool) ($opts['is_final_receiving'] ?? false),
                'remarks' => $remarks ?: null,
            ], $userId);
        } else {
            $items = collect($lines)->filter(fn($l) => $l['accepted_qty'] + $l['rejected_qty'] > 0.0001)->map(fn($l) => [
                'purchase_order_item_id' => $l['purchase_order_item_id'],
                'quantity_received' => round($l['accepted_qty'] + $l['rejected_qty'], 3),
                'quantity_rejected' => $l['rejected_qty'],
            ])->values()->all();
            $receiving = $this->receivingService->create(
                ['challan_id' => $challan->id, 'receiving_date' => $date, 'remarks' => $remarks ?: null], $items, $userId
            );
        }

        // The incharge is the approver — post straight away
        $this->receivingService->approve($receiving, $userId);

        $objectionId = $challan->objection_id;
        if ($decision === 'accept_with_objection') {
            $objection = PurchaseOrderObjection::create([
                'purchase_order_id' => $po->id, 'source' => 'receiving', 'challan_id' => $challan->id,
                'purchase_receiving_id' => $receiving->id, 'remarks' => $remarks, 'status' => 'Open', 'raised_by' => $userId,
            ]);
            $objectionId = $objection->id;
        }

        $challan->update([
            'status' => $decision === 'accept' ? Challan::ACCEPTED : Challan::OBJECTION,
            'decision' => $decision, 'decision_remarks' => $remarks ?: null, 'objection_id' => $objectionId,
            'reviewed_by' => $userId, 'reviewed_at' => now(), 'updated_by' => $userId,
        ]);

        $this->notifyOutcome($challan, $po, $decision === 'accept'
            ? "Challan {$challan->challan_no} accepted — {$receiving->receiving_no} posted."
            : "Challan {$challan->challan_no} accepted with objection: {$remarks}");

        return $challan->fresh(['receiving', 'items']);
    }

    private function doAmend(Challan $challan, PurchaseOrder $po, array $lines, array $opts, string $remarks, int $userId): Challan
    {
        $newValues = array_filter((array) ($opts['amend'] ?? []), fn($v) => $v !== null && $v !== '');
        $itemChanges = collect($lines)
            ->filter(fn($l) => $l['purchase_order_item_id'] && ($l['new_quantity'] !== null || $l['new_rate'] !== null))
            ->map(fn($l) => array_filter(['id' => $l['purchase_order_item_id'], 'quantity' => $l['new_quantity'], 'rate' => $l['new_rate']], fn($v) => $v !== null))
            ->values()->all();
        if ($itemChanges) $newValues['items'] = $itemChanges;

        $amendment = $this->amendmentService->propose($po, $newValues, "[{$challan->challan_no}] {$remarks}", $userId, $challan->id);

        $challan->update([
            'status' => Challan::AMENDING, 'decision' => 'amend', 'decision_remarks' => $remarks,
            'amendment_id' => $amendment->id, 'reviewed_by' => $userId, 'reviewed_at' => now(), 'updated_by' => $userId,
        ]);

        return $challan->fresh(['amendment', 'items']);
    }

    private function doReject(Challan $challan, PurchaseOrder $po, string $remarks, int $userId): Challan
    {
        $objection = PurchaseOrderObjection::create([
            'purchase_order_id' => $po->id, 'source' => 'receiving', 'challan_id' => $challan->id,
            'remarks' => "Consignment rejected: {$remarks}", 'status' => 'Open', 'raised_by' => $userId,
        ]);

        $challan->update([
            'status' => Challan::REJECTED, 'decision' => 'reject', 'decision_remarks' => $remarks, 'objection_id' => $objection->id,
            'reviewed_by' => $userId, 'reviewed_at' => now(), 'updated_by' => $userId,
        ]);

        $this->notifyOutcome($challan, $po, "Challan {$challan->challan_no} REJECTED — return goods to vendor. Reason: {$remarks}");
        return $challan->fresh('items');
    }

    // ── helpers ────────────────────────────────────────────────────────

    private function normaliseLines(PurchaseOrder $po, array $lines): array
    {
        $num = fn($v) => ($v === null || $v === '') ? null : round((float) $v, 3);
        $out = [];
        foreach ($lines as $l) {
            $accepted = max(0, (float) ($l['accepted_qty'] ?? 0));
            $rejected = max(0, (float) ($l['rejected_qty'] ?? 0));
            $poItemId = $po->type === 'weaving' ? null : (int) ($l['purchase_order_item_id'] ?? 0);
            if ($po->type !== 'weaving' && !$po->items->firstWhere('id', $poItemId)) {
                throw new \Exception('A line does not belong to this Purchase Order.');
            }
            $out[] = [
                'purchase_order_item_id' => $poItemId ?: null,
                'accepted_qty' => round($accepted, 3), 'rejected_qty' => round($rejected, 3),
                'new_quantity' => $num($l['new_quantity'] ?? null), 'new_rate' => $num($l['new_rate'] ?? null),
                'note' => $l['note'] ?? null,
            ];
        }
        if (empty($out)) throw new \Exception('No lines submitted.');
        return $out;
    }

    // Record the incharge's per-line verdict on the challan items (creating rows for web-logged challans)
    private function saveLineVerdicts(Challan $challan, PurchaseOrder $po, array $lines, string $decision): void
    {
        foreach ($lines as $l) {
            $item = $po->type === 'weaving'
                ? $challan->items->first()
                : $challan->items->firstWhere('purchase_order_item_id', $l['purchase_order_item_id']);

            if (!$item) {
                if ($l['accepted_qty'] + $l['rejected_qty'] <= 0.0001) continue;
                $poItem = $po->items->firstWhere('id', $l['purchase_order_item_id']);
                $item = ChallanItem::create([
                    'challan_id' => $challan->id, 'purchase_order_item_id' => $l['purchase_order_item_id'],
                    'product_id' => $po->type === 'weaving' ? $po->greige_product_id : $poItem?->product_id,
                    'expected_qty' => $poItem?->outstanding_qty ?? 0, 'received_qty' => $l['accepted_qty'] + $l['rejected_qty'],
                ]);
            }

            $lineDecision = match (true) {
                $decision === 'reject' => 'rejected',
                $decision === 'amend' => 'pending',
                $l['rejected_qty'] > 0.0001 && $l['accepted_qty'] > 0.0001 => 'partial',
                $l['rejected_qty'] > 0.0001 => 'rejected',
                default => 'accepted',
            };

            $item->update([
                'accepted_qty' => $decision === 'reject' ? 0 : $l['accepted_qty'],
                'rejected_qty' => $decision === 'reject' ? (float) $item->received_qty : $l['rejected_qty'],
                'decision' => $lineDecision,
                'rejection_note' => $l['note'] ?? $item->rejection_note,
            ]);
        }
    }

    private function notifyOutcome(Challan $challan, PurchaseOrder $po, string $body): void
    {
        $this->notificationService->notifyUsers(
            [$challan->received_by, $po->locked_by], 'challan_reviewed', 'Challan ' . $challan->status_label, $body, 'challan', $challan->id
        );
    }

    private function weavingReceivedMeters(PurchaseOrder $po): float
    {
        return (float) PurchaseReceiving::where('purchase_order_id', $po->id)->where('status', 'Approved')
            ->join('purchase_receiving_items', 'purchase_receivings.id', '=', 'purchase_receiving_items.purchase_receiving_id')
            ->sum('purchase_receiving_items.quantity_received');
    }

    private function isFinalWeaving(PurchaseOrder $po, array $opts): bool
    {
        return $po->type === 'weaving' && !empty($opts['is_final_receiving']);
    }
}
