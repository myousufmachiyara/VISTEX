<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\TaxMaster;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private DocumentNumberService $numberService) {}

    // $items: [ ['product_id'=>.., 'quantity'=>.., 'rate'=>..], ... ]
    public function create(array $data, array $items, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter at least one item.');
            }

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['quantity'] * (float) $item['rate'];
            }

            $gstApplicable = (bool) ($data['gst_applicable'] ?? false);
            $taxRate = 0;
            if ($gstApplicable && !empty($data['tax_id'])) {
                $tax = TaxMaster::find($data['tax_id']);
                $taxRate = $tax ? (float) $tax->rate : 0;
            }
            $gstAmount = $gstApplicable ? round($subtotal * ($taxRate / 100), 2) : 0;

            $category = \App\Models\ProductCategory::findOrFail($data['product_category_id']);

            $order = PurchaseOrder::create([
                'order_no'               => $this->numberService->next('purchase_order', 'purchase_orders', 'order_no', strtoupper(substr($category->code, 0, 3))),
                'revision_no'            => 1,
                'vendor_id'              => $data['vendor_id'],
                'product_category_id'    => $data['product_category_id'],
                'from_location_id'       => $data['from_location_id'] ?? null,
                'drop_off_location_id'   => $data['drop_off_location_id'],
                'order_date'             => $data['order_date'],
                'expected_date'          => $data['expected_date'] ?? null,
                'gst_applicable'         => $gstApplicable,
                'tax_id'                 => $gstApplicable ? ($data['tax_id'] ?? null) : null,
                'gst_rate'               => $taxRate,
                'subtotal'               => $subtotal,
                'gst_amount'             => $gstAmount,
                'total_amount'           => $subtotal + $gstAmount,
                'status'                 => 'Pending',
                'locked_by'              => $userId,
                'remarks'                => $data['remarks'] ?? null,
                'attachments'            => $data['attachments'] ?? null,
                'created_by'             => $userId,
                'updated_by'             => $userId,
            ]);

            foreach ($items as $item) {
                $qty  = (float) $item['quantity'];
                $rate = (float) $item['rate'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $item['product_id'],
                    'forecast_id'       => $item['forecast_id'] ?? null,
                    'quantity'          => $qty,
                    'rate'              => $rate,
                    'amount'            => round($qty * $rate, 2),
                ]);
            }

            return $order->load('items.product', 'items.forecast.customer', 'vendor', 'category', 'fromLocation', 'dropOffLocation');
        });
    }

    public function update(PurchaseOrder $order, array $data, array $items, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $data, $items, $userId) {

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter at least one item.');
            }

            if ($order->status !== 'Pending') {
                throw new \Exception('Cannot edit a PO that already has receivings against it.');
            }

            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float) $item['quantity'] * (float) $item['rate'];
            }

            $gstApplicable = (bool) ($data['gst_applicable'] ?? false);
            $taxRate = 0;
            if ($gstApplicable && !empty($data['tax_id'])) {
                $tax = TaxMaster::find($data['tax_id']);
                $taxRate = $tax ? (float) $tax->rate : 0;
            }
            $gstAmount = $gstApplicable ? round($subtotal * ($taxRate / 100), 2) : 0;

            $order->update([
                'vendor_id'              => $data['vendor_id'],
                'product_category_id'    => $data['product_category_id'],
                'from_location_id'       => $data['from_location_id'] ?? null,
                'drop_off_location_id'   => $data['drop_off_location_id'],
                'forecast_id'            => $data['forecast_id'] ?? null,
                'order_date'             => $data['order_date'],
                'expected_date'          => $data['expected_date'] ?? null,
                'gst_applicable'         => $gstApplicable,
                'tax_id'                 => $gstApplicable ? ($data['tax_id'] ?? null) : null,
                'gst_rate'               => $taxRate,
                'subtotal'               => $subtotal,
                'gst_amount'             => $gstAmount,
                'total_amount'           => $subtotal + $gstAmount,
                'revision_no'            => $order->revision_no + 1,
                'remarks'                => $data['remarks'] ?? null,
                'attachments'            => $data['attachments'] ?? $order->attachments,
                'updated_by'             => $userId,
            ]);

            $order->items()->delete();
            foreach ($items as $item) {
                $qty  = (float) $item['quantity'];
                $rate = (float) $item['rate'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $qty,
                    'rate'              => $rate,
                    'amount'            => round($qty * $rate, 2),
                ]);
            }

            return $order->load('items.product', 'vendor', 'category', 'fromLocation', 'dropOffLocation', 'forecast');
        });
    }

    public function delete(PurchaseOrder $order): void
    {
        if ($order->items()->where('quantity_received', '>', 0)->exists()) {
            throw new \Exception('Cannot delete — this PO already has receivings against it.');
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
        return Product::where('category_id', $categoryId)->active()->orderBy('name')->get(['id', 'name', 'sku']);
    }
}