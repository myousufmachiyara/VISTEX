<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServiceTypeController extends Controller
{
    public function index()
    {
        $serviceTypes = ServiceType::with('costAccount')->orderBy('name')->get();
        $accounts = ChartOfAccounts::active()->orderBy('account_code')->get();

        return view('service_types.index', compact('serviceTypes', 'accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                     => 'required|string|max:255',
            'service_cost_account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active'                => 'nullable|boolean',
        ]);

        try {
            $serviceType = ServiceType::create([
                'name'                     => $request->name,
                'service_cost_account_id' => $request->service_cost_account_id,
                'is_active'                => $request->boolean('is_active', true),
            ]);

            Log::info('[ServiceType] Created', ['id' => $serviceType->id, 'by' => auth()->id()]);

            return redirect()->route('service_types.index')->with('success', 'Service Type "' . $serviceType->name . '" created successfully.');

        } catch (\Exception $e) {
            Log::error('[ServiceType] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function edit($id)
    {
        try {
            return response()->json(ServiceType::findOrFail($id));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Service Type not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                     => 'required|string|max:255',
            'service_cost_account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active'                => 'nullable|boolean',
        ]);

        try {
            $serviceType = ServiceType::findOrFail($id);

            $serviceType->update([
                'name'                     => $request->name,
                'service_cost_account_id' => $request->service_cost_account_id,
                'is_active'                => $request->boolean('is_active', $serviceType->is_active),
            ]);

            Log::info('[ServiceType] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('service_types.index')->with('success', 'Service Type updated successfully.');

        } catch (\Exception $e) {
            Log::error('[ServiceType] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $serviceType = ServiceType::findOrFail($id);
            $serviceType->delete();

            return redirect()->route('service_types.index')->with('success', 'Service Type deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[ServiceType] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete service type.');
        }
    }
}