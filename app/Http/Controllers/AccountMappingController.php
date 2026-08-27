<?php

namespace App\Http\Controllers;

use App\Models\AccountMapping;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountMappingController extends Controller
{
    public function index()
    {
        $mappings = AccountMapping::with('account')->orderBy('role_key')->get();
        $accounts = ChartOfAccounts::active()->orderBy('account_code')->get();

        return view('accounts.mapping', compact('mappings', 'accounts'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'mappings'              => 'required|array',
            'mappings.*'            => 'nullable|exists:chart_of_accounts,id',
        ]);

        try {
            foreach ($request->mappings as $roleKey => $accountId) {
                if (!$accountId) continue;

                AccountMapping::updateOrCreate(
                    ['role_key' => $roleKey],
                    ['account_id' => $accountId]
                );
            }

            Log::info('[AccountMapping] Updated', ['by' => auth()->id()]);

            return redirect()->route('account-mappings.index')->with('success', 'Account mappings updated successfully.');

        } catch (\Exception $e) {
            Log::error('[AccountMapping] Update failed', ['message' => $e->getMessage()]);
            return back()->with('error', 'Something went wrong.');
        }
    }
}