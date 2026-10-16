<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Accounts used by customer invoicing: Sales Tax Payable, Exchange Gain / Loss, Cheques in Hand, Sales Returns, Brokerage. Safe to run any number of times. */
class SalesAccounts
{
    public static function ensure(): void
    {
        if (!DB::table('head_of_accounts')->whereIn('id', [2, 4])->count()) return; // chart of accounts not seeded yet
        $now = now();
        $shoa = fn(int $hoa, string $name) => DB::table('sub_head_of_accounts')->where('name', $name)->value('id')
            ?? DB::table('sub_head_of_accounts')->insertGetId(['hoa_id' => $hoa, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        $account = fn(string $code, int $shoaId, string $name, string $type) => DB::table('chart_of_accounts')->where('account_code', $code)->value('id')
            ?? DB::table('chart_of_accounts')->insertGetId(['account_code' => $code, 'shoa_id' => $shoaId, 'name' => $name, 'account_type' => $type,
                'opening_balance' => 0, 'opening_date' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now]);
        $map = function (string $key, int $id) use ($now) {
            if (!DB::table('account_mappings')->where('role_key', $key)->exists()) {
                DB::table('account_mappings')->insert(['role_key' => $key, 'account_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
            }
        };
        $map('sales_tax_payable', $account('202001', $shoa(2, 'Taxes Payable'), 'Sales Tax Payable (Output Tax)', 'tax_payable'));
        $map('exchange_gain_loss', $account('402001', $shoa(4, 'Other Income'), 'Exchange Gain / Loss', 'other_income'));
        $map('cheques_in_hand', $account('107001', $shoa(1, 'Cash & Cash Equivalents'), 'Cheques in Hand (Customer PDC)', 'cheques_in_hand'));
        $map('sales_returns', $account('401002', $shoa(4, 'Sales'), 'Sales Returns', 'revenue_contra'));
        $map('brokerage_expense', $account('503001', $shoa(5, 'Selling Expenses'), 'Brokerage / Commission', 'expenses'));
    }
}
