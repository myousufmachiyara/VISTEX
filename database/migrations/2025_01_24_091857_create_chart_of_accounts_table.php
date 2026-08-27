<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('head_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sub_head_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hoa_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('hoa_id', 'shoa_hoa_fk')->references('id')->on('head_of_accounts')->onDelete('cascade');
        });

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 20)->unique();
            $table->unsignedBigInteger('shoa_id');
            $table->string('name');
            $table->string('account_type', 50); // cash|bank|receivable|payable|inventory|revenue|cogs|expenses|equity|... (role tag, not accounting classification)

            $table->decimal('receivables', 15, 2)->default(0);
            $table->decimal('payables', 15, 2)->default(0);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_date')->nullable();
            $table->text('remarks')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_no')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('shoa_id', 'coa_shoa_fk')->references('id')->on('sub_head_of_accounts')->onDelete('cascade');
            $table->foreign('created_by', 'coa_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'coa_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('account_type', 'idx_coa_type');
        });

        // Role-key → account mapping, so services never hardcode account IDs
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('role_key', 50)->unique(); // 'accounts_payable', 'stock_in_hand', 'cash', etc.
            $table->unsignedBigInteger('account_id');
            $table->timestamps();

            $table->foreign('account_id', 'am_account_fk')->references('id')->on('chart_of_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('sub_head_of_accounts');
        Schema::dropIfExists('head_of_accounts');
    }
};