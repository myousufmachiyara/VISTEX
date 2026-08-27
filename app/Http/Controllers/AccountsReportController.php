<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccounts;
use App\Models\VoucherEntry;
use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountsReportController extends Controller
{
    // Single tabbed entry point — sidebar links only here
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'general_ledger');

        $data = match ($tab) {
            'general_ledger' => $this->generalLedgerData($request),
            'trial_balance'  => $this->trialBalanceData($request),
            'profit_loss'    => $this->profitLossData($request),
            'balance_sheet'  => $this->balanceSheetData($request),
            'receivables'    => $this->receivablesData($request),
            'payables'       => $this->payablesData($request),
            'party_ledger'   => $this->partyLedgerData($request),
            'cash_bank'      => $this->cashBankData($request),
            default          => [],
        };

        $accounts  = ChartOfAccounts::orderBy('account_code')->get();
        $customers = Customer::active()->orderBy('name')->get();
        $vendors   = Vendor::active()->orderBy('name')->get();

        return view('reports.accounting', compact('tab', 'data', 'accounts', 'customers', 'vendors'));
    }

    // ── Individual standalone routes (kept for direct linking / API use) ──
    public function generalLedger(Request $request) { return $this->index($request->merge(['tab' => 'general_ledger'])); }
    public function trialBalance(Request $request)  { return $this->index($request->merge(['tab' => 'trial_balance'])); }
    public function profitLoss(Request $request)    { return $this->index($request->merge(['tab' => 'profit_loss'])); }
    public function balanceSheet(Request $request)  { return $this->index($request->merge(['tab' => 'balance_sheet'])); }
    public function receivables(Request $request)   { return $this->index($request->merge(['tab' => 'receivables'])); }
    public function payables(Request $request)      { return $this->index($request->merge(['tab' => 'payables'])); }
    public function partyLedger(Request $request)   { return $this->index($request->merge(['tab' => 'party_ledger'])); }
    public function cashBank(Request $request)      { return $this->index($request->merge(['tab' => 'cash_bank'])); }
    public function bankReconciliation(Request $request) { return $this->index($request->merge(['tab' => 'cash_bank'])); }

    // ── Data builders ────────────────────────────────────────────────

    private function generalLedgerData(Request $request): array
    {
        $accountId = $request->get('account_id');
        $from = $request->get('from_date');
        $to   = $request->get('to_date');

        if (!$accountId) {
            return ['entries' => collect(), 'account' => null, 'opening' => 0];
        }

        $account = ChartOfAccounts::find($accountId);

        $opening = VoucherEntry::where('account_id', $accountId)
            ->when($from, fn($q) => $q->whereHas('voucher', fn($v) => $v->where('voucher_date', '<', $from)))
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->value('net') ?? 0;

        $opening = (float) ($account->opening_balance ?? 0) + (float) $opening;

        $entries = VoucherEntry::with('voucher')
            ->where('account_id', $accountId)
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->when($from, fn($q2) => $q2->where('voucher_date', '>=', $from))
                  ->when($to, fn($q2) => $q2->where('voucher_date', '<=', $to));
            })
            ->get()
            ->sortBy(fn($e) => $e->voucher->voucher_date ?? null);

        $running = $opening;
        $entries = $entries->map(function ($e) use (&$running) {
            $running += (float) $e->debit - (float) $e->credit;
            $e->running_balance = $running;
            return $e;
        });

        return ['entries' => $entries, 'account' => $account, 'opening' => $opening];
    }

    private function trialBalanceData(Request $request): array
    {
        $to = $request->get('to_date', now()->toDateString());

        $rows = ChartOfAccounts::orderBy('account_code')->get()->map(function ($acc) use ($to) {
            $net = VoucherEntry::where('account_id', $acc->id)
                ->whereHas('voucher', fn($v) => $v->where('voucher_date', '<=', $to))
                ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
                ->value('net') ?? 0;

            $balance = (float) $acc->opening_balance + (float) $net;

            return [
                'account' => $acc,
                'debit'   => $balance > 0 ? $balance : 0,
                'credit'  => $balance < 0 ? abs($balance) : 0,
            ];
        })->filter(fn($r) => $r['debit'] != 0 || $r['credit'] != 0)->values();

        return [
            'rows'          => $rows,
            'total_debit'   => $rows->sum('debit'),
            'total_credit'  => $rows->sum('credit'),
        ];
    }

    private function profitLossData(Request $request): array
    {
        $from = $request->get('from_date', now()->startOfMonth()->toDateString());
        $to   = $request->get('to_date', now()->toDateString());

        $revenueTypes = ['revenue', 'other_income'];
        $expenseTypes = ['cogs', 'expenses', 'freight', 'service_cost', 'sampling', 'packaging'];

        $revenue = ChartOfAccounts::whereIn('account_type', $revenueTypes)->get()->map(fn($acc) => [
            'account' => $acc,
            'amount'  => $this->periodNet($acc->id, $from, $to, 'credit'),
        ])->filter(fn($r) => $r['amount'] != 0)->values();

        $expenses = ChartOfAccounts::whereIn('account_type', $expenseTypes)->get()->map(fn($acc) => [
            'account' => $acc,
            'amount'  => $this->periodNet($acc->id, $from, $to, 'debit'),
        ])->filter(fn($r) => $r['amount'] != 0)->values();

        $totalRevenue = $revenue->sum('amount');
        $totalExpense = $expenses->sum('amount');

        return [
            'revenue'      => $revenue,
            'expenses'     => $expenses,
            'total_revenue'=> $totalRevenue,
            'total_expense'=> $totalExpense,
            'net_profit'   => $totalRevenue - $totalExpense,
            'from'         => $from,
            'to'           => $to,
        ];
    }

    private function balanceSheetData(Request $request): array
    {
        $to = $request->get('to_date', now()->toDateString());

        $assetTypes     = ['cash', 'bank', 'receivable', 'inventory', 'inventory_transit', 'tax_receivable', 'advance', 'fixed_asset'];
        $liabilityTypes = ['payable', 'loan', 'tax_payable', 'customer_advance'];
        $equityTypes    = ['equity', 'drawings', 'retained_earnings'];

        $build = fn($types) => ChartOfAccounts::whereIn('account_type', $types)->get()->map(fn($acc) => [
            'account' => $acc,
            'balance' => (float) $acc->opening_balance + $this->periodNet($acc->id, null, $to, 'debit'),
        ])->filter(fn($r) => $r['balance'] != 0)->values();

        $assets      = $build($assetTypes);
        $liabilities = $build($liabilityTypes);
        $equity      = $build($equityTypes);

        return [
            'assets'           => $assets,
            'liabilities'      => $liabilities,
            'equity'           => $equity,
            'total_assets'     => $assets->sum('balance'),
            'total_liabilities'=> $liabilities->sum('balance'),
            'total_equity'     => $equity->sum('balance'),
            'to'               => $to,
        ];
    }

    private function receivablesData(Request $request): array
    {
        $customers = Customer::active()->get()->map(fn($c) => [
            'customer' => $c,
            'balance'  => $c->balance,
        ])->filter(fn($r) => $r['balance'] > 0)->sortByDesc('balance')->values();

        return ['customers' => $customers, 'total' => $customers->sum('balance')];
    }

    private function payablesData(Request $request): array
    {
        $vendors = Vendor::active()->get()->map(fn($v) => [
            'vendor'  => $v,
            'balance' => $v->balance,
        ])->filter(fn($r) => $r['balance'] < 0)->sortBy('balance')->values();

        return ['vendors' => $vendors, 'total' => $vendors->sum(fn($r) => abs($r['balance']))];
    }

    private function partyLedgerData(Request $request): array
    {
        $partyType = $request->get('party_type');
        $partyId   = $request->get('party_id');
        $from = $request->get('from_date');
        $to   = $request->get('to_date');

        if (!$partyType || !$partyId) {
            return ['entries' => collect(), 'party' => null];
        }

        $party = $partyType === 'customer' ? Customer::find($partyId) : Vendor::find($partyId);

        $entries = VoucherEntry::with('voucher', 'account')
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->when($from, fn($q2) => $q2->where('voucher_date', '>=', $from))
                  ->when($to, fn($q2) => $q2->where('voucher_date', '<=', $to));
            })
            ->get()
            ->sortBy(fn($e) => $e->voucher->voucher_date ?? null);

        $running = (float) ($party->opening_balance_signed ?? 0);
        $entries = $entries->map(function ($e) use (&$running) {
            $running += (float) $e->debit - (float) $e->credit;
            $e->running_balance = $running;
            return $e;
        });

        return ['entries' => $entries, 'party' => $party, 'party_type' => $partyType];
    }

    private function cashBankData(Request $request): array
    {
        $from = $request->get('from_date');
        $to   = $request->get('to_date', now()->toDateString());

        $accounts = ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->get()->map(function ($acc) use ($from, $to) {
            $entries = VoucherEntry::with('voucher')
                ->where('account_id', $acc->id)
                ->whereHas('voucher', function ($q) use ($from, $to) {
                    $q->when($from, fn($q2) => $q2->where('voucher_date', '>=', $from))
                      ->when($to, fn($q2) => $q2->where('voucher_date', '<=', $to));
                })
                ->get()
                ->sortBy(fn($e) => $e->voucher->voucher_date ?? null);

            $balance = (float) $acc->opening_balance;
            $entries = $entries->map(function ($e) use (&$balance) {
                $balance += (float) $e->debit - (float) $e->credit;
                $e->running_balance = $balance;
                return $e;
            });

            return ['account' => $acc, 'entries' => $entries, 'closing' => $balance];
        });

        return ['accounts' => $accounts];
    }

    private function periodNet(int $accountId, ?string $from, ?string $to, string $normalSide): float
    {
        $sum = VoucherEntry::where('account_id', $accountId)
            ->whereHas('voucher', function ($q) use ($from, $to) {
                $q->when($from, fn($q2) => $q2->where('voucher_date', '>=', $from))
                  ->when($to, fn($q2) => $q2->where('voucher_date', '<=', $to));
            })
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        return $normalSide === 'debit'
            ? (float) $sum->d - (float) $sum->c
            : (float) $sum->c - (float) $sum->d;
    }
}