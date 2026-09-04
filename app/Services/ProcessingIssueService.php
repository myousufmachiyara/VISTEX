<?php

namespace App\Services;

use App\Models\ProcessingIssue;
use App\Models\ProcessingIssueItem;
use App\Models\PurchaseOrder;
use App\Models\LocationStockLedger;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProcessingIssueService
{
    public function __construct(private DocumentNumberService $numberService) {}

    // $items: [ ['product_id'=>.., 'purchase_order_item_id'=>.., 'quantity'=>..], ... ]
    public function create(array $data, array $items, ?int $userId = null): ProcessingIssue
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $po = PurchaseOrder::findOrFail($data['purchase_order_id']);

            if ($po->type !== 'processing') {
                throw new \Exception('This action only applies to Processing-type Purchase Orders.');
            }
            if ($po->status !== 'Issued') {
                throw new \Exception('This Processing PO has not reached Issued status.');
            }

            $items = array_values(array_filter($items, fn($i) => (float) ($i['quantity'] ?? 0) > 0));
            if (empty($items)) {
                throw new \Exception('Enter a quantity for at least one item.');
            }

            $issue = ProcessingIssue::create([
                'issue_no'          => $this->numberService->next('processing_issue', 'processing_issues', 'issue_no', 'PI'),
                'purchase_order_id' => $po->id,
                'location_id'       => $data['location_id'],
                'lot_no'            => $data['lot_no'],
                'issue_date'        => $data['issue_date'],
                'remarks'           => $data['remarks'] ?? null,
                'attachments'       => $data['attachments'] ?? null,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $product = Product::findOrFail($item['product_id']);

                $available = LocationStockLedger::balance($data['location_id'], $product->id, 'fresh', $data['lot_no']);
                if ($qty > $available + 0.001) {
                    throw new \Exception("Insufficient stock of {$product->name} in lot {$data['lot_no']} — available: " . round($available, 3) . ".");
                }

                $rate = $product->weightedAverageCost($data['location_id']);
                $amount = round($qty * $rate, 2);

                ProcessingIssueItem::create([
                    'processing_issue_id'    => $issue->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'product_id'             => $product->id,
                    'quantity'               => $qty,
                    'rate'                   => $rate,
                    'amount'                 => $amount,
                ]);

                // Consumes the lot at the vendor location — this greige is
                // now "in process" at the mill, no longer sitting as raw
                // stock waiting to be worked on.
                LocationStockLedger::create([
                    'doc_no'          => $issue->issue_no,
                    'location_id'     => $data['location_id'],
                    'product_id'      => $product->id,
                    'status'          => 'issued', // distinct status: consumed for processing, not simply "fresh" stock anymore
                    'lot_no'          => $data['lot_no'],
                    'quantity'        => -$qty,
                    'amount'          => -$amount,
                    'reference_type'  => 'ProcessingIssue',
                    'reference_id'    => $issue->id,
                    'entry_date'      => $data['issue_date'],
                ]);
            }

            return $issue->load('items.product', 'purchaseOrder', 'location');
        });
    }

    public function delete(ProcessingIssue $issue): void
    {
        DB::transaction(function () use ($issue) {
            LocationStockLedger::where('reference_type', 'ProcessingIssue')->where('reference_id', $issue->id)->delete();
            $issue->items()->delete();
            $issue->delete();
        });
    }
}