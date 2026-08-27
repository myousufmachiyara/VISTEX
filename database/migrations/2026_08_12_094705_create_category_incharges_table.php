<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_incharges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_category_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('product_category_id', 'ci_category_fk')->references('id')->on('product_categories')->onDelete('cascade');
            $table->foreign('user_id', 'ci_user_fk')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['product_category_id', 'user_id'], 'uniq_category_incharge');
        });
    }

    public function down(): void { Schema::dropIfExists('category_incharges'); }
};