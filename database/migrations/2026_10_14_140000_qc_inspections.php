<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\{Permission, Role};

/**
 * QC of processed fabric, one inspection per processing GRN.
 *  - kanta (weight) check: rolls, gross / tare kg, measured meters, width → actual GSM
 *  - final grading A / B / C, meters sent back for reprocessing, shortage
 *  - B/C quality claim on the mill (debit note)
 * Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qc_inspections')) {
            Schema::create('qc_inspections', function (Blueprint $t) {
                $t->id();
                $t->string('qc_no', 50)->unique();
                $t->unsignedBigInteger('purchase_receiving_id')->unique();
                $t->unsignedBigInteger('purchase_order_id');
                $t->unsignedBigInteger('vendor_id')->nullable();
                $t->unsignedBigInteger('location_id')->nullable();
                $t->date('inspection_date');
                $t->string('status', 20)->default('Open'); // Open, Closed
                $t->decimal('claim_amount', 15, 2)->default(0);      // B/C quality claim on the mill
                $t->string('claim_reason', 255)->nullable();
                $t->decimal('shortage_amount', 15, 2)->default(0);   // value of meters short on the kanta
                $t->decimal('pdc_reduced_by', 15, 2)->default(0);
                $t->text('remarks')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->unsignedBigInteger('closed_by')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->timestamps();
                $t->index(['status', 'inspection_date']);
            });
        }

        if (!Schema::hasTable('qc_inspection_items')) {
            Schema::create('qc_inspection_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('qc_inspection_id');
                $t->unsignedBigInteger('purchase_receiving_item_id');
                $t->unsignedBigInteger('product_id');
                $t->string('lot_no', 100)->nullable();
                $t->decimal('received_a', 15, 3)->default(0);
                $t->decimal('received_b', 15, 3)->default(0);
                $t->decimal('received_c', 15, 3)->default(0);
                $t->decimal('rate', 15, 4)->default(0);
                // Kanta
                $t->unsignedInteger('rolls')->nullable();
                $t->decimal('gross_kg', 12, 3)->nullable();
                $t->decimal('tare_kg', 12, 3)->nullable();
                $t->decimal('measured_meters', 15, 3)->nullable();
                $t->decimal('width_inch', 8, 2)->nullable();
                $t->decimal('expected_gsm', 8, 2)->nullable();
                // Result
                $t->decimal('final_a', 15, 3)->default(0);
                $t->decimal('final_b', 15, 3)->default(0);
                $t->decimal('final_c', 15, 3)->default(0);
                $t->decimal('reprocess_qty', 15, 3)->default(0);
                $t->decimal('shortage_qty', 15, 3)->default(0);
                $t->json('defects')->nullable();
                $t->text('defect_notes')->nullable();
                $t->timestamps();
                $t->foreign('qc_inspection_id')->references('id')->on('qc_inspections')->cascadeOnDelete();
            });
        }

        Schema::table('purchase_receivings', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_receivings', 'qc_status')) $t->string('qc_status', 20)->nullable()->after('status');
        });
        // Processing GRNs already approved are waiting for QC
        \DB::table('purchase_receivings as r')->join('purchase_orders as p', 'p.id', '=', 'r.purchase_order_id')
            ->where('p.type', 'processing')->where('r.status', 'Approved')->whereNull('r.qc_status')
            ->update(['r.qc_status' => 'Pending']);

        if (Schema::hasTable('permissions')) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $perms = collect(['index', 'create', 'edit', 'delete', 'print'])
                ->map(fn($a) => Permission::firstOrCreate(['name' => "qc_inspections.{$a}", 'guard_name' => 'web']));
            if ($super = Role::where('name', 'superadmin')->first()) $super->givePermissionTo($perms);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_inspection_items');
        Schema::dropIfExists('qc_inspections');
        Schema::table('purchase_receivings', function (Blueprint $t) {
            if (Schema::hasColumn('purchase_receivings', 'qc_status')) $t->dropColumn('qc_status');
        });
        Permission::where('name', 'like', 'qc_inspections.%')->delete();
    }
};
