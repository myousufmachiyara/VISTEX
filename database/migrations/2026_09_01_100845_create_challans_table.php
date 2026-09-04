<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challans', function (Blueprint $table) {
            $table->id();
            $table->string('challan_no', 30)->unique(); // V26-CHL-00001 — our internal tracking number

            $table->unsignedBigInteger('purchase_order_id');
            $table->string('vendor_challan_no', 50)->nullable(); // the number printed on the vendor's own challan, if any

            $table->date('received_date');
            $table->json('challan_images'); // photo(s) of the physical challan document

            $table->string('status', 20)->default('AwaitingInspection');
            // AwaitingInspection | Processed  (Processed = a Receiving action — Approve/Reject/Return/Amendment — has been taken against it)

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('received_by')->nullable(); // gatekeeper or whoever logged it
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchase_order_id', 'chl_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('received_by', 'chl_received_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'chl_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'chl_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_chl_status');
            $table->index('purchase_order_id', 'idx_chl_po');
        });
    }

    public function down(): void { Schema::dropIfExists('challans'); }
};