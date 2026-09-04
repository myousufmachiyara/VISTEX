<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_amendments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->integer('amendment_no'); // 1, 2, 3... sequential per PO

            // Snapshot of every amendable field's PREVIOUS value, for audit
            $table->json('previous_values');
            // Snapshot of the NEW values being applied
            $table->json('new_values');

            $table->text('reason');
            $table->unsignedBigInteger('requested_by')->nullable(); // ← fixed: must be nullable to support onDelete('set null')
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default('Pending'); // Pending | Approved | Rejected
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->foreign('purchase_order_id', 'poa_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('requested_by', 'poa_requested_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by', 'poa_approved_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index(['purchase_order_id', 'status'], 'idx_poa_po_status');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_order_amendments'); }
};