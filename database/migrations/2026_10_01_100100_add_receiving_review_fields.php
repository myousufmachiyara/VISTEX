<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category-incharge review of a gate challan.
 *
 * challans.status after review:
 *   AwaitingInspection -> Accepted | AcceptedWithObjection | AwaitingAmendment | Rejected
 *   AwaitingAmendment  -> AwaitingInspection (amendment approved or rejected, back to incharge)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            // 'AcceptedWithObjection' is 21 chars; the column was 20
            $table->string('status', 30)->default('AwaitingInspection')->change();
            $table->string('decision', 30)->nullable()->after('status');       // accept | accept_with_objection | amend | reject
            $table->text('decision_remarks')->nullable()->after('decision');
            $table->unsignedBigInteger('amendment_id')->nullable()->after('decision_remarks');
            $table->unsignedBigInteger('objection_id')->nullable()->after('amendment_id');

            $table->foreign('amendment_id', 'chl_amendment_fk')->references('id')->on('purchase_order_amendments')->nullOnDelete();
            $table->foreign('objection_id', 'chl_objection_fk')->references('id')->on('purchase_order_objections')->nullOnDelete();
        });

        Schema::table('challan_items', function (Blueprint $table) {
            $table->decimal('accepted_qty', 15, 3)->default(0)->after('received_qty');
            $table->decimal('rejected_qty', 15, 3)->default(0)->after('accepted_qty');
        });

        Schema::table('purchase_order_objections', function (Blueprint $table) {
            $table->string('source', 20)->default('general')->after('purchase_order_id'); // general | gate | receiving
            $table->unsignedBigInteger('challan_id')->nullable()->after('source');
            $table->unsignedBigInteger('purchase_receiving_id')->nullable()->after('challan_id');

            $table->foreign('challan_id', 'poo_challan_fk')->references('id')->on('challans')->nullOnDelete();
            $table->foreign('purchase_receiving_id', 'poo_receiving_fk')->references('id')->on('purchase_receivings')->nullOnDelete();
        });

        Schema::table('purchase_order_amendments', function (Blueprint $table) {
            $table->unsignedBigInteger('challan_id')->nullable()->after('purchase_order_id');
            $table->foreign('challan_id', 'poa_challan_fk')->references('id')->on('challans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_amendments', function (Blueprint $table) {
            $table->dropForeign('poa_challan_fk');
            $table->dropColumn('challan_id');
        });
        Schema::table('purchase_order_objections', function (Blueprint $table) {
            $table->dropForeign('poo_challan_fk');
            $table->dropForeign('poo_receiving_fk');
            $table->dropColumn(['source', 'challan_id', 'purchase_receiving_id']);
        });
        Schema::table('challan_items', function (Blueprint $table) {
            $table->dropColumn(['accepted_qty', 'rejected_qty']);
        });
        Schema::table('challans', function (Blueprint $table) {
            $table->dropForeign('chl_amendment_fk');
            $table->dropForeign('chl_objection_fk');
            $table->dropColumn(['decision', 'decision_remarks', 'amendment_id', 'objection_id']);
        });
    }
};
