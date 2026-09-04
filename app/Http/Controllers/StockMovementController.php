<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\Location;
use App\Models\Product;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StockMovementController extends Controller
{
    public function __construct(private StockMovementService $service) {}

    public function index(Request $request)
    {
        $movements = StockMovement::with('fromLocation', 'toLocation', 'items.product')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->where('movement_type', $request->type))
            ->orderByDesc('movement_date')
            ->get();

        return view('stock_movements.index', compact('movements'));
    }

    public function create()
    {
        $locations = Location::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();

        return view('stock_movements.create', compact('locations', 'products'));
    }

    // AJAX: available lots at a vendor location for a product (for vendor_to_warehouse)
    public function availableLots(Request $request)
    {
        $request->validate(['location_id' => 'required|exists:locations,id', 'product_id' => 'required|exists:products,id']);

        $lots = \App\Models\LocationStockLedger::availableLots($request->location_id, $request->product_id);

        return response()->json($lots->map(fn($qty, $lot) => ['lot_no' => $lot, 'quantity' => $qty])->values());
    }

    public function store(Request $request)
    {
        $request->validate([
            'movement_type'      => 'required|in:warehouse_to_warehouse,warehouse_to_vendor,vendor_to_warehouse',
            'from_location_id'   => 'required|exists:locations,id',
            'to_location_id'     => 'required|exists:locations,id|different:from_location_id',
            'lot_no'              => 'required_if:movement_type,warehouse_to_vendor|nullable|string|max:100',
            'movement_date'      => 'required|date',
            'remarks'            => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.source_lot_no' => 'nullable|string|max:100',
        ]);

        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('stock_movement_attachments', 'public');
                }
            }

            $movement = $this->service->create(
                array_merge($request->all(), ['attachments' => $attachments ?: null]),
                $request->items,
                auth()->id()
            );

            Log::info('[StockMovement] Created', ['id' => $movement->id, 'by' => auth()->id()]);

            return redirect()->route('stock_movements.index')->with('success', $movement->movement_no . ' created — pending approval.');

        } catch (\Exception $e) {
            Log::error('[StockMovement] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $movement = StockMovement::with('fromLocation', 'toLocation', 'items.product', 'approver')->findOrFail($id);
        return view('stock_movements.show', compact('movement'));
    }

    public function approve($id)
    {
        $movement = StockMovement::findOrFail($id);
        if (!$movement->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only the destination location\'s in-charge or a superadmin can approve this movement.');
        }

        try {
            $this->service->approve($movement, auth()->id());
            return back()->with('success', 'Movement approved — stock updated at both locations.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $movement = StockMovement::findOrFail($id);
        if (!$movement->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only the destination location\'s in-charge or a superadmin can reject this movement.');
        }

        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->service->reject($movement, auth()->id(), $request->reason);
            return back()->with('success', 'Movement rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}