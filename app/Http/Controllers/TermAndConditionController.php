<?php

namespace App\Http\Controllers;

use App\Models\TermAndCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TermAndConditionController extends Controller
{
    public function index()
    {
        $terms = TermAndCondition::orderBy('applies_to')->orderBy('sort_order')->get();
        return view('terms_and_conditions.index', compact('terms'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'required|string',
            'applies_to'          => 'required|in:all,purchase,weaving,processing',
            'is_default_checked'  => 'nullable|boolean',
            'sort_order'          => 'nullable|integer',
            'is_active'           => 'nullable|boolean',
        ]);

        try {
            $term = TermAndCondition::create([
                'title'              => $request->title,
                'description'        => $request->description,
                'applies_to'         => $request->applies_to,
                'is_default_checked' => $request->boolean('is_default_checked'),
                'sort_order'         => $request->sort_order ?? 0,
                'is_active'          => $request->boolean('is_active', true),
                'created_by'         => auth()->id(),
                'updated_by'         => auth()->id(),
            ]);

            Log::info('[TermAndCondition] Created', ['id' => $term->id, 'by' => auth()->id()]);

            return redirect()->route('terms_and_conditions.index')->with('success', 'Term "' . $term->title . '" created successfully.');

        } catch (\Exception $e) {
            Log::error('[TermAndCondition] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function edit($id)
    {
        try {
            return response()->json(TermAndCondition::findOrFail($id));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Term not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'required|string',
            'applies_to'          => 'required|in:all,purchase,weaving,processing',
            'is_default_checked'  => 'nullable|boolean',
            'sort_order'          => 'nullable|integer',
            'is_active'           => 'nullable|boolean',
        ]);

        try {
            $term = TermAndCondition::findOrFail($id);

            $term->update([
                'title'              => $request->title,
                'description'        => $request->description,
                'applies_to'         => $request->applies_to,
                'is_default_checked' => $request->boolean('is_default_checked'),
                'sort_order'         => $request->sort_order ?? 0,
                'is_active'          => $request->boolean('is_active', $term->is_active),
                'updated_by'         => auth()->id(),
            ]);

            Log::info('[TermAndCondition] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('terms_and_conditions.index')->with('success', 'Term updated successfully.');

        } catch (\Exception $e) {
            Log::error('[TermAndCondition] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $term = TermAndCondition::findOrFail($id);
            $term->delete();
            return redirect()->route('terms_and_conditions.index')->with('success', 'Term deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[TermAndCondition] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete term.');
        }
    }

    // AJAX: terms applicable to a PO type, for the create form
    public function forType(string $type)
    {
        $terms = TermAndCondition::active()->forType($type)->orderBy('sort_order')->get();
        return response()->json($terms);
    }
}