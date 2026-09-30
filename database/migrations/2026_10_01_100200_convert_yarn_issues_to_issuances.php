<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * "Yarn Issue" becomes a general "Issuance" module.
 *
 * The existing tables are renamed in place (not copied) so every row keeps
 * its id, and the ledgers/vouchers that point at those ids stay valid —
 * only their reference_type changes from 'YarnIssue' to 'Issuance'.
 *
 * issue_type:
 *   yarn_weaving        Yarn -> weaving mill, against a Weaving PO          (active)
 *   greige_processing   Greige -> processing mill, against a Processing PO  (active)
 *   greige_sale         Greige -> customer, against a Sale Order            (locked, pending client)
 *   fabric_reprocess    Finished fabric -> mill for reprocess               (locked, pending client)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Detach the item FK so the parent table and column can be renamed cleanly
        Schema::table('yarn_issue_items', function (Blueprint $table) {
            $table->dropForeign('yii_issue_fk');
        });

        Schema::rename('yarn_issues', 'issuances');
        Schema::rename('yarn_issue_items', 'issuance_items');

        Schema::table('issuance_items', function (Blueprint $table) {
            $table->renameColumn('yarn_issue_id', 'issuance_id');
        });

        Schema::table('issuance_items', function (Blueprint $table) {
            $table->foreign('issuance_id', 'isi_issuance_fk')->references('id')->on('issuances')->onDelete('cascade');
            $table->string('lot_no', 100)->nullable()->after('product_id');
            $table->unsignedBigInteger('purchase_order_item_id')->nullable()->after('lot_no');
            $table->foreign('purchase_order_item_id', 'isi_po_item_fk')->references('id')->on('purchase_order_items')->nullOnDelete();
        });

        // 2. New header columns
        Schema::table('issuances', function (Blueprint $table) {
            $table->string('issue_type', 30)->default('yarn_weaving')->after('issue_no');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->change();
            $table->unsignedBigInteger('job_id')->nullable()->after('purchase_order_id');
            $table->unsignedBigInteger('vendor_id')->nullable()->after('job_id');
            $table->unsignedBigInteger('source_location_id')->nullable()->after('vendor_id');
            $table->unsignedBigInteger('destination_location_id')->nullable()->after('source_location_id');
            $table->decimal('total_quantity', 15, 3)->default(0)->after('issue_date');
            $table->decimal('total_amount', 15, 2)->default(0)->after('total_quantity');

            $table->foreign('job_id', 'iss_job_fk')->references('id')->on('job_orders')->nullOnDelete();
            $table->foreign('vendor_id', 'iss_vendor_fk')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('source_location_id', 'iss_src_loc_fk')->references('id')->on('locations')->nullOnDelete();
            $table->foreign('destination_location_id', 'iss_dst_loc_fk')->references('id')->on('locations')->nullOnDelete();
            $table->index('issue_type', 'idx_iss_type');
        });

        // 3. Back-fill the new columns for existing yarn issues
        $defaultLocationId = DB::table('locations')->whereNull('vendor_id')->orderByDesc('is_default')->orderBy('id')->value('id');
        DB::table('issuances')->update(['issue_type' => 'yarn_weaving', 'source_location_id' => $defaultLocationId]);
        DB::statement('UPDATE issuances i JOIN purchase_orders p ON p.id = i.purchase_order_id SET i.vendor_id = p.vendor_id');
        DB::statement('UPDATE issuances i SET
            total_quantity = (SELECT COALESCE(SUM(quantity),0) FROM issuance_items WHERE issuance_id = i.id),
            total_amount   = (SELECT COALESCE(SUM(amount),0)   FROM issuance_items WHERE issuance_id = i.id)');

        // 4. Re-point ledger + voucher references at the renamed document
        foreach (['location_stock_ledger', 'yarn_in_process_ledger', 'vouchers'] as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->where('reference_type', 'YarnIssue')->update(['reference_type' => 'Issuance']);
            }
        }
    }

    public function down(): void
    {
        foreach (['location_stock_ledger', 'yarn_in_process_ledger', 'vouchers'] as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->where('reference_type', 'Issuance')->update(['reference_type' => 'YarnIssue']);
            }
        }

        Schema::table('issuances', function (Blueprint $table) {
            foreach (['iss_job_fk', 'iss_vendor_fk', 'iss_src_loc_fk', 'iss_dst_loc_fk'] as $fk) $table->dropForeign($fk);
            $table->dropIndex('idx_iss_type');
            $table->dropColumn(['issue_type', 'job_id', 'vendor_id', 'source_location_id', 'destination_location_id', 'total_quantity', 'total_amount']);
        });

        Schema::table('issuance_items', function (Blueprint $table) {
            $table->dropForeign('isi_issuance_fk');
            $table->dropForeign('isi_po_item_fk');
            $table->dropColumn(['lot_no', 'purchase_order_item_id']);
            $table->renameColumn('issuance_id', 'yarn_issue_id');
        });

        Schema::rename('issuance_items', 'yarn_issue_items');
        Schema::rename('issuances', 'yarn_issues');

        Schema::table('yarn_issue_items', function (Blueprint $table) {
            $table->foreign('yarn_issue_id', 'yii_issue_fk')->references('id')->on('yarn_issues')->onDelete('cascade');
        });
    }
};
