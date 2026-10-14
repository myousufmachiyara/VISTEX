<?php

namespace App\Http\Controllers;

use App\Models\{Location, LocationStockLedger, Product, PurchaseOrder, StockMovement};
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Stock Movement → Gate Pass → Challan OUT → Receive
class StockMovementController extends Controller
{
    public function __construct(private StockMovementService $service) {}

    public function index(Request $request)
    {
        $movements = StockMovement::with('fromLocation', 'toLocation', 'items.product', 'purchaseOrder')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->where('movement_type', $request->type))
            ->orderByDesc('movement_date')->orderByDesc('id')
            ->get();

        return view('stock_movements.index', compact('movements'));
    }

    // Gatekeeper's list: gate passes waiting to leave, and today's exits
    public function gate()
    {
        $waiting = StockMovement::awaitingGate()->with('fromLocation', 'toLocation', 'items.product')->orderBy('approved_at')->get();
        $today = StockMovement::where('status', '!=', StockMovement::APPROVED)->whereDate('dispatched_at', today())
            ->with('fromLocation', 'toLocation', 'dispatcher')->orderByDesc('dispatched_at')->get();

        return view('stock_movements.gate', compact('waiting', 'today'));
    }

    public function create(Request $request)
    {
        return view('stock_movements.create', [
            'locations'      => Location::active()->with('vendor')->orderBy('name')->get(),
            'products'       => Product::active()->with('category')->orderBy('name')->get(['id', 'name', 'sku', 'category_id']),
            'processingPos'  => PurchaseOrder::where('type', 'processing')->whereIn('status', ['Approved', 'Issued', 'PartiallyReceived'])
                                   ->with('vendor')->orderByDesc('order_date')->get(),
            'preselectPo'    => $request->integer('purchase_order_id') ?: null,
        ]);
    }

    // AJAX: free stock of a product at a location, by lot
    public function availableLots(Request $request)
    {
        $request->validate(['location_id' => 'required|exists:locations,id', 'product_id' => 'required|exists:products,id']);

        $rows = LocationStockLedger::where('location_id', $request->location_id)->where('product_id', $request->product_id)
            ->where('status', 'fresh')->groupBy('lot_no')->selectRaw('lot_no, SUM(quantity) AS qty')
            ->havingRaw('SUM(quantity) > 0.0005')->orderBy('lot_no')->get();

        return response()->json([
            'total' => round((float) $rows->sum('qty'), 3),
            'lots'  => $rows->filter(fn($r) => $r->lot_no !== null)->map(fn($r) => ['lot_no' => $r->lot_no, 'quantity' => round((float) $r->qty, 3)])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'movement_type'         => 'required|in:' . implode(',', array_keys(StockMovement::TYPES)),
            'from_location_id'      => 'required|exists:locations,id',
            'to_location_id'        => 'required|exists:locations,id|different:from_location_id',
            'purchase_order_id'     => 'nullable|exists:purchase_orders,id',
            'movement_date'         => 'required|date',
            'remarks'               => 'nullable|string',
            'attachments'           => 'nullable|array',
            'attachments.*'         => 'file|max:5120',
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'nullable|exists:products,id',
            'items.*.quantity'      => 'nullable|numeric|min:0',
            'items.*.source_lot_no' => 'nullable|string|max:100',
        ]);

        try {
            $attachments = [];
            foreach ($request->file('attachments', []) as $file) $attachments[] = $file->store('stock_movement_attachments', 'public');

            $movement = $this->service->create(array_merge($request->all(), ['attachments' => $attachments ?: null]), $request->items, auth()->id());
            Log::info('[StockMovement] Created', ['id' => $movement->id, 'by' => auth()->id()]);

            return redirect()->route('stock_movements.show', $movement->id)->with('success', $movement->movement_no . ' created — waiting for approval by the source location in-charge.');
        } catch (\Exception $e) {
            Log::error('[StockMovement] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $movement = StockMovement::with('fromLocation.vendor', 'toLocation.vendor', 'items.product.measurementUnit', 'purchaseOrder',
            'approver', 'creator', 'dispatcher', 'receiver')->findOrFail($id);

        // Which lots actually left the source (set at the gate)
        $lots = LocationStockLedger::where('reference_type', 'StockMovement')->where('reference_id', $movement->id)
            ->where('location_id', $movement->from_location_id)->where('quantity', '<', 0)
            ->get(['product_id', 'lot_no', 'quantity'])->groupBy('product_id');

        return view('stock_movements.show', compact('movement', 'lots'));
    }

    // Printable gate pass (also the outward challan)
    public function gatePass($id)
    {
        $movement = StockMovement::with('fromLocation.vendor', 'toLocation.vendor', 'items.product.measurementUnit', 'purchaseOrder',
            'approver', 'creator', 'dispatcher')->findOrFail($id);
        abort_if(in_array($movement->status, [StockMovement::PENDING, StockMovement::REJECTED], true), 403, 'A gate pass is issued once the movement is approved.');
        return view('stock_movements.gate_pass', compact('movement'));
    }

    public function approve($id)
    {
        $movement = StockMovement::with('fromLocation', 'toLocation')->findOrFail($id);
        abort_unless($movement->canBeApprovedBy(auth()->user()), 403, 'Only the source location in-charge or a superadmin can approve this movement.');

        try {
            $movement = $this->service->approve($movement, auth()->id());
            return back()->with('success', $movement->status === StockMovement::DISPATCHED
                ? 'Approved — the goods are on their way. Receive them when they arrive.'
                : 'Approved — gate pass issued. The gate will verify the goods before they leave.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $movement = StockMovement::with('fromLocation')->findOrFail($id);
        abort_unless($movement->canBeApprovedBy(auth()->user()), 403, 'Only the source location in-charge or a superadmin can cancel this movement.');
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->service->reject($movement, auth()->id(), $request->reason);
            return back()->with('success', 'Movement rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // Gatekeeper verifies the goods and lets them out (Challan OUT)
    public function gateOut(Request $request, $id)
    {
        $movement = StockMovement::findOrFail($id);
        $request->validate(\App\Http\Controllers\ChallanController::TRANSPORT_RULES + [
            'gate_remarks'  => 'nullable|string|max:1000',
            'gate_images'   => 'nullable|array',
            'gate_images.*' => 'file|image|max:5120',
        ]);

        try {
            $images = [];
            foreach ($request->file('gate_images', []) as $file) $images[] = $file->store('gate_images', 'public');

            $movement = $this->service->dispatch($movement, $request->only('vehicle_no', 'driver_name', 'driver_contact', 'gate_remarks') + ['gate_images' => $images ?: null], auth()->id());
            return redirect()->route('stock_movements.show', $movement->id)->with('success', "{$movement->movement_no} verified at the gate — stock is in transit.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // Destination in-charge receives; a mill destination enters its lot #
    public function receive(Request $request, $id)
    {
        $movement = StockMovement::with('toLocation')->findOrFail($id);
        abort_unless($movement->canBeReceivedBy(auth()->user()), 403, 'Only the destination location in-charge or a superadmin can receive this movement.');
        $request->validate(['lot_no' => 'nullable|string|max:100', 'remarks' => 'nullable|string|max:1000']);

        try {
            $movement = $this->service->receive($movement, $request->only('lot_no', 'remarks'), auth()->id());
            return back()->with('success', "Received at {$movement->toLocation->name}" . ($movement->lot_no ? " under lot {$movement->lot_no}" : '') . '.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
