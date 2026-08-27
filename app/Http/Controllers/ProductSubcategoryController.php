<?php

namespace App\Http\Controllers;

use App\Models\ProductSubcategory;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProductSubcategoryController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $subcategories = ProductSubcategory::with('category')->get();
        $categories    = ProductCategory::all();

        return view('products.subcategories', compact('subcategories', 'categories'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:product_categories,id',
            'name'        => 'required|string|max:255|unique:product_subcategories,name',
            'code'        => 'required|string|max:50|unique:product_subcategories,code',
        ]);

        DB::beginTransaction();
        try {
            // FIX: removed 'status' from only() — column does not exist in migration.
            // 'description' kept since it's in migration (nullable text).
            $subcategory = ProductSubcategory::create(
                $request->only('category_id', 'name', 'code', 'description')
            );

            DB::commit();
            Log::info('[Subcategory] Created', ['id' => $subcategory->id, 'by' => auth()->id()]);

            return redirect()->route('product_subcategories.index')
                ->with('success', 'Subcategory created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Subcategory] Store error', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id' => 'required|exists:product_categories,id',
            'name'        => ['required', 'string', 'max:255', Rule::unique('product_subcategories', 'name')->ignore($id)],
            'code'        => ['required', 'string', 'max:50',  Rule::unique('product_subcategories', 'code')->ignore($id)],
        ]);

        DB::beginTransaction();
        try {
            $subcategory = ProductSubcategory::findOrFail($id);
            $subcategory->update(
                $request->only('category_id', 'name', 'code', 'description')
            );

            DB::commit();
            Log::info('[Subcategory] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('product_subcategories.index')
                ->with('success', 'Subcategory updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Subcategory] Update error', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        try {
            $subcategory = ProductSubcategory::findOrFail($id);

            // Guard: cannot delete if products are linked
            if ($subcategory->products()->count() > 0) {
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $subcategory->name . '" — it has products linked to it.');
            }

            $subcategory->delete();

            Log::info('[Subcategory] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('product_subcategories.index')
                ->with('success', 'Subcategory deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Subcategory] Destroy error', ['message' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Could not delete subcategory. Please try again.');
        }
    }
}