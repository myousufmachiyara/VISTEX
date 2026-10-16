<?php

namespace App\Http\Controllers;

use App\Models\{ChartOfAccounts, Customer, CustomerInvoice, CustomerReceipt};
use App\Services\CustomerReceiptService;
use Illuminate\Http\Request;

class CustomerReceiptController extends Controller
{
    public function __construct(private CustomerReceiptService $service) {}

    public function index()
    {
        $receipts = CustomerReceipt::with('customer', 'account')->orderByDesc('receipt_date')->orderByDesc('id')->get();
        return view('customer_receipts.index', compact('receipts'));
    }

    public function create(Request $request)
    {
        return view('customer_receipts.create', [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'accounts' => ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'account_type']),
            'preset' => $request->only('customer_id', 'invoice_id'),
        ]);
    }

    // Open invoices of a customer with balances
    public function openInvoices($customerId)
    {
        return response()->json(CustomerInvoice::where('customer_id', $customerId)->whereIn('status', ['Unpaid', 'PartiallyPaid'])
            ->orderBy('invoice_date')->orderBy('id')->get()
            ->map(fn($i) => ['id' => $i->id, 'invoice_no' => $i->invoice_no, 'date' => $i->invoice_date->format('d-M-Y'),
                'due' => $i->due_date?->format('d-M-Y'), 'currency' => $i->currency, 'exchange_rate' => $i->exchange_rate,
                'total' => $i->total, 'balance' => $i->balance, 'overdue' => $i->isOverdue()]));
    }

    public function store(Request $request)
    {
        $request->validate(['customer_id' => 'required|exists:customers,id', 'receipt_date' => 'required|date', 'account_id' => 'required|exists:chart_of_accounts,id',
            'method' => 'required|in:cash,bank,cheque', 'reference' => 'nullable|string|max:100', 'currency' => 'required|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0', 'amount' => 'required|numeric|min:0.01', 'remarks' => 'nullable|string',
            'allocations' => 'nullable|array', 'allocations.*.customer_invoice_id' => 'required|integer', 'allocations.*.amount' => 'nullable|numeric|min:0']);
        try {
            $r = $this->service->create($request->except('allocations', '_token'), $request->input('allocations', []), auth()->id());
            return redirect()->route('customer_receipts.show', $r->id)->with('success', "Receipt {$r->receipt_no} posted.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $receipt = CustomerReceipt::with('customer', 'account', 'allocations.invoice', 'creator')->findOrFail($id);
        return view('customer_receipts.show', compact('receipt'));
    }

    public function destroy($id)
    {
        $this->service->delete(CustomerReceipt::findOrFail($id));
        return redirect()->route('customer_receipts.index')->with('success', 'Receipt deleted; its invoices are open again.');
    }
}
