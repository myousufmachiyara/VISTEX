<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\{Permission, Role};

/**
 * Stock Movement → Gate Pass → Challan OUT → Receive (mill lot #).
 *   Pending → Approved (gate pass) → Dispatched (gate verified exit; stock in transit) → Received
 * Old rows: 'PendingApproval' becomes 'Pending'; old 'Approved' already moved stock, so it becomes 'Received'.
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $t) {
            $cols = [
                'purchase_order_id' => fn() => $t->unsignedBigInteger('purchase_order_id')->nullable()->after('to_location_id'),
                'vehicle_no'        => fn() => $t->string('vehicle_no', 30)->nullable()->after('remarks'),
                'driver_name'       => fn() => $t->string('driver_name', 100)->nullable()->after('vehicle_no'),
                'driver_contact'    => fn() => $t->string('driver_contact', 30)->nullable()->after('driver_name'),
                'gate_images'       => fn() => $t->json('gate_images')->nullable()->after('driver_contact'),
                'gate_remarks'      => fn() => $t->text('gate_remarks')->nullable()->after('gate_images'),
                'dispatched_by'     => fn() => $t->unsignedBigInteger('dispatched_by')->nullable()->after('gate_remarks'),
                'dispatched_at'     => fn() => $t->timestamp('dispatched_at')->nullable()->after('dispatched_by'),
                'received_by'       => fn() => $t->unsignedBigInteger('received_by')->nullable()->after('dispatched_at'),
                'received_at'       => fn() => $t->timestamp('received_at')->nullable()->after('received_by'),
                'receive_remarks'   => fn() => $t->text('receive_remarks')->nullable()->after('received_at'),
            ];
            foreach ($cols as $name => $add) if (!Schema::hasColumn('stock_movements', $name)) $add();
        });

        Schema::table('stock_movement_items', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_movement_items', 'source_lot_no')) $t->string('source_lot_no', 100)->nullable()->after('product_id');
        });

        DB::table('stock_movements')->where('status', 'PendingApproval')->update(['status' => 'Pending']);
        DB::table('stock_movements')->where('status', 'Approved')->whereNull('dispatched_at')
            ->update(['status' => 'Received', 'received_at' => DB::raw('approved_at'), 'received_by' => DB::raw('approved_by')]);
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->string('status', 20)->default('Pending')->change();
            $t->string('movement_type', 30)->change(); // 'warehouse_to_warehouse' did not fit in 20
        });

        // Gatekeepers verify exits; superadmin gets everything
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $gate = Permission::firstOrCreate(['name' => 'stock_movements.gate', 'guard_name' => 'web']);
        foreach (['gatekeeper', 'superadmin'] as $name) {
            if ($role = Role::where('name', $name)->first()) $role->givePermissionTo($gate);
        }
        if ($super = Role::where('name', 'superadmin')->first()) {
            $super->givePermissionTo(Permission::where('name', 'like', 'stock_movements.%')->get());
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('stock_movement_items', function (Blueprint $t) {
            if (Schema::hasColumn('stock_movement_items', 'source_lot_no')) $t->dropColumn('source_lot_no');
        });
        Schema::table('stock_movements', function (Blueprint $t) {
            foreach (['purchase_order_id', 'vehicle_no', 'driver_name', 'driver_contact', 'gate_images', 'gate_remarks',
                      'dispatched_by', 'dispatched_at', 'received_by', 'received_at', 'receive_remarks'] as $c) {
                if (Schema::hasColumn('stock_movements', $c)) $t->dropColumn($c);
            }
        });
        Permission::where('name', 'stock_movements.gate')->delete();
    }
};
