<?php
// create_purchase_orders_table.php
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
            $table->string('type', 20); // purchase | weaving | processing
            $table->integer('revision_no')->default(1);

            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_category_id');
            $table->unsignedBigInteger('service_type_id')->nullable();
            $table->unsignedBigInteger('job_id')->nullable();
            $table->string('program')->nullable();
            $table->json('fabric_specs')->nullable();

            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('drop_off_location_id');

            $table->date('order_date');
            $table->date('expected_date')->nullable();

            $table->unsignedBigInteger('broker_id')->nullable();
            $table->string('broker_commission_type', 10)->nullable();
            $table->decimal('broker_commission_value', 15, 4)->default(0);
            $table->decimal('broker_commission_amount', 15, 2)->default(0);

            $table->string('payment_term_type', 20)->default('cash');
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->string('payment_term_note')->nullable();

            $table->boolean('gst_applicable')->default(true);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('gst_rate', 5, 2)->default(0);

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->string('status', 20)->default('Pending');
            // Pending | Approved | Issued | PartiallyReceived | Received | Rejected

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();

            // ── Weaving / CPO formula fields ──
            $table->unsignedBigInteger('warp_product_id')->nullable();
            $table->unsignedBigInteger('weft_product_id')->nullable();
            $table->unsignedBigInteger('greige_product_id')->nullable();
            $table->decimal('warp_count', 10, 2)->nullable();
            $table->decimal('weft_count', 10, 2)->nullable();
            $table->decimal('reed_count', 10, 2)->nullable();
            $table->decimal('pick', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('total_meters_required', 15, 3)->nullable();
            $table->decimal('rate_per_pick', 15, 4)->nullable();
            $table->decimal('sizing_lbs', 15, 4)->nullable();
            $table->decimal('warp_conversion_pct', 10, 6)->nullable();
            $table->decimal('warp_shrinkage_pct', 10, 6)->nullable();
            $table->decimal('weft_shrinkage_pct', 10, 6)->nullable();
            $table->decimal('gsm', 10, 3)->nullable();
            $table->decimal('warp_consumption', 15, 6)->nullable();
            $table->decimal('weft_consumption', 15, 6)->nullable();
            $table->decimal('total_greige_qty_required', 15, 6)->nullable();
            $table->decimal('total_yarn_weight_consumed', 15, 3)->nullable();
            $table->decimal('rate_per_meter', 15, 4)->nullable();
            $table->decimal('sizing_per_meter', 15, 4)->nullable();
            $table->decimal('weaving_rate', 15, 4)->nullable();
            $table->decimal('weaving_cost', 15, 2)->nullable();
            $table->string('item_name')->nullable();

            // ── Weaving receiving metadata (moved off Cache, proper columns) ──
            $table->boolean('is_final_receiving_done')->default(false);

            $table->unsignedBigInteger('forecast_id')->nullable();
            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id', 'po_vendor_fk')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('product_category_id', 'po_category_fk')->references('id')->on('product_categories')->onDelete('restrict');
            $table->foreign('service_type_id', 'po_service_type_fk')->references('id')->on('service_types')->onDelete('set null');
            $table->foreign('job_id', 'po_job_fk')->references('id')->on('jobs')->onDelete('set null');
            $table->foreign('from_location_id', 'po_from_loc_fk')->references('id')->on('locations')->onDelete('set null');
            $table->foreign('drop_off_location_id', 'po_dropoff_loc_fk')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('broker_id', 'po_broker_fk')->references('id')->on('brokers')->onDelete('set null');
            $table->foreign('tax_id', 'po_tax_fk')->references('id')->on('tax_masters')->onDelete('set null');
            $table->foreign('warp_product_id', 'po_warp_fk')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('weft_product_id', 'po_weft_fk')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('greige_product_id', 'po_greige_fk')->references('id')->on('products')->onDelete('set null');
            $table->foreign('forecast_id', 'po_forecast_fk')->references('id')->on('forecasts')->onDelete('set null');
            $table->foreign('approved_by', 'po_approved_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('locked_by', 'po_locked_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'po_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'po_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_po_status');
            $table->index('type', 'idx_po_type');
            $table->index('vendor_id', 'idx_po_vendor');
        });
    }

    public function down(): void { Schema::dropIfExists('purchase_orders'); }
};