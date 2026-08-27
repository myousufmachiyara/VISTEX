<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor_type')->default('other'); // spinning_mill | weaving_mill | processing_mill | packager | courier | other
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();

            $table->string('tax_id_number', 50)->nullable();
            $table->string('payment_terms_type', 20)->default('days_after_invoice');
            $table->unsignedSmallInteger('payment_days')->default(30);
            $table->string('currency', 10)->default('PKR');

            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('opening_type', 10)->default('payable');
            $table->date('opening_balance_date')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('created_by', 'vend_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'vend_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('vendor_type', 'idx_vendors_type');
            $table->index('is_active', 'idx_vendors_active');
        });
    }

    public function down(): void { Schema::dropIfExists('vendors'); }
};