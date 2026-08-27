<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name');
            $table->string('sku')->unique();
            $table->text('description')->nullable();

            // Category-specific fields, keyed by config/product_attributes.php
            $table->json('attributes')->nullable();

            $table->decimal('opening_stock', 15, 3)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->unsignedBigInteger('measurement_unit');
            $table->boolean('is_active')->default(true);
            $table->boolean('track_lots')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id', 'prod_category_fk')->references('id')->on('product_categories')->onDelete('cascade');
            $table->foreign('measurement_unit', 'prod_unit_fk')->references('id')->on('measurement_units')->onDelete('restrict');
            $table->foreign('created_by', 'prod_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'prod_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('category_id', 'idx_products_category');
            $table->index('is_active', 'idx_products_active');
        });
    }

    public function down(): void { Schema::dropIfExists('products'); }
};