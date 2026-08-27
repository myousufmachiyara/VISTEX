<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Vendor;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    public function __construct(private LocationService $service) {}

    public function index()
    {
        $locations = Location::with('vendor', 'inCharge')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $vendors = Vendor::active()->orderBy('name')->get();
        $users   = User::orderBy('name')->get();

        return view('locations.index', compact('locations', 'vendors', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'location_type'      => 'required|in:own,vendor',
            'vendor_id'          => 'nullable|required_if:location_type,vendor|exists:vendors,id',
            'name'               => 'required|string|max:255',
            'address'            => 'nullable|string|max:500',
            'contact_person'     => 'nullable|string|max:255',
            'phone'              => 'nullable|string|max:50',
            'in_charge_user_id'  => 'nullable|exists:users,id',
            'is_default'         => 'nullable|boolean',
            'is_active'          => 'nullable|boolean',
        ]);

        try {
            $data = [
                'vendor_id'          => $request->location_type === 'vendor' ? $request->vendor_id : null,
                'name'               => $request->name,
                'address'            => $request->address,
                'contact_person'     => $request->contact_person,
                'phone'              => $request->phone,
                'in_charge_user_id'  => $request->in_charge_user_id,
                'is_default'         => $request->boolean('is_default'),
                'is_active'          => $request->boolean('is_active', true),
            ];

            // A vendor-side location can't be the default warehouse
            if ($request->location_type === 'vendor') {
                $data['is_default'] = false;
            }

            $location = $this->service->create($data);

            Log::info('[Location] Created', ['id' => $location->id, 'by' => auth()->id()]);

            return redirect()->route('locations.index')
                ->with('success', 'Location "' . $location->name . '" created successfully.');

        } catch (\Exception $e) {
            Log::error('[Location] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Could not create location.');
        }
    }

    public function edit($id)
    {
        try {
            $location = Location::findOrFail($id);
            return response()->json($location);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Location not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'location_type'      => 'required|in:own,vendor',
            'vendor_id'          => 'nullable|required_if:location_type,vendor|exists:vendors,id',
            'name'               => 'required|string|max:255',
            'address'            => 'nullable|string|max:500',
            'contact_person'     => 'nullable|string|max:255',
            'phone'              => 'nullable|string|max:50',
            'in_charge_user_id'  => 'nullable|exists:users,id',
            'is_default'         => 'nullable|boolean',
            'is_active'          => 'nullable|boolean',
        ]);

        try {
            $location = Location::findOrFail($id);

            $data = [
                'vendor_id'          => $request->location_type === 'vendor' ? $request->vendor_id : null,
                'name'               => $request->name,
                'address'            => $request->address,
                'contact_person'     => $request->contact_person,
                'phone'              => $request->phone,
                'in_charge_user_id'  => $request->in_charge_user_id,
                'is_default'         => $request->boolean('is_default'),
                'is_active'          => $request->boolean('is_active', true),
            ];

            if ($request->location_type === 'vendor') {
                $data['is_default'] = false;
            }

            $this->service->update($location, $data);

            Log::info('[Location] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('locations.index')
                ->with('success', 'Location updated successfully.');

        } catch (\Exception $e) {
            Log::error('[Location] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Could not update location.');
        }
    }

    public function destroy($id)
    {
        try {
            $location = Location::findOrFail($id);
            $this->service->delete($location);

            return redirect()->route('locations.index')
                ->with('success', 'Location deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Location] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
}