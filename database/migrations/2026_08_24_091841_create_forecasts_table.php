<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('forecast_no', 30)->unique();
            $table->unsignedBigInteger('customer_id')->nullable(); // customer-wise forecasting; nullable for general/internal stock planning
            $table->unsignedBigInteger('product_id'); // greige or yarn product being forecast
            $table->decimal('required_qty', 15, 3);
            $table->decimal('stock_on_hand', 15, 3)->default(0);   // snapshot at creation time
            $table->decimal('on_order_qty', 15, 3)->default(0);    // snapshot: qty on open/unapproved POs at creation time
            $table->decimal('shortfall_qty', 15, 3);               // required - stock_on_hand - on_order_qty (floored at 0)
            $table->date('required_by_date')->nullable();
            $table->text('remarks')->nullable();

            $table->string('status', 20)->default('Pending'); // Pending | Approved | Rejected
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id', 'fc_customer_fk')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('product_id', 'fc_product_fk')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('approved_by', 'fc_approved_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'fc_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'fc_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_fc_status');
        });
    }

    public function down(): void { Schema::dropIfExists('forecasts'); }
};