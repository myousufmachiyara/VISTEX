<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::orderBy('name')->get();
        return view('parties.vendors', compact('vendors'));
    }

    private function rules(): array
    {
        return [
            'name'                 => 'required|string|max:255',
            'vendor_type'          => ['required', Rule::in(array_keys(Vendor::TYPES))],
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'contact_person'       => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            'city'                 => 'nullable|string|max:100',
            'tax_id_number'        => 'nullable|string|max:50',
            'ntn_number' => 'nullable|string|max:50',
            'payment_terms_type'   => 'required|in:days_after_invoice,of_current_month,of_following_month',
            'payment_days'         => 'required|integer|min:0|max:31',
            'currency'             => 'required|string|max:10',
            'opening_balance'      => 'nullable|numeric|min:0',
            'opening_type'         => 'nullable|in:receivable,payable',
            'opening_balance_date' => 'nullable|date',
            'notes'                => 'nullable|string|max:1000',
            'is_active'            => 'nullable|boolean',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());
        DB::beginTransaction();
        try {
            $vendor = Vendor::create(array_merge($request->only([
                'name', 'vendor_type', 'phone', 'email', 'contact_person', 'address', 'city','ntn_number',
                'tax_id_number', 'payment_terms_type', 'payment_days', 'currency', 'notes',
            ]), [
                'opening_balance'      => $request->opening_balance ?? 0,
                'opening_type'         => $request->opening_type ?? 'payable',
                'opening_balance_date' => $request->opening_balance_date ?? now()->toDateString(),
                'is_active'            => $request->boolean('is_active', true),
                'created_by'           => auth()->id(),
                'updated_by'           => auth()->id(),
            ]));

            DB::commit();
            Log::info('[Vendor] Created', ['id' => $vendor->id, 'by' => auth()->id()]);

            return redirect()->route('vendors.index')->with('success', 'Vendor "' . $vendor->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function edit($id)
    {
        try {
            return response()->json(Vendor::findOrFail($id));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Vendor not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate($this->rules());

        DB::beginTransaction();
        try {
            $vendor = Vendor::findOrFail($id);

            $vendor->update(array_merge($request->only([
                'name', 'vendor_type', 'phone', 'email', 'contact_person', 'address', 'city','ntn_number',
                'tax_id_number', 'payment_terms_type', 'payment_days', 'currency', 'notes',
            ]), [
                'opening_balance'      => $request->opening_balance ?? $vendor->opening_balance,
                'opening_type'         => $request->opening_type ?? $vendor->opening_type,
                'opening_balance_date' => $request->opening_balance_date ?? $vendor->opening_balance_date,
                'is_active'            => $request->boolean('is_active', $vendor->is_active),
                'updated_by'           => auth()->id(),
            ]));

            DB::commit();
            Log::info('[Vendor] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('vendors.index')->with('success', 'Vendor updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $vendor = Vendor::findOrFail($id);

            $hasEntries = DB::table('voucher_entries')->where('party_type', 'vendor')->where('party_id', $id)->exists();
            $hasPOs     = DB::table('purchase_orders')->where('vendor_id', $id)->exists();

            if ($hasEntries || $hasPOs) {
                DB::rollBack();
                return back()->with('error', 'Cannot delete "' . $vendor->name . '" — it has transaction history. Deactivate instead.');
            }

            $vendor->delete();
            DB::commit();

            return redirect()->route('vendors.index')->with('success', 'Vendor deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete vendor.');
        }
    }

    public function search(Request $request)
    {
        $q = $request->get('q', '');
        $vendors = Vendor::active()
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($request->filled('type'), fn($query) => $query->where('vendor_type', $request->type))
            ->orderBy('name')->limit(30)->get();

        return response()->json($vendors->map->toLookup()->values());
    }
}