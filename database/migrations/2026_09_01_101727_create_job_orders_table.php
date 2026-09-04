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
        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table->string('job_no', 30)->unique(); // our internal number: V26-JOB-00001

            $table->unsignedBigInteger('customer_id');
            $table->string('buyer_name')->nullable(); // individual contact placing the order, may differ from customer company name
            $table->text('shipping_address')->nullable();

            $table->string('customer_po_number', 100)->nullable();   // customer's own PO#
            $table->string('customer_reference', 150)->nullable();   // customer's order reference

            $table->date('order_date');
            $table->date('expected_date')->nullable(); // expected arrival/delivery date

            $table->string('payment_term_type', 20)->default('cash'); // cash | credit | pdc | other
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->string('payment_term_note')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->string('status', 20)->default('Pending'); // Pending | Approved | InProgress | Completed | Rejected
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable(); // e.g. scanned customer PO document

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id', 'job_customer_fk')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('approved_by', 'job_approved_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'job_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'job_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_jobs_status');
            $table->index('customer_id', 'idx_jobs_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_orders');
    }
};
