<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greige_receives', function (Blueprint $table) {
            $table->id();
            $table->string('receive_no', 30)->unique();
            $table->unsignedBigInteger('purchase_order_id'); // weaving-type PO
            $table->date('receive_date');
            $table->string('vendor_challan_no', 50);
            $table->boolean('is_final_receiving')->default(false);
            $table->decimal('yarn_cost_amount', 15, 2)->default(0);
            $table->decimal('weaving_charge_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('PendingApproval'); // PendingApproval | Approved | Rejected
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->json('attachments');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchase_order_id', 'gr_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('approved_by', 'gr_approved_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'gr_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'gr_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_gr_status');
            $table->index('purchase_order_id', 'idx_gr_po');
        });
    }

    public function down(): void { Schema::dropIfExists('greige_receives'); }
};