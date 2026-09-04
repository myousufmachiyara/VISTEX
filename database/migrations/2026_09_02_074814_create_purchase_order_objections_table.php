<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_objections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->text('remarks');
            $table->string('status', 20)->default('Open'); // Open | Resolved

            $table->unsignedBigInteger('raised_by')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->foreign('purchase_order_id', 'poo_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('raised_by', 'poo_raised_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by', 'poo_resolved_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index(['purchase_order_id', 'status'], 'idx_poo_po_status');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_order_objections'); }
};