<?php

namespace App\Http\Controllers;

use App\Models\GreigeReceive;
use App\Models\ConversionPurchaseOrder;
use App\Models\YarnInProcessLedger;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\GreigeReceiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GreigeReceiveController extends Controller
{
    public function __construct(private GreigeReceiveService $service) {}

    public function index()
    {
        $receives = GreigeReceive::with('cpo.vendor', 'outputs.greigeProduct')->orderByDesc('receive_date')->get();
        return view('greige_receives.index', compact('receives'));
    }

    public function create()
    {
        $cpos = ConversionPurchaseOrder::active()->with('vendor', 'warpProduct', 'weftProduct')->orderByDesc('po_date')->get();

        $greigeCategory = ProductCategory::where('code', 'greige')->first();
        $products = $greigeCategory ? Product::active()->where('category_id', $greigeCategory->id)->orderBy('name')->get() : collect();

        return view('greige_receives.create', compact('cpos', 'products'));
    }

    public function cpoYarnBalance($cpoId)
    {
        $cpo = ConversionPurchaseOrder::with('warpProduct', 'weftProduct')->findOrFail($cpoId);

        $rows = [];
        foreach ([$cpo->warpProduct, $cpo->weftProduct] as $product) {
            if (!$product) continue;
            $bal = YarnInProcessLedger::balanceForCpoProduct($cpo->id, $product->id);
            $rows[] = ['product_name' => $product->name, 'quantity' => $bal['quantity'], 'amount' => $bal['amount']];
        }

        return response()->json(['yarn_balance' => collect($rows)->unique('product_name')->values()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cpo_id'                        => 'required|exists:conversion_purchase_orders,id',
            'receive_date'                  => 'required|date',
            'vendor_challan_no'             => 'required|string|max:50',
            'is_final_receiving'            => 'nullable|boolean',
            'attachments'                    => 'required|array|min:1',
            'attachments.*'                  => 'file|max:5120',
            'remarks'                       => 'nullable|string',
            'outputs'                         => 'required|array|min:1',
            'outputs.*.greige_product_id'   => 'required|exists:products,id',
            'outputs.*.quantity_output'     => 'required|numeric|min:0.001',
            'outputs.*.weaving_rate'        => 'required|numeric|min:0',
        ]);

        try {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('greige_receive_attachments', 'public');
            }

            $receive = $this->service->create([
                'cpo_id'               => $request->cpo_id,
                'receive_date'         => $request->receive_date,
                'vendor_challan_no'    => $request->vendor_challan_no,
                'is_final_receiving'   => $request->boolean('is_final_receiving'),
                'remarks'              => $request->remarks,
                'attachments'          => $attachments,
            ], $request->outputs, auth()->id());

            Log::info('[GreigeReceive] Created', ['id' => $receive->id, 'by' => auth()->id()]);

            return redirect()->route('greige_receives.index')->with('success', 'Greige receive ' . $receive->receive_no . ' recorded — pending approval.');

        } catch (\Exception $e) {
            Log::error('[GreigeReceive] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve($id)
    {
        $receive = GreigeReceive::findOrFail($id);

        if (!$receive->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only the Greige category in-charge or a superadmin can approve this receiving.');
        }

        try {
            $this->service->approve($receive, auth()->id());
            Log::info('[GreigeReceive] Approved', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'Greige receive approved — stock and accounting posted.');

        } catch (\Exception $e) {
            Log::error('[GreigeReceive] Approve failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $receive = GreigeReceive::findOrFail($id);

        if (!$receive->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only the Greige category in-charge or a superadmin can reject this receiving.');
        }

        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->service->reject($receive, auth()->id(), $request->reason);
            return back()->with('success', 'Greige receive rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        if (!auth()->user()->hasRole('superadmin')) {
            abort(403, 'Only a superadmin can delete a greige receive.');
        }

        try {
            $receive = GreigeReceive::findOrFail($id);
            $this->service->delete($receive);
            return redirect()->route('greige_receives.index')->with('success', 'Greige receive deleted.');
        } catch (\Exception $e) {
            Log::error('[GreigeReceive] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
}