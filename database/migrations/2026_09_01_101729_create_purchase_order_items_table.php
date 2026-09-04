<?php
// create_purchase_order_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id')->nullable(); // nullable: Processing lines may not map to a catalog Product directly
            $table->unsignedBigInteger('forecast_id')->nullable();
            $table->unsignedBigInteger('job_item_id')->nullable(); // Processing lines trace back to a Job line
            $table->unsignedBigInteger('measurement_unit')->nullable();

            $table->string('collection')->nullable();
            $table->string('pattern_code')->nullable();
            $table->string('description')->nullable();

            $table->decimal('quantity', 15, 3);
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);

            $table->timestamps();

            $table->foreign('purchase_order_id', 'poi_order_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id', 'poi_product_fk')->references('id')->on('products')->onDelete('set null');
            $table->foreign('forecast_id', 'poi_forecast_fk')->references('id')->on('forecasts')->onDelete('set null');
            $table->foreign('job_item_id', 'poi_job_item_fk')->references('id')->on('job_order_items')->onDelete('set null');
            $table->foreign('measurement_unit', 'poi_unit_fk')->references('id')->on('measurement_units')->onDelete('set null');

            $table->index('purchase_order_id', 'idx_poi_order');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_order_items'); }
};