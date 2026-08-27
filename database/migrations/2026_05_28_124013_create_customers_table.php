<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();

            $table->string('tax_id_number', 50)->nullable();
            $table->string('payment_terms_type', 20)->default('days_after_invoice'); // days_after_invoice | of_current_month | of_following_month
            $table->unsignedSmallInteger('payment_days')->default(30);
            $table->string('currency', 10)->default('PKR');

            // Parties are separated from Chart of Accounts. Balance is
            // computed from voucher_entries (party_type='customer',
            // party_id=this.id) against the Accounts Receivable control account.
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('opening_type', 10)->default('receivable'); // receivable | payable
            $table->date('opening_balance_date')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('created_by', 'cust_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'cust_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('is_active', 'idx_customers_active');
        });
    }

    public function down(): void { Schema::dropIfExists('customers'); }
};