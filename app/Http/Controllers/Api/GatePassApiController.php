<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ChallanController;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\Request;

// Challan OUT at the gate (mobile): gate passes waiting to leave, and letting them out
class GatePassApiController extends Controller
{
    public function __construct(private StockMovementService $service) {}

    private function denied(Request $request)
    {
        return $request->user()->can('stock_movements.gate') ? null
            : response()->json(['message' => 'Only the gate can check goods out.'], 403);
    }

    private function row(StockMovement $m): array
    {
        return [
            'id' => $m->id, 'movement_no' => $m->movement_no, 'status' => $m->status, 'status_label' => $m->status_label,
            'from' => $m->fromLocation->name ?? '', 'to' => $m->toLocation->name ?? '',
            'to_vendor' => $m->toLocation?->vendor?->name, 'po_no' => $m->purchaseOrder->order_no ?? null,
            'date' => $m->movement_date->format('Y-m-d'), 'approved_at' => $m->approved_at?->format('Y-m-d H:i'),
            'dispatched_at' => $m->dispatched_at?->format('Y-m-d H:i'),
            'total_qty' => round((float) $m->items->sum('quantity'), 3), 'item_count' => $m->items->count(),
        ];
    }

    public function index(Request $request)
    {
        if ($r = $this->denied($request)) return $r;
        $with = ['fromLocation', 'toLocation.vendor', 'purchaseOrder', 'items'];

        return response()->json([
            'waiting' => StockMovement::awaitingGate()->with($with)->orderBy('approved_at')->get()->map(fn($m) => $this->row($m))->values(),
            'today'   => StockMovement::whereDate('dispatched_at', today())->with($with)->orderByDesc('dispatched_at')->get()->map(fn($m) => $this->row($m))->values(),
        ]);
    }

    public function show(Request $request, $id)
    {
        if ($r = $this->denied($request)) return $r;
        $m = StockMovement::with('fromLocation', 'toLocation.vendor', 'purchaseOrder', 'items.product.measurementUnit', 'approver', 'dispatcher')->findOrFail($id);

        return response()->json($this->row($m) + [
            'remarks' => $m->remarks, 'approved_by' => $m->approver->name ?? null,
            'vehicle_no' => $m->vehicle_no, 'driver_name' => $m->driver_name, 'driver_contact' => $m->driver_contact,
            'gate_remarks' => $m->gate_remarks, 'dispatched_by' => $m->dispatcher->name ?? null,
            'gate_images' => collect($m->gate_images ?? [])->map(fn($p) => \App\Support\Media::url($p))->values(),
            'can_dispatch' => $m->status === StockMovement::APPROVED,
            'items' => $m->items->map(fn($i) => [
                'product' => $i->product->name ?? '', 'lot_no' => $i->source_lot_no,
                'quantity' => (float) $i->quantity, 'unit' => $i->product->measurementUnit->shortcode ?? '',
            ])->values(),
        ]);
    }

    public function dispatch(Request $request, $id)
    {
        if ($r = $this->denied($request)) return $r;
        $request->validate(ChallanController::TRANSPORT_RULES + [
            'gate_remarks' => 'nullable|string|max:1000',
            'gate_images' => 'nullable|array', 'gate_images.*' => 'file|image|max:5120',
        ]);

        $images = [];
        try {
            foreach ($request->file('gate_images', []) as $f) $images[] = $f->store('gate_images', 'public');
            $m = $this->service->dispatch(StockMovement::findOrFail($id),
                $request->only('vehicle_no', 'driver_name', 'driver_contact', 'gate_remarks') + ['gate_images' => $images ?: null], $request->user()->id);

            return response()->json(['id' => $m->id, 'status' => $m->status, 'message' => "{$m->movement_no} checked out — stock is in transit."]);
        } catch (\Exception $e) {
            foreach ($images as $p) \Illuminate\Support\Facades\Storage::disk('public')->delete($p);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
