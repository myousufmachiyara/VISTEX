<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('cpo_no', 30)->unique();
            $table->unsignedBigInteger('vendor_id'); // weaving mill
            $table->unsignedBigInteger('warp_product_id'); // yarn
            $table->unsignedBigInteger('weft_product_id'); // yarn
            $table->unsignedBigInteger('greige_product_id')->nullable(); // output greige product, if already known
            $table->unsignedBigInteger('forecast_id')->nullable();

            // Inputs
            $table->decimal('warp_count', 10, 2);
            $table->decimal('weft_count', 10, 2);
            $table->decimal('reed_count', 10, 2);
            $table->decimal('pick', 10, 2);
            $table->decimal('width', 10, 2);
            $table->decimal('total_meters_required', 15, 3);
            $table->decimal('rate_per_pick', 15, 4);
            $table->decimal('sizing_lbs', 15, 4)->default(0);
            $table->decimal('warp_conversion_pct', 10, 6)->default(0);

            // Calculated (stored as snapshot — formulas may evolve later,
            // but this CPO's numbers must stay frozen once created)
            $table->decimal('gsm', 10, 3)->default(0);
            $table->decimal('warp_consumption', 15, 6)->default(0); // lbs of warp yarn per meter of greige
            $table->decimal('weft_consumption', 15, 6)->default(0); // lbs of weft yarn per meter of greige
            $table->decimal('total_greige_qty_required', 15, 6)->default(0); // yarn lbs per meter, combined
            $table->decimal('total_yarn_weight_consumed', 15, 3)->default(0); // total yarn lbs needed for this CPO
            $table->decimal('rate_per_meter', 15, 4)->default(0);
            $table->decimal('sizing_per_meter', 15, 4)->default(0);
            $table->decimal('weaving_rate', 15, 4)->default(0); // Rs per meter
            $table->decimal('weaving_cost', 15, 2)->default(0);

            $table->boolean('gst_applicable')->default(true);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);

            $table->string('item_name')->nullable(); // "{warp}x{weft}/{reed}-{pick}-{width}"

            $table->date('po_date');
            $table->string('status', 20)->default('Active'); // Active | Closed
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id', 'cpo_vendor_fk')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('warp_product_id', 'cpo_warp_fk')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('weft_product_id', 'cpo_weft_fk')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('greige_product_id', 'cpo_greige_fk')->references('id')->on('products')->onDelete('set null');
            $table->foreign('forecast_id', 'cpo_forecast_fk')->references('id')->on('forecasts')->onDelete('set null');
            $table->foreign('tax_id', 'cpo_tax_fk')->references('id')->on('tax_masters')->onDelete('set null');
            $table->foreign('created_by', 'cpo_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'cpo_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_cpo_status');
        });
    }

    public function down(): void { Schema::dropIfExists('conversion_purchase_orders'); }
};