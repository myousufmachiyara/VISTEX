<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Spatie\Permission\Models\{Permission, Role};

/**
 * Customer order dispatch, customer invoices and receipts.
 *  - stock_movements.job_id / items.job_item_id: a delivery gate pass against a Customer Order
 *  - stock_movement_items.invoiced_qty: how much of a delivered line is invoiced
 *  - customer_invoices (+items), customer_receipts (+allocations)
 *  - accounts: Sales Tax Payable, Exchange Gain / Loss (and their mappings)
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_movements', 'job_id')) $t->unsignedBigInteger('job_id')->nullable()->index();
        });
        Schema::table('stock_movement_items', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_movement_items', 'job_item_id'))  $t->unsignedBigInteger('job_item_id')->nullable();
            if (!Schema::hasColumn('stock_movement_items', 'invoiced_qty')) $t->decimal('invoiced_qty', 15, 3)->default(0);
        });

        if (!Schema::hasTable('customer_invoices')) {
            Schema::create('customer_invoices', function (Blueprint $t) {
                $t->id();
                $t->string('invoice_no', 50)->unique();
                $t->unsignedBigInteger('customer_id');
                $t->date('invoice_date');
                $t->date('due_date')->nullable();
                $t->string('currency', 3)->default('PKR');
                $t->decimal('exchange_rate', 15, 4)->default(1);
                $t->decimal('subtotal', 15, 2)->default(0);       // invoice currency
                $t->decimal('tax_amount', 15, 2)->default(0);
                $t->decimal('total', 15, 2)->default(0);
                $t->decimal('total_pkr', 15, 2)->default(0);
                $t->decimal('paid_amount', 15, 2)->default(0);    // invoice currency
                $t->string('status', 20)->default('Unpaid');      // Unpaid, PartiallyPaid, Paid, Cancelled
                $t->text('remarks')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['customer_id', 'status']);
            });
        }
        if (!Schema::hasTable('customer_invoice_items')) {
            Schema::create('customer_invoice_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_invoice_id');
                $t->unsignedBigInteger('stock_movement_item_id');
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('sale_order_item_id')->nullable();
                $t->unsignedBigInteger('job_item_id')->nullable();
                $t->string('description', 255)->nullable();
                $t->decimal('delivered_qty', 15, 3);                // stock unit (kg, m, pcs)
                $t->decimal('quantity', 15, 3);                     // billed unit (meters for customer orders)
                $t->string('unit', 20)->nullable();
                $t->decimal('rate', 15, 4);
                $t->decimal('discount_pct', 6, 2)->default(0);
                $t->decimal('amount', 15, 2);
                $t->decimal('tax_rate', 6, 2)->default(0);
                $t->decimal('tax_amount', 15, 2)->default(0);
                $t->timestamps();
                $t->foreign('customer_invoice_id')->references('id')->on('customer_invoices')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('customer_receipts')) {
            Schema::create('customer_receipts', function (Blueprint $t) {
                $t->id();
                $t->string('receipt_no', 50)->unique();
                $t->unsignedBigInteger('customer_id');
                $t->date('receipt_date');
                $t->unsignedBigInteger('account_id');                // cash / bank account received into
                $t->string('method', 20)->default('bank');           // cash, bank, cheque
                $t->string('reference', 100)->nullable();            // cheque # / transfer ref
                $t->string('currency', 3)->default('PKR');
                $t->decimal('exchange_rate', 15, 4)->default(1);
                $t->decimal('amount', 15, 2);                        // receipt currency
                $t->decimal('amount_pkr', 15, 2);
                $t->decimal('unallocated', 15, 2)->default(0);       // receipt currency, on account
                $t->decimal('exchange_difference', 15, 2)->default(0); // PKR, + gain / − loss
                $t->text('remarks')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('customer_receipt_allocations')) {
            Schema::create('customer_receipt_allocations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_receipt_id');
                $t->unsignedBigInteger('customer_invoice_id');
                $t->decimal('amount', 15, 2);                        // invoice currency
                $t->timestamps();
                $t->foreign('customer_receipt_id')->references('id')->on('customer_receipts')->cascadeOnDelete();
            });
        }

        // Accounts for sales tax collected and exchange differences (fresh installs: DatabaseSeeder)
        if (Schema::hasTable('head_of_accounts')) \App\Support\SalesAccounts::ensure();

        if (Schema::hasTable('permissions')) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $super = Role::where('name', 'superadmin')->first();
            foreach (['customer_invoices', 'customer_receipts'] as $module) {
                foreach (['index', 'create', 'edit', 'delete', 'print'] as $a) {
                    $p = Permission::firstOrCreate(['name' => "{$module}.{$a}", 'guard_name' => 'web']);
                    $super?->givePermissionTo($p);
                }
            }
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_receipt_allocations');
        Schema::dropIfExists('customer_receipts');
        Schema::dropIfExists('customer_invoice_items');
        Schema::dropIfExists('customer_invoices');
        Schema::table('stock_movement_items', function (Blueprint $t) {
            foreach (['job_item_id', 'invoiced_qty'] as $c) if (Schema::hasColumn('stock_movement_items', $c)) $t->dropColumn($c);
        });
        Schema::table('stock_movements', function (Blueprint $t) {
            if (Schema::hasColumn('stock_movements', 'job_id')) $t->dropColumn('job_id');
        });
        Permission::where('name', 'like', 'customer_invoices.%')->orWhere('name', 'like', 'customer_receipts.%')->delete();
    }
};
