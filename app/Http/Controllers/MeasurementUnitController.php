<?php

namespace App\Http\Controllers;

use App\Models\MeasurementUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MeasurementUnitController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $units = MeasurementUnit::orderBy('name')->get();
        return view('products.measurement_units', compact('units'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:100|unique:measurement_units,name',
            'shortcode' => 'required|string|max:20|unique:measurement_units,shortcode',
        ]);

        DB::beginTransaction();
        try {
            $unit = MeasurementUnit::create([
                'name'      => $request->name,
                'shortcode' => strtolower(trim($request->shortcode)),
            ]);

            DB::commit();
            Log::info('[MeasurementUnit] Created', ['id' => $unit->id, 'by' => auth()->id()]);

            return redirect()->route('measurement_units.index')
                ->with('success', 'Unit "' . $unit->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[MeasurementUnit] Store failed', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Returns JSON for edit modal
    public function edit($id)
    {
        try {
            $unit = MeasurementUnit::findOrFail($id);
            return response()->json($unit);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unit not found.'], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:100',
                            Rule::unique('measurement_units', 'name')->ignore($id)],
            'shortcode' => ['required', 'string', 'max:20',
                            Rule::unique('measurement_units', 'shortcode')->ignore($id)],
        ]);

        DB::beginTransaction();
        try {
            $unit = MeasurementUnit::findOrFail($id);
            $unit->update([
                'name'      => $request->name,
                'shortcode' => strtolower(trim($request->shortcode)),
            ]);

            DB::commit();
            Log::info('[MeasurementUnit] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('measurement_units.index')
                ->with('success', 'Unit updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[MeasurementUnit] Update failed', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $unit = MeasurementUnit::findOrFail($id);

            // Guard: cannot delete if products are using this unit
            $inUse = DB::table('products')
                ->where('measurement_unit', $id)
                ->exists();

            if ($inUse) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $unit->name . '" — it is used by one or more products.');
            }

            // Guard: seeded units (id 1-9) cannot be deleted
            if ($unit->id <= 9) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'System unit "' . $unit->name . '" cannot be deleted.');
            }

            $unit->delete();

            DB::commit();
            Log::info('[MeasurementUnit] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('measurement_units.index')
                ->with('success', 'Unit deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[MeasurementUnit] Destroy failed', ['message' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Could not delete unit. Please try again.');
        }
    }
}