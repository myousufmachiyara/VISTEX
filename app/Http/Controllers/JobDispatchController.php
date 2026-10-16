<?php

namespace App\Http\Controllers;

use App\Models\{Job, Location};
use App\Services\DispatchService;
use Illuminate\Http\Request;

class JobDispatchController extends Controller
{
    public function __construct(private DispatchService $service) {}

    public function create($id)
    {
        $job = Job::with('customer')->findOrFail($id);
        $lines = $this->service->lines($job);
        $warehouses = Location::whereNull('vendor_id')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('jobs.dispatch', compact('job', 'lines', 'warehouses'));
    }

    // Over-shipment allowed on this order (can be changed after approval)
    public function tolerance(Request $request, $id)
    {
        $request->validate(['dispatch_tolerance_pct' => 'required|numeric|min:0|max:50']);
        Job::findOrFail($id)->update(['dispatch_tolerance_pct' => round((float) $request->dispatch_tolerance_pct, 2)]);
        return back()->with('success', 'Dispatch tolerance saved.');
    }

    public function store(Request $request, $id)
    {
        $request->validate(['location_id' => 'required|exists:locations,id', 'dispatch_date' => 'required|date', 'remarks' => 'nullable|string|max:500',
            'lines' => 'required|array', 'lines.*.job_item_id' => 'required|integer', 'lines.*.product_id' => 'required|integer',
            'lines.*.lot_no' => 'nullable|string', 'lines.*.grade' => 'nullable|in:A,B,C', 'lines.*.pieces' => 'nullable|numeric|min:0']);
        try {
            // Only lines from the warehouse chosen
            $lines = array_filter($request->input('lines'), fn($l) => (int) ($l['location_id'] ?? $request->location_id) === (int) $request->location_id);
            $m = $this->service->create(Job::findOrFail($id), $request->only('location_id', 'dispatch_date', 'remarks'), $lines, auth()->id());
            return redirect()->route('stock_movements.show', $m->id)->with('success', "Dispatch gate pass {$m->movement_no} created — waiting for the warehouse in-charge's approval.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
