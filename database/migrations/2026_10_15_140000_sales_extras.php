<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\{Permission, Role};

/**
 * Sales extras:
 *  - receipts by post-dated cheque (held in Cheques in Hand until cleared; bounce reverses)
 *  - applying money on account / credit notes to invoices later (with exchange difference)
 *  - customer returns + credit notes
 *  - brokerage accrued on invoicing sale orders
 *  - dispatch tolerance % per customer order
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_receipts', function (Blueprint $t) {
            if (!Schema::hasColumn('customer_receipts', 'status'))        $t->string('status', 20)->default('Cleared');   // Cleared, Pending (PDC), Bounced
            if (!Schema::hasColumn('customer_receipts', 'cheque_date'))   $t->date('cheque_date')->nullable();
            if (!Schema::hasColumn('customer_receipts', 'cleared_at'))    $t->date('cleared_at')->nullable();
            if (!Schema::hasColumn('customer_receipts', 'bounce_reason')) $t->string('bounce_reason', 255)->nullable();
        });
        Schema::table('customer_receipt_allocations', function (Blueprint $t) {
            if (!Schema::hasColumn('customer_receipt_allocations', 'exchange_difference')) $t->decimal('exchange_difference', 15, 2)->default(0);
            if (!Schema::hasColumn('customer_receipt_allocations', 'applied_later'))       $t->boolean('applied_later')->default(false);
        });
        Schema::table('stock_movement_items', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_movement_items', 'returned_qty')) $t->decimal('returned_qty', 15, 3)->default(0);
        });
        Schema::table('customer_invoice_items', function (Blueprint $t) {
            if (!Schema::hasColumn('customer_invoice_items', 'credited_qty')) $t->decimal('credited_qty', 15, 3)->default(0); // delivered units
        });
        Schema::table('customer_invoices', function (Blueprint $t) {
            if (!Schema::hasColumn('customer_invoices', 'brokerage_amount')) $t->decimal('brokerage_amount', 15, 2)->default(0);
            if (!Schema::hasColumn('customer_invoices', 'credited_amount'))  $t->decimal('credited_amount', 15, 2)->default(0);
        });
        Schema::table('job_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('job_orders', 'dispatch_tolerance_pct')) $t->decimal('dispatch_tolerance_pct', 5, 2)->default(0);
        });

        if (!Schema::hasTable('customer_returns')) {
            Schema::create('customer_returns', function (Blueprint $t) {
                $t->id();
                $t->string('return_no', 50)->unique();
                $t->unsignedBigInteger('customer_id');
                $t->unsignedBigInteger('location_id');
                $t->date('return_date');
                $t->string('reason', 255)->nullable();
                $t->text('remarks')->nullable();
                $t->decimal('cost_amount', 15, 2)->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('customer_return_items')) {
            Schema::create('customer_return_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_return_id');
                $t->unsignedBigInteger('stock_movement_item_id');
                $t->unsignedBigInteger('product_id');
                $t->string('lot_no', 100)->nullable();
                $t->string('grade', 1)->nullable();
                $t->decimal('quantity', 15, 3);
                $t->string('condition', 20)->default('fresh');  // fresh = back in stock, rejected = held as rejected
                $t->decimal('cost_amount', 15, 2)->default(0);
                $t->decimal('uninvoiced_qty', 15, 3)->default(0); // part that was never invoiced
                $t->decimal('credited_qty', 15, 3)->default(0);   // part credited on a credit note
                $t->timestamps();
                $t->foreign('customer_return_id')->references('id')->on('customer_returns')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('customer_credit_notes')) {
            Schema::create('customer_credit_notes', function (Blueprint $t) {
                $t->id();
                $t->string('credit_no', 50)->unique();
                $t->unsignedBigInteger('customer_id');
                $t->unsignedBigInteger('customer_invoice_id')->nullable();
                $t->unsignedBigInteger('customer_return_id')->nullable();
                $t->date('credit_date');
                $t->string('currency', 3)->default('PKR');
                $t->decimal('exchange_rate', 15, 4)->default(1);
                $t->decimal('subtotal', 15, 2)->default(0);
                $t->decimal('tax_amount', 15, 2)->default(0);
                $t->decimal('total', 15, 2)->default(0);
                $t->decimal('total_pkr', 15, 2)->default(0);
                $t->decimal('unapplied', 15, 2)->default(0);     // on account
                $t->string('reason', 255)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('customer_credit_applications')) {
            Schema::create('customer_credit_applications', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_credit_note_id');
                $t->unsignedBigInteger('customer_invoice_id');
                $t->decimal('amount', 15, 2);
                $t->decimal('exchange_difference', 15, 2)->default(0);
                $t->timestamps();
                $t->foreign('customer_credit_note_id')->references('id')->on('customer_credit_notes')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('head_of_accounts')) \App\Support\SalesAccounts::ensure();

        if (Schema::hasTable('permissions')) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $super = Role::where('name', 'superadmin')->first();
            foreach (['index', 'create', 'edit', 'delete', 'print'] as $a) {
                $super?->givePermissionTo(Permission::firstOrCreate(['name' => "customer_returns.{$a}", 'guard_name' => 'web']));
            }
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_applications');
        Schema::dropIfExists('customer_credit_notes');
        Schema::dropIfExists('customer_return_items');
        Schema::dropIfExists('customer_returns');
        $drop = function (string $table, array $cols) {
            Schema::table($table, function (Blueprint $t) use ($table, $cols) {
                foreach ($cols as $c) if (Schema::hasColumn($table, $c)) $t->dropColumn($c);
            });
        };
        $drop('customer_receipts', ['status', 'cheque_date', 'cleared_at', 'bounce_reason']);
        $drop('customer_receipt_allocations', ['exchange_difference', 'applied_later']);
        $drop('stock_movement_items', ['returned_qty']);
        $drop('customer_invoice_items', ['credited_qty']);
        $drop('customer_invoices', ['brokerage_amount', 'credited_amount']);
        $drop('job_orders', ['dispatch_tolerance_pct']);
        Permission::where('name', 'like', 'customer_returns.%')->delete();
    }
};
