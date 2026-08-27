<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sku_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('product_id'); // SKU-category product
            $table->decimal('rate', 15, 2);
            $table->date('effective_date');
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id', 'csr_customer_fk')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('product_id', 'csr_product_fk')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by', 'csr_created_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index(['customer_id', 'product_id', 'effective_date'], 'idx_csr_lookup');
        });
    }

    public function down(): void { Schema::dropIfExists('customer_sku_rates'); }
};