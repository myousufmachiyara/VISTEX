<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\Product;
use App\Models\TaxMaster;
use Illuminate\Support\Facades\DB;

class JobService
{
    public function __construct(private DocumentNumberService $numberService) {}

    // $items: [ ['product_id'=>.., 'quantity'=>.., 'measurement_unit'=>.., 'unit_price'=>.., 'discount_pct'=>.., 'tax_id'=>..], ... ]
    public function create(array $data, array $items, ?int $userId = null): Job
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter at least one line item.');
            }

            $lineCalcs = array_map(fn($i) => $this->calcLine($i), $items);

            $subtotal      = array_sum(array_column($lineCalcs, 'lineSubtotal'));
            $discountTotal = array_sum(array_column($lineCalcs, 'discountAmount'));
            $taxTotal      = array_sum(array_column($lineCalcs, 'taxAmount'));
            $grandTotal    = array_sum(array_column($lineCalcs, 'amount'));

            $job = Job::create([
                'job_no'              => $this->numberService->next('job', 'jobs', 'job_no', 'JOB'),
                'customer_id'         => $data['customer_id'],
                'buyer_name'          => $data['buyer_name'] ?? null,
                'shipping_address'    => $data['shipping_address'] ?? null,
                'customer_po_number'  => $data['customer_po_number'] ?? null,
                'customer_reference'  => $data['customer_reference'] ?? null,
                'order_date'          => $data['order_date'],
                'expected_date'       => $data['expected_date'] ?? null,
                'payment_term_type'   => $data['payment_term_type'] ?? 'cash',
                'payment_term_days'   => in_array($data['payment_term_type'] ?? 'cash', ['credit', 'pdc']) ? ($data['payment_term_days'] ?? null) : null,
                'payment_term_note'   => ($data['payment_term_type'] ?? '') === 'other' ? ($data['payment_term_note'] ?? null) : null,
                'subtotal'            => $subtotal,
                'discount_amount'     => $discountTotal,
                'tax_amount'          => $taxTotal,
                'total_amount'        => $grandTotal,
                'status'              => 'Pending',
                'remarks'             => $data['remarks'] ?? null,
                'attachments'         => $data['attachments'] ?? null,
                'created_by'          => $userId,
                'updated_by'          => $userId,
            ]);

            foreach ($items as $idx => $item) {
                $calc = $lineCalcs[$idx];
                $product = Product::find($item['product_id']);

                JobItem::create([
                    'job_id'            => $job->id,
                    'product_id'        => $item['product_id'],
                    'quantity'          => $calc['quantity'],
                    'measurement_unit'  => $item['measurement_unit'] ?? $product?->measurement_unit,
                    'unit_price'        => $calc['unitPrice'],
                    'discount_pct'      => $calc['discountPct'],
                    'tax_id'            => $item['tax_id'] ?? null,
                    'tax_amount'        => $calc['taxAmount'],
                    'amount'            => $calc['amount'],
                ]);
            }

            return $job->load('items.product', 'customer');
        });
    }

    private function calcLine(array $item): array
    {
        $qty = (float) $item['quantity'];
        $unitPrice = (float) $item['unit_price'];
        $discountPct = (float) ($item['discount_pct'] ?? 0);

        $lineSubtotal = round($qty * $unitPrice, 2);
        $discountAmount = round($lineSubtotal * ($discountPct / 100), 2);
        $afterDiscount = $lineSubtotal - $discountAmount;

        $taxAmount = 0;
        if (!empty($item['tax_id'])) {
            $tax = TaxMaster::find($item['tax_id']);
            if ($tax) $taxAmount = round($afterDiscount * ((float) $tax->rate / 100), 2);
        }

        return [
            'quantity'       => $qty,
            'unitPrice'      => $unitPrice,
            'discountPct'    => $discountPct,
            'lineSubtotal'   => $lineSubtotal,
            'discountAmount' => $discountAmount,
            'taxAmount'      => $taxAmount,
            'amount'         => round($afterDiscount + $taxAmount, 2),
        ];
    }

    public function approve(Job $job, int $approverId): Job
    {
        if ($job->status !== 'Pending') {
            throw new \Exception('Only a Pending Job can be approved.');
        }

        $job->update(['status' => 'Approved', 'approved_by' => $approverId, 'approved_at' => now(), 'updated_by' => $approverId]);
        return $job->fresh();
    }

    public function reject(Job $job, int $approverId, string $reason): Job
    {
        if ($job->status !== 'Pending') {
            throw new \Exception('Only a Pending Job can be rejected.');
        }

        $job->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'updated_by' => $approverId]);
        return $job->fresh();
    }
    
    public function update(Job $job, array $data, array $items, ?int $userId = null): Job
    {
        return DB::transaction(function () use ($job, $data, $items, $userId) {
            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) throw new \Exception('Enter at least one line item.');

            $lineCalcs = array_map(fn($i) => $this->calcLine($i), $items);
            $subtotal = array_sum(array_column($lineCalcs, 'lineSubtotal'));
            $discountTotal = array_sum(array_column($lineCalcs, 'discountAmount'));
            $taxTotal = array_sum(array_column($lineCalcs, 'taxAmount'));
            $grandTotal = array_sum(array_column($lineCalcs, 'amount'));

            $job->update([
                'customer_id' => $data['customer_id'],
                'buyer_name' => $data['buyer_name'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'customer_po_number' => $data['customer_po_number'] ?? null,
                'customer_reference' => $data['customer_reference'] ?? null,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'payment_term_type' => $data['payment_term_type'] ?? 'cash',
                'payment_term_days' => in_array($data['payment_term_type'] ?? 'cash', ['credit','pdc']) ? ($data['payment_term_days'] ?? null) : null,
                'payment_term_note' => ($data['payment_term_type'] ?? '') === 'other' ? ($data['payment_term_note'] ?? null) : null,
                'subtotal' => $subtotal, 'discount_amount' => $discountTotal, 'tax_amount' => $taxTotal, 'total_amount' => $grandTotal,
                'remarks' => $data['remarks'] ?? null,
                'updated_by' => $userId,
            ]);

            $job->items()->delete();
            foreach ($items as $idx => $item) {
                $calc = $lineCalcs[$idx];
                $product = Product::find($item['product_id']);
                JobItem::create([
                    'job_id' => $job->id, 'product_id' => $item['product_id'], 'quantity' => $calc['quantity'],
                    'measurement_unit' => $item['measurement_unit'] ?? $product?->measurement_unit,
                    'unit_price' => $calc['unitPrice'], 'discount_pct' => $calc['discountPct'],
                    'tax_id' => $item['tax_id'] ?? null, 'tax_amount' => $calc['taxAmount'], 'amount' => $calc['amount'],
                ]);
            }

            return $job->load('items.product', 'customer');
        });
    }
    public function delete(Job $job): void
    {
        if ($job->status !== 'Pending') {
            throw new \Exception('Cannot delete — only a Pending Job can be deleted.');
        }

        $job->items()->delete();
        $job->delete();
    }
}