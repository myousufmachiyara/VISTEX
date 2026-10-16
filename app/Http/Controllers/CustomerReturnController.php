<?php

namespace App\Http\Controllers;

use App\Models\{Customer, CustomerCreditNote, CustomerInvoice, CustomerReturn, Location};
use App\Services\{CustomerCreditService, CustomerReturnService};
use Illuminate\Http\Request;

class CustomerReturnController extends Controller
{
    public function __construct(private CustomerReturnService $service) {}

    public function index()
    {
        $returns = CustomerReturn::with('customer', 'location', 'items', 'creditNotes')->orderByDesc('return_date')->orderByDesc('id')->get();
        $notes = CustomerCreditNote::with('customer', 'invoice')->orderByDesc('credit_date')->orderByDesc('id')->get();
        return view('customer_returns.index', compact('returns', 'notes'));
    }

    public function create(Request $request)
    {
        return view('customer_returns.create', [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Location::whereNull('vendor_id')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'preset' => $request->customer_id,
        ]);
    }

    public function returnable($customerId) { return response()->json($this->service->returnable((int) $customerId)); }

    public function store(Request $request)
    {
        $request->validate(['customer_id' => 'required|exists:customers,id', 'location_id' => 'required|exists:locations,id', 'return_date' => 'required|date',
            'reason' => 'nullable|string|max:255', 'remarks' => 'nullable|string', 'lines' => 'required|array',
            'lines.*.stock_movement_item_id' => 'required|integer', 'lines.*.quantity' => 'nullable|numeric|min:0', 'lines.*.condition' => 'nullable|in:fresh,rejected']);
        try {
            $r = $this->service->create($request->only('customer_id', 'location_id', 'return_date', 'reason', 'remarks'), $request->input('lines'), auth()->id());
            $cn = $r->creditNotes->count();
            return redirect()->route('customer_returns.show', $r->id)->with('success', "Return {$r->return_no} posted" . ($cn ? " with {$cn} credit note(s)." : '.'));
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $return = CustomerReturn::with('customer', 'location', 'creator', 'items.product', 'items.stockMovementItem.stockMovement', 'creditNotes.invoice')->findOrFail($id);
        return view('customer_returns.show', compact('return'));
    }

    public function creditNote($id)
    {
        $note = CustomerCreditNote::with('customer', 'invoice', 'return', 'applications.invoice')->findOrFail($id);
        $open = $note->unapplied > 0.004
            ? CustomerInvoice::where('customer_id', $note->customer_id)->where('currency', $note->currency)->whereIn('status', ['Unpaid', 'PartiallyPaid'])->orderBy('invoice_date')->get()
            : collect();
        return view('customer_returns.credit_note', compact('note', 'open'));
    }

    public function applyCreditNote(Request $request, CustomerCreditService $credits, $id)
    {
        $request->validate(['rows' => 'required|array', 'rows.*.customer_invoice_id' => 'required|integer', 'rows.*.amount' => 'nullable|numeric|min:0']);
        try {
            $credits->applyCreditNote(CustomerCreditNote::findOrFail($id), $request->input('rows'), auth()->id());
            return back()->with('success', 'Credit applied to the invoices.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
