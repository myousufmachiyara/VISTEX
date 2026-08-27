<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\User;
use App\Models\CategoryIncharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::with('inchargeUsers')->orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('products.categories', compact('categories', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'required|string|max:50|unique:product_categories,code',
            'incharge_ids'  => 'nullable|array',
            'incharge_ids.*'=> 'exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $category = ProductCategory::create([
                'name' => $request->name,
                'code' => $request->code,
            ]);

            foreach ($request->input('incharge_ids', []) as $userId) {
                CategoryIncharge::create([
                    'product_category_id' => $category->id,
                    'user_id'             => $userId,
                ]);
            }

            DB::commit();

            Log::info('[ProductCategory] Created', ['id' => $category->id, 'by' => auth()->id()]);

            return redirect()->route('product_categories.index')
                ->with('success', 'Category "' . $category->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProductCategory] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'required|string|max:50|unique:product_categories,code,' . $id,
            'incharge_ids'  => 'nullable|array',
            'incharge_ids.*'=> 'exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $category = ProductCategory::findOrFail($id);

            $category->update([
                'name' => $request->name,
                'code' => $request->code,
            ]);

            // Sync in-charges — remove all, re-add selected
            CategoryIncharge::where('product_category_id', $category->id)->delete();
            foreach ($request->input('incharge_ids', []) as $userId) {
                CategoryIncharge::create([
                    'product_category_id' => $category->id,
                    'user_id'             => $userId,
                ]);
            }

            DB::commit();

            Log::info('[ProductCategory] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('product_categories.index')
                ->with('success', 'Category updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProductCategory] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $category = ProductCategory::findOrFail($id);

            $hasProducts = DB::table('products')->where('category_id', $id)->exists();
            if ($hasProducts) {
                DB::rollBack();
                return back()->with('error', 'Cannot delete "' . $category->name . '" — it has linked products.');
            }

            CategoryIncharge::where('product_category_id', $id)->delete();
            $category->delete();

            DB::commit();

            Log::info('[ProductCategory] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('product_categories.index')->with('success', 'Category deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProductCategory] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete category. Please try again.');
        }
    }
}