<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Imports\ChartOfAccountsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class COAController extends Controller
{
    public function index()
    {
        $accounts = ChartOfAccounts::with('subHead.head')->orderBy('account_code')->get();
        $subHeads = SubHeadOfAccounts::with('head')->orderBy('name')->get();

        return view('accounts.coa', compact('accounts', 'subHeads'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'account_code'     => 'required|string|max:20|unique:chart_of_accounts,account_code',
            'shoa_id'          => 'required|exists:sub_head_of_accounts,id',
            'name'             => 'required|string|max:255',
            'account_type'     => 'required|string|max:50',
            'opening_balance'  => 'nullable|numeric',
            'opening_date'     => 'nullable|date',
            'is_active'        => 'nullable|boolean',
        ]);

        try {
            $account = ChartOfAccounts::create([
                'account_code'    => $request->account_code,
                'shoa_id'         => $request->shoa_id,
                'name'            => $request->name,
                'account_type'    => $request->account_type,
                'opening_balance' => $request->opening_balance ?? 0,
                'opening_date'    => $request->opening_date ?? now()->toDateString(),
                'is_active'       => $request->boolean('is_active', true),
                'created_by'      => auth()->id(),
                'updated_by'      => auth()->id(),
            ]);

            Log::info('[COA] Created', ['id' => $account->id, 'by' => auth()->id()]);

            return redirect()->route('coa.index')->with('success', 'Account "' . $account->name . '" created successfully.');

        } catch (\Exception $e) {
            Log::error('[COA] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function edit($id)
    {
        try {
            return response()->json(ChartOfAccounts::findOrFail($id));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Account not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'account_code'     => 'required|string|max:20|unique:chart_of_accounts,account_code,' . $id,
            'shoa_id'          => 'required|exists:sub_head_of_accounts,id',
            'name'             => 'required|string|max:255',
            'account_type'     => 'required|string|max:50',
            'opening_balance'  => 'nullable|numeric',
            'opening_date'     => 'nullable|date',
            'is_active'        => 'nullable|boolean',
        ]);

        try {
            $account = ChartOfAccounts::findOrFail($id);

            $account->update([
                'account_code'    => $request->account_code,
                'shoa_id'         => $request->shoa_id,
                'name'            => $request->name,
                'account_type'    => $request->account_type,
                'opening_balance' => $request->opening_balance ?? $account->opening_balance,
                'opening_date'    => $request->opening_date ?? $account->opening_date,
                'is_active'       => $request->boolean('is_active', $account->is_active),
                'updated_by'      => auth()->id(),
            ]);

            Log::info('[COA] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('coa.index')->with('success', 'Account updated successfully.');

        } catch (\Exception $e) {
            Log::error('[COA] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $account = ChartOfAccounts::findOrFail($id);

            $hasEntries = \App\Models\VoucherEntry::where('account_id', $id)->exists();
            if ($hasEntries) {
                return back()->with('error', 'Cannot delete "' . $account->name . '" — it has transaction history. Deactivate instead.');
            }

            $account->delete();
            return redirect()->route('coa.index')->with('success', 'Account deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[COA] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete account.');
        }
    }

    public function search(Request $request)
    {
        $q = $request->get('q', '');
        $accounts = ChartOfAccounts::active()
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%")->orWhere('account_code', 'like', "%{$q}%"))
            ->orderBy('account_code')
            ->limit(30)
            ->get(['id', 'account_code', 'name']);

        return response()->json($accounts);
    }
    public function importForm()
    {
        return view('accounts.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            $import = new ChartOfAccountsImport();
            Excel::import($import, $request->file('file'));

            $message = "Imported {$import->imported} accounts. Skipped {$import->skipped}.";

            Log::info('[COA Import] Completed', [
                'imported' => $import->imported,
                'skipped'  => $import->skipped,
                'by'       => auth()->id(),
            ]);

            return redirect()->route('coa.index')
                ->with('success', $message)
                ->with('import_errors', $import->errors);

        } catch (\Exception $e) {
            Log::error('[COA Import] Failed', ['message' => $e->getMessage()]);
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function downloadImportTemplate()
    {
        $headers = ['account_code', 'shoa_id', 'name', 'account_type', 'opening_balance', 'opening_date'];

        $filename = 'coa_import_template.csv';
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $headers);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}