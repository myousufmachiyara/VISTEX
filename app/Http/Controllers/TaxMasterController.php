<?php

namespace App\Http\Controllers;

use App\Models\TaxMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaxMasterController extends Controller
{
    public function index()
    {
        $taxes = TaxMaster::orderBy('rate', 'desc')->get();
        return view('tax_masters.index', compact('taxes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:50',
            'rate'       => 'required|numeric|min:0|max:100',
            'is_default' => 'nullable|boolean',
            'is_active'  => 'nullable|boolean',
        ]);

        try {
            if ($request->boolean('is_default')) {
                TaxMaster::where('is_default', true)->update(['is_default' => false]);
            }

            $tax = TaxMaster::create([
                'name'       => $request->name,
                'rate'       => $request->rate,
                'is_default' => $request->boolean('is_default'),
                'is_active'  => $request->boolean('is_active', true),
            ]);

            Log::info('[TaxMaster] Created', ['id' => $tax->id, 'by' => auth()->id()]);

            return redirect()->route('tax-masters.index')->with('success', 'Tax "' . $tax->name . '" created successfully.');

        } catch (\Exception $e) {
            Log::error('[TaxMaster] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function edit($id)
    {
        try {
            return response()->json(TaxMaster::findOrFail($id));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Tax not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'       => 'required|string|max:50',
            'rate'       => 'required|numeric|min:0|max:100',
            'is_default' => 'nullable|boolean',
            'is_active'  => 'nullable|boolean',
        ]);

        try {
            $tax = TaxMaster::findOrFail($id);

            if ($request->boolean('is_default')) {
                TaxMaster::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
            }

            $tax->update([
                'name'       => $request->name,
                'rate'       => $request->rate,
                'is_default' => $request->boolean('is_default'),
                'is_active'  => $request->boolean('is_active', true),
            ]);

            Log::info('[TaxMaster] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('tax-masters.index')->with('success', 'Tax updated successfully.');

        } catch (\Exception $e) {
            Log::error('[TaxMaster] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $tax = TaxMaster::findOrFail($id);

            if (\App\Models\PurchaseOrder::where('tax_id', $id)->exists()) {
                return back()->with('error', 'Cannot delete — this tax is used on existing Purchase Orders. Deactivate instead.');
            }

            $tax->delete();
            return redirect()->route('tax-masters.index')->with('success', 'Tax deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[TaxMaster] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete tax.');
        }
    }
}