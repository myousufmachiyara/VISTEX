<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
use Illuminate\Http\Request;

class ProductCategoryApiController extends Controller
{
    // GET /api/product-categories
    public function index()
    {
        $categories = ProductCategory::orderBy('name')->get(['id', 'name']);
        return response()->json(['data' => $categories]);
    }

    // GET /api/product-categories/{id}/subcategories
    public function subcategories($id)
    {
        $subcategories = ProductSubcategory::where('category_id', $id)
            ->orderBy('name')
            ->get(['id', 'name']);
        return response()->json(['data' => $subcategories]);
    }
}