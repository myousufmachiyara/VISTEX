<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_no', 30)->unique(); // V26-SM-00001

            $table->string('movement_type', 20); // warehouse_to_warehouse | warehouse_to_vendor | vendor_to_warehouse

            $table->unsignedBigInteger('from_location_id');
            $table->unsignedBigInteger('to_location_id');

            $table->string('lot_no', 100)->nullable(); // assigned by the receiving mill; only meaningful for warehouse_to_vendor

            $table->date('movement_date');
            $table->string('status', 20)->default('PendingApproval'); // PendingApproval | Approved | Rejected
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('from_location_id', 'sm_from_loc_fk')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('to_location_id', 'sm_to_loc_fk')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('approved_by', 'sm_approved_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'sm_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'sm_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_sm_status');
            $table->index('movement_type', 'idx_sm_type');
            $table->index('lot_no', 'idx_sm_lot');
        });
    }

    public function down(): void { Schema::dropIfExists('stock_movements'); }
};