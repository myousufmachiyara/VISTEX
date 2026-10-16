<?php

namespace App\Http\Controllers;

use App\Models\{Customer, CustomerInvoice};
use App\Services\CustomerInvoiceService;
use Illuminate\Http\Request;

class CustomerInvoiceController extends Controller
{
    public function __construct(private CustomerInvoiceService $service) {}

    public function index(Request $request)
    {
        $invoices = CustomerInvoice::with('customer')
            ->when($request->filled('customer_id'), fn($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('invoice_date')->orderByDesc('id')->get();
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        return view('customer_invoices.index', compact('invoices', 'customers'));
    }

    public function create(Request $request)
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('customer_invoices.create', ['customers' => $customers, 'preset' => $request->customer_id]);
    }

    public function uninvoiced($customerId) { return response()->json($this->service->uninvoiced((int) $customerId)); }

    public function store(Request $request)
    {
        $request->validate(['customer_id' => 'required|exists:customers,id', 'invoice_date' => 'required|date', 'due_date' => 'nullable|date',
            'exchange_rate' => 'nullable|numeric|min:0', 'remarks' => 'nullable|string', 'lines' => 'required|array',
            'lines.*.stock_movement_item_id' => 'required|integer', 'lines.*.quantity' => 'nullable|numeric|min:0', 'lines.*.pick' => 'nullable']);
        try {
            $lines = array_values(array_filter($request->input('lines'), fn($l) => !empty($l['pick'])));
            $inv = $this->service->create((int) $request->customer_id, $request->only('invoice_date', 'due_date', 'exchange_rate', 'remarks'), $lines, auth()->id());
            return redirect()->route('customer_invoices.show', $inv->id)->with('success', "Invoice {$inv->invoice_no} posted.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $invoice = CustomerInvoice::with('customer', 'items.product', 'allocations.receipt', 'creator', 'creditNotes', 'creditApplications.creditNote')->findOrFail($id);
        return view('customer_invoices.show', compact('invoice'));
    }

    public function print($id)
    {
        $invoice = CustomerInvoice::with('customer', 'items.product', 'creator')->findOrFail($id);
        return view('customer_invoices.print', compact('invoice'));
    }

    public function cancel($id)
    {
        try {
            $this->service->cancel(CustomerInvoice::findOrFail($id), auth()->id());
            return back()->with('success', 'Invoice cancelled; its deliveries can be invoiced again.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
