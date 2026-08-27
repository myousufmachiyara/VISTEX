<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Models\JobOrderReceive;
use App\Models\Product;
use App\Models\VendorStockLedger;
use App\Http\Resources\JobOrderReceiveResource;
use App\Services\JobOrderReceiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JobOrderReceiveApiController extends Controller
{
    public function __construct(private JobOrderReceiveService $service) {}

    public function index()
    {
        $receives = JobOrderReceive::with('jobOrder.vendor', 'items.rawProduct', 'items.outputProduct')
            ->orderByDesc('receive_date')
            ->get();

        return JobOrderReceiveResource::collection($receives);
    }

    public function show($id)
    {
        $receive = JobOrderReceive::with('jobOrder.vendor', 'items.rawProduct', 'items.outputProduct')
            ->findOrFail($id);

        return new JobOrderReceiveResource($receive);
    }

    // GET /api/job-orders (only those that still have outstanding stock)
    public function pendingJobOrders()
    {
        $jobOrders = JobOrder::with('vendor')
            ->whereIn('status', ['Issued', 'PartiallyReceived'])
            ->orderByDesc('issue_date')
            ->get();

        return response()->json($jobOrders->map(fn($j) => [
            'id'          => $j->id,
            'job_no'      => $j->job_no,
            'vendor_id'   => $j->vendor_id,
            'vendor_name' => $j->vendor?->name,
            'status'      => $j->status,
        ]));
    }

    // GET /api/job-receives/outstanding/{jobOrderId}
    public function outstanding($jobOrderId)
    {
        $jobOrder = JobOrder::with('vendor')->findOrFail($jobOrderId);

        $issued = VendorStockLedger::where('vendor_id', $jobOrder->vendor_id)
            ->where('reference_type', 'JobOrder')
            ->where('reference_id', $jobOrder->id)
            ->where('status', 'issued')
            ->selectRaw('product_id, SUM(quantity) as issued_qty')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $received = VendorStockLedger::where('vendor_id', $jobOrder->vendor_id)
            ->whereIn('reference_id', $jobOrder->receives()->pluck('id'))
            ->where('reference_type', 'JobOrderReceive')
            ->where('status', 'issued')
            ->selectRaw('product_id, SUM(quantity) as received_qty')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $outstanding = [];
        foreach ($issued as $productId => $row) {
            $stillOutstanding = (float) $row->issued_qty + (float) ($received[$productId]->received_qty ?? 0);
            if ($stillOutstanding > 0.001) {
                $product = Product::find($productId);
                $outstanding[] = [
                    'product_id'   => $productId,
                    'product_name' => $product?->name,
                    'outstanding'  => round($stillOutstanding, 3),
                ];
            }
        }

        return response()->json($outstanding);
    }

    // GET /api/products (already exists — reused for output product picker)

    public function store(Request $request)
    {
        $request->validate([
            'job_order_id'                => 'required|exists:job_orders,id',
            'receive_date'                 => 'required|date',
            'processing_charge'            => 'nullable|numeric|min:0',
            'remarks'                      => 'nullable|string',
            'items'                        => 'required|array|min:1',
            'items.*.raw_product_id'      => 'required|exists:products,id',
            'items.*.quantity_consumed'   => 'required|numeric|min:0',
            'items.*.output_product_id'   => 'nullable|exists:products,id',
            'items.*.quantity_output'     => 'nullable|numeric|min:0',
        ]);

        try {
            $receive = $this->service->create([
                'job_order_id'      => $request->job_order_id,
                'receive_date'      => $request->receive_date,
                'processing_charge' => $request->processing_charge ?? 0,
                'remarks'           => $request->remarks,
            ], $request->items, $request->user()->id);

            return response()->json(['success' => true, 'id' => $receive->id, 'receive_no' => $receive->receive_no]);

        } catch (\Exception $e) {
            Log::error('[JobOrderReceive API] Store failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $receive = JobOrderReceive::findOrFail($id);
            $this->service->delete($receive);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}