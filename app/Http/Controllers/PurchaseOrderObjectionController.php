<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderObjection;
use App\Models\PurchaseOrder;
use App\Models\Challan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseOrderObjectionController extends Controller
{
    public function index(Request $request)
    {
        $objections = PurchaseOrderObjection::with('purchaseOrder.vendor', 'raisedBy', 'resolvedBy')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->get();

        return view('purchase_order_objections.index', compact('objections'));
    }

    // Raise objection against a PO — usable from Challan show page (gatekeeper)
    // or from Receiving show page (category in-charge)
    public function create($poId)
    {
        $order = PurchaseOrder::findOrFail($poId);
        return view('purchase_order_objections.create', compact('order'));
    }

    public function store(Request $request, $poId)
    {
        $order = PurchaseOrder::findOrFail($poId);

        $request->validate(['remarks' => 'required|string|max:1000']);

        try {
            $objection = PurchaseOrderObjection::create([
                'purchase_order_id' => $order->id,
                'remarks'           => $request->remarks,
                'status'            => 'Open',
                'raised_by'         => auth()->id(),
            ]);

            Log::info('[PurchaseOrderObjection] Raised', ['id' => $objection->id, 'po_id' => $order->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_orders.show', $order->id)
                ->with('success', 'Objection raised — the PO creator or category in-charge will review it.');

        } catch (\Exception $e) {
            Log::error('[PurchaseOrderObjection] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    // Resolve — only PO creator (locked_by) or category in-charge or superadmin
    public function resolve(Request $request, $id)
    {
        $objection = PurchaseOrderObjection::with('purchaseOrder')->findOrFail($id);
        $order = $objection->purchaseOrder;

        $user = auth()->user();
        $canResolve = $user->hasRole('superadmin')
            || $order->locked_by === $user->id
            || $order->category->incharges->contains('user_id', $user->id);

        if (!$canResolve) {
            abort(403, 'Only the PO creator, category in-charge, or superadmin can resolve this objection.');
        }

        if ($objection->status !== 'Open') {
            return back()->with('error', 'This objection has already been resolved.');
        }

        $request->validate(['resolution_notes' => 'nullable|string|max:1000']);

        $objection->update([
            'status'       => 'Resolved',
            'resolved_by'  => $user->id,
            'resolved_at'  => now(),
            'remarks'      => $objection->remarks . ($request->resolution_notes ? "\n\nResolution: " . $request->resolution_notes : ''),
        ]);

        Log::info('[PurchaseOrderObjection] Resolved', ['id' => $id, 'by' => $user->id]);

        return back()->with('success', 'Objection marked as resolved.');
    }
}