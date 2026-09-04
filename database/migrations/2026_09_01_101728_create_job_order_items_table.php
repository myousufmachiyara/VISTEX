<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('product_id'); // SKU-category product

            $table->decimal('quantity', 15, 3);
            $table->unsignedBigInteger('measurement_unit')->nullable(); // overridable, defaults from product
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0); // (qty * unit_price) - discount + tax

            // Fulfilment tracking — how much of this line has been produced/
            // packed/dispatched so far (used once Processing/Packaging exist)
            $table->decimal('quantity_fulfilled', 15, 3)->default(0);

            $table->timestamps();

            $table->foreign('job_id', 'ji_job_fk')->references('id')->on('jobs')->onDelete('cascade');
            $table->foreign('product_id', 'ji_product_fk')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('measurement_unit', 'ji_unit_fk')->references('id')->on('measurement_units')->onDelete('set null');
            $table->foreign('tax_id', 'ji_tax_fk')->references('id')->on('tax_masters')->onDelete('set null');

            $table->index('job_id', 'idx_ji_job');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_order_items');
    }
};
