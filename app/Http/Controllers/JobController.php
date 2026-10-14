<?php

namespace App\Http\Controllers;

use App\Models\{Customer, CustomerSkuRate, Job, Product, ProductCategory, TaxMaster, TermAndCondition};
use App\Services\JobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Customer Order (Job)
class JobController extends Controller
{
    public function __construct(private JobService $service) {}

    public function index(Request $request)
    {
        $jobs = Job::with('customer', 'items')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('order_date')->orderByDesc('id')
            ->get();

        return view('jobs.index', compact('jobs'));
    }

    public function create()
    {
        return view('jobs.create', $this->formData());
    }

    public function edit($id)
    {
        $job = Job::with('items.product', 'terms')->findOrFail($id);
        if (!$job->isEditable()) {
            return redirect()->route('jobs.show', $id)->with('error', 'Only a Pending or Rejected customer order can be edited.');
        }
        return view('jobs.edit', ['job' => $job] + $this->formData($job));
    }

    private function formData(?Job $job = null): array
    {
        $sku = ProductCategory::where('code', 'sku')->value('id');
        $terms = TermAndCondition::where('applies_to', 'customer_order')
            ->where(fn($q) => $q->where('is_active', true)->orWhereIn('id', $job ? $job->terms->pluck('term_id')->filter() : [0]))
            ->orderBy('sort_order')->orderBy('title')->get();

        return [
            'customers'   => Customer::active()->orderBy('name')->get(),
            'taxes'       => TaxMaster::active()->orderBy('rate', 'desc')->get(),
            'packagings'  => $sku ? Product::active()->where('category_id', $sku)->orderBy('name')->get(['id', 'name', 'sku', 'attributes']) : collect(),
            'terms'       => $terms,
            'selectedIds' => $job ? $job->terms->pluck('term_id')->filter()->all()
                                  : $terms->where('is_default_checked', true)->pluck('id')->all(),
            'orphans'     => $job ? $job->terms->whereNull('term_id') : collect(),
        ];
    }

    // AJAX: customer's current rate for a packaging SKU
    public function suggestRate(Request $request)
    {
        $request->validate(['customer_id' => 'required|exists:customers,id', 'product_id' => 'required|exists:products,id']);
        return response()->json(['rate' => CustomerSkuRate::currentRate($request->customer_id, $request->product_id)]);
    }

    private function rules(): array
    {
        return [
            'customer_id'          => 'required|exists:customers,id',
            'buyer_name'           => 'nullable|string|max:255',
            'consignee_name'       => 'nullable|string|max:191',
            'shipping_address'     => 'nullable|string|max:1000',
            'customer_po_number'   => 'required|string|max:100',
            'customer_reference'   => 'nullable|string|max:150',
            'order_date'           => 'required|date',
            'expected_date'        => 'nullable|date|after_or_equal:order_date',
            'currency'             => 'required|in:' . implode(',', Job::CURRENCIES),
            'exchange_rate'        => 'required_unless:currency,PKR|nullable|numeric|min:0.0001',
            'payment_term_type'    => 'required|in:cash,credit,pdc,other',
            'payment_term_days'    => 'required_if:payment_term_type,credit,pdc|nullable|integer|min:1',
            'payment_term_note'    => 'required_if:payment_term_type,other|nullable|string|max:255',
            'remarks'              => 'nullable|string',
            'term_ids'             => 'nullable|array',
            'term_ids.*'           => 'integer|exists:terms_and_conditions,id',
            'keep_snapshot_ids'    => 'nullable|array',
            'items'                => 'required|array|min:1',
            'items.*.collection'   => 'nullable|string|max:60',
            'items.*.pattern_code' => 'nullable|string|max:120',
            'items.*.design'       => 'nullable|string|max:191',
            'items.*.color_scheme' => 'nullable|string|max:191',
            'items.*.product_id'   => 'nullable|exists:products,id',
            'items.*.quantity'     => 'required|numeric|min:0',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.discount_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_id'       => 'nullable|exists:tax_masters,id',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());

        try {
            $attachments = [];
            foreach ($request->file('attachments', []) as $file) $attachments[] = $file->store('job_attachments', 'public');

            $job = $this->service->create(array_merge($request->all(), ['attachments' => $attachments ?: null]), $request->items, auth()->id());
            Log::info('[Job] Created', ['id' => $job->id, 'by' => auth()->id()]);

            return redirect()->route('jobs.show', $job->id)->with('success', $job->job_no . ' saved — pending superadmin approval.');
        } catch (\Exception $e) {
            Log::error('[Job] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $job = Job::findOrFail($id);
        if (!$job->isEditable()) return back()->with('error', 'Only a Pending or Rejected customer order can be edited.');
        $request->validate($this->rules());

        try {
            $data = $request->all();
            $new = [];
            foreach ($request->file('attachments', []) as $file) $new[] = $file->store('job_attachments', 'public');
            if ($new) $job->update(['attachments' => array_merge($job->attachments ?? [], $new)]);

            $this->service->update($job, $data, $request->items, auth()->id());
            return redirect()->route('jobs.show', $job->id)->with('success', $job->job_no . ' updated.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $job = Job::with('customer', 'approver', 'creator', 'items.product', 'items.tax', 'terms')->findOrFail($id);
        return view('jobs.show', compact('job'));
    }

    public function print($id)
    {
        $job = Job::with('customer', 'approver', 'items.product', 'terms')->findOrFail($id);
        return view('jobs.print', compact('job'));
    }

    public function approve($id)
    {
        $job = Job::findOrFail($id);
        abort_unless($job->canBeApprovedBy(auth()->user()), 403, 'Only a superadmin can approve a customer order.');

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
        abort_unless($job->canBeApprovedBy(auth()->user()), 403, 'Only a superadmin can reject a customer order.');
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
            $this->service->delete(Job::findOrFail($id));
            return redirect()->route('jobs.index')->with('success', 'Customer order deleted.');
        } catch (\Exception $e) {
            Log::error('[Job] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
}
