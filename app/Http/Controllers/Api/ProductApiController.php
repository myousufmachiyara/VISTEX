<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductApiController extends Controller
{
    // GET /api/products
    public function index()
    {
        $products = Product::with(['category', 'subcategory', 'measurementUnit'])
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }

    // GET /api/products/{id}
    public function show($id)
    {
        try {
            $product = Product::with(['category', 'subcategory', 'measurementUnit'])->findOrFail($id);
            return new ProductResource($product);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }
    }

    // GET /api/products/suggest-sku?category_id=1&name=Yarn
    // Mirrors web's generateAutoSku() JS — server-side, so mobile and web
    // never drift out of sync on SKU format.
    public function suggestSku(Request $request)
    {
        $categoryName = 'CAT';
        if ($request->filled('category_id')) {
            $category = \App\Models\ProductCategory::find($request->category_id);
            if ($category) {
                $categoryName = $category->name;
            }
        }

        $productName = $request->get('name', 'ITEM');

        $cleanCat  = Str::upper(Str::slug($categoryName, '-'));
        $cleanProd = Str::upper(Str::slug($productName, '-'));

        $suggested = "{$cleanProd}-{$cleanCat}";

        // ensure uniqueness — append a counter if the base SKU is taken
        $base = $suggested;
        $i = 1;
        while (Product::where('sku', $suggested)->exists()) {
            $suggested = "{$base}-{$i}";
            $i++;
        }

        return response()->json(['sku' => $suggested]);
    }

    // POST /api/products
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255|unique:products,name',
            'category_id'      => 'required|exists:product_categories,id',
            'subcategory_id'   => 'nullable|exists:product_subcategories,id',
            'sku'              => 'required|string|unique:products,sku',
            'description'      => 'nullable|string',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'selling_price'    => 'nullable|numeric|min:0',
            'opening_stock'    => 'nullable|numeric|min:0',
            'is_active'        => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $data['opening_stock'] = $data['opening_stock'] ?? 0;
            $data['is_active']     = $data['is_active'] ?? true;

            $product = Product::create($data);

            DB::commit();

            Log::info('[Product API] Created', ['id' => $product->id, 'by' => $request->user()->id]);

            return new ProductResource($product->load(['category', 'subcategory', 'measurementUnit']));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Product API] Store failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not create product.'], 500);
        }
    }

    // PUT /api/products/{id}
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255|unique:products,name,' . $id,
            'category_id'      => 'required|exists:product_categories,id',
            'subcategory_id'   => 'nullable|exists:product_subcategories,id',
            'sku'              => 'required|string|unique:products,sku,' . $id,
            'description'      => 'nullable|string',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'selling_price'    => 'nullable|numeric|min:0',
            'opening_stock'    => 'nullable|numeric|min:0',
            'is_active'        => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::findOrFail($id);
            $product->update($data);

            DB::commit();

            Log::info('[Product API] Updated', ['id' => $id, 'by' => $request->user()->id]);

            return new ProductResource($product->load(['category', 'subcategory', 'measurementUnit']));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Product API] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not update product.'], 500);
        }
    }

    // DELETE /api/products/{id}
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            $usedInPurchase = DB::table('purchase_items')->where('product_id', $id)->exists();
            $usedInSale     = DB::table('sale_items')->where('product_id', $id)->exists();
            $usedInStock    = DB::table('stock_movements')->where('product_id', $id)->exists();

            if ($usedInPurchase || $usedInSale || $usedInStock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete — this product has transaction history. Deactivate instead.',
                ], 422);
            }

            $product->delete();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('[Product API] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not delete product.'], 500);
        }
    }
}