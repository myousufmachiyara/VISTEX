<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerApiController extends Controller
{
    // GET /api/customers
    public function index()
    {
        $customers = Customer::orderBy('name')->get();
        return CustomerResource::collection($customers);
    }

    // GET /api/customers/{id}
    public function show($id)
    {
        try {
            $customer = Customer::findOrFail($id);
            return new CustomerResource($customer);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }
    }

    // POST /api/customers
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'contact_person'       => 'nullable|string|max:255',
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'address'              => 'nullable|string|max:500',
            'city'                 => 'nullable|string|max:100',
            'ntn'                  => 'nullable|string|max:50',
            'opening_balance'      => 'nullable|numeric|min:0',
            'opening_type'         => 'nullable|in:receivable,payable',
            'opening_balance_date' => 'nullable|date',
            'credit_limit'         => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string|max:1000',
            'is_active'            => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $data['opening_balance']      = $data['opening_balance'] ?? 0;
            $data['opening_type']         = $data['opening_type'] ?? 'receivable';
            $data['opening_balance_date'] = $data['opening_balance_date'] ?? now()->toDateString();
            $data['credit_limit']         = $data['credit_limit'] ?? 0;
            $data['is_active']            = $data['is_active'] ?? true;
            $data['created_by']           = $request->user()->id;
            $data['updated_by']           = $request->user()->id;

            $customer = Customer::create($data);

            DB::commit();

            Log::info('[Customer API] Created', ['id' => $customer->id, 'by' => $request->user()->id]);

            return new CustomerResource($customer);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer API] Store failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not create customer.'], 500);
        }
    }

    // PUT /api/customers/{id}
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'contact_person'       => 'nullable|string|max:255',
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'address'              => 'nullable|string|max:500',
            'city'                 => 'nullable|string|max:100',
            'ntn'                  => 'nullable|string|max:50',
            'opening_balance'      => 'nullable|numeric|min:0',
            'opening_type'         => 'nullable|in:receivable,payable',
            'opening_balance_date' => 'nullable|date',
            'credit_limit'         => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string|max:1000',
            'is_active'            => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $customer = Customer::findOrFail($id);
            $data['updated_by'] = $request->user()->id;

            $customer->update($data);

            DB::commit();

            Log::info('[Customer API] Updated', ['id' => $id, 'by' => $request->user()->id]);

            return new CustomerResource($customer);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer API] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not update customer.'], 500);
        }
    }

    // DELETE /api/customers/{id}
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $customer = Customer::findOrFail($id);

            $hasEntries = DB::table('voucher_entries')->where('party_type', 'customer')->where('party_id', $id)->exists();
            $hasOrders  = DB::table('orders')->where('customer_id', $id)->exists();
            $hasSales   = DB::table('sales')->where('customer_id', $id)->exists();

            if ($hasEntries || $hasOrders || $hasSales) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete — this customer has linked orders/sales. Deactivate instead.',
                ], 422);
            }

            $customer->delete();
            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer API] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not delete customer.'], 500);
        }
    }

    // GET /api/customers/search?q=...
    public function search(Request $request)
    {
        $q = $request->get('q', '');

        $customers = Customer::active()
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return CustomerResource::collection($customers);
    }
}