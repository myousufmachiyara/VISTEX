<?php

namespace App\Http\Controllers;

use App\Models\CustomerSkuRate;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerSkuRateController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::active()->orderBy('name')->get();

        $skuCategory = ProductCategory::where('code', 'sku')->first();
        $products = $skuCategory
            ? Product::active()->where('category_id', $skuCategory->id)->orderBy('name')->get()
            : collect();

        $selectedCustomerId = $request->get('customer_id');
        $rates = collect();

        if ($selectedCustomerId) {
            // one row per product, showing its current rate
            $rates = $products->map(function ($product) use ($selectedCustomerId) {
                return [
                    'product' => $product,
                    'rate'    => CustomerSkuRate::currentRate($selectedCustomerId, $product->id),
                ];
            });
        }

        return view('customer_sku_rates.index', compact('customers', 'products', 'rates', 'selectedCustomerId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'    => 'required|exists:customers,id',
            'product_id'     => 'required|exists:products,id',
            'rate'           => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'remarks'        => 'nullable|string|max:500',
        ]);

        try {
            $rate = CustomerSkuRate::create([
                'customer_id'    => $request->customer_id,
                'product_id'     => $request->product_id,
                'rate'           => $request->rate,
                'effective_date' => $request->effective_date,
                'remarks'        => $request->remarks,
                'created_by'     => auth()->id(),
            ]);

            Log::info('[CustomerSkuRate] Created', ['id' => $rate->id, 'by' => auth()->id()]);

            return redirect()->route('customer_sku_rates.index', ['customer_id' => $request->customer_id])
                ->with('success', 'Rate recorded successfully.');

        } catch (\Exception $e) {
            Log::error('[CustomerSkuRate] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    // AJAX: full rate history for a customer+product pair
    public function history($customerId, $productId)
    {
        $history = CustomerSkuRate::historyFor($customerId, $productId);

        return response()->json($history->map(fn($r) => [
            'rate'           => (float) $r->rate,
            'effective_date' => $r->effective_date->format('d-M-Y'),
            'remarks'        => $r->remarks,
            'created_by'     => $r->creator->name ?? '',
        ]));
    }

    // AJAX: suggest current rate — used by Order/Sale Invoice creation screens later
    public function suggest(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'product_id'  => 'required|exists:products,id',
        ]);

        $rate = CustomerSkuRate::currentRate($request->customer_id, $request->product_id);

        return response()->json(['rate' => $rate]);
    }

    public function destroy($id)
    {
        try {
            $rate = CustomerSkuRate::findOrFail($id);
            $rate->delete();

            return redirect()->route('customer_sku_rates.index', ['customer_id' => $rate->customer_id])
                ->with('success', 'Rate entry deleted.');

        } catch (\Exception $e) {
            Log::error('[CustomerSkuRate] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete rate entry.');
        }
    }
}