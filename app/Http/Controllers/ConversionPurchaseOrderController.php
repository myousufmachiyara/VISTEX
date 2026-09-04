<?php

namespace App\Http\Controllers;

use App\Models\ConversionPurchaseOrder;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxMaster;
use App\Models\Forecast;
use App\Services\CpoFormulaService;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversionPurchaseOrderController extends Controller
{
    public function __construct(
        private CpoFormulaService $formulaService,
        private DocumentNumberService $numberService
    ) {}

    public function index()
    {
        $cpos = ConversionPurchaseOrder::with('vendor', 'warpProduct', 'weftProduct', 'greigeProduct', 'forecast')
            ->orderByDesc('po_date')
            ->get();

        return view('cpo.index', compact('cpos'));
    }

    public function create()
    {
        $vendors = Vendor::active()->where('vendor_type', 'weaving_mill')->orderBy('name')->get();
        // Fallback: if no vendors are typed as weaving_mill yet, show all active vendors
        if ($vendors->isEmpty()) {
            $vendors = Vendor::active()->orderBy('name')->get();
        }

        $yarnCategory = ProductCategory::where('code', 'yarn')->first();
        $yarnProducts = $yarnCategory ? Product::active()->where('category_id', $yarnCategory->id)->orderBy('name')->get() : collect();

        $greigeCategory = ProductCategory::where('code', 'greige')->first();
        $greigeProducts = $greigeCategory ? Product::active()->where('category_id', $greigeCategory->id)->orderBy('name')->get() : collect();

        $taxes = TaxMaster::active()->orderBy('rate', 'desc')->get();
        $forecasts = Forecast::approved()->with('customer', 'product')->get();

        return view('cpo.create', compact('vendors', 'yarnProducts', 'greigeProducts', 'taxes', 'forecasts'));
    }

    // AJAX: live calculation preview as the user fills the form
    public function calculate(Request $request)
    {
        $request->validate([
            'warp_count' => 'required|numeric|min:0.01', 'weft_count' => 'required|numeric|min:0.01',
            'reed_count' => 'required|numeric|min:0.01', 'pick' => 'required|numeric|min:0.01',
            'width' => 'required|numeric|min:0.01', 'total_meters_required' => 'required|numeric|min:0.001',
            'rate_per_pick' => 'required|numeric|min:0', 'sizing_lbs' => 'nullable|numeric|min:0',
            'warp_conversion_pct' => 'nullable|numeric|min:0',
            'warp_shrinkage_pct' => 'nullable|numeric|min:0|max:100',
            'weft_shrinkage_pct' => 'nullable|numeric|min:0|max:100',
            'gst_applicable' => 'nullable|boolean', 'gst_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $formulaService = app(\App\Services\CpoFormulaService::class);
        $calc = $formulaService->calculate($request->all());
        $calc = $formulaService->withGst($calc, $request->boolean('gst_applicable'), (float) $request->gst_rate);

        return response()->json($calc);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id'              => 'required|exists:vendors,id',
            'warp_product_id'        => 'required|exists:products,id',
            'weft_product_id'        => 'required|exists:products,id',
            'greige_product_id'      => 'nullable|exists:products,id',
            'forecast_id'            => 'nullable|exists:forecasts,id',
            'warp_count'             => 'required|numeric|min:0.01',
            'weft_count'             => 'required|numeric|min:0.01',
            'reed_count'             => 'required|numeric|min:0.01',
            'pick'                   => 'required|numeric|min:0.01',
            'width'                  => 'required|numeric|min:0.01',
            'total_meters_required'  => 'required|numeric|min:0.001',
            'rate_per_pick'          => 'required|numeric|min:0',
            'sizing_lbs'             => 'nullable|numeric|min:0',
            'warp_conversion_pct'    => 'nullable|numeric|min:0',
            'gst_applicable'         => 'required|boolean',
            'tax_id'                 => 'required_if:gst_applicable,1|nullable|exists:tax_masters,id',
            'po_date'                => 'required|date',
            'remarks'                => 'nullable|string',
        ]);

        try {
            $taxRate = 0;
            if ($request->boolean('gst_applicable') && $request->tax_id) {
                $tax = TaxMaster::find($request->tax_id);
                $taxRate = $tax ? (float) $tax->rate : 0;
            }

            $calc = $this->formulaService->calculate($request->all());
            $calc = $this->formulaService->withGst($calc, $request->boolean('gst_applicable'), $taxRate);

            $cpo = ConversionPurchaseOrder::create(array_merge(
                $request->only([
                    'vendor_id', 'warp_product_id', 'weft_product_id', 'greige_product_id', 'forecast_id',
                    'warp_count', 'weft_count', 'reed_count', 'pick', 'width', 'total_meters_required',
                    'rate_per_pick', 'sizing_lbs', 'warp_conversion_pct', 'po_date', 'remarks',
                ]),
                [
                    'cpo_no'          => $this->numberService->next('cpo', 'conversion_purchase_orders', 'cpo_no', 'CPO'),
                    'gst_applicable'  => $request->boolean('gst_applicable'),
                    'tax_id'          => $request->boolean('gst_applicable') ? $request->tax_id : null,
                    'gst_rate'        => $taxRate,
                    'status'          => 'Active',
                    'created_by'      => auth()->id(),
                    'updated_by'      => auth()->id(),
                ],
                $calc
            ));

            Log::info('[CPO] Created', ['id' => $cpo->id, 'by' => auth()->id()]);

            return redirect()->route('cpo.index')->with('success', 'CPO ' . $cpo->cpo_no . ' created successfully.');

        } catch (\Exception $e) {
            Log::error('[CPO] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $cpo = ConversionPurchaseOrder::findOrFail($id);

        if ($cpo->yarn_issued_total > 0) {
            return redirect()->route('cpo.index')->with('error', 'Cannot edit — yarn has already been issued against this CPO.');
        }

        $vendors = Vendor::active()->where('vendor_type', 'weaving_mill')->orderBy('name')->get();
        if ($vendors->isEmpty()) {
            $vendors = Vendor::active()->orderBy('name')->get();
        }

        $yarnCategory = ProductCategory::where('code', 'yarn')->first();
        $yarnProducts = $yarnCategory ? Product::active()->where('category_id', $yarnCategory->id)->orderBy('name')->get() : collect();

        $greigeCategory = ProductCategory::where('code', 'greige')->first();
        $greigeProducts = $greigeCategory ? Product::active()->where('category_id', $greigeCategory->id)->orderBy('name')->get() : collect();

        $taxes = TaxMaster::active()->orderBy('rate', 'desc')->get();
        $forecasts = Forecast::approved()->with('customer', 'product')->get();

        return view('cpo.edit', compact('cpo', 'vendors', 'yarnProducts', 'greigeProducts', 'taxes', 'forecasts'));
    }

    public function update(Request $request, $id)
    {
        $cpo = ConversionPurchaseOrder::findOrFail($id);

        if ($cpo->yarn_issued_total > 0) {
            return back()->with('error', 'Cannot edit — yarn has already been issued against this CPO.');
        }

        $request->validate([
            'vendor_id'              => 'required|exists:vendors,id',
            'warp_product_id'        => 'required|exists:products,id',
            'weft_product_id'        => 'required|exists:products,id',
            'greige_product_id'      => 'nullable|exists:products,id',
            'forecast_id'            => 'nullable|exists:forecasts,id',
            'warp_count'             => 'required|numeric|min:0.01',
            'weft_count'             => 'required|numeric|min:0.01',
            'reed_count'             => 'required|numeric|min:0.01',
            'pick'                   => 'required|numeric|min:0.01',
            'width'                  => 'required|numeric|min:0.01',
            'total_meters_required'  => 'required|numeric|min:0.001',
            'rate_per_pick'          => 'required|numeric|min:0',
            'sizing_lbs'             => 'nullable|numeric|min:0',
            'warp_conversion_pct'    => 'nullable|numeric|min:0',
            'gst_applicable'         => 'required|boolean',
            'tax_id'                 => 'required_if:gst_applicable,1|nullable|exists:tax_masters,id',
            'po_date'                => 'required|date',
            'remarks'                => 'nullable|string',
        ]);

        try {
            $taxRate = 0;
            if ($request->boolean('gst_applicable') && $request->tax_id) {
                $tax = TaxMaster::find($request->tax_id);
                $taxRate = $tax ? (float) $tax->rate : 0;
            }

            $calc = $this->formulaService->calculate($request->all());
            $calc = $this->formulaService->withGst($calc, $request->boolean('gst_applicable'), $taxRate);

            $cpo->update(array_merge(
                $request->only([
                    'vendor_id', 'warp_product_id', 'weft_product_id', 'greige_product_id', 'forecast_id',
                    'warp_count', 'weft_count', 'reed_count', 'pick', 'width', 'total_meters_required',
                    'rate_per_pick', 'sizing_lbs', 'warp_conversion_pct', 'po_date', 'remarks',
                ]),
                [
                    'gst_applicable'  => $request->boolean('gst_applicable'),
                    'tax_id'          => $request->boolean('gst_applicable') ? $request->tax_id : null,
                    'gst_rate'        => $taxRate,
                    'updated_by'      => auth()->id(),
                ],
                $calc
            ));

            Log::info('[CPO] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('cpo.index')->with('success', 'CPO ' . $cpo->cpo_no . ' updated successfully.');

        } catch (\Exception $e) {
            Log::error('[CPO] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $cpo = ConversionPurchaseOrder::findOrFail($id);

            if ($cpo->yarn_issued_total > 0) {
                return back()->with('error', 'Cannot delete — yarn has already been issued against this CPO.');
            }

            $cpo->delete();
            return redirect()->route('cpo.index')->with('success', 'CPO deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[CPO] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete CPO.');
        }
    }
}