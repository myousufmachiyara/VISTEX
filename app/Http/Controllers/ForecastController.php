<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ForecastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ForecastController extends Controller
{
    public function __construct(private ForecastService $service) {}

    public function index(Request $request)
    {
        $forecasts = Forecast::with('customer', 'product')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->get();

        return view('forecasts.index', compact('forecasts'));
    }

    public function create()
    {
        $customers = Customer::active()->orderBy('name')->get();

        // Forecasting applies to Greige and Yarn — the two categories PO/CPO care about
        $categories = ProductCategory::whereIn('code', ['greige', 'yarn'])->pluck('id');
        $products = Product::active()->whereIn('category_id', $categories)->orderBy('name')->get();

        return view('forecasts.create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'       => 'nullable|exists:customers,id',
            'product_id'        => 'required|exists:products,id',
            'required_qty'      => 'required|numeric|min:0.001',
            'stock_on_hand'     => 'nullable|numeric|min:0',
            'on_order_qty'      => 'nullable|numeric|min:0',
            'required_by_date'  => 'nullable|date',
            'remarks'           => 'nullable|string',
        ]);

        try {
            $forecast = $this->service->create($request->all(), auth()->id());

            Log::info('[Forecast] Created', ['id' => $forecast->id, 'by' => auth()->id()]);

            return redirect()->route('forecasts.index')
                ->with('success', 'Forecast ' . $forecast->forecast_no . ' created — pending approval.');

        } catch (\Exception $e) {
            Log::error('[Forecast] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve($id)
    {
        try {
            $forecast = Forecast::findOrFail($id);
            $this->service->approve($forecast, auth()->id());

            Log::info('[Forecast] Approved', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'Forecast approved.');

        } catch (\Exception $e) {
            Log::error('[Forecast] Approve failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $forecast = Forecast::findOrFail($id);
            $this->service->reject($forecast, auth()->id(), $request->reason);

            Log::info('[Forecast] Rejected', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'Forecast rejected.');

        } catch (\Exception $e) {
            Log::error('[Forecast] Reject failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $forecast = Forecast::findOrFail($id);
            $this->service->delete($forecast);

            return redirect()->route('forecasts.index')->with('success', 'Forecast deleted.');

        } catch (\Exception $e) {
            Log::error('[Forecast] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    // AJAX: approved forecasts for a product, for the PO create screen's "link to forecast" dropdown
    public function approvedForProduct($productId)
    {
        $forecasts = Forecast::approved()
            ->where('product_id', $productId)
            ->with('customer')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($forecasts->map(fn($f) => [
            'id'            => $f->id,
            'forecast_no'   => $f->forecast_no,
            'customer_name' => $f->customer->name ?? 'General',
            'shortfall_qty' => (float) $f->shortfall_qty,
        ]));
    }
}