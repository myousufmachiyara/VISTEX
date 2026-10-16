<?php

use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Clean PO status / type values that differ only by case, spaces or underscores
 * (e.g. "approved", "Approved ", "Partially Received"). MySQL treats them as equal when listing,
 * the application did not — so a listed PO could be refused when receiving. Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) return;
        foreach (DB::table('purchase_orders')->select('id', 'status', 'type')->get() as $po) {
            $status = PurchaseOrder::canonicalStatus($po->status);
            $type = PurchaseOrder::canonicalType($po->type);
            if ($status !== $po->status || $type !== $po->type) {
                DB::table('purchase_orders')->where('id', $po->id)->update(['status' => $status, 'type' => $type]);
            }
        }
    }

    public function down(): void {}
};
