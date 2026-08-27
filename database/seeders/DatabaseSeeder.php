<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\HeadOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\ChartOfAccounts;
use App\Models\MeasurementUnit;
use App\Models\AccountMapping;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now    = now();
        $userId = 1;

        // ─────────────────────────────────────────────────────────────────
        // USERS
        // ─────────────────────────────────────────────────────────────────

        $farhan = User::firstOrCreate(
            ['username' => 'farhan'],
            ['name' => 'Farhan', 'email' => null, 'password' => Hash::make('12345678')]
        );

        $yousuf = User::firstOrCreate(
            ['username' => 'yousuf'],
            ['name' => 'Yousuf', 'email' => null, 'password' => Hash::make('12345678')]
        );

        // ─────────────────────────────────────────────────────────────────
        // ROLES
        // ─────────────────────────────────────────────────────────────────

        $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $gatekeeperRole = Role::firstOrCreate(['name' => 'gatekeeper']);

        $farhan->assignRole($superAdminRole);
        $yousuf->assignRole($superAdminRole);

        // ─────────────────────────────────────────────────────────────────
        // MODULE PERMISSIONS — in build order, Modules 1–17
        // ─────────────────────────────────────────────────────────────────

        $modules = [
            // 1-2: Accounts
            'coa', 'shoa', 'tax_masters',

            // 3: Parties
            'customers', 'vendors',

            // 4: Locations
            'locations',

            // 5: Categories
            'product_categories', 'measurement_units',

            // 7: Products
            'products',

            // 8: Users/Roles
            'user_roles', 'users',

            // 9: Service Types
            'service_types',

            // 10: Customer SKU Rates
            'customer_sku_rates',

            // 11: Vouchers
            'vouchers',

            // 12: Forecasting
            'forecasts',

            // 13: Purchase Order
            'purchase_orders',

            // 14: Purchase Receiving
            'purchase_receivings',

            // 15: CPO
            'cpo',

            // 16: Yarn Issue
            'yarn_issues',

            // 17: Greige Receive
            'greige_receives',
        ];

        foreach ($modules as $module) {
            foreach (['index', 'create', 'edit', 'delete', 'print'] as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}"]);
            }
        }

        $superAdminRole->syncPermissions(Permission::all());

        $gatekeeperRole->syncPermissions(
            Permission::whereIn('name', [
                'locations.index',
                'purchase_receivings.index', 'purchase_receivings.create',
            ])->get()
        );

        // ─────────────────────────────────────────────────────────────────
        // HEADS OF ACCOUNTS
        // ─────────────────────────────────────────────────────────────────

        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses',    'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // SUB HEADS OF ACCOUNTS
        // ─────────────────────────────────────────────────────────────────

        SubHeadOfAccounts::insert([
            ['id' =>  1, 'hoa_id' => 1, 'name' => 'Cash & Cash Equivalents', 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  2, 'hoa_id' => 1, 'name' => 'Bank Accounts',           'created_at' => $now, 'updated_at' => $now],
            ['id' =>  3, 'hoa_id' => 1, 'name' => 'Accounts Receivable',     'created_at' => $now, 'updated_at' => $now],
            ['id' =>  4, 'hoa_id' => 1, 'name' => 'Inventory',               'created_at' => $now, 'updated_at' => $now],
            ['id' => 19, 'hoa_id' => 1, 'name' => 'Inventory in Transit',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 20, 'hoa_id' => 1, 'name' => 'Tax Receivables',         'created_at' => $now, 'updated_at' => $now],
            ['id' => 21, 'hoa_id' => 1, 'name' => 'Advances & Deposits',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 22, 'hoa_id' => 1, 'name' => 'Fixed Assets',             'created_at' => $now, 'updated_at' => $now],
            ['id' => 23, 'hoa_id' => 1, 'name' => 'Other Current Assets',    'created_at' => $now, 'updated_at' => $now],

            ['id' =>  5, 'hoa_id' => 2, 'name' => 'Accounts Payable',        'created_at' => $now, 'updated_at' => $now],
            ['id' =>  6, 'hoa_id' => 2, 'name' => 'Loans & Borrowings',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 24, 'hoa_id' => 2, 'name' => 'Taxes Payable',            'created_at' => $now, 'updated_at' => $now],
            ['id' => 25, 'hoa_id' => 2, 'name' => 'Customer Advances',        'created_at' => $now, 'updated_at' => $now],
            ['id' => 26, 'hoa_id' => 2, 'name' => 'Accrued Liabilities',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 27, 'hoa_id' => 2, 'name' => 'Other Current Liabilities','created_at' => $now, 'updated_at' => $now],

            ['id' =>  7, 'hoa_id' => 3, 'name' => 'Owner / Share Capital',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 28, 'hoa_id' => 3, 'name' => 'Retained Earnings',        'created_at' => $now, 'updated_at' => $now],
            ['id' => 29, 'hoa_id' => 3, 'name' => 'Drawings',                 'created_at' => $now, 'updated_at' => $now],
            ['id' => 30, 'hoa_id' => 3, 'name' => 'Reserves',                 'created_at' => $now, 'updated_at' => $now],

            ['id' =>  8, 'hoa_id' => 4, 'name' => 'Sales',                    'created_at' => $now, 'updated_at' => $now],
            ['id' =>  9, 'hoa_id' => 4, 'name' => 'Other Income',             'created_at' => $now, 'updated_at' => $now],
            ['id' => 31, 'hoa_id' => 4, 'name' => 'Service Revenue',          'created_at' => $now, 'updated_at' => $now],

            ['id' => 10, 'hoa_id' => 5, 'name' => 'Cost of Goods Sold',       'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'hoa_id' => 5, 'name' => 'Salaries & Wages',         'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'hoa_id' => 5, 'name' => 'Rent',                     'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'hoa_id' => 5, 'name' => 'Utilities',                'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'hoa_id' => 5, 'name' => 'Administrative Expenses',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 15, 'hoa_id' => 5, 'name' => 'Freight & Logistics',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 16, 'hoa_id' => 5, 'name' => 'Service Costs',             'created_at' => $now, 'updated_at' => $now],
            ['id' => 17, 'hoa_id' => 5, 'name' => 'Sampling Expenses',         'created_at' => $now, 'updated_at' => $now],
            ['id' => 18, 'hoa_id' => 5, 'name' => 'Packaging Expenses',        'created_at' => $now, 'updated_at' => $now],
            ['id' => 32, 'hoa_id' => 5, 'name' => 'Selling & Marketing',       'created_at' => $now, 'updated_at' => $now],
            ['id' => 33, 'hoa_id' => 5, 'name' => 'Finance Costs',             'created_at' => $now, 'updated_at' => $now],
            ['id' => 34, 'hoa_id' => 5, 'name' => 'Depreciation',              'created_at' => $now, 'updated_at' => $now],
            ['id' => 35, 'hoa_id' => 5, 'name' => 'Other Expenses',             'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // CHART OF ACCOUNTS
        // ─────────────────────────────────────────────────────────────────

        $coaBase = ['opening_balance' => 0, 'opening_date' => $now->toDateString(), 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now];

        $coaRows = [
            [1, '101001', 1, 'Cash in Hand',                  'cash'],
            [2, '102001', 2, 'Main Bank Account',              'bank'],
            [3, '103001', 3, 'Accounts Receivable — Control',  'receivable'],
            [4, '104001', 4, 'Stock in Hand — Yarn',            'inventory'],
            [5, '104002', 4, 'Stock in Hand — Greige Fabric',   'inventory'],
            [6, '104003', 4, 'Stock in Hand — Packaging',       'inventory'],
            [7, '105001', 4, 'Yarn in Process — Weaving Mills', 'yarn_in_process'],
            [8, '201001', 5, 'Accounts Payable — Control',     'payable'],
            [9, '401001', 6, 'Sales Revenue',                   'revenue'],
            [10, '501001', 7, 'Cost of Goods Sold',              'cogs'],
            [11, '502001', 8, 'Weaving Service Cost',            'service_cost'],
            [12, '106001', 4, 'Purchase Tax / Input Tax Receivable', 'tax_receivable'],
        ];

        foreach ($coaRows as [$id, $code, $shoa, $name, $type]) {
            ChartOfAccounts::firstOrCreate(['id' => $id], array_merge($coaBase, [
                'account_code' => $code, 'shoa_id' => $shoa, 'name' => $name, 'account_type' => $type,
            ]));
        }

        // ─────────────────────────────────────────────────────────────────
        // ACCOUNT MAPPINGS
        // ─────────────────────────────────────────────────────────────────

        $mappings = [
            'cash' => 1, 'bank' => 2, 'accounts_receivable' => 3,
            'stock_in_hand' => 4, 'yarn_in_process' => 7, 'accounts_payable' => 8,
            'sales_revenue' => 9, 'cogs' => 10, 'weaving_charges' => 11,
            'purchase_tax' => 12,
        ];

        foreach ($mappings as $key => $accountId) {
            AccountMapping::updateOrCreate(['role_key' => $key], ['account_id' => $accountId]);
        }

        // ─────────────────────────────────────────────────────────────────
        // TAX MASTER
        // ─────────────────────────────────────────────────────────────────

        DB::table('tax_masters')->insertOrIgnore([
            ['id' => 1, 'name' => 'GST 18%', 'rate' => 18.00, 'is_default' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'GST 15%', 'rate' => 15.00, 'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'No Tax',  'rate' => 0.00,  'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // MEASUREMENT UNITS
        // ─────────────────────────────────────────────────────────────────

        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Kilogram', 'shortcode' => 'kg',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Meter',    'shortcode' => 'm',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Pieces',   'shortcode' => 'pcs',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Bag',      'shortcode' => 'bag',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Bundle',   'shortcode' => 'bundle', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Pounds',   'shortcode' => 'lbs',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Yard',     'shortcode' => 'yd',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'Roll',     'shortcode' => 'roll',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'name' => 'Box',      'shortcode' => 'box',    'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // PRODUCT CATEGORIES
        // ─────────────────────────────────────────────────────────────────

        DB::table('product_categories')->insertOrIgnore([
            ['id' => 1, 'name' => 'Yarn',                 'code' => 'yarn',       'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Greige Fabric',        'code' => 'greige',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'SKU',                  'code' => 'sku',        'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Packaging Materials',  'code' => 'packaging',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Cut Pcs',              'code' => 'cut_pcs',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Leftover',             'code' => 'leftover',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Rejection',            'code' => 'rejection',  'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('product_categories')->where('id', 1)->update(['stock_account_id' => 4, 'cogs_account_id' => 10]);
        DB::table('product_categories')->where('id', 2)->update(['stock_account_id' => 5, 'cogs_account_id' => 10]);
        DB::table('product_categories')->where('id', 3)->update(['stock_account_id' => 6, 'cogs_account_id' => 10]);
        DB::table('product_categories')->where('id', 4)->update(['stock_account_id' => 6, 'cogs_account_id' => 10]);

        // ─────────────────────────────────────────────────────────────────
        // SERVICE TYPES
        // ─────────────────────────────────────────────────────────────────

        DB::table('service_types')->insertOrIgnore([
            ['id' => 1, 'name' => 'Weaving',               'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Processing / Printing', 'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Dyeing',                'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Finishing',             'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Packaging',             'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Other',                 'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}