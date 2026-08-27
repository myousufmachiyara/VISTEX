<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Http\Resources\VendorResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class VendorApiController extends Controller
{
    // GET /api/vendors
    public function index()
    {
        $vendors = Vendor::orderBy('name')->get();
        return VendorResource::collection($vendors);
    }

    // GET /api/vendors/{id}
    public function show($id)
    {
        try {
            $vendor = Vendor::findOrFail($id);
            return new VendorResource($vendor);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Vendor not found.'], 404);
        }
    }

    // POST /api/vendors
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'vendor_type'          => ['required', Rule::in(array_keys(Vendor::TYPES))],
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'contact_person'       => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            'city'                 => 'nullable|string|max:100',
            'ntn'                  => 'nullable|string|max:50',
            'opening_balance'      => 'nullable|numeric|min:0',
            'opening_type'         => 'nullable|in:receivable,payable',
            'opening_balance_date' => 'nullable|date',
            'notes'                => 'nullable|string|max:1000',
            'is_active'            => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $data['opening_balance']      = $data['opening_balance'] ?? 0;
            $data['opening_type']         = $data['opening_type'] ?? 'payable';
            $data['opening_balance_date'] = $data['opening_balance_date'] ?? now()->toDateString();
            $data['is_active']            = $data['is_active'] ?? true;
            $data['created_by']           = $request->user()->id;
            $data['updated_by']           = $request->user()->id;

            $vendor = Vendor::create($data);

            DB::commit();

            Log::info('[Vendor API] Created', ['id' => $vendor->id, 'by' => $request->user()->id]);

            return new VendorResource($vendor);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor API] Store failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not create vendor.'], 500);
        }
    }

    // PUT /api/vendors/{id}
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'vendor_type'          => ['required', Rule::in(array_keys(Vendor::TYPES))],
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'contact_person'       => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            'city'                 => 'nullable|string|max:100',
            'ntn'                  => 'nullable|string|max:50',
            'opening_balance'      => 'nullable|numeric|min:0',
            'opening_type'         => 'nullable|in:receivable,payable',
            'opening_balance_date' => 'nullable|date',
            'notes'                => 'nullable|string|max:1000',
            'is_active'            => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $vendor = Vendor::findOrFail($id);
            $data['updated_by'] = $request->user()->id;

            $vendor->update($data);

            DB::commit();

            Log::info('[Vendor API] Updated', ['id' => $id, 'by' => $request->user()->id]);

            return new VendorResource($vendor);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor API] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not update vendor.'], 500);
        }
    }

    // DELETE /api/vendors/{id}
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $vendor = Vendor::findOrFail($id);

            $hasEntries   = DB::table('voucher_entries')->where('party_type', 'vendor')->where('party_id', $id)->exists();
            $hasPurchases = DB::table('purchases')->where('vendor_id', $id)->exists();
            $hasJobOrders = DB::table('job_orders')->where('vendor_id', $id)->exists();

            if ($hasEntries || $hasPurchases || $hasJobOrders) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete — this vendor has transaction history. Deactivate instead.',
                ], 422);
            }

            $vendor->delete();
            DB::commit();

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor API] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not delete vendor.'], 500);
        }
    }

    // GET /api/vendors/search?q=...&type=...
    public function search(Request $request)
    {
        $q = $request->get('q', '');

        $vendors = Vendor::active()
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($request->filled('type'), fn($query) => $query->where('vendor_type', $request->type))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return VendorResource::collection($vendors);
    }
}