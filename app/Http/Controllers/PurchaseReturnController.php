<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReceiving;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseReturnController extends Controller
{
    public function __construct(private PurchaseReturnService $service) {}

    public function index()
    {
        $returns = PurchaseReturn::with('purchaseReceiving.purchaseOrder.vendor', 'items.purchaseReceivingItem.product')
            ->orderByDesc('return_date')->get();

        return view('purchase_returns.index', compact('returns'));
    }

    public function create($receivingId)
    {
        $receiving = PurchaseReceiving::with('items.product', 'purchaseOrder.vendor')->findOrFail($receivingId);

        $pendingItems = $receiving->items->filter(fn($i) => $i->quantity_pending_return > 0)->values();

        if ($pendingItems->isEmpty()) {
            return back()->with('error', 'No items are pending return on this receiving.');
        }

        return view('purchase_returns.create', compact('receiving', 'pendingItems'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_receiving_id'                       => 'required|exists:purchase_receivings,id',
            'return_date'                                   => 'required|date',
            'remarks'                                       => 'nullable|string',
            'proof_images'                                   => 'nullable|array',
            'proof_images.*'                                 => 'file|image|max:5120',
            'items'                                           => 'required|array|min:1',
            'items.*.purchase_receiving_item_id'          => 'required|exists:purchase_receiving_items,id',
            'items.*.quantity_returned'                    => 'required|numeric|min:0.001',
        ]);

        try {
            $images = [];
            if ($request->hasFile('proof_images')) {
                foreach ($request->file('proof_images') as $file) {
                    $images[] = $file->store('purchase_return_proofs', 'public');
                }
            }

            $return = $this->service->create(
                array_merge($request->all(), ['proof_images' => $images ?: null]),
                $request->items,
                auth()->id()
            );

            Log::info('[PurchaseReturn] Created', ['id' => $return->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_receivings.show', $request->purchase_receiving_id)
                ->with('success', $return->return_no . ' recorded successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReturn] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $return = PurchaseReturn::with('items.purchaseReceivingItem.product')->findOrFail($id);
        return view('purchase_returns.edit', compact('return'));
    }

    public function update(Request $request, $id)
    {
        $request->validate(['remarks' => 'nullable|string', 'proof_images' => 'nullable|array', 'proof_images.*' => 'file|image|max:5120']);

        $return = PurchaseReturn::findOrFail($id);
        $images = $return->proof_images ?? [];
        if ($request->hasFile('proof_images')) {
            foreach ($request->file('proof_images') as $file) $images[] = $file->store('purchase_return_proofs', 'public');
        }

        $return->update(['remarks' => $request->remarks, 'proof_images' => $images ?: null]);
        return redirect()->route('purchase_returns.index')->with('success', 'Return updated successfully.');
    }
}