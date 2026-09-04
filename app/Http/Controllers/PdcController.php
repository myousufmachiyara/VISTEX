<?php
namespace App\Http\Controllers;

use App\Models\{Pdc, PdcCheque, ChartOfAccounts};
use App\Services\PdcService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PdcController extends Controller
{
    public function __construct(private PdcService $service) {}

    public function index(Request $request)
    {
        $pdcs = Pdc::with('party', 'cheques')->orderByDesc('due_date')->get();
        $summary = $this->service->summary();

        return view('pdcs.index', compact('pdcs', 'summary'));
    }

    public function uncleared()
    {
        $cheques = PdcCheque::uncleared()->with('pdc.party', 'bankAccount')->orderBy('issued_date')->get();
        return view('pdcs.uncleared', compact('cheques'));
    }

    public function show($id)
    {
        $pdc = Pdc::with('party', 'cheques.bankAccount', 'creator')->findOrFail($id);
        $bankAccounts = ChartOfAccounts::active()->where('account_type', 'bank')->orderBy('name')->get();

        $receiving = null;
        if ($pdc->reference_type === 'PurchaseReceiving' && $pdc->reference_id) {
            $receiving = \App\Models\PurchaseReceiving::with('purchaseOrder')->find($pdc->reference_id);
        }

        return view('pdcs.show', compact('pdc', 'bankAccounts', 'receiving'));
    }

    public function addCheque(Request $request, $pdcId)
    {
        $request->validate([
            'amount'                => 'required|numeric|min:0.01',
            'bank_account_id'       => 'required|exists:chart_of_accounts,id',
            'cheque_no'             => 'required|string|max:50',
            'unsigned_cheque_image' => 'required|file|image|max:5120',
        ]);

        try {
            $pdc = Pdc::findOrFail($pdcId);
            $path = $request->file('unsigned_cheque_image')->store('pdc_cheques', 'public');

            $this->service->addCheque($pdc, [
                'amount' => $request->amount, 'bank_account_id' => $request->bank_account_id,
                'cheque_no' => $request->cheque_no, 'unsigned_cheque_image' => $path,
            ], auth()->id());

            return back()->with('success', 'Cheque added.');
        } catch (\Exception $e) {
            Log::error('[PdcCheque] addCheque failed', ['message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function markSigned(Request $request, $chequeId)
    {
        $request->validate(['signed_cheque_image' => 'required|file|image|max:5120']);
        try {
            $path = $request->file('signed_cheque_image')->store('pdc_cheques', 'public');
            $this->service->markSigned(PdcCheque::findOrFail($chequeId), $path, auth()->id());
            return back()->with('success', 'Cheque marked as Signed.');
        } catch (\Exception $e) { return back()->with('error', $e->getMessage()); }
    }

    public function markIssued(Request $request, $chequeId)
    {
        $request->validate([
            'issue_method' => 'required|in:handed_to_vendor,bank_deposit',
            'receiver_name' => 'required_if:issue_method,handed_to_vendor|nullable|string|max:255',
            'receiver_contact' => 'nullable|string|max:50',
            'receiver_cnic' => 'nullable|string|max:30',
            'receipt_signed_image' => 'required_if:issue_method,handed_to_vendor|nullable|file|image|max:5120',
            'bank_slip_image' => 'required_if:issue_method,bank_deposit|nullable|file|image|max:5120',
            'issued_date' => 'nullable|date',
        ]);

        try {
            $cheque = PdcCheque::findOrFail($chequeId);
            $data = $request->only(['issue_method', 'receiver_name', 'receiver_contact', 'receiver_cnic', 'issued_date']);
            if ($request->hasFile('receipt_signed_image')) $data['receipt_signed_image'] = $request->file('receipt_signed_image')->store('pdc_receipts', 'public');
            if ($request->hasFile('bank_slip_image')) $data['bank_slip_image'] = $request->file('bank_slip_image')->store('pdc_bank_slips', 'public');

            $this->service->markIssued($cheque, $data, auth()->id());
            return back()->with('success', 'Cheque marked as Issued.');
        } catch (\Exception $e) { return back()->with('error', $e->getMessage()); }
    }

    public function markCleared(Request $request, $chequeId)
    {
        $request->validate(['cleared_date' => 'nullable|date']);
        try {
            $this->service->markCleared(PdcCheque::findOrFail($chequeId), $request->cleared_date, auth()->id());
            return back()->with('success', 'Cheque cleared — ledger updated.');
        } catch (\Exception $e) { return back()->with('error', $e->getMessage()); }
    }

    public function markBounced(Request $request, $chequeId)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        try {
            $this->service->markBounced(PdcCheque::findOrFail($chequeId), $request->reason, auth()->id());
            return back()->with('success', 'Cheque marked as Bounced.');
        } catch (\Exception $e) { return back()->with('error', $e->getMessage()); }
    }
}