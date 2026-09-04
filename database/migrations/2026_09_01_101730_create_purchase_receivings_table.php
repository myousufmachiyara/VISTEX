<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receivings', function (Blueprint $table) {
            $table->id();
            $table->string('receiving_no', 30)->unique(); // V26-GRN-00001

            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('challan_id'); // every receiving now originates from a logged Challan

            $table->date('receiving_date');
            $table->string('status', 20)->default('PendingApproval');
            $table->boolean('is_final_receiving')->default(false);
            $table->json('yarn_consumed_meta')->nullable();
            $table->decimal('yarn_cost_amount', 15, 2)->default(0);
            $table->decimal('weaving_charge_amount', 15, 2)->default(0);
            // PendingApproval | Approved | Rejected

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->decimal('amount', 15, 2)->default(0);
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // NOTE: no delete route will ever be exposed for this table,
            // per "No Delete option for PO Receiving" — softDeletes kept
            // only as a safety net at the DB layer, not user-facing.

            $table->foreign('purchase_order_id', 'pr_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('challan_id', 'pr_challan_fk')->references('id')->on('challans')->onDelete('cascade');
            $table->foreign('approved_by', 'pr_approved_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'pr_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'pr_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_pr_status');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_receivings'); }
};