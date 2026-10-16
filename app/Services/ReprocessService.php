<?php

namespace App\Services;

use App\Models\{Location, LocationStockLedger, Product, ProductCategory, PurchaseOrder, PurchaseOrderItem, PurchaseReceivingItem, StockMovement, StockMovementItem};
use Illuminate\Support\Facades\DB;

/**
 * Sending finished fabric back to a mill for reprocessing.
 *   FOC     → no charge: the order is approved at once (no PO to raise) and its gate pass is created
 *   Charged → a reprocess PO at a rate; normal PO approval, the gate pass is created on approval
 * The fabric keeps its lot #. At the mill it goes straight into process for the order; it comes back on the
 * order's challan (A / B / C), is costed at its own value (+ charge if any) and goes through QC again.
 */
class ReprocessService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private StockMovementService $movements,
        private NotificationService $notifications,
    ) {}

    /**
     * Finished fabric that can be sent: the QC reprocess hold and graded fresh stock, by lot.
     * [['location_id','product_id','product','lot_no','source' => 'reprocess'|'A'|'B'|'C','qty','free'], ...]
     */
    public function available(?int $locationId = null): array
    {
        $category = ProductCategory::where('code', 'processed')->value('id');
        $rows = LocationStockLedger::query()
            ->join('products as p', 'p.id', '=', 'location_stock_ledger.product_id')
            ->join('locations as l', 'l.id', '=', 'location_stock_ledger.location_id')
            ->whereNull('l.vendor_id')->where('p.category_id', $category)->whereNotNull('location_stock_ledger.lot_no')
            ->where(fn($q) => $q->where('location_stock_ledger.status', 'reprocess')
                ->orWhere(fn($f) => $f->where('location_stock_ledger.status', 'fresh')->whereNotNull('location_stock_ledger.grade')))
            ->when($locationId, fn($q) => $q->where('location_stock_ledger.location_id', $locationId))
            ->groupBy('location_stock_ledger.location_id', 'location_stock_ledger.product_id', 'location_stock_ledger.lot_no', 'location_stock_ledger.status', 'location_stock_ledger.grade', 'p.name')
            ->selectRaw('location_stock_ledger.location_id, location_stock_ledger.product_id, p.name AS product, location_stock_ledger.lot_no, location_stock_ledger.status, location_stock_ledger.grade, SUM(location_stock_ledger.quantity) AS qty')
            ->havingRaw('SUM(location_stock_ledger.quantity) > 0.0005')->get();

        return $rows->map(function ($r) {
            $source = $r->status === 'reprocess' ? 'reprocess' : $r->grade;
            $held = (float) StockMovementItem::where('product_id', $r->product_id)->where('source_lot_no', $r->lot_no)
                ->where('source_status', $r->status)->when($r->status === 'fresh', fn($q) => $q->where('source_grade', $r->grade))
                ->whereHas('stockMovement', fn($q) => $q->where('from_location_id', $r->location_id)->whereIn('status', [StockMovement::PENDING, StockMovement::APPROVED]))
                ->sum('quantity');
            return ['location_id' => (int) $r->location_id, 'product_id' => (int) $r->product_id, 'product' => $r->product, 'lot_no' => $r->lot_no,
                    'source' => $source, 'qty' => round((float) $r->qty, 3), 'free' => round(max(0, (float) $r->qty - $held), 3)];
        })->sortBy(fn($r) => [$r['source'] === 'reprocess' ? 0 : 1, $r['lot_no'], $r['product'], $r['source']])->values()->all();
    }

    /**
     * $data: from_location_id, mill_location_id, lot_no, is_foc, order_date, expected_date, remarks, reason
     * $lines: [['product_id', 'source' => reprocess|A|B|C, 'quantity', 'rate'], ...]
     */
    public function create(array $data, array $lines, int $userId): PurchaseOrder
    {
        $order = DB::transaction(function () use ($data, $lines, $userId) {
            $lot = trim((string) ($data['lot_no'] ?? ''));
            $original = PurchaseOrder::where('type', 'processing')->whereNull('reprocess_of_po_id')->where('order_no', $lot)->first();
            if (!$original) throw new \Exception("Lot {$lot} is not a processing lot.");

            $from = Location::findOrFail($data['from_location_id']);
            $mill = Location::findOrFail($data['mill_location_id']);
            if ($from->vendor_id) throw new \Exception('Fabric is sent from one of our warehouses.');
            if (!$mill->vendor_id) throw new \Exception('Choose the mill the fabric goes to.');
            $foc = (bool) ($data['is_foc'] ?? true);

            $lines = array_values(array_filter($lines, fn($l) => (float) ($l['quantity'] ?? 0) > 0));
            if (empty($lines)) throw new \Exception('Enter the meters to send for at least one line.');

            // Free stock check per source (stock movement re-checks when it is created)
            $avail = collect($this->available($from->id))->where('lot_no', $lot);
            $want = [];
            foreach ($lines as $l) {
                $k = $l['product_id'] . '|' . $l['source'];
                $want[$k] = ($want[$k] ?? 0) + (float) $l['quantity'];
            }
            foreach ($want as $k => $qty) {
                [$pid, $src] = explode('|', $k);
                $free = (float) ($avail->first(fn($a) => $a['product_id'] == $pid && $a['source'] === $src)['free'] ?? 0);
                if ($qty > $free + 0.001) {
                    $what = $src === 'reprocess' ? 'on reprocess hold' : "in grade {$src}";
                    throw new \Exception((Product::find($pid)->name ?? 'Item') . ": only {$free} m {$what} in lot {$lot}, you entered {$qty}.");
                }
            }

            // One PO line per product (the pattern it was processed as)
            $byProduct = collect($lines)->groupBy('product_id');
            $subtotal = 0; $poLines = [];
            foreach ($byProduct as $pid => $group) {
                $qty = round($group->sum(fn($l) => (float) $l['quantity']), 3);
                $rate = $foc ? 0 : round((float) ($group->first()['rate'] ?? 0), 2);
                if (!$foc && $rate <= 0) throw new \Exception('Enter the reprocessing rate, or mark the order Free of Charge.');
                $src = PurchaseReceivingItem::where('product_id', $pid)->whereHas('purchaseReceiving', fn($q) => $q->where('purchase_order_id', $original->id))
                    ->with('purchaseOrderItem')->first()?->purchaseOrderItem;
                $poLines[] = compact('pid', 'qty', 'rate', 'src');
                $subtotal += round($qty * $rate, 2);
            }

            $order = PurchaseOrder::create([
                'order_no' => $this->numbers->next('reprocess_order', 'purchase_orders', 'order_no', 'RPO'),
                'type' => 'processing', 'revision_no' => 1, 'vendor_id' => $mill->vendor_id,
                'product_category_id' => ProductCategory::where('code', 'processed')->value('id') ?? $original->product_category_id,
                'service_type_id' => $original->service_type_id, 'job_id' => $original->job_id, 'program' => $original->program,
                'from_location_id' => $from->id, 'drop_off_location_id' => $from->id,
                'order_date' => $data['order_date'] ?? now()->toDateString(), 'expected_date' => $data['expected_date'] ?? null,
                'payment_term_type' => $foc ? 'cash' : ($data['payment_term_type'] ?? $original->payment_term_type ?? 'cash'),
                'payment_term_days' => $foc ? null : ($data['payment_term_days'] ?? $original->payment_term_days),
                'gst_applicable' => false, 'gst_rate' => 0, 'gst_amount' => 0,
                'subtotal' => $subtotal, 'total_amount' => $subtotal,
                'reprocess_of_po_id' => $original->id, 'reprocess_lot_no' => $lot, 'is_foc' => $foc,
                'fabric_specs' => array_filter([
                    'po_kind' => $foc ? 'Reprocess (FOC)' : 'Reprocess',
                    'finished_width' => $original->fabric_specs['finished_width'] ?? null,
                    'finished_gsm' => $original->fabric_specs['finished_gsm'] ?? null,
                    'notes' => array_values(array_filter([trim((string) ($data['reason'] ?? ''))])),
                    'mill_location_id' => $mill->id,
                    'reprocess_lines' => array_map(fn($l) => ['product_id' => (int) $l['product_id'], 'source' => $l['source'], 'quantity' => round((float) $l['quantity'], 3)], $lines),
                ], fn($v) => $v !== null && $v !== []),
                'status' => PurchaseOrder::STATUS_PENDING, 'submitted_by' => $userId, 'submitted_at' => now(),
                'remarks' => $data['remarks'] ?? null, 'created_by' => $userId, 'updated_by' => $userId,
            ]);

            $meter = DB::table('measurement_units')->where('shortcode', 'm')->value('id');
            foreach ($poLines as $l) {
                $product = Product::find($l['pid']);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id, 'product_id' => $l['pid'], 'job_item_id' => $l['src']?->job_item_id,
                    'collection' => $l['src']?->collection, 'pattern_code' => $l['src']?->pattern_code ?? $product->sku,
                    'design' => $l['src']?->design, 'color_scheme' => $l['src']?->color_scheme,
                    'description' => 'Reprocess: ' . $product->name, 'measurement_unit' => $meter,
                    'quantity' => $l['qty'], 'rate' => $l['rate'], 'amount' => round($l['qty'] * $l['rate'], 2),
                ]);
            }

            if ($foc) {
                // Nothing to pay, nothing to approve: the order goes out on its gate pass
                $order->update(['status' => PurchaseOrder::STATUS_APPROVED, 'approved_by' => $userId, 'approved_at' => now()]);
                $this->createMovement($order->fresh(), $userId);
            } else {
                $this->notifications->notifyRole('superadmin', 'po_submitted', 'Reprocess PO Awaiting Approval',
                    "{$order->order_no} (reprocess of lot {$lot}) was submitted for approval.", 'purchase_order', $order->id);
            }
            return $order;
        });
        return $order->fresh(['items', 'vendor']);
    }

    /** Gate pass (stock movement) taking the fabric from the warehouse to the mill. */
    public function createMovement(PurchaseOrder $order, int $userId): StockMovement
    {
        if (!$order->isReprocess()) throw new \Exception('Not a reprocess order.');
        if ($m = StockMovement::where('purchase_order_id', $order->id)->whereNotIn('status', [StockMovement::REJECTED])->first()) return $m;

        $items = array_map(fn($l) => [
            'product_id' => $l['product_id'], 'quantity' => $l['quantity'], 'source_lot_no' => $order->reprocess_lot_no,
            'source_status' => $l['source'] === 'reprocess' ? 'reprocess' : 'fresh',
            'source_grade' => $l['source'] === 'reprocess' ? null : $l['source'],
        ], $order->fabric_specs['reprocess_lines'] ?? []);

        return $this->movements->create([
            'movement_type' => 'warehouse_to_vendor', 'from_location_id' => $order->from_location_id,
            'to_location_id' => $order->fabric_specs['mill_location_id'], 'purchase_order_id' => $order->id,
            'movement_date' => now()->toDateString(),
            'remarks' => "Reprocess {$order->order_no} — lot {$order->reprocess_lot_no}" . ($order->is_foc ? ' (FOC)' : ''),
        ], $items, $userId);
    }
}
