<?php

namespace App\Http\Controllers;

use App\Models\Pdc;
use App\Models\ChartOfAccounts;
use App\Services\PdcService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PdcController extends Controller
{
    public function __construct(private PdcService $service) {}

    public function index(Request $request)
    {
        $pdcs = Pdc::with('party', 'bankAccount')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('due_date')
            ->get();

        $receivingIds = $pdcs->where('reference_type', 'PurchaseReceiving')->pluck('reference_id')->filter()->unique();
        $receivings = \App\Models\PurchaseReceiving::with('purchaseOrder')->whereIn('id', $receivingIds)->get()->keyBy('id');

        $pdcs->each(function ($pdc) use ($receivings) {
            $pdc->receivingRef = $pdc->reference_type === 'PurchaseReceiving' ? $receivings->get($pdc->reference_id) : null;
        });

        return view('pdcs.index', compact('pdcs'));
    }

    public function uncleared()
    {
        $pdcs = Pdc::uncleared()->with('party', 'bankAccount')->orderBy('due_date')->get();

        $receivingIds = $pdcs->where('reference_type', 'PurchaseReceiving')->pluck('reference_id')->filter()->unique();
        $receivings = \App\Models\PurchaseReceiving::with('purchaseOrder')->whereIn('id', $receivingIds)->get()->keyBy('id');

        $pdcs->each(function ($pdc) use ($receivings) {
            $pdc->receivingRef = $pdc->reference_type === 'PurchaseReceiving' ? $receivings->get($pdc->reference_id) : null;
        });

        return view('pdcs.uncleared', compact('pdcs'));
    }

    public function show($id)
    {
        $pdc = Pdc::with('party', 'bankAccount', 'creator')->findOrFail($id);
        $bankAccounts = ChartOfAccounts::active()->where('account_type', 'bank')->orderBy('name')->get();

        $receiving = null;
        if ($pdc->reference_type === 'PurchaseReceiving' && $pdc->reference_id) {
            $receiving = \App\Models\PurchaseReceiving::with('purchaseOrder')->find($pdc->reference_id);
        }

        return view('pdcs.show', compact('pdc', 'bankAccounts', 'receiving'));
    }

    public function markCreated(Request $request, $id)
    {
        $request->validate([
            'bank_account_id'      => 'required|exists:chart_of_accounts,id',
            'cheque_no'             => 'required|string|max:50',
            'unsigned_cheque_image' => 'required|file|image|max:5120',
        ]);

        try {
            $path = $request->file('unsigned_cheque_image')->store('pdc_cheques', 'public');
            $pdc = Pdc::findOrFail($id);

            $this->service->markCreated($pdc, [
                'bank_account_id'       => $request->bank_account_id,
                'cheque_no'             => $request->cheque_no,
                'unsigned_cheque_image' => $path,
            ], auth()->id());

            Log::info('[PDC] Marked Created', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'PDC marked as Created.');

        } catch (\Exception $e) {
            Log::error('[PDC] markCreated failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function markSigned(Request $request, $id)
    {
        $request->validate(['signed_cheque_image' => 'required|file|image|max:5120']);

        try {
            $path = $request->file('signed_cheque_image')->store('pdc_cheques', 'public');
            $pdc = Pdc::findOrFail($id);

            $this->service->markSigned($pdc, $path, auth()->id());

            Log::info('[PDC] Marked Signed', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'PDC marked as Signed.');

        } catch (\Exception $e) {
            Log::error('[PDC] markSigned failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function markIssued(Request $request, $id)
    {
        $request->validate([
            'issue_method'          => 'required|in:handed_to_vendor,bank_deposit',
            'receiver_name'         => 'required_if:issue_method,handed_to_vendor|nullable|string|max:255',
            'receiver_contact'      => 'nullable|string|max:50',
            'receiver_cnic'         => 'nullable|string|max:30',
            'receipt_signed_image'  => 'required_if:issue_method,handed_to_vendor|nullable|file|image|max:5120',
            'bank_slip_image'       => 'required_if:issue_method,bank_deposit|nullable|file|image|max:5120',
            'issued_date'           => 'nullable|date',
        ]);

        try {
            $pdc = Pdc::findOrFail($id);
            $data = $request->only(['issue_method', 'receiver_name', 'receiver_contact', 'receiver_cnic', 'issued_date']);

            if ($request->hasFile('receipt_signed_image')) {
                $data['receipt_signed_image'] = $request->file('receipt_signed_image')->store('pdc_receipts', 'public');
            }
            if ($request->hasFile('bank_slip_image')) {
                $data['bank_slip_image'] = $request->file('bank_slip_image')->store('pdc_bank_slips', 'public');
            }

            $this->service->markIssued($pdc, $data, auth()->id());

            Log::info('[PDC] Marked Issued', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'PDC marked as Issued.');

        } catch (\Exception $e) {
            Log::error('[PDC] markIssued failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function markCleared(Request $request, $id)
    {
        $request->validate(['cleared_date' => 'nullable|date']);

        try {
            $pdc = Pdc::findOrFail($id);
            $this->service->markCleared($pdc, $request->cleared_date, auth()->id());

            Log::info('[PDC] Marked Cleared', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'PDC cleared — vendor ledger and bank balance updated.');

        } catch (\Exception $e) {
            Log::error('[PDC] markCleared failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function markBounced(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $pdc = Pdc::findOrFail($id);
            $this->service->markBounced($pdc, $request->reason, auth()->id());

            Log::info('[PDC] Marked Bounced', ['id' => $id, 'by' => auth()->id()]);
            return back()->with('success', 'PDC marked as Bounced.');

        } catch (\Exception $e) {
            Log::error('[PDC] markBounced failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
}