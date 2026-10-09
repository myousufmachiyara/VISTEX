<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Spatie\Permission\Models\{Permission, Role};

/**
 * Groundwork for the Yarn / Greige / Packaging reports.
 *
 * 1. products.reorder_level — for Low Stock / Reorder Point.
 * 2. Received stock carries its PO number as lot_no, so stock can be reported
 *    "by Yarn PO" and issuances can show which PO the yarn came from.
 *    Existing receipt rows are back-filled.
 * 3. Purchase returns now take the returned goods out of "rejected" stock.
 *    Returns made before this change are back-filled.
 * 4. reports.* permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'reorder_level')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('reorder_level', 15, 3)->nullable()->after('opening_stock');
            });
        }

        // 2. lot = PO number on receipt rows that have none
        DB::statement("
            UPDATE location_stock_ledger l
            JOIN purchase_receivings r ON r.id = l.reference_id
            JOIN purchase_orders p ON p.id = r.purchase_order_id
            SET l.lot_no = p.order_no
            WHERE l.reference_type IN ('PurchaseReceiving', 'PurchaseReceivingRejection') AND l.lot_no IS NULL
        ");

        // 3. stock-out for returns already made
        $returns = DB::table('purchase_return_items as ri')
            ->join('purchase_returns as rt', 'rt.id', '=', 'ri.purchase_return_id')
            ->join('purchase_receiving_items as gi', 'gi.id', '=', 'ri.purchase_receiving_item_id')
            ->join('purchase_receivings as r', 'r.id', '=', 'gi.purchase_receiving_id')
            ->join('purchase_orders as p', 'p.id', '=', 'r.purchase_order_id')
            ->whereNull('rt.deleted_at')
            ->select('ri.id', 'ri.quantity_returned', 'rt.id as return_id', 'rt.return_no', 'rt.return_date',
                     'gi.product_id', 'r.id as receiving_id', 'p.order_no')
            ->get();

        foreach ($returns as $row) {
            $exists = DB::table('location_stock_ledger')->where('reference_type', 'PurchaseReturn')
                ->where('reference_id', $row->return_id)->where('product_id', $row->product_id)->exists();
            if ($exists) continue;

            $locationId = DB::table('location_stock_ledger')
                ->where('reference_type', 'PurchaseReceivingRejection')->where('reference_id', $row->receiving_id)
                ->where('product_id', $row->product_id)->value('location_id')
                ?? DB::table('locations')->whereNull('vendor_id')->orderByDesc('is_default')->value('id');
            if (!$locationId) continue;

            DB::table('location_stock_ledger')->insert([
                'doc_no' => $row->return_no, 'location_id' => $locationId, 'product_id' => $row->product_id,
                'status' => 'rejected', 'lot_no' => $row->order_no, 'quantity' => -1 * (float) $row->quantity_returned, 'amount' => 0,
                'reference_type' => 'PurchaseReturn', 'reference_id' => $row->return_id, 'entry_date' => $row->return_date,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 4. permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['reports.yarn', 'reports.greige', 'reports.packaging'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        if ($super = Role::where('name', 'superadmin')->first()) {
            $super->givePermissionTo(['reports.yarn', 'reports.greige', 'reports.packaging']);
        }
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('location_stock_ledger')->where('reference_type', 'PurchaseReturn')->delete();
        Permission::whereIn('name', ['reports.yarn', 'reports.greige', 'reports.packaging'])->delete();
        if (Schema::hasColumn('products', 'reorder_level')) {
            Schema::table('products', fn(Blueprint $t) => $t->dropColumn('reorder_level'));
        }
    }
};
