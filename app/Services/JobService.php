<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\MeasurementUnit;
use App\Models\TaxMaster;
use App\Models\TermAndCondition;
use Illuminate\Support\Facades\DB;

/**
 * Customer Order (Job). Lines are design-wise: collection, pattern #, design,
 * colour scheme, packaging (put-up SKU, optional), quantity in meters, rate, disc %, tax.
 */
class JobService
{
    public function __construct(private DocumentNumberService $numberService) {}

    public function create(array $data, array $items, ?int $userId = null): Job
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            [$items, $calcs, $totals] = $this->prepareLines($items);

            $job = Job::create($this->header($data, $totals) + [
                'job_no'      => $this->numberService->next('job', 'job_orders', 'job_no', 'JOB'),
                'status'      => 'Pending',
                'attachments' => $data['attachments'] ?? null,
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);

            $this->writeLines($job, $items, $calcs);
            $this->syncTerms($job, $data);

            return $job->load('items.product', 'customer', 'terms');
        });
    }

    public function update(Job $job, array $data, array $items, ?int $userId = null): Job
    {
        return DB::transaction(function () use ($job, $data, $items, $userId) {
            if (!$job->isEditable()) throw new \Exception('Only a Pending or Rejected customer order can be edited.');
            [$items, $calcs, $totals] = $this->prepareLines($items);

            // Editing a rejected order resubmits it for approval
            $job->update($this->header($data, $totals) + ['status' => 'Pending', 'rejection_reason' => null, 'updated_by' => $userId]);
            $job->items()->delete();
            $this->writeLines($job, $items, $calcs);
            $this->syncTerms($job, $data);

            return $job->load('items.product', 'customer', 'terms');
        });
    }

    private function header(array $data, array $totals): array
    {
        $term = $data['payment_term_type'] ?? 'cash';
        $currency = strtoupper($data['currency'] ?? 'PKR');

        return [
            'customer_id'        => $data['customer_id'],
            'buyer_name'         => $data['buyer_name'] ?? null,
            'consignee_name'     => $data['consignee_name'] ?? null,
            'shipping_address'   => $data['shipping_address'] ?? null,
            'customer_po_number' => $data['customer_po_number'] ?? null,
            'customer_reference' => $data['customer_reference'] ?? null,
            'order_date'         => $data['order_date'],
            'expected_date'      => $data['expected_date'] ?? null,
            'currency'           => $currency,
            'dispatch_tolerance_pct' => max(0, min(50, round((float) ($data['dispatch_tolerance_pct'] ?? 0), 2))),
            'exchange_rate'      => $currency === 'PKR' ? 1 : max(0.0001, (float) ($data['exchange_rate'] ?? 1)),
            'payment_term_type'  => $term,
            'payment_term_days'  => in_array($term, ['credit', 'pdc']) ? ($data['payment_term_days'] ?? null) : null,
            'payment_term_note'  => $term === 'other' ? ($data['payment_term_note'] ?? null) : null,
            'remarks'            => $data['remarks'] ?? null,
        ] + $totals;
    }

    // A line counts when it has a quantity and something identifying it (pattern, design or packaging)
    private function prepareLines(array $items): array
    {
        $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0
            && (trim($i['pattern_code'] ?? '') !== '' || trim($i['design'] ?? '') !== '' || !empty($i['product_id']))));
        if (empty($items)) throw new \Exception('Enter at least one line with a pattern # or design and a quantity.');

        $calcs = array_map(fn($i) => $this->calcLine($i), $items);
        $totals = [
            'subtotal'        => round(array_sum(array_column($calcs, 'lineSubtotal')), 2),
            'discount_amount' => round(array_sum(array_column($calcs, 'discountAmount')), 2),
            'tax_amount'      => round(array_sum(array_column($calcs, 'taxAmount')), 2),
            'total_amount'    => round(array_sum(array_column($calcs, 'amount')), 2),
        ];
        return [$items, $calcs, $totals];
    }

    private function writeLines(Job $job, array $items, array $calcs): void
    {
        $meter = MeasurementUnit::where('shortcode', 'm')->value('id');
        $clean = fn($v) => ($v = trim((string) $v)) === '' ? null : $v;

        foreach ($items as $idx => $item) {
            $calc = $calcs[$idx];
            JobItem::create([
                'job_id'           => $job->id,
                'collection'       => $clean($item['collection'] ?? null),
                'pattern_code'     => $clean($item['pattern_code'] ?? null),
                'design'           => $clean($item['design'] ?? null),
                'color_scheme'     => $clean($item['color_scheme'] ?? null),
                'product_id'       => !empty($item['product_id']) ? $item['product_id'] : null,
                'quantity'         => $calc['quantity'],
                'measurement_unit' => $item['measurement_unit'] ?? $meter,
                'unit_price'       => $calc['unitPrice'],
                'discount_pct'     => $calc['discountPct'],
                'tax_id'           => $item['tax_id'] ?? null,
                'tax_amount'       => $calc['taxAmount'],
                'amount'           => $calc['amount'],
            ]);
        }
    }

    private function calcLine(array $item): array
    {
        $qty = (float) $item['quantity'];
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $discountPct = min(100, max(0, (float) ($item['discount_pct'] ?? 0)));

        $lineSubtotal = round($qty * $unitPrice, 2);
        $discountAmount = round($lineSubtotal * ($discountPct / 100), 2);
        $afterDiscount = $lineSubtotal - $discountAmount;

        $taxAmount = 0;
        if (!empty($item['tax_id']) && ($tax = TaxMaster::find($item['tax_id']))) {
            $taxAmount = round($afterDiscount * ((float) $tax->rate / 100), 2);
        }

        return [
            'quantity' => $qty, 'unitPrice' => $unitPrice, 'discountPct' => $discountPct,
            'lineSubtotal' => $lineSubtotal, 'discountAmount' => $discountAmount,
            'taxAmount' => $taxAmount, 'amount' => round($afterDiscount + $taxAmount, 2),
        ];
    }

    /**
     * Snapshot the ticked Terms & Conditions (title + text copied from the master).
     * Only runs when the form contained the terms picker.
     */
    private function syncTerms(Job $job, array $data): void
    {
        if (empty($data['terms_submitted'])) return;

        $ids  = array_map('intval', (array) ($data['term_ids'] ?? []));
        $keep = array_map('intval', (array) ($data['keep_snapshot_ids'] ?? []));
        $orphans = $job->terms()->whereIn('id', $keep ?: [0])->whereNull('term_id')->get(['title', 'description']);
        $masters = TermAndCondition::whereIn('id', $ids ?: [0])->where('applies_to', 'customer_order')
            ->orderBy('sort_order')->orderBy('title')->get();

        $job->terms()->delete();
        foreach ($masters as $t) $job->terms()->create(['term_id' => $t->id, 'title' => $t->title, 'description' => $t->description]);
        foreach ($orphans as $o) $job->terms()->create(['term_id' => null, 'title' => $o->title, 'description' => $o->description]);
    }

    public function approve(Job $job, int $approverId): Job
    {
        if ($job->status !== 'Pending') throw new \Exception('Only a Pending customer order can be approved.');
        $job->update(['status' => 'Approved', 'approved_by' => $approverId, 'approved_at' => now(), 'updated_by' => $approverId]);
        return $job->fresh();
    }

    public function reject(Job $job, int $approverId, string $reason): Job
    {
        if ($job->status !== 'Pending') throw new \Exception('Only a Pending customer order can be rejected.');
        $job->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'updated_by' => $approverId]);
        return $job->fresh();
    }

    public function delete(Job $job): void
    {
        if ($job->status !== 'Pending') throw new \Exception('Cannot delete — only a Pending customer order can be deleted.');
        $job->items()->delete();
        $job->terms()->delete();
        $job->delete();
    }
}
