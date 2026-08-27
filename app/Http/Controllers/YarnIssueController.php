<?php

namespace App\Http\Controllers;

use App\Models\YarnIssue;
use App\Models\ConversionPurchaseOrder;
use App\Models\Location;
use App\Models\LocationStockLedger;
use App\Services\YarnIssueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YarnIssueController extends Controller
{
    public function __construct(private YarnIssueService $service) {}

    public function index()
    {
        $issues = YarnIssue::with('cpo.vendor', 'items.product')->orderByDesc('issue_date')->get();
        return view('yarn_issues.index', compact('issues'));
    }

    public function create()
    {
        $cpos = ConversionPurchaseOrder::active()->with('vendor', 'warpProduct', 'weftProduct')->orderByDesc('po_date')->get();
        return view('yarn_issues.create', compact('cpos'));
    }

    public function cpoDetails($cpoId)
    {
        $cpo = ConversionPurchaseOrder::with('warpProduct', 'weftProduct')->findOrFail($cpoId);
        $defaultLocationId = Location::whereNull('vendor_id')->value('id');

        $rows = [];
        foreach ([$cpo->warpProduct, $cpo->weftProduct] as $product) {
            if (!$product) continue;
            $rows[] = [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'available'    => $defaultLocationId ? LocationStockLedger::balance($defaultLocationId, $product->id, 'fresh') : 0,
            ];
        }

        return response()->json([
            'items'             => collect($rows)->unique('product_id')->values(),
            'total_required'    => (float) $cpo->total_yarn_weight_consumed,
            'already_issued'    => $cpo->yarn_issued_total,
            'remaining_allowed' => round((float) $cpo->total_yarn_weight_consumed - $cpo->yarn_issued_total, 3),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cpo_id'                => 'required|exists:conversion_purchase_orders,id',
            'issue_date'            => 'required|date',
            'remarks'               => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.quantity'      => 'required|numeric|min:0.001',
        ]);

        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('yarn_issue_attachments', 'public');
                }
            }

            $issue = $this->service->create([
                'cpo_id'      => $request->cpo_id,
                'issue_date'  => $request->issue_date,
                'remarks'     => $request->remarks,
                'attachments' => $attachments ?: null,
            ], $request->items, auth()->id());

            Log::info('[YarnIssue] Created', ['id' => $issue->id, 'by' => auth()->id()]);

            return redirect()->route('yarn_issues.index')->with('success', 'Yarn issue ' . $issue->issue_no . ' created successfully.');

        } catch (\Exception $e) {
            Log::error('[YarnIssue] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $issue = YarnIssue::findOrFail($id);
            $this->service->delete($issue);
            return redirect()->route('yarn_issues.index')->with('success', 'Yarn issue deleted.');
        } catch (\Exception $e) {
            Log::error('[YarnIssue] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
    
    public function edit($id)
    {
        $issue = YarnIssue::with('items.product', 'cpo')->findOrFail($id);

        if (\App\Models\GreigeReceive::where('cpo_id', $issue->cpo_id)->exists()) {
            return redirect()->route('yarn_issues.index')->with('error', 'Cannot edit — greige has already been received against this CPO.');
        }

        $cpos = ConversionPurchaseOrder::active()->with('vendor', 'warpProduct', 'weftProduct')->orderByDesc('po_date')->get();

        return view('yarn_issues.edit', compact('issue', 'cpos'));
    }

    public function update(Request $request, $id)
    {
        $issue = YarnIssue::with('items')->findOrFail($id);

        if (\App\Models\GreigeReceive::where('cpo_id', $issue->cpo_id)->exists()) {
            return back()->with('error', 'Cannot edit — greige has already been received against this CPO.');
        }

        $request->validate([
            'issue_date'            => 'required|date',
            'remarks'               => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.quantity'      => 'required|numeric|min:0.001',
        ]);

        try {
            $attachments = $issue->attachments ?? [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('yarn_issue_attachments', 'public');
                }
            }

            $this->service->update($issue, [
                'issue_date'  => $request->issue_date,
                'remarks'     => $request->remarks,
                'attachments' => $attachments ?: null,
            ], $request->items, auth()->id());

            Log::info('[YarnIssue] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('yarn_issues.index')->with('success', 'Yarn issue updated successfully.');

        } catch (\Exception $e) {
            Log::error('[YarnIssue] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}