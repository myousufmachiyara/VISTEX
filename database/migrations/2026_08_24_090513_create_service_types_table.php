<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('service_cost_account_id')->nullable(); // COA account this service's charges post to
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('service_cost_account_id', 'st_cost_account_fk')->references('id')->on('chart_of_accounts')->onDelete('set null');
        });
    }

    public function down(): void { Schema::dropIfExists('service_types'); }
};