<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\{Permission, Role};

/**
 * Sale orders for yarn, greige and other stock (finished SKUs go through Customer Orders).
 * Deliveries go out on the existing gate pass: a stock movement of type warehouse_to_customer.
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sale_orders')) {
            Schema::create('sale_orders', function (Blueprint $t) {
                $t->id();
                $t->string('so_no', 50)->unique();
                $t->unsignedBigInteger('customer_id');
                $t->unsignedBigInteger('location_id');          // dispatch from
                $t->unsignedBigInteger('broker_id')->nullable();
                $t->decimal('broker_commission_amount', 15, 2)->default(0);
                $t->string('customer_po_no', 100)->nullable();
                $t->date('order_date');
                $t->date('delivery_date')->nullable();
                $t->string('payment_term_type', 20)->default('cash');
                $t->unsignedInteger('payment_term_days')->nullable();
                $t->boolean('gst_applicable')->default(false);
                $t->unsignedBigInteger('tax_id')->nullable();
                $t->decimal('gst_rate', 6, 2)->default(0);
                $t->decimal('subtotal', 15, 2)->default(0);
                $t->decimal('gst_amount', 15, 2)->default(0);
                $t->decimal('total_amount', 15, 2)->default(0);
                $t->string('status', 30)->default('Pending');
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->text('rejection_reason')->nullable();
                $t->text('remarks')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['status', 'order_date']);
            });
        }
        if (!Schema::hasTable('sale_order_items')) {
            Schema::create('sale_order_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sale_order_id');
                $t->unsignedBigInteger('product_id');
                $t->string('lot_no', 100)->nullable();       // a specific lot, or any (oldest first)
                $t->string('description', 255)->nullable();
                $t->decimal('quantity', 15, 3);
                $t->decimal('rate', 15, 4);
                $t->decimal('amount', 15, 2);
                $t->decimal('delivered_qty', 15, 3)->default(0);
                $t->timestamps();
                $t->foreign('sale_order_id')->references('id')->on('sale_orders')->cascadeOnDelete();
            });
        }

        Schema::table('stock_movements', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_movements', 'sale_order_id')) $t->unsignedBigInteger('sale_order_id')->nullable()->index();
            if (!Schema::hasColumn('stock_movements', 'customer_id'))   $t->unsignedBigInteger('customer_id')->nullable();
            if (!Schema::hasColumn('stock_movements', 'cogs_amount'))   $t->decimal('cogs_amount', 15, 2)->default(0);
        });
        Schema::table('stock_movements', fn(Blueprint $t) => $t->unsignedBigInteger('to_location_id')->nullable()->change());
        Schema::table('stock_movement_items', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_movement_items', 'sale_order_item_id')) $t->unsignedBigInteger('sale_order_item_id')->nullable();
        });

        if (Schema::hasTable('permissions')) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $perms = collect(['index', 'create', 'edit', 'delete', 'print'])
                ->map(fn($a) => Permission::firstOrCreate(['name' => "sale_orders.{$a}", 'guard_name' => 'web']));
            if ($super = Role::where('name', 'superadmin')->first()) $super->givePermissionTo($perms);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::table('stock_movement_items', function (Blueprint $t) {
            if (Schema::hasColumn('stock_movement_items', 'sale_order_item_id')) $t->dropColumn('sale_order_item_id');
        });
        Schema::table('stock_movements', function (Blueprint $t) {
            foreach (['sale_order_id', 'customer_id', 'cogs_amount'] as $c) if (Schema::hasColumn('stock_movements', $c)) $t->dropColumn($c);
        });
        Schema::dropIfExists('sale_order_items');
        Schema::dropIfExists('sale_orders');
        Permission::where('name', 'like', 'sale_orders.%')->delete();
    }
};
