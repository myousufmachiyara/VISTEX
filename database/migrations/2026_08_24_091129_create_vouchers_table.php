<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 30)->unique();
            $table->string('type', 20); // journal | payment | receipt | contra | system
            $table->date('voucher_date');
            $table->text('narration')->nullable();

            // Set only for system-generated vouchers (posted automatically by
            // another module) — lets us find/reverse the voucher tied to a
            // specific business document, e.g. reference_type='PurchaseReceiving'.
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by', 'v_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'v_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('type', 'idx_v_type');
            $table->index(['reference_type', 'reference_id'], 'idx_v_reference');
        });

        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);

            // Optional party tagging — lets a line simultaneously post to a
            // COA control account (e.g. Accounts Payable) AND update a
            // specific vendor/customer's running balance.
            $table->string('party_type', 20)->nullable(); // 'customer' | 'vendor'
            $table->unsignedBigInteger('party_id')->nullable();

            $table->text('narration')->nullable();
            $table->timestamps();

            $table->foreign('voucher_id', 've_voucher_fk')->references('id')->on('vouchers')->onDelete('cascade');
            $table->foreign('account_id', 've_account_fk')->references('id')->on('chart_of_accounts')->onDelete('restrict');

            $table->index('account_id', 'idx_ve_account');
            $table->index(['party_type', 'party_id'], 'idx_ve_party');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_entries');
        Schema::dropIfExists('vouchers');
    }
};