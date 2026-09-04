<?php

namespace App\Http\Controllers;

use App\Models\Broker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrokerController extends Controller
{
    public function index()
    {
        $brokers = Broker::orderBy('name')->get();
        return view('brokers.index', compact('brokers'));
    }
    // BrokerController — add this method

    public function ledger($id)
    {
        $broker = Broker::findOrFail($id);

        $entries = \App\Models\VoucherEntry::where('party_type', 'broker')->where('party_id', $id)
            ->with('voucher')
            ->join('vouchers', 'voucher_entries.voucher_id', '=', 'vouchers.id')
            ->orderBy('vouchers.voucher_date')
            ->select('voucher_entries.*', 'vouchers.voucher_date', 'vouchers.narration', 'vouchers.voucher_no')
            ->get();

        $balance = 0;
        $entries = $entries->map(function ($e) use (&$balance) {
            $balance += (float) $e->credit - (float) $e->debit;
            $e->running_balance = $balance;
            return $e;
        });

        return view('brokers.ledger', compact('broker', 'entries', 'balance'));
}

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'notes'      => 'nullable|string|max:1000',
            'is_active'  => 'nullable|boolean',
        ]);

        try {
            $broker = Broker::create([
                'name'       => $request->name,
                'phone'      => $request->phone,
                'notes'      => $request->notes,
                'is_active'  => $request->boolean('is_active', true),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            Log::info('[Broker] Created', ['id' => $broker->id, 'by' => auth()->id()]);

            return redirect()->route('brokers.index')->with('success', 'Broker "' . $broker->name . '" created successfully.');

        } catch (\Exception $e) {
            Log::error('[Broker] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function edit($id)
    {
        try {
            return response()->json(Broker::findOrFail($id));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Broker not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'notes'      => 'nullable|string|max:1000',
            'is_active'  => 'nullable|boolean',
        ]);

        try {
            $broker = Broker::findOrFail($id);

            $broker->update([
                'name'       => $request->name,
                'phone'      => $request->phone,
                'notes'      => $request->notes,
                'is_active'  => $request->boolean('is_active', $broker->is_active),
                'updated_by' => auth()->id(),
            ]);

            Log::info('[Broker] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('brokers.index')->with('success', 'Broker updated successfully.');

        } catch (\Exception $e) {
            Log::error('[Broker] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $broker = Broker::findOrFail($id);

            // Guard against deleting a broker already referenced on a PO,
            // once PO-level broker linkage is added in a later stage.
            if (\Illuminate\Support\Facades\Schema::hasTable('purchase_orders') &&
                \Illuminate\Support\Facades\Schema::hasColumn('purchase_orders', 'broker_id')) {
                $inUse = \Illuminate\Support\Facades\DB::table('purchase_orders')->where('broker_id', $id)->exists();
                if ($inUse) {
                    return back()->with('error', 'Cannot delete — this broker is linked to existing Purchase Orders. Deactivate instead.');
                }
            }

            $broker->delete();
            return redirect()->route('brokers.index')->with('success', 'Broker deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Broker] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete broker.');
        }
    }

    public function search(Request $request)
    {
        $q = $request->get('q', '');
        $brokers = Broker::active()
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->limit(30)->get();

        return response()->json($brokers->map->toLookup()->values());
    }
}