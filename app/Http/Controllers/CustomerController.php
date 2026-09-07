<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::orderBy('name')->get();
        return view('parties.customers', compact('customers'));
    }

    private function rules(): array
    {
        return [
            'name'                 => 'required|string|max:255',
            'contact_person'       => 'nullable|string|max:255',
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'address'              => 'nullable|string|max:500',
            'city'                 => 'nullable|string|max:100',
            'tax_id_number'        => 'nullable|string|max:50',
            'ntn_number'           => 'nullable|string|max:50',
            'payment_terms_type'   => 'required|in:days_after_invoice,of_current_month,of_following_month',
            'payment_days'         => 'required|integer|min:0|max:31',
            'currency'             => 'required|string|max:10',
            'opening_balance'      => 'nullable|numeric|min:0',
            'opening_type'         => 'nullable|in:receivable,payable',
            'opening_balance_date' => 'nullable|date',
            'credit_limit'         => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string|max:1000',
            'is_active'            => 'nullable|boolean',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());

        DB::beginTransaction();
        try {
            $customer = Customer::create(array_merge($request->only([
                'name', 'contact_person', 'phone', 'email', 'address', 'city', 'ntn_number',
                'tax_id_number', 'payment_terms_type', 'payment_days', 'currency', 'notes',
            ]), [
                'opening_balance'      => $request->opening_balance ?? 0,
                'opening_type'         => $request->opening_type ?? 'receivable',
                'opening_balance_date' => $request->opening_balance_date ?? now()->toDateString(),
                'credit_limit'         => $request->credit_limit ?? 0,
                'is_active'            => $request->boolean('is_active', true),
                'created_by'           => auth()->id(),
                'updated_by'           => auth()->id(),
            ]));

            DB::commit();
            Log::info('[Customer] Created', ['id' => $customer->id, 'by' => auth()->id()]);

            return redirect()->route('customers.index')->with('success', 'Customer "' . $customer->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function edit($id)
    {
        try {
            return response()->json(Customer::findOrFail($id));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Customer not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate($this->rules());

        DB::beginTransaction();
        try {
            $customer = Customer::findOrFail($id);

            $customer->update(array_merge($request->only([
                'name', 'contact_person', 'phone', 'email', 'address', 'city', 'ntn_number',
                'tax_id_number', 'payment_terms_type', 'payment_days', 'currency', 'notes',
            ]), [
                'opening_balance'      => $request->opening_balance ?? $customer->opening_balance,
                'opening_type'         => $request->opening_type ?? $customer->opening_type,
                'opening_balance_date' => $request->opening_balance_date ?? $customer->opening_balance_date,
                'credit_limit'         => $request->credit_limit ?? $customer->credit_limit,
                'is_active'            => $request->boolean('is_active', $customer->is_active),
                'updated_by'           => auth()->id(),
            ]));

            DB::commit();
            Log::info('[Customer] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $customer = Customer::findOrFail($id);

            $hasEntries = DB::table('voucher_entries')->where('party_type', 'customer')->where('party_id', $id)->exists();
            $hasOrders  = DB::table('orders')->where('customer_id', $id)->exists();

            if ($hasEntries || $hasOrders) {
                DB::rollBack();
                return back()->with('error', 'Cannot delete "' . $customer->name . '" — it has linked orders/transactions. Deactivate instead.');
            }

            $customer->delete();
            DB::commit();

            return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete customer.');
        }
    }

    public function search(Request $request)
    {
        $q = $request->get('q', '');
        $customers = Customer::active()
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')->limit(30)->get();

        return response()->json($customers->map->toLookup()->values());
    }
}