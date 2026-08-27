<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\MeasurementUnit;
use App\Services\ProductAttributeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private ProductAttributeService $attrService) {}

    public function index(Request $request)
    {
        $products = Product::with('category', 'measurementUnit')
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
            ->orderBy('name')
            ->get();

        $categories = ProductCategory::orderBy('name')->get();

        return view('products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = ProductCategory::orderBy('name')->get();
        $units      = MeasurementUnit::orderBy('name')->get();
        $schemas    = $this->allSchemasKeyedByCategoryId($categories);

        return view('products.create', compact('categories', 'units', 'schemas'));
    }

    public function store(Request $request)
    {
        $baseRules = [
            'category_id'      => 'required|exists:product_categories,id',
            'name'             => 'required|string|max:255',
            'sku'              => 'nullable|string|max:255|unique:products,sku',
            'description'      => 'nullable|string',
            'opening_stock'    => 'nullable|numeric|min:0',
            'selling_price'    => 'nullable|numeric|min:0',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'is_active'        => 'nullable|boolean',
            'track_lots'       => 'nullable|boolean',
        ];

        $category = ProductCategory::find($request->category_id);
        $schema = $this->attrService->schemaFor($request->category_id);
        $dynamicRules = $this->attrService->validationRules($schema);

        $request->validate(array_merge($baseRules, $dynamicRules));

        try {
            $attributes = [];
            foreach ($schema as $field) {
                $attributes[$field['key']] = $request->input('attributes.' . $field['key']);
            }

            $sku = $request->sku ?: $this->generateSku($request->name);

            $product = Product::create([
                'category_id'      => $request->category_id,
                'name'             => $request->name,
                'sku'              => $sku,
                'description'      => $request->description,
                'attributes'       => $attributes,
                'opening_stock'    => $request->opening_stock ?? 0,
                'selling_price'    => $request->selling_price ?? 0,
                'measurement_unit' => $request->measurement_unit,
                'is_active'        => $request->boolean('is_active', true),
                'track_lots'       => $request->boolean('track_lots'),
                'created_by'       => auth()->id(),
                'updated_by'       => auth()->id(),
            ]);

            Log::info('[Product] Created', ['id' => $product->id, 'by' => auth()->id()]);

            return redirect()->route('products.index')->with('success', 'Product "' . $product->name . '" created successfully.');

        } catch (\Exception $e) {
            Log::error('[Product] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function edit($id)
    {
        $product    = Product::findOrFail($id);
        $categories = ProductCategory::orderBy('name')->get();
        $units      = MeasurementUnit::orderBy('name')->get();
        $schemas    = $this->allSchemasKeyedByCategoryId($categories);

        return view('products.edit', compact('product', 'categories', 'units', 'schemas'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $baseRules = [
            'category_id'      => 'required|exists:product_categories,id',
            'name'             => 'required|string|max:255',
            'sku'              => 'required|string|max:255|unique:products,sku,' . $id,
            'description'      => 'nullable|string',
            'opening_stock'    => 'nullable|numeric|min:0',
            'selling_price'    => 'nullable|numeric|min:0',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'is_active'        => 'nullable|boolean',
            'track_lots'       => 'nullable|boolean',
        ];

        $schema = $this->attrService->schemaFor($request->category_id);
        $dynamicRules = $this->attrService->validationRules($schema);

        $request->validate(array_merge($baseRules, $dynamicRules));

        try {
            $attributes = [];
            foreach ($schema as $field) {
                $attributes[$field['key']] = $request->input('attributes.' . $field['key']);
            }

            $product->update([
                'category_id'      => $request->category_id,
                'name'             => $request->name,
                'sku'              => $request->sku,
                'description'      => $request->description,
                'attributes'       => $attributes,
                'opening_stock'    => $request->opening_stock ?? $product->opening_stock,
                'selling_price'    => $request->selling_price ?? $product->selling_price,
                'measurement_unit' => $request->measurement_unit,
                'is_active'        => $request->boolean('is_active', $product->is_active),
                'track_lots'       => $request->boolean('track_lots', $product->track_lots),
                'updated_by'       => auth()->id(),
            ]);

            Log::info('[Product] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('products.index')->with('success', 'Product updated successfully.');

        } catch (\Exception $e) {
            Log::error('[Product] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return redirect()->route('products.index')->with('success', 'Product deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Product] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete product.');
        }
    }

    // AJAX: category-specific field schema, for the create/edit form's dynamic section
    public function attributeSchema($categoryId)
    {
        return response()->json($this->attrService->schemaFor($categoryId));
    }

    private function allSchemasKeyedByCategoryId($categories): array
    {
        $out = [];
        foreach ($categories as $cat) {
            $out[$cat->id] = $this->attrService->schemaForCode($cat->code);
        }
        return $out;
    }

    private function generateSku(string $name): string
    {
        $base = Str::slug($name);
        $sku = strtoupper($base);
        $suffix = 2;

        while (Product::where('sku', $sku)->exists()) {
            $sku = strtoupper($base) . '-' . $suffix;
            $suffix++;
        }

        return $sku;
    }
    
    public function importForm()
    {
        $categories = \App\Models\ProductCategory::orderBy('name')->get();
        return view('products.import', compact('categories'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $handle = fopen($request->file('import_file')->getRealPath(), 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) ? "\t" : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'Could not read a header row from the file.');
        }
        $header = array_map(fn($h) => strtolower(trim($h, "\xEF\xBB\xBF \"")), $header);

        $categories = \App\Models\ProductCategory::pluck('id', 'code');
        $units = \App\Models\MeasurementUnit::pluck('id', 'shortcode');

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $rowNum   = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim($row[0]) === '') continue;

            $data = array_combine(array_slice($header, 0, count($row)), $row);
            if ($data === false) { $skipped++; continue; }

            $name = trim($data['name'] ?? '');
            $categoryCode = strtolower(trim($data['category_code'] ?? ''));

            if (!$name || !$categoryCode) {
                $skipped++;
                $errors[] = "Row {$rowNum}: missing name or category_code.";
                continue;
            }

            $categoryId = $categories[$categoryCode] ?? null;
            if (!$categoryId) {
                $skipped++;
                $errors[] = "Row {$rowNum} ('{$name}'): category_code '{$categoryCode}' not found.";
                continue;
            }

            $sku = trim($data['sku'] ?? '') ?: strtoupper(\Illuminate\Support\Str::slug($name));
            $originalSku = $sku;
            $suffix = 2;
            while (\App\Models\Product::where('sku', $sku)->exists()) {
                $sku = $originalSku . '-' . $suffix;
                $suffix++;
            }

            $unitCode = strtolower(trim($data['unit_shortcode'] ?? 'pcs'));
            $unitId = $units[$unitCode] ?? $units['pcs'] ?? null;

            // Collect any column not part of the core set into the attributes JSON
            $coreFields = ['name', 'sku', 'category_code', 'description', 'opening_stock', 'selling_price', 'unit_shortcode', 'is_active'];
            $attributes = [];
            foreach ($data as $key => $value) {
                if (!in_array($key, $coreFields) && trim((string) $value) !== '') {
                    $attributes[$key] = trim((string) $value);
                }
            }

            try {
                \App\Models\Product::create([
                    'category_id'      => $categoryId,
                    'name'             => $name,
                    'sku'              => $sku,
                    'description'      => trim($data['description'] ?? '') ?: null,
                    'attributes'       => $attributes,
                    'opening_stock'    => (float) str_replace(',', '', $data['opening_stock'] ?? 0),
                    'selling_price'    => (float) str_replace(',', '', $data['selling_price'] ?? 0),
                    'measurement_unit' => $unitId,
                    'is_active'        => isset($data['is_active']) ? (bool) ((int) $data['is_active']) : true,
                    'track_lots'       => false,
                    'created_by'       => auth()->id(),
                    'updated_by'       => auth()->id(),
                ]);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Row {$rowNum} ('{$name}'): " . $e->getMessage();
            }
        }

        fclose($handle);

        Log::info('[ProductImport] Completed', ['imported' => $imported, 'skipped' => $skipped, 'by' => auth()->id()]);

        return redirect()->route('products.index')
            ->with('success', "Imported {$imported} products. Skipped {$skipped}.")
            ->with('import_errors', $errors);
    }


    public function xeroImportForm()
    {
        return view('products.import_xero');
    }

    public function xeroImport(Request $request, \App\Services\ImportProductsService $service)
    {
        $request->validate(['import_file' => 'required|file|mimes:csv,txt|max:10240']);

        $result = $service->import($request->file('import_file')->getRealPath(), auth()->id());

        Log::info('[ProductImport:Xero] Completed', $result + ['by' => auth()->id()]);

        return redirect()->route('products.index')
            ->with('success', "Imported {$result['imported']} products. Skipped {$result['skipped']}.")
            ->with('import_errors', $result['errors']);
    }
}