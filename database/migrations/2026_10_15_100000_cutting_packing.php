<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\{Permission, Role};

/**
 * Cutting & packing.
 *  - put_up_packagings:      packaging materials one piece of a put-up uses (the packaging list / BOM)
 *  - production_orders:      a cutting or packing entry at a warehouse (optionally for a customer order)
 *  - production_order_lines: fabric → cut pcs (+ leftover / rejection meters), or cut pcs → packed SKU
 *  - production_consumptions: packaging materials used by a packing line
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('put_up_packagings')) {
            Schema::create('put_up_packagings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('put_up_product_id');
                $t->unsignedBigInteger('material_product_id');
                $t->decimal('qty_per_piece', 12, 4);
                $t->timestamps();
                $t->unique(['put_up_product_id', 'material_product_id'], 'uq_putup_material');
            });
        }
        if (!Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $t) {
                $t->id();
                $t->string('order_no', 50)->unique();
                $t->string('type', 20); // cutting, packing
                $t->unsignedBigInteger('location_id');
                $t->unsignedBigInteger('job_id')->nullable();
                $t->date('order_date');
                $t->text('remarks')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['type', 'order_date']);
            });
        }
        if (!Schema::hasTable('production_order_lines')) {
            Schema::create('production_order_lines', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('production_order_id');
                $t->unsignedBigInteger('job_item_id')->nullable();
                $t->unsignedBigInteger('input_product_id');
                $t->string('lot_no', 100)->nullable();
                $t->string('grade', 1)->nullable();
                $t->decimal('input_qty', 15, 3);           // meters cut (incl. leftover + rejected) or cut pcs packed
                $t->unsignedBigInteger('put_up_product_id')->nullable();
                $t->unsignedBigInteger('output_product_id');
                $t->decimal('output_qty', 15, 3);          // pieces
                $t->decimal('leftover_qty', 15, 3)->default(0);
                $t->decimal('rejected_qty', 15, 3)->default(0);
                $t->decimal('input_amount', 15, 2)->default(0);
                $t->decimal('packaging_amount', 15, 2)->default(0);
                $t->decimal('output_amount', 15, 2)->default(0);
                $t->timestamps();
                $t->foreign('production_order_id')->references('id')->on('production_orders')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('production_consumptions')) {
            Schema::create('production_consumptions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('production_order_id');
                $t->unsignedBigInteger('production_order_line_id');
                $t->unsignedBigInteger('product_id');
                $t->decimal('quantity', 15, 4);
                $t->decimal('amount', 15, 2)->default(0);
                $t->timestamps();
                $t->foreign('production_order_id')->references('id')->on('production_orders')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('permissions')) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $perms = collect(['index', 'create', 'edit', 'delete', 'print'])
                ->map(fn($a) => Permission::firstOrCreate(['name' => "production_orders.{$a}", 'guard_name' => 'web']));
            if ($super = Role::where('name', 'superadmin')->first()) $super->givePermissionTo($perms);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_consumptions');
        Schema::dropIfExists('production_order_lines');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('put_up_packagings');
        Permission::where('name', 'like', 'production_orders.%')->delete();
    }
};
