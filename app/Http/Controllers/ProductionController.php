<?php

namespace App\Http\Controllers;

use App\Models\{Job, JobItem, Location, Product, ProductCategory, ProductionOrder, PutUpPackaging};
use App\Services\ProductionService;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function __construct(private ProductionService $service) {}

    public function index(Request $request)
    {
        $orders = ProductionOrder::with('location', 'job', 'lines.outputProduct', 'creator')
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->type))
            ->orderByDesc('order_date')->orderByDesc('id')->get();
        return view('production.index', compact('orders'));
    }

    public function create(Request $request, string $type)
    {
        abort_unless(isset(ProductionOrder::TYPES[$type]), 404);
        $warehouses = Location::whereNull('vendor_id')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $putUps = $this->putUps();
        $jobs = Job::approved()->with('customer:id,name')->orderByDesc('id')->get(['id', 'job_no', 'customer_id']);
        return view('production.create', compact('type', 'warehouses', 'putUps', 'jobs'));
    }

    // Stock available at a warehouse for cutting (processed fabric) or packing (cut pcs)
    public function stock(Request $request, string $type)
    {
        $loc = (int) $request->location_id;
        return response()->json($type === 'cutting' ? $this->service->cuttable($loc) : $this->service->packable($loc));
    }

    // Lines of an approved customer order, with pieces ordered / packed
    public function jobItems($jobId)
    {
        $items = JobItem::where('job_id', $jobId)->with('product')->get()->map(fn($i) => [
            'id' => $i->id, 'pattern_code' => $i->pattern_code, 'put_up_product_id' => $i->product_id,
            'label' => trim("{$i->pattern_code} {$i->design} {$i->color_scheme}") . ($i->product ? " — {$i->product->name}" : ''),
            'pieces' => $i->pieces, 'packed' => ProductionService::packedFor($i->id),
        ]);
        return response()->json($items);
    }

    public function store(Request $request, string $type)
    {
        abort_unless(isset(ProductionOrder::TYPES[$type]), 404);
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'order_date'  => 'required|date',
            'job_id'      => 'nullable|exists:job_orders,id',
            'remarks'     => 'nullable|string',
            'lines'       => 'required|array',
            'lines.*.product_id' => 'required|integer',
            'lines.*.lot_no'     => 'nullable|string',
            'lines.*.grade'      => 'nullable|in:A,B,C',
            'lines.*.pieces'     => 'nullable|numeric|min:0',
            'lines.*.put_up_product_id' => $type === 'cutting' ? 'required_with:lines.*.pieces|nullable|integer' : 'nullable',
            'lines.*.leftover_qty' => 'nullable|numeric|min:0',
            'lines.*.rejected_qty' => 'nullable|numeric|min:0',
            'lines.*.job_item_id'  => 'nullable|integer',
        ]);
        try {
            $data = $request->only('location_id', 'order_date', 'job_id', 'remarks') + ['pack_now' => $request->boolean('pack_now')];
            $order = $type === 'cutting'
                ? $this->service->cut($data, $request->input('lines'), auth()->id())
                : $this->service->pack($data, $request->input('lines'), auth()->id());
            return redirect()->route('production.show', $order->id)->with('success', "{$order->order_no} posted.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $order = ProductionOrder::with('location', 'job.customer', 'creator', 'lines.inputProduct', 'lines.outputProduct', 'lines.putUp', 'lines.jobItem', 'lines.consumptions.product')->findOrFail($id);
        $packedFrom = $order->type === 'cutting'
            ? ProductionOrder::where('type', 'packing')->where('remarks', "Packed on cutting {$order->order_no}")->first() : null;
        return view('production.show', compact('order', 'packedFrom'));
    }

    // ── Packaging list (BOM) per put-up ──
    public function bom(Request $request)
    {
        $putUps = $this->putUps();
        $counts = PutUpPackaging::selectRaw('put_up_product_id, COUNT(*) AS n')->groupBy('put_up_product_id')->pluck('n', 'put_up_product_id');
        $selected = $request->filled('put_up') ? $putUps->firstWhere('id', (int) $request->put_up) : null;
        $rows = $selected ? $this->service->bom($selected->id) : [];
        $materials = Product::where('category_id', ProductCategory::where('code', 'packaging')->value('id'))->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku']);
        return view('production.bom', compact('putUps', 'counts', 'selected', 'rows', 'materials'));
    }

    public function saveBom(Request $request)
    {
        $request->validate(['put_up_product_id' => 'required|exists:products,id', 'rows' => 'nullable|array',
            'rows.*.material_product_id' => 'nullable|integer', 'rows.*.qty_per_piece' => 'nullable|numeric|min:0']);
        try {
            $this->service->saveBom((int) $request->put_up_product_id, $request->input('rows', []));
            return redirect()->route('production.bom', ['put_up' => $request->put_up_product_id])->with('success', 'Packaging list saved.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function putUps()
    {
        return Product::where('category_id', ProductCategory::where('code', 'sku')->value('id'))->where('sku', 'like', 'PU-%')
            ->where('is_active', true)->orderBy('id')->get(['id', 'name', 'sku', 'attributes']);
    }
}
