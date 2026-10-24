<?php
namespace App\Services;

use App\Models\{Challan, Location, LocationStockLedger, Product, ProductCategory, PurchaseOrder, PurchaseOrderItem, PurchaseReceiving, PurchaseReceivingItem};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Processing PO receiving: finished fabric back from the mill, counted A / B / C.
 *  - accepted meters = A + B + C, stocked by grade at the PO's drop-off warehouse (lot = PO number)
 *  - rejected meters are not stocked; they stay with the mill for reprocessing
 *  - greige "in process" for the PO is used up: remaining × accepted ÷ outstanding (all of it on the final delivery)
 *  - cost = greige used + processing charge; vendor is owed the charge (+GST)
 */
class ProcessingReceivingService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private VoucherService $vouchers,
        private AccountMappingService $mapping,
        private PdcService $pdcs,
        private ChallanService $challans,
        private PurchaseReceivingService $receivings,
    ) {}

    /** $items: [['purchase_order_item_id', 'qty_a', 'qty_b', 'qty_c', 'quantity_rejected'], ...] */
    public function create(array $data, array $items, ?int $userId = null): PurchaseReceiving
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $challan = Challan::findOrFail($data['challan_id']);
            $po = PurchaseOrder::with('items', 'serviceType')->findOrFail($challan->purchase_order_id);
            if ($po->type !== 'processing') throw new \Exception('This action only applies to Processing POs.');
            $isFinal = (bool) ($data['is_final_receiving'] ?? false);

            $lines = $this->lines($po, $items);
            $acceptedTotal = array_sum(array_column($lines, 'accepted'));
            $charge = round(array_sum(array_column($lines, 'charge')), 2);

            if ($po->isReprocess()) {
                // Each line uses up its own fabric at the mill (same product, same lot)
                $consumed = [];
                foreach ($lines as $i => $l) {
                    $lines[$i]['consumed'] = $this->fabricToUse($po, $l['item'], $l['accepted'], $isFinal);
                    $consumed = array_merge($consumed, $lines[$i]['consumed']);
                }
            } else {
                $consumed = $this->greigeToUse($po, $acceptedTotal, $isFinal);
            }
            $greigeCost = round(array_sum(array_column($consumed, 'amount')), 2);
            $gst = $po->gst_applicable ? round($charge * ((float) $po->gst_rate / 100), 2) : 0;

            $receiving = PurchaseReceiving::create([
                'receiving_no' => $this->numbers->next('purchase_receiving', 'purchase_receivings', 'receiving_no', 'GRN'),
                'purchase_order_id' => $po->id, 'challan_id' => $challan->id,
                'receiving_date' => $data['receiving_date'], 'status' => 'PendingApproval',
                'amount' => round($greigeCost + $charge, 2), 'is_final_receiving' => $isFinal,
                'greige_consumed_meta' => $consumed, 'greige_cost_amount' => $greigeCost, 'processing_charge_amount' => $charge,
                'remarks' => $data['remarks'] ?? null, 'created_by' => $userId, 'updated_by' => $userId,
            ]);

            foreach ($lines as $l) {
                // Greige cost shared by accepted meters
                $share = isset($l['consumed']) ? round(array_sum(array_column($l['consumed'], 'amount')), 2)
                    : ($acceptedTotal > 0 ? round($greigeCost * $l['accepted'] / $acceptedTotal, 2) : 0);
                $amount = round($l['charge'] + $share, 2);
                PurchaseReceivingItem::create([
                    'purchase_receiving_id' => $receiving->id, 'purchase_order_item_id' => $l['item']->id,
                    'product_id' => $this->finishedProduct($po, $l['item'])->id,
                    'quantity_received' => round($l['accepted'] + $l['rejected'], 3), 'quantity_rejected' => $l['rejected'],
                    'qty_a' => $l['g']['A'], 'qty_b' => $l['g']['B'], 'qty_c' => $l['g']['C'],
                    'rate' => $l['accepted'] > 0 ? round($amount / $l['accepted'], 4) : 0, 'amount' => $amount,
                ]);
                $l['item']->increment('quantity_received', $l['accepted']);
            }

            return $receiving->load('items.product', 'purchaseOrder.vendor');
        });
    }

    public function approve(PurchaseReceiving $receiving, int $userId): PurchaseReceiving
    {
        return DB::transaction(function () use ($receiving, $userId) {
            $receiving = PurchaseReceiving::with('items.product', 'challan')->lockForUpdate()->findOrFail($receiving->id);
            if ($receiving->status !== 'PendingApproval') throw new \Exception('This receiving has already been ' . strtolower($receiving->status) . '.');
            $po = PurchaseOrder::with('vendor')->findOrFail($receiving->purchase_order_id);
            $dropOff = Location::find($po->drop_off_location_id);
            $locationId = ($dropOff && $dropOff->isOwnWarehouse()) ? $dropOff->id : Location::defaultId();

            $post = fn(array $row) => LocationStockLedger::create($row + [
                'doc_no' => $receiving->receiving_no, 'reference_type' => 'PurchaseReceiving',
                'reference_id' => $receiving->id, 'entry_date' => $receiving->receiving_date,
            ]);

            // Greige used up at the mill
            $greigeCredits = [];
            foreach ($receiving->greige_consumed_meta ?? [] as $c) {
                $post(['location_id' => $c['location_id'], 'product_id' => $c['product_id'], 'status' => 'in_process',
                       'lot_no' => $c['lot_no'], 'quantity' => -$c['quantity'], 'amount' => -$c['amount']]);
                $acc = Product::with('category')->find($c['product_id'])?->category?->stock_account_id ?? $this->mapping->accountId('stock_in_hand');
                $greigeCredits[$acc] = ($greigeCredits[$acc] ?? 0) + (float) $c['amount'];
            }

            // Finished fabric in, by grade, at the drop-off warehouse
            $stockDebits = [];
            foreach ($receiving->items as $item) {
                $accepted = (float) $item->qty_a + (float) $item->qty_b + (float) $item->qty_c;
                if ($accepted <= 0) continue;
                $left = (float) $item->amount;
                $grades = array_filter(['A' => (float) $item->qty_a, 'B' => (float) $item->qty_b, 'C' => (float) $item->qty_c], fn($q) => $q > 0);
                $n = 0;
                foreach ($grades as $grade => $qty) {
                    $amt = ++$n === count($grades) ? round($left, 2) : round((float) $item->amount * $qty / $accepted, 2);
                    $left -= $amt;
                    $post(['location_id' => $locationId, 'product_id' => $item->product_id, 'status' => 'fresh',
                           'lot_no' => $po->stock_lot_no, 'grade' => $grade, 'quantity' => $qty, 'amount' => $amt]);
                }
                $acc = $item->product->category->stock_account_id ?? $this->mapping->accountId('stock_in_hand');
                $stockDebits[$acc] = ($stockDebits[$acc] ?? 0) + (float) $item->amount;
            }

            // Voucher: Dr finished stock (+ input tax) / Cr greige stock, Cr vendor for the processing charge
            $charge = (float) $receiving->processing_charge_amount;
            $gst = $po->gst_applicable ? round($charge * ((float) $po->gst_rate / 100), 2) : 0;
            $taxAcc = $gst > 0 ? $this->mapping->accountId('purchase_tax') : null;
            if (!$taxAcc) $gst = 0;
            $ap = $this->mapping->accountId('accounts_payable');
            if (!$ap) throw new \Exception('Accounts Payable mapping is not configured.');

            $lines = [];
            $net = [];
            foreach ($stockDebits as $acc => $amt) $net[$acc] = ($net[$acc] ?? 0) + $amt;
            foreach ($greigeCredits as $acc => $amt) $net[$acc] = ($net[$acc] ?? 0) - $amt;
            foreach ($net as $acc => $amt) if ($amt > 0.004) $lines[] = ['account_id' => $acc, 'debit' => round($amt, 2), 'credit' => 0];
            if ($gst > 0) $lines[] = ['account_id' => $taxAcc, 'debit' => $gst, 'credit' => 0];
            foreach ($net as $acc => $amt) if ($amt < -0.004) $lines[] = ['account_id' => $acc, 'debit' => 0, 'credit' => round(-$amt, 2)];
            if ($charge + $gst > 0) $lines[] = ['account_id' => $ap, 'debit' => 0, 'credit' => round($charge + $gst, 2), 'party_type' => 'vendor', 'party_id' => $po->vendor_id];
            if (count($lines) >= 2) {
                $this->vouchers->post('system', $receiving->receiving_date->format('Y-m-d'), $lines,
                    "Processing Receiving {$receiving->receiving_no} — {$po->order_no}", 'PurchaseReceiving', $receiving->id, $userId);
            }

            // Processing charge payable on the PO's payment terms
            $owed = round($charge + $gst, 2);
            if ($owed > 0 && in_array($po->payment_term_type, ['cash', 'credit', 'pdc'])) {
                $due = $po->payment_term_type === 'cash' ? $receiving->receiving_date->toDateString()
                    : $receiving->receiving_date->copy()->addDays((int) ($po->payment_term_days ?? 0))->toDateString();
                $this->pdcs->createPending('vendor', $po->vendor_id, $owed, $due, 'PurchaseReceiving', $receiving->id, $userId);
            }

            $receiving->update(['status' => 'Approved', 'qc_status' => 'Pending', 'approved_by' => $userId, 'approved_at' => now(), 'updated_by' => $userId]);
            $this->challans->markProcessed($receiving->challan);

            $this->receivings->refreshPoStatus($po);
            if ($receiving->is_final_receiving) $po->fresh()->update(['status' => 'Received', 'is_final_receiving_done' => true]);

            return $receiving->fresh('items.product');
        });
    }

    private function lines(PurchaseOrder $po, array $items): array
    {
        $out = [];
        foreach ($items as $row) {
            $item = $po->items->firstWhere('id', (int) ($row['purchase_order_item_id'] ?? 0));
            if (!$item) throw new \Exception('A line does not belong to this Purchase Order.');
            $g = [];
            foreach (['A' => 'qty_a', 'B' => 'qty_b', 'C' => 'qty_c'] as $grade => $key) $g[$grade] = max(0, round((float) ($row[$key] ?? 0), 3));
            $accepted = round(array_sum($g), 3);
            $rejected = max(0, round((float) ($row['quantity_rejected'] ?? 0), 3));
            if ($accepted + $rejected <= 0.0001) continue;

            $outstanding = round((float) $item->quantity - (float) $item->quantity_received, 3);
            if ($accepted > $outstanding + 0.001) {
                throw new \Exception("Cannot accept {$accepted} m of {$item->pattern_code} — only {$outstanding} m remains on the PO. Request a PO amendment to receive more.");
            }
            $out[] = ['item' => $item, 'g' => $g, 'accepted' => $accepted, 'rejected' => $rejected, 'charge' => round($accepted * (float) $item->rate, 2)];
        }
        if (empty($out)) throw new \Exception('Enter the A / B / C meters received for at least one line.');
        return $out;
    }

    /** Greige still in process for this PO, per mill location / product / lot (oldest first). */
    public function inProcessGreige(PurchaseOrder $po): array
    {
        $issuanceIds = DB::table('issuances')->where('purchase_order_id', $po->id)->whereNull('deleted_at')->pluck('id');
        $receivingIds = DB::table('purchase_receivings')->where('purchase_order_id', $po->id)->where('status', 'Approved')->pluck('id');
        $movementIds = DB::table('stock_movements')->where('purchase_order_id', $po->id)->where('status', 'Received')->pluck('id');

        return LocationStockLedger::where('status', 'in_process')
            ->where(fn($q) => $q->where(fn($a) => $a->where('reference_type', 'Issuance')->whereIn('reference_id', $issuanceIds ?: [0]))
                               ->orWhere(fn($b) => $b->where('reference_type', 'PurchaseReceiving')->whereIn('reference_id', $receivingIds ?: [0]))
                               ->orWhere(fn($c) => $c->where('reference_type', 'StockMovement')->whereIn('reference_id', $movementIds ?: [0])))
            ->groupBy('location_id', 'product_id', 'lot_no')
            ->selectRaw('location_id, product_id, lot_no, SUM(quantity) AS qty, SUM(amount) AS amount, MIN(entry_date) AS first_date')
            ->havingRaw('SUM(quantity) > 0.0005')->get()
            ->sortBy(fn($b) => [$b->first_date, $b->lot_no])->values()
            ->map(fn($b) => ['location_id' => $b->location_id, 'product_id' => $b->product_id, 'lot_no' => $b->lot_no,
                             'qty' => round((float) $b->qty, 3), 'amount' => round((float) $b->amount, 2)])->all();
    }

    private function greigeToUse(PurchaseOrder $po, float $accepted, bool $final): array
    {
        $buckets = $this->inProcessGreige($po);
        $remaining = array_sum(array_column($buckets, 'qty'));
        $outstanding = (float) $po->items->sum(fn($i) => max(0, (float) $i->quantity - (float) $i->quantity_received));
        $want = ($final || $outstanding <= 0.001) ? $remaining : round($remaining * min(1, $accepted / $outstanding), 3);

        $out = [];
        foreach ($buckets as $b) {
            if ($want <= 0.0005) break;
            $take = round(min($want, $b['qty']), 3);
            $out[] = ['location_id' => $b['location_id'], 'product_id' => $b['product_id'], 'lot_no' => $b['lot_no'],
                      'quantity' => $take, 'amount' => round($b['amount'] * $take / $b['qty'], 2)];
            $want = round($want - $take, 3);
        }
        return $out;
    }

    /** Reprocess: this line's own fabric in process at the mill, in proportion to what came back (all of it when final). */
    private function fabricToUse(PurchaseOrder $po, PurchaseOrderItem $item, float $accepted, bool $final): array
    {
        $buckets = array_values(array_filter($this->inProcessGreige($po), fn($b) => (int) $b['product_id'] === (int) $item->product_id));
        $remaining = array_sum(array_column($buckets, 'qty'));
        $outstanding = max(0, (float) $item->quantity - (float) $item->quantity_received);
        $want = ($final || $outstanding <= 0.001) ? $remaining : round($remaining * min(1, $accepted / $outstanding), 3);
        $out = [];
        foreach ($buckets as $b) {
            if ($want <= 0.0005) break;
            $take = round(min($want, $b['qty']), 3);
            $out[] = ['location_id' => $b['location_id'], 'product_id' => $b['product_id'], 'lot_no' => $b['lot_no'],
                      'quantity' => $take, 'amount' => round($b['amount'] * $take / $b['qty'], 2)];
            $want = round($want - $take, 3);
        }
        return $out;
    }

    /** The Processed Fabric product for a pattern, created the first time it is received. */
    public function finishedProduct(PurchaseOrder $po, PurchaseOrderItem $item): Product
    {
        // Reprocessed fabric comes back as the same product
        if ($po->isReprocess() && $item->product_id) return Product::findOrFail($item->product_id);

        $category = ProductCategory::where('code', 'processed')->first();
        if (!$category) throw new \Exception('The "Processed Fabric" category (code: processed) is missing.');

        $sku = 'PF-' . strtoupper(Str::slug($item->pattern_code ?: ('line-' . $item->id)));
        if ($p = Product::withTrashed()->where('sku', $sku)->first()) return $p;

        return Product::create([
            'category_id' => $category->id, 'sku' => $sku,
            'name' => trim($item->pattern_code . ' ' . collect([$item->design, $item->color_scheme])->filter()->implode(' - ')),
            'measurement_unit' => \App\Models\MeasurementUnit::meterId(),
            'attributes' => array_filter([
                'design_code' => $item->pattern_code, 'color' => $item->color_scheme,
                'print_method' => $po->serviceType->name ?? null, 'finished_width' => $po->fabric_specs['finished_width'] ?? null,
            ]),
            'is_active' => true, 'track_lots' => true,
        ]);
    }
}
