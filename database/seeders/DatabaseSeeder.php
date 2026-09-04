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

        $this->seedUsersAndRoles();
        $this->seedPermissions();
        $this->seedAccounting($now, $userId);
        $this->seedTaxMaster($now);
        $this->seedMeasurementUnits($now);
        $this->seedProductCategories($now);
        $this->seedServiceTypes($now);
        $this->seedTermsAndConditions($now);
    }

    // ─────────────────────────────────────────────────────────────────
    // USERS & ROLES
    // ─────────────────────────────────────────────────────────────────
    private function seedUsersAndRoles(): void
    {
        $farhan = User::firstOrCreate(
            ['username' => 'farhan'],
            ['name' => 'Farhan', 'email' => null, 'password' => Hash::make('12345678')]
        );

        $yousuf = User::firstOrCreate(
            ['username' => 'yousuf'],
            ['name' => 'Yousuf', 'email' => null, 'password' => Hash::make('12345678')]
        );

        $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']);
        Role::firstOrCreate(['name' => 'gatekeeper']);

        $farhan->assignRole($superAdminRole);
        $yousuf->assignRole($superAdminRole);
    }

    // ─────────────────────────────────────────────────────────────────
    // MODULE PERMISSIONS — Master Setup, then Operational, in build order
    // ─────────────────────────────────────────────────────────────────
    private function seedPermissions(): void
    {
        $modules = [
            // ── Master Setup ──
            'coa', 'shoa', 'tax_masters', 'account_mappings',
            'customers', 'vendors', 'brokers',
            'locations',
            'product_categories', 'measurement_units', 'products',
            'user_roles', 'users',
            'service_types',
            'customer_sku_rates',
            'terms_and_conditions',

            // ── Operational ──
            'forecasts',
            'jobs',
            'purchase_orders', 'purchase_order_amendments',
            'challans',
            'purchase_receivings', 'purchase_returns',
            'yarn_issues',
            'stock_movements',
            'processing_issues',
            'vouchers',
            'pdcs',
        ];

        foreach ($modules as $module) {
            foreach (['index', 'create', 'edit', 'delete', 'print'] as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}"]);
            }
        }

        $superAdminRole = Role::findByName('superadmin');
        $superAdminRole->syncPermissions(Permission::all());

        $gatekeeperRole = Role::findByName('gatekeeper');
        $gatekeeperRole->syncPermissions(Permission::whereIn('name', [
            'locations.index',
            'purchase_receivings.index', 'purchase_receivings.create',
            'challans.index', 'challans.create',
        ])->get());
    }

    // ─────────────────────────────────────────────────────────────────
    // CHART OF ACCOUNTS — Heads, Sub Heads, Accounts, Mappings
    // ─────────────────────────────────────────────────────────────────
    private function seedAccounting($now, int $userId): void
    {
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses',    'created_at' => $now, 'updated_at' => $now],
        ]);

        SubHeadOfAccounts::insert([
            ['id' =>  1, 'hoa_id' => 1, 'name' => 'Cash & Cash Equivalents',   'created_at' => $now, 'updated_at' => $now],
            ['id' =>  2, 'hoa_id' => 1, 'name' => 'Bank Accounts',             'created_at' => $now, 'updated_at' => $now],
            ['id' =>  3, 'hoa_id' => 1, 'name' => 'Accounts Receivable',       'created_at' => $now, 'updated_at' => $now],
            ['id' =>  4, 'hoa_id' => 1, 'name' => 'Inventory',                 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  5, 'hoa_id' => 2, 'name' => 'Accounts Payable',          'created_at' => $now, 'updated_at' => $now],
            ['id' =>  6, 'hoa_id' => 2, 'name' => 'Loans & Borrowings',        'created_at' => $now, 'updated_at' => $now],
            ['id' =>  7, 'hoa_id' => 5, 'name' => 'Cost of Goods Sold',        'created_at' => $now, 'updated_at' => $now],
            ['id' =>  8, 'hoa_id' => 5, 'name' => 'Service Costs',             'created_at' => $now, 'updated_at' => $now],
            ['id' =>  9, 'hoa_id' => 4, 'name' => 'Sales',                     'created_at' => $now, 'updated_at' => $now],
            ['id' => 19, 'hoa_id' => 1, 'name' => 'Inventory in Transit',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 20, 'hoa_id' => 1, 'name' => 'Tax Receivables',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 21, 'hoa_id' => 1, 'name' => 'Advances & Deposits',       'created_at' => $now, 'updated_at' => $now],
            ['id' => 22, 'hoa_id' => 1, 'name' => 'Fixed Assets',              'created_at' => $now, 'updated_at' => $now],
            ['id' => 23, 'hoa_id' => 2, 'name' => 'Suspense / Clearing',       'created_at' => $now, 'updated_at' => $now],
            ['id' => 25, 'hoa_id' => 1, 'name' => 'Accumulated Depreciation',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 30, 'hoa_id' => 3, 'name' => 'Owner Investment / Equity', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $coaBase = [
            'opening_balance' => 0, 'opening_date' => $now->toDateString(),
            'created_by' => $userId, 'updated_by' => $userId,
            'created_at' => $now, 'updated_at' => $now,
        ];

        $coaRows = [
            [1,  '101001', 1, 'Cash in Hand',                        'cash'],
            [2,  '102001', 2, 'Main Bank Account',                   'bank'],
            [3,  '103001', 3, 'Accounts Receivable — Control',       'receivable'],
            [4,  '104001', 4, 'Stock in Hand — Yarn',                'inventory'],
            [5,  '104002', 4, 'Stock in Hand — Greige Fabric',       'inventory'],
            [6,  '104003', 4, 'Stock in Hand — Processed Fabric',    'inventory'],
            [7,  '105001', 4, 'Yarn in Process — Weaving Mills',     'yarn_in_process'],
            [8,  '201001', 5, 'Accounts Payable — Control',          'payable'],
            [9,  '401001', 6, 'Sales Revenue',                        'revenue'],
            [10, '501001', 7, 'Cost of Goods Sold',                    'cogs'],
            [11, '502001', 8, 'Weaving / Processing Service Cost',     'service_cost'],
            [12, '106001', 4, 'Purchase Tax / Input Tax Receivable',   'tax_receivable'],
            [13, '104004', 4, 'Stock in Hand — SKU / Finished Goods',  'inventory'],
            [14, '104005', 4, 'Stock in Hand — Packaging Materials',  'inventory'],
            [15, '104006', 4, 'Stock in Hand — Cut Pcs (WIP)',         'inventory'],
            [16, '104007', 4, 'Stock in Hand — Leftover',              'inventory'],
            [17, '104008', 4, 'Stock in Hand — Rejection',             'inventory'],
        ];

        foreach ($coaRows as [$id, $code, $shoa, $name, $type]) {
            ChartOfAccounts::firstOrCreate(['id' => $id], array_merge($coaBase, [
                'account_code' => $code, 'shoa_id' => $shoa, 'name' => $name, 'account_type' => $type,
            ]));
        }

        $mappings = [
            'cash' => 1, 'bank' => 2, 'accounts_receivable' => 3,
            'stock_in_hand' => 4, 'yarn_in_process' => 7, 'accounts_payable' => 8,
            'sales_revenue' => 9, 'cogs' => 10, 'weaving_charges' => 11,
            'purchase_tax' => 12,
        ];

        foreach ($mappings as $key => $accountId) {
            AccountMapping::updateOrCreate(['role_key' => $key], ['account_id' => $accountId]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // TAX MASTER
    // ─────────────────────────────────────────────────────────────────
    private function seedTaxMaster($now): void
    {
        DB::table('tax_masters')->insertOrIgnore([
            ['id' => 1, 'name' => 'GST 18%', 'rate' => 18.00, 'is_default' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'GST 15%', 'rate' => 15.00, 'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'No Tax',  'rate' => 0.00,  'is_default' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // MEASUREMENT UNITS
    // ─────────────────────────────────────────────────────────────────
    private function seedMeasurementUnits($now): void
    {
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
    }

    // ─────────────────────────────────────────────────────────────────
    // PRODUCT CATEGORIES — each `code` unique, each mapped to a
    // stock/COGS account
    // ─────────────────────────────────────────────────────────────────
    private function seedProductCategories($now): void
    {
        DB::table('product_categories')->insertOrIgnore([
            ['id' => 1, 'name' => 'Yarn',                'code' => 'yarn',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Greige Fabric',       'code' => 'greige',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Processed Fabric',    'code' => 'processed', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'SKU',                 'code' => 'sku',       'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Packaging Materials', 'code' => 'packaging', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Cut Pcs',             'code' => 'cut_pcs',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Leftover',            'code' => 'leftover',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'Rejection',           'code' => 'rejection', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $categoryAccounts = [
            1 => 4,  // Yarn                 -> Stock in Hand — Yarn
            2 => 5,  // Greige Fabric        -> Stock in Hand — Greige
            3 => 6,  // Processed Fabric     -> Stock in Hand — Processed
            4 => 13, // SKU                  -> Stock in Hand — SKU/FG
            5 => 14, // Packaging Materials  -> Stock in Hand — Packaging
            6 => 15, // Cut Pcs              -> Stock in Hand — Cut Pcs (WIP)
            7 => 16, // Leftover             -> Stock in Hand — Leftover
            8 => 17, // Rejection            -> Stock in Hand — Rejection
        ];

        foreach ($categoryAccounts as $categoryId => $stockAccountId) {
            DB::table('product_categories')->where('id', $categoryId)
                ->update(['stock_account_id' => $stockAccountId, 'cogs_account_id' => 10]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // SERVICE TYPES
    // ─────────────────────────────────────────────────────────────────
    private function seedServiceTypes($now): void
    {
        DB::table('service_types')->insertOrIgnore([
            ['id' => 1, 'name' => 'Weaving',               'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Processing / Printing', 'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Dyeing',                'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Finishing',             'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Packaging',             'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Other',                 'service_cost_account_id' => 11, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // TERMS & CONDITIONS
    // ─────────────────────────────────────────────────────────────────
    private function seedTermsAndConditions($now): void
    {
        DB::table('terms_and_conditions')->insertOrIgnore([
            ['id' => 1, 'title' => 'Payment Terms',     'description' => 'Payment shall be made strictly as per the agreed payment terms mentioned on this PO.', 'applies_to' => 'all', 'is_default_checked' => 1, 'sort_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'title' => 'Quality Inspection','description' => 'Goods received are subject to quality inspection and may be rejected if not conforming to agreed specifications.', 'applies_to' => 'all', 'is_default_checked' => 1, 'sort_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'title' => 'Delivery Timeline', 'description' => 'Goods must be delivered within the expected date mentioned on this PO, unless otherwise agreed in writing.', 'applies_to' => 'all', 'is_default_checked' => 0, 'sort_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}