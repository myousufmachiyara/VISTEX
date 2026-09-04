<?php
namespace App\Services;

use App\Models\{PurchaseOrder, PurchaseOrderItem, Location, Product, TaxMaster, ProductCategory};
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private DocumentNumberService $numberService,
        private CpoFormulaService $formulaService
    ) {}

    public function create(array $data, array $items, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $type = $data['type'];
            if ($type === 'purchase') return $this->createPurchaseType($data, $items, $userId);
            if ($type === 'weaving') return $this->createWeavingType($data, $userId);
            if ($type === 'processing') return $this->createProcessingType($data, $items, $userId);
            throw new \Exception('Unknown PO type.');
        });
    }

    private function createPurchaseType(array $data, array $items, ?int $userId): PurchaseOrder
    {
        $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
        if (empty($items)) throw new \Exception('Enter at least one item.');

        $subtotal = 0;
        foreach ($items as $item) $subtotal += (float) $item['quantity'] * (float) $item['rate'];

        [$taxRate, $gstAmount] = $this->calcTax($data, $subtotal);
        $brokerAmount = $this->calcBrokerAmount($data, $subtotal);

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
        $calc = $this->formulaService->calculate($data);
        $taxApplicable = (bool) ($data['gst_applicable'] ?? false);
        $taxRate = 0;
        if ($taxApplicable && !empty($data['tax_id'])) {
            $tax = TaxMaster::find($data['tax_id']);
            $taxRate = $tax ? (float) $tax->rate : 0;
        }
        $calc = $this->formulaService->withGst($calc, $taxApplicable, $taxRate);
        $brokerAmount = $this->calcBrokerAmount($data, $calc['weaving_cost']);

        $order = PurchaseOrder::create(array_merge(
            $this->baseHeaderPayload($data, $userId, 'WPO', [
                'subtotal' => $calc['weaving_cost'], 'gst_amount' => $calc['gst_amount'], 'gst_rate' => $taxRate,
                'total_amount' => $calc['net_amount'] + $brokerAmount,
                'broker_commission_amount' => $brokerAmount,
            ]),
            [
                'warp_product_id' => $data['warp_product_id'], 'weft_product_id' => $data['weft_product_id'],
                'greige_product_id' => $data['greige_product_id'] ?? null,
                'warp_count' => $data['warp_count'], 'weft_count' => $data['weft_count'], 'reed_count' => $data['reed_count'],
                'pick' => $data['pick'], 'width' => $data['width'], 'total_meters_required' => $data['total_meters_required'],
                'rate_per_pick' => $data['rate_per_pick'], 'sizing_lbs' => $data['sizing_lbs'] ?? 0,
                'warp_conversion_pct' => $data['warp_conversion_pct'] ?? 0,
                'warp_shrinkage_pct' => $data['warp_shrinkage_pct'] ?? 0,
                'weft_shrinkage_pct' => $data['weft_shrinkage_pct'] ?? 0,
            ],
            $calc
        ));

        return $order->load('vendor', 'category', 'warpProduct', 'weftProduct', 'broker', 'tax');
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
        return DB::transaction(function () use ($order, $data, $items, $userId) {
            if ($order->status !== 'Pending') throw new \Exception('Only a Pending PO can be edited.');

            if ($order->type === 'purchase') {
                $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
                if (empty($items)) throw new \Exception('Enter at least one item.');
                $subtotal = 0;
                foreach ($items as $item) $subtotal += (float) $item['quantity'] * (float) $item['rate'];
                [$taxRate, $gstAmount] = $this->calcTax($data, $subtotal);
                $brokerAmount = $this->calcBrokerAmount($data, $subtotal);

                $order->update($this->updateHeaderPayload($data, $userId, [
                    'subtotal' => $subtotal, 'gst_amount' => $gstAmount, 'gst_rate' => $taxRate,
                    'total_amount' => $subtotal + $gstAmount + $brokerAmount, 'broker_commission_amount' => $brokerAmount,
                ]));
                $order->items()->delete();
                $this->syncItems($order, $items);

            } elseif ($order->type === 'weaving') {
                $calc = $this->formulaService->calculate($data);
                $taxApplicable = (bool) ($data['gst_applicable'] ?? false);
                $taxRate = 0;
                if ($taxApplicable && !empty($data['tax_id'])) {
                    $tax = TaxMaster::find($data['tax_id']); $taxRate = $tax ? (float) $tax->rate : 0;
                }
                $calc = $this->formulaService->withGst($calc, $taxApplicable, $taxRate);
                $brokerAmount = $this->calcBrokerAmount($data, $calc['weaving_cost']);

                $order->update(array_merge(
                    $this->updateHeaderPayload($data, $userId, [
                        'subtotal' => $calc['weaving_cost'], 'gst_amount' => $calc['gst_amount'], 'gst_rate' => $taxRate,
                        'total_amount' => $calc['net_amount'] + $brokerAmount, 'broker_commission_amount' => $brokerAmount,
                    ]),
                    [
                        'warp_product_id' => $data['warp_product_id'], 'weft_product_id' => $data['weft_product_id'],
                        'greige_product_id' => $data['greige_product_id'] ?? null,
                        'warp_count' => $data['warp_count'], 'weft_count' => $data['weft_count'], 'reed_count' => $data['reed_count'],
                        'pick' => $data['pick'], 'width' => $data['width'], 'total_meters_required' => $data['total_meters_required'],
                        'rate_per_pick' => $data['rate_per_pick'], 'sizing_lbs' => $data['sizing_lbs'] ?? 0,
                        'warp_conversion_pct' => $data['warp_conversion_pct'] ?? 0,
                        'warp_shrinkage_pct' => $data['warp_shrinkage_pct'] ?? 0,
                        'weft_shrinkage_pct' => $data['weft_shrinkage_pct'] ?? 0,
                    ],
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

            return $order->fresh(['items', 'vendor', 'category']);
        });
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
            'status' => 'Pending', 'locked_by' => $userId, 'forecast_id' => $data['forecast_id'] ?? null,
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
        if ($order->status !== 'Pending') throw new \Exception('Only a Pending PO can be approved.');
        $order->update(['status' => 'Approved', 'approved_by' => $approverId, 'approved_at' => now(), 'updated_by' => $approverId]);
        return $order->fresh();
    }

    public function reject(PurchaseOrder $order, int $approverId, string $reason): PurchaseOrder
    {
        if ($order->status !== 'Pending') throw new \Exception('Only a Pending PO can be rejected.');
        $order->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'updated_by' => $approverId]);
        return $order->fresh();
    }

    public function delete(PurchaseOrder $order): void
    {
        if ($order->status !== 'Pending') throw new \Exception('Cannot delete — only a Pending PO can be deleted.');
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