<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxMaster;
use App\Models\MeasurementUnit;
use App\Services\JobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JobController extends Controller
{
    public function __construct(private JobService $service) {}
    public function index(Request $request)
    {
        $jobs = Job::with('customer', 'items')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('order_date')
            ->get();

        return view('jobs.index', compact('jobs'));
    }

    public function create()
    {
        $customers = Customer::active()->orderBy('name')->get();
        $taxes = TaxMaster::active()->orderBy('rate', 'desc')->get();
        $units = MeasurementUnit::orderBy('name')->get();

        $skuCategory = ProductCategory::where('code', 'sku')->first();
        $products = $skuCategory ? Product::active()->where('category_id', $skuCategory->id)->orderBy('name')->get() : collect();

        return view('jobs.create', compact('customers', 'taxes', 'units', 'products'));
    }

    // AJAX: current customer SKU rate suggestion for a line
    public function suggestRate(Request $request)
    {
        $request->validate(['customer_id' => 'required|exists:customers,id', 'product_id' => 'required|exists:products,id']);

        $rate = \App\Models\CustomerSkuRate::currentRate($request->customer_id, $request->product_id);

        return response()->json(['rate' => $rate]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'              => 'required|exists:customers,id',
            'buyer_name'                => 'nullable|string|max:255',
            'shipping_address'          => 'nullable|string|max:1000',
            'customer_po_number'        => 'nullable|string|max:100',
            'customer_reference'        => 'nullable|string|max:150',
            'order_date'                => 'required|date',
            'expected_date'             => 'nullable|date|after_or_equal:order_date',
            'payment_term_type'         => 'required|in:cash,credit,pdc,other',
            'payment_term_days'         => 'required_if:payment_term_type,credit,pdc|nullable|integer|min:1',
            'payment_term_note'         => 'required_if:payment_term_type,other|nullable|string|max:255',
            'remarks'                   => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.quantity'          => 'required|numeric|min:0.001',
            'items.*.measurement_unit'  => 'nullable|exists:measurement_units,id',
            'items.*.unit_price'        => 'required|numeric|min:0',
            'items.*.discount_pct'      => 'nullable|numeric|min:0|max:100',
            'items.*.tax_id'            => 'nullable|exists:tax_masters,id',
        ]);

        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('job_attachments', 'public');
                }
            }

            $job = $this->service->create(
                array_merge($request->all(), ['attachments' => $attachments ?: null]),
                $request->items,
                auth()->id()
            );

            Log::info('[Job] Created', ['id' => $job->id, 'by' => auth()->id()]);

            return redirect()->route('jobs.index')->with('success', $job->job_no . ' created — pending superadmin approval.');

        } catch (\Exception $e) {
            Log::error('[Job] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $job = Job::with('items')->findOrFail($id);
        if ($job->status !== 'Pending') {
            return redirect()->route('jobs.show', $id)->with('error', 'Only a Pending Job can be edited.');
        }
        $customers = Customer::active()->orderBy('name')->get();
        $taxes = TaxMaster::active()->orderBy('rate','desc')->get();
        $units = MeasurementUnit::orderBy('name')->get();
        $skuCategory = ProductCategory::where('code','sku')->first();
        $products = $skuCategory ? Product::active()->where('category_id',$skuCategory->id)->orderBy('name')->get() : collect();
        return view('jobs.edit', compact('job','customers','taxes','units','products'));
    }

    public function update(Request $request, $id)
    {
        $job = Job::findOrFail($id);
        if ($job->status !== 'Pending') {
            return back()->with('error', 'Only a Pending Job can be edited.');
        }
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'buyer_name' => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string|max:1000',
            'customer_po_number' => 'nullable|string|max:100',
            'customer_reference' => 'nullable|string|max:150',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'payment_term_type' => 'required|in:cash,credit,pdc,other',
            'payment_term_days' => 'required_if:payment_term_type,credit,pdc|nullable|integer|min:1',
            'payment_term_note' => 'required_if:payment_term_type,other|nullable|string|max:255',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.measurement_unit' => 'nullable|exists:measurement_units,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_id' => 'nullable|exists:tax_masters,id',
        ]);

        try {
            $this->service->update($job, $request->all(), $request->items, auth()->id());
            return redirect()->route('jobs.show', $job->id)->with('success', $job->job_no . ' updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $job = Job::with('customer', 'approver', 'creator', 'items.product.measurementUnit', 'items.tax')->findOrFail($id);
        return view('jobs.show', compact('job'));
    }

    public function approve($id)
    {
        $job = Job::findOrFail($id);
        if (!$job->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only a superadmin can approve a Job.');
        }

        try {
            $this->service->approve($job, auth()->id());
            return back()->with('success', $job->job_no . ' approved.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $job = Job::findOrFail($id);
        if (!$job->canBeApprovedBy(auth()->user())) {
            abort(403, 'Only a superadmin can reject a Job.');
        }

        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->service->reject($job, auth()->id(), $request->reason);
            return back()->with('success', $job->job_no . ' rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $job = Job::findOrFail($id);
            $this->service->delete($job);
            return redirect()->route('jobs.index')->with('success', 'Job deleted successfully.');
        } catch (\Exception $e) {
            Log::error('[Job] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
}