<?php
namespace App\Services;

use App\Models\TermAndCondition;

use App\Models\{PurchaseOrder, PurchaseOrderItem, Location, Product, TaxMaster, ProductCategory};
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private CpoFormulaService $formulaService,
        private NotificationService $notificationService
    ) {}

    // $data['submit_action']: 'draft' (default) keeps the PO private to its creator,
    // 'submit' sends it straight to the approver as Pending.
    public function create(array $data, array $items, ?int $userId = null): PurchaseOrder
    {
        $order = DB::transaction(function () use ($data, $items, $userId) {
            $type = $data['type'];
            $order = match ($type) {
                'purchase'   => $this->createPurchaseType($data, $items, $userId),
                'weaving'    => $this->createWeavingType($data, $userId),
                'processing' => $this->createProcessingType($data, $items, $userId),
                default      => throw new \Exception('Unknown PO type.'),
            };
            $this->syncTerms($order, $data);
            return $order;
        });

        if (($data['submit_action'] ?? 'draft') === 'submit') {
            $order = $this->submit($order, $userId);
        }
        return $order;
    }

    // Draft -> Pending
    public function submit(PurchaseOrder $order, ?int $userId): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new \Exception('Only a Draft PO can be submitted for approval.');
        }
        if ($order->type !== 'weaving' && $order->items()->count() === 0) {
            throw new \Exception('Add at least one item before submitting.');
        }

        $order->update([
            'status' => PurchaseOrder::STATUS_PENDING, 'submitted_by' => $userId, 'submitted_at' => now(),
            'rejection_reason' => null, 'updated_by' => $userId,
        ]);

        $this->notificationService->notifyRole(
            'superadmin', 'po_submitted', 'PO Awaiting Approval',
            "{$order->order_no} was submitted for approval.", 'purchase_order', $order->id
        );

        return $order->fresh();
    }

    // Pending -> Draft (creator pulls it back before it is approved)
    public function recall(PurchaseOrder $order, ?int $userId): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::STATUS_PENDING) {
            throw new \Exception('Only a PO that is Pending approval can be recalled to Draft.');
        }
        $order->update(['status' => PurchaseOrder::STATUS_DRAFT, 'submitted_by' => null, 'submitted_at' => null, 'updated_by' => $userId]);
        return $order->fresh();
    }

    private function createPurchaseType(array $data, array $items, ?int $userId): PurchaseOrder
    {
        $items = $this->normalisePacking($items);
        $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
        if (empty($items)) throw new \Exception('Enter at least one item.');

        $subtotal = 0;
        foreach ($items as $item) $subtotal += (float) $item['quantity'] * (float) $item['rate'];

        [$taxRate, $gstAmount] = $this->calcTax($data, $subtotal);
        $brokerAmount = $this->calcBrokerAmount($data);

        $order = PurchaseOrder::create($this->baseHeaderPayload($data, $userId, 'PO', [
            'subtotal' => $subtotal, 'gst_amount' => $gstAmount, 'gst_rate' => $taxRate,
            'total_amount' => $subtotal + $gstAmount + $brokerAmount,
            'broker_commission_amount' => $brokerAmount,
        ]));

        $this->syncItems($order, $items);
        return $order->load('items.product', 'vendor', 'category', 'broker', 'tax');
    }

    private function createWeavingType(array $data, ?int $userId): PurchaseOrder
    {
        $calc = $this->formulaService->calculate($this->formulaInputs($data));
        $taxApplicable = (bool) ($data['gst_applicable'] ?? false);
        $taxRate = 0;
        if ($taxApplicable && !empty($data['tax_id'])) {
            $tax = TaxMaster::find($data['tax_id']);
            $taxRate = $tax ? (float) $tax->rate : 0;
        }
        $calc = $this->formulaService->withGst($calc, $taxApplicable, $taxRate);
        $brokerAmount = $this->calcBrokerAmount($data);

        $order = PurchaseOrder::create(array_merge(
            $this->baseHeaderPayload($data, $userId, 'WPO', [
                'subtotal' => $calc['weaving_cost'], 'gst_amount' => $calc['gst_amount'], 'gst_rate' => $taxRate,
                'total_amount' => $calc['net_amount'] + $brokerAmount,
                'broker_commission_amount' => $brokerAmount,
            ]),
            $this->weavingFieldsPayload($data),
            $calc
        ));

        return $order->load('vendor', 'category', 'warpProduct', 'weftProduct', 'greigeProduct', 'broker', 'tax');
    }

    private function createProcessingType(array $data, array $items, ?int $userId): PurchaseOrder
    {
        $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
        if (empty($items)) throw new \Exception('Enter at least one processing line item.');

        $subtotal = 0;
        foreach ($items as $item) $subtotal += (float) $item['quantity'] * (float) $item['rate'];
        [$taxRate, $gstAmount] = $this->calcTax($data, $subtotal);

        $order = PurchaseOrder::create(array_merge(
            $this->baseHeaderPayload($data, $userId, 'PPO', [
                'subtotal' => $subtotal, 'gst_amount' => $gstAmount, 'gst_rate' => $taxRate,
                'total_amount' => $subtotal + $gstAmount,
            ]),
            ['job_id' => $data['job_id'] ?? null, 'program' => $data['program'] ?? null, 'fabric_specs' => $data['fabric_specs'] ?? null]
        ));

        foreach ($items as $item) {
            $qty = (float) $item['quantity']; $rate = (float) $item['rate'];
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id, 'job_item_id' => $item['job_item_id'] ?? null,
                'product_id' => $item['product_id'] ?? null, 'collection' => $item['collection'] ?? null,
                'pattern_code' => $item['pattern_code'] ?? null, 'description' => $item['description'] ?? null,
                'measurement_unit' => $item['measurement_unit'] ?? null,
                'quantity' => $qty, 'rate' => $rate, 'amount' => round($qty * $rate, 2),
            ]);
        }

        return $order->load('items', 'vendor', 'category', 'serviceType', 'job', 'tax');
    }

    public function update(PurchaseOrder $order, array $data, array $items, ?int $userId = null): PurchaseOrder
    {
        $order = DB::transaction(function () use ($order, $data, $items, $userId) {
            if (!in_array($order->status, PurchaseOrder::EDITABLE_STATUSES, true)) {
                throw new \Exception('Only a Draft, Pending or Rejected PO can be edited.');
            }

            // Editing a rejected PO re-opens it as a Draft for resubmission
            if ($order->status === PurchaseOrder::STATUS_REJECTED) {
                $order->update(['status' => PurchaseOrder::STATUS_DRAFT, 'rejection_reason' => null]);
            }

            if ($order->type === 'purchase') {
                $items = $this->normalisePacking($items);
                $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
                if (empty($items)) throw new \Exception('Enter at least one item.');
                $subtotal = 0;
                foreach ($items as $item) $subtotal += (float) $item['quantity'] * (float) $item['rate'];
                [$taxRate, $gstAmount] = $this->calcTax($data, $subtotal);
                $brokerAmount = $this->calcBrokerAmount($data);

                $order->update($this->updateHeaderPayload($data, $userId, [
                    'subtotal' => $subtotal, 'gst_amount' => $gstAmount, 'gst_rate' => $taxRate,
                    'total_amount' => $subtotal + $gstAmount + $brokerAmount, 'broker_commission_amount' => $brokerAmount,
                ]));
                $order->items()->delete();
                $this->syncItems($order, $items);

            } elseif ($order->type === 'weaving') {
                $calc = $this->formulaService->calculate($this->formulaInputs($data));
                $taxApplicable = (bool) ($data['gst_applicable'] ?? false);
                $taxRate = 0;
                if ($taxApplicable && !empty($data['tax_id'])) {
                    $tax = TaxMaster::find($data['tax_id']); $taxRate = $tax ? (float) $tax->rate : 0;
                }
                $calc = $this->formulaService->withGst($calc, $taxApplicable, $taxRate);
                $brokerAmount = $this->calcBrokerAmount($data);

                $order->update(array_merge(
                    $this->updateHeaderPayload($data, $userId, [
                        'subtotal' => $calc['weaving_cost'], 'gst_amount' => $calc['gst_amount'], 'gst_rate' => $taxRate,
                        'total_amount' => $calc['net_amount'] + $brokerAmount, 'broker_commission_amount' => $brokerAmount,
                    ]),
                    $this->weavingFieldsPayload($data),
                    $calc
                ));

            } elseif ($order->type === 'processing') {
                $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
                if (empty($items)) throw new \Exception('Enter at least one processing line item.');
                $subtotal = 0;
                foreach ($items as $item) $subtotal += (float) $item['quantity'] * (float) $item['rate'];
                [$taxRate, $gstAmount] = $this->calcTax($data, $subtotal);

                $order->update(array_merge(
                    $this->updateHeaderPayload($data, $userId, ['subtotal' => $subtotal, 'gst_amount' => $gstAmount, 'gst_rate' => $taxRate, 'total_amount' => $subtotal + $gstAmount]),
                    ['job_id' => $data['job_id'] ?? null, 'program' => $data['program'] ?? null, 'fabric_specs' => $data['fabric_specs'] ?? null]
                ));

                $order->items()->delete();
                foreach ($items as $item) {
                    $qty = (float) $item['quantity']; $rate = (float) $item['rate'];
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id, 'job_item_id' => $item['job_item_id'] ?? null,
                        'product_id' => $item['product_id'] ?? null, 'collection' => $item['collection'] ?? null,
                        'pattern_code' => $item['pattern_code'] ?? null, 'description' => $item['description'] ?? null,
                        'measurement_unit' => $item['measurement_unit'] ?? null,
                        'quantity' => $qty, 'rate' => $rate, 'amount' => round($qty * $rate, 2),
                    ]);
                }
            }

            $this->syncTerms($order, $data);
            return $order->fresh(['items', 'vendor', 'category']);
        });

        if (($data['submit_action'] ?? null) === 'submit' && $order->status === PurchaseOrder::STATUS_DRAFT) {
            $order = $this->submit($order, $userId);
        }
        return $order;
    }

    // Shared between createWeavingType() and update()'s weaving branch —
    // stores every raw formula INPUT as its own column (so Edit can pre-fill
    // correctly), separate from $calc which holds the derived OUTPUTS.
    private function weavingFieldsPayload(array $data): array
    {
        return [
            'warp_product_id' => $data['warp_product_id'],
            'weft_product_id' => $data['weft_product_id'],
            'greige_product_id' => $data['greige_product_id'] ?? null,
            'reed' => $data['reed'],
            'reed_count' => $data['reed_count'],
            'warp_count' => $data['warp_count'],
            'weft_count' => $data['weft_count'],
            'pick' => $data['pick'],
            'width' => $data['width'],
            'total_meters_required' => $data['total_meters_required'],
            'rate_per_pick' => $data['rate_per_pick'],
            'sizing_lbs' => $data['sizing_lbs'] ?? 0,
            'warping' => $data['warping'] ?? 1,
            'warp_conversion_pct' => $data['warp_conversion_pct'] ?? 0,
            'weft_conversion_pct' => $data['weft_conversion_pct'] ?? 0,
            'warp_yarn_cost_price' => $data['warp_yarn_cost_price'] ?? 0,
            'weft_yarn_cost_price' => $data['weft_yarn_cost_price'] ?? 0,
        ];
    }

    // The form/columns call the shrinkage % fields warp/weft_conversion_pct, but
    // CpoFormulaService reads warp/weft_shrinkage_pct. Without this bridge the
    // stored consumption and yarn-required figures ignored shrinkage (only the
    // print re-applied it), so issuance limits were understated.
    private function formulaInputs(array $data): array
    {
        $data['warp_shrinkage_pct'] = $data['warp_shrinkage_pct'] ?? ($data['warp_conversion_pct'] ?? 0);
        $data['weft_shrinkage_pct'] = $data['weft_shrinkage_pct'] ?? ($data['weft_conversion_pct'] ?? 0);
        return $data;
    }

    /**
     * Save the ticked Terms & Conditions as snapshot rows (title + text copied
     * from the master), in master sort order. Only runs when the form actually
     * contained the terms picker, so API/other callers leave terms untouched.
     *
     * POs are only editable before approval, so the snapshot is refreshed from
     * the master on every save and frozen once the PO is approved.
     * keep_snapshot_ids keeps ticked terms whose master row has since been deleted.
     */
    private function syncTerms(PurchaseOrder $order, array $data): void
    {
        if (empty($data['terms_submitted'])) return;

        $ids = array_map('intval', (array) ($data['term_ids'] ?? []));
        $keep = array_map('intval', (array) ($data['keep_snapshot_ids'] ?? []));

        // Ticked terms whose master row was deleted: keep their saved text
        $orphans = $order->terms()->whereIn('id', $keep ?: [0])->whereNull('term_id')->get(['title', 'description']);

        $masters = TermAndCondition::whereIn('id', $ids ?: [0])
            ->forType($order->type)                                          // ignore terms for other PO types
            ->orderBy('sort_order')->orderBy('title')->get();

        // Rebuild in a stable order: master terms by sort order, then kept orphans
        $order->terms()->delete();
        foreach ($masters as $t) {
            $order->terms()->create(['term_id' => $t->id, 'title' => $t->title, 'description' => $t->description]);
        }
        foreach ($orphans as $o) {
            $order->terms()->create(['term_id' => null, 'title' => $o->title, 'description' => $o->description]);
        }
    }

    /**
     * Purchase lines may be entered as packs: pack_qty (bags) x qty_per_pack (lbs per bag).
     * The server — not the browser — works out quantity = pack_qty x qty_per_pack,
     * and amount is then quantity x rate. Lines without packing keep their quantity.
     */
    private function normalisePacking(array $items): array
    {
        return array_map(function ($i) {
            $packs = $i['pack_qty'] ?? null;
            if ($packs === null || $packs === '') {
                $i['pack_qty'] = null; $i['qty_per_pack'] = null;
                return $i;
            }
            $perPack = (float) (($i['qty_per_pack'] ?? '') === '' ? 1 : $i['qty_per_pack']);
            if ($perPack <= 0) throw new \Exception('Qty per pack must be greater than zero.');
            $i['pack_qty'] = round((float) $packs, 3);
            $i['qty_per_pack'] = round($perPack, 4);
            $i['quantity'] = round($i['pack_qty'] * $i['qty_per_pack'], 3);
            return $i;
        }, $items);
    }

    private function syncItems(PurchaseOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $qty = (float) $item['quantity']; $rate = (float) $item['rate'];
            $product = Product::find($item['product_id']);
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id, 'product_id' => $item['product_id'],
                'forecast_id' => $item['forecast_id'] ?? null,
                'measurement_unit' => $item['measurement_unit'] ?? $product?->measurement_unit,
                'pack_qty' => $item['pack_qty'] ?? null, 'qty_per_pack' => $item['qty_per_pack'] ?? null,
                'quantity' => $qty, 'rate' => $rate, 'amount' => round($qty * $rate, 2),
            ]);
        }
    }

    private function baseHeaderPayload(array $data, ?int $userId, string $docCode, array $amounts): array
    {
        $category = ProductCategory::findOrFail($data['product_category_id']);
        return array_merge([
            'order_no' => $this->numberService->next(
                match($data['type']) { 'purchase' => 'purchase_order', 'weaving' => 'weaving_order', 'processing' => 'processing_order' },
                'purchase_orders', 'order_no', $docCode
            ),
            'type' => $data['type'], 'revision_no' => 1, 'vendor_id' => $data['vendor_id'],
            'product_category_id' => $category->id, 'service_type_id' => $data['service_type_id'] ?? null,
            'from_location_id' => $data['from_location_id'] ?? null, 'drop_off_location_id' => $data['drop_off_location_id'],
            'order_date' => $data['order_date'], 'expected_date' => $data['expected_date'] ?? null,
            'broker_id' => $data['broker_id'] ?? null, 'payment_term_type' => $data['payment_term_type'] ?? 'cash',
            'payment_term_days' => in_array($data['payment_term_type'] ?? 'cash', ['credit', 'pdc']) ? ($data['payment_term_days'] ?? null) : null,
            'payment_term_note' => ($data['payment_term_type'] ?? '') === 'other' ? ($data['payment_term_note'] ?? null) : null,
            'gst_applicable' => (bool) ($data['gst_applicable'] ?? false),
            'tax_id' => ($data['gst_applicable'] ?? false) ? ($data['tax_id'] ?? null) : null,
            'status' => PurchaseOrder::STATUS_DRAFT, 'locked_by' => $userId, 'forecast_id' => $data['forecast_id'] ?? null,
            'remarks' => $data['remarks'] ?? null, 'attachments' => $data['attachments'] ?? null,
            'created_by' => $userId, 'updated_by' => $userId,
        ], $amounts);
    }

    private function updateHeaderPayload(array $data, ?int $userId, array $amounts): array
    {
        return array_merge([
            'vendor_id' => $data['vendor_id'], 'product_category_id' => $data['product_category_id'],
            'service_type_id' => $data['service_type_id'] ?? null,
            'from_location_id' => $data['from_location_id'] ?? null, 'drop_off_location_id' => $data['drop_off_location_id'],
            'order_date' => $data['order_date'], 'expected_date' => $data['expected_date'] ?? null,
            'broker_id' => $data['broker_id'] ?? null, 'payment_term_type' => $data['payment_term_type'] ?? 'cash',
            'payment_term_days' => in_array($data['payment_term_type'] ?? 'cash', ['credit', 'pdc']) ? ($data['payment_term_days'] ?? null) : null,
            'payment_term_note' => ($data['payment_term_type'] ?? '') === 'other' ? ($data['payment_term_note'] ?? null) : null,
            'gst_applicable' => (bool) ($data['gst_applicable'] ?? false),
            'tax_id' => ($data['gst_applicable'] ?? false) ? ($data['tax_id'] ?? null) : null,
            'remarks' => $data['remarks'] ?? null, 'updated_by' => $userId,
        ], $amounts);
    }

    private function calcTax(array $data, float $subtotal): array
    {
        $applicable = (bool) ($data['gst_applicable'] ?? false);
        $rate = 0;
        if ($applicable && !empty($data['tax_id'])) {
            $tax = TaxMaster::find($data['tax_id']); $rate = $tax ? (float) $tax->rate : 0;
        }
        return [$rate, $applicable ? round($subtotal * ($rate / 100), 2) : 0];
    }

    private function calcBrokerAmount(array $data): float
    {
        if (empty($data['broker_id'])) return 0;
        return round((float) ($data['broker_commission_amount'] ?? 0), 2);
    }

    public function approve(PurchaseOrder $order, int $approverId): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::STATUS_PENDING) throw new \Exception('Only a Pending PO can be approved.');
        $order->update(['status' => PurchaseOrder::STATUS_APPROVED, 'approved_by' => $approverId, 'approved_at' => now(), 'updated_by' => $approverId]);

        $this->notifyCreator($order, 'po_approved', 'PO Approved', "{$order->order_no} has been approved.");
        return $order->fresh();
    }

    public function reject(PurchaseOrder $order, int $approverId, string $reason): PurchaseOrder
    {
        if ($order->status !== PurchaseOrder::STATUS_PENDING) throw new \Exception('Only a Pending PO can be rejected.');
        $order->update(['status' => PurchaseOrder::STATUS_REJECTED, 'rejection_reason' => $reason, 'updated_by' => $approverId]);

        $this->notifyCreator($order, 'po_rejected', 'PO Rejected', "{$order->order_no} was rejected: {$reason}");
        return $order->fresh();
    }

    private function notifyCreator(PurchaseOrder $order, string $type, string $title, string $body): void
    {
        if ($order->locked_by) {
            $this->notificationService->notifyUsers([$order->locked_by], $type, $title, $body, 'purchase_order', $order->id);
        }
    }

    public function delete(PurchaseOrder $order): void
    {
        if (!in_array($order->status, PurchaseOrder::EDITABLE_STATUSES, true)) {
            throw new \Exception('Cannot delete — only a Draft, Pending or Rejected PO can be deleted.');
        }
        $order->items()->delete();
        $order->delete();
    }

    public function vendorLocations(int $vendorId)
    {
        return Location::where('vendor_id', $vendorId)->active()->orderBy('name')->get(['id', 'name']);
    }

    public function categoryProducts(int $categoryId)
    {
        return Product::where('category_id', $categoryId)->active()->orderBy('name')->get(['id', 'name', 'sku', 'measurement_unit']);
    }
}