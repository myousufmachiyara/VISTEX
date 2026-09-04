<?php

namespace App\Http\Controllers;

use App\Models\Challan;
use App\Models\PurchaseOrder;
use App\Services\ChallanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChallanController extends Controller
{
    public function __construct(private ChallanService $service) {}

    public function index(Request $request)
    {
        $challans = Challan::with('purchaseOrder.vendor', 'purchaseOrder.category', 'receivedBy')
            ->forCategoryIncharge(auth()->user())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('received_date')
            ->get();

        return view('challans.index', compact('challans'));
    }

    // Dashboard widget/page — pending challans for the current user's categories
    public function pending()
    {
        $challans = Challan::awaitingInspection()
            ->with('purchaseOrder.vendor', 'purchaseOrder.category')
            ->forCategoryIncharge(auth()->user())
            ->orderBy('received_date')
            ->get();

        return view('challans.pending', compact('challans'));
    }

    public function create()
    {
        $user = auth()->user();

        // Purchasing: Approved POs. Weaving/Processing: Issued POs.
        $orders = PurchaseOrder::where(function ($q) {
                $q->where(fn($q2) => $q2->where('type', 'purchase')->where('status', 'Approved'))
                  ->orWhere(fn($q2) => $q2->whereIn('type', ['weaving', 'processing'])->where('status', 'Issued'));
            })
            ->when(!$user->hasRole('superadmin') && !$user->hasRole('gatekeeper'), function ($q) use ($user) {
                $q->whereHas('dropOffLocation', fn($q2) => $q2->where('in_charge_user_id', $user->id));
            })
            ->with('vendor', 'category')
            ->orderByDesc('order_date')
            ->get();

        return view('challans.create', compact('orders'));
    }

    // AJAX: expected items for the selected PO
    public function poItems($poId)
    {
        $po = PurchaseOrder::with('items.product')->findOrFail($poId);

        if ($po->type === 'purchase') {
            return response()->json($po->items->map(fn($i) => [
                'product_name' => $i->product->name ?? '',
                'quantity'     => (float) $i->quantity,
            ]));
        }

        // Weaving type — single formula-defined "item"
        return response()->json([[
            'product_name' => $po->item_name ?? 'Greige (per CPO)',
            'quantity'     => (float) $po->total_meters_required,
        ]]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'    => 'required|exists:purchase_orders,id',
            'vendor_challan_no'    => 'nullable|string|max:50',
            'received_date'        => 'required|date',
            'challan_images'        => 'required|array|min:1',
            'challan_images.*'      => 'file|image|max:5120',
            'remarks'              => 'nullable|string',
        ]);

        try {
            $images = [];
            foreach ($request->file('challan_images') as $file) {
                $images[] = $file->store('challan_images', 'public');
            }

            $challan = $this->service->create([
                'purchase_order_id'  => $request->purchase_order_id,
                'vendor_challan_no'  => $request->vendor_challan_no,
                'received_date'      => $request->received_date,
                'challan_images'     => $images,
                'remarks'            => $request->remarks,
            ], auth()->id());

            Log::info('[Challan] Created', ['id' => $challan->id, 'by' => auth()->id()]);

            return redirect()->route('challans.create')->with('success', $challan->challan_no . ' logged successfully.');

        } catch (\Exception $e) {
            Log::error('[Challan] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $challan = Challan::with('purchaseOrder.vendor', 'purchaseOrder.category', 'purchaseOrder.items.product', 'receivedBy')->findOrFail($id);
        return view('challans.show', compact('challan'));
    }
}