<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30)->unique();
            $table->integer('revision_no')->default(1);

            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_category_id');
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('drop_off_location_id');

            $table->date('order_date');
            $table->date('expected_date')->nullable();

            $table->boolean('gst_applicable')->default(true);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('gst_rate', 5, 2)->default(0);

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->string('status', 30)->default('Pending');
            $table->unsignedBigInteger('locked_by')->nullable();

            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id', 'po_vendor_fk')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('product_category_id', 'po_category_fk')->references('id')->on('product_categories')->onDelete('restrict');
            $table->foreign('from_location_id', 'po_from_loc_fk')->references('id')->on('locations')->onDelete('set null');
            $table->foreign('drop_off_location_id', 'po_dropoff_loc_fk')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('tax_id', 'po_tax_fk')->references('id')->on('tax_masters')->onDelete('set null');
            $table->foreign('locked_by', 'po_locked_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'po_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'po_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_po_status');
            $table->index('vendor_id', 'idx_po_vendor');
            $table->index('product_category_id', 'idx_po_category');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_orders'); }
};