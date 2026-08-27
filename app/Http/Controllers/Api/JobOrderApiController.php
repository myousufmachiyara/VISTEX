<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Models\VendorStockLedger;
use App\Http\Resources\JobOrderResource;
use App\Services\JobOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JobOrderApiController extends Controller
{
    public function __construct(private JobOrderService $service) {}

    public function index()
    {
        $jobOrders = JobOrder::with('vendor', 'items.product')
            ->withCount('comments')
            ->orderByDesc('issue_date')
            ->get();

        return JobOrderResource::collection($jobOrders);
    }

    public function show($id)
    {
        $jobOrder = JobOrder::with('vendor', 'items.product', 'comments.user')->findOrFail($id);
        return new JobOrderResource($jobOrder);
    }

    public function availableStock(Request $request)
    {
        $fresh    = VendorStockLedger::balance($request->vendor_id, $request->product_id, 'fresh');
        $leftover = VendorStockLedger::balance($request->vendor_id, $request->product_id, 'leftover');

        return response()->json([
            'fresh'    => $fresh,
            'leftover' => $leftover,
            'total'    => $fresh + $leftover,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id'          => 'required|exists:vendors,id',
            'sale_id'            => 'nullable|exists:sales,id',
            'job_type'           => 'nullable|string|max:50',
            'issue_date'         => 'required|date',
            'remarks'            => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
        ]);

        try {
            $jobOrder = $this->service->create([
                'vendor_id'  => $request->vendor_id,
                'sale_id'    => $request->sale_id,
                'job_type'   => $request->job_type,
                'issue_date' => $request->issue_date,
                'remarks'    => $request->remarks,
            ], $request->items, $request->user()->id);

            return response()->json(['success' => true, 'id' => $jobOrder->id, 'job_no' => $jobOrder->job_no]);

        } catch (\Exception $e) {
            Log::error('[JobOrder API] Store failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $jobOrder = JobOrder::findOrFail($id);
            $this->service->delete($jobOrder);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function addComment(Request $request, $id)
    {
        $request->validate(['comment' => 'required|string|max:1000']);

        try {
            $jobOrder = JobOrder::findOrFail($id);
            $comment = $jobOrder->comments()->create([
                'user_id' => $request->user()->id,
                'comment' => $request->comment,
            ]);

            return response()->json([
                'success' => true,
                'comment' => [
                    'id'         => $comment->id,
                    'comment'    => $comment->comment,
                    'user_name'  => $request->user()->name,
                    'created_at' => $comment->created_at->diffForHumans(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Could not add comment.'], 500);
        }
    }

    public function getComments($id)
    {
        $jobOrder = JobOrder::with('comments.user')->findOrFail($id);

        return response()->json($jobOrder->comments->map(fn($c) => [
            'id'         => $c->id,
            'comment'    => $c->comment,
            'user_name'  => $c->user->name ?? 'User',
            'user_id'    => $c->user_id,
            'created_at' => $c->created_at->diffForHumans(),
        ]));
    }
}