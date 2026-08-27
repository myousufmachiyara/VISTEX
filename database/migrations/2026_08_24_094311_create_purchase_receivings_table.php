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
            $table->string('receiving_no', 30)->unique();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('location_id');
            $table->date('receiving_date');
            $table->string('vendor_challan_no', 50);
            $table->decimal('amount', 15, 2)->default(0);
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

            $table->foreign('purchase_order_id', 'pr_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('location_id', 'pr_location_fk')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('approved_by', 'pr_approved_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'pr_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'pr_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_pr_status');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_receivings'); }
};