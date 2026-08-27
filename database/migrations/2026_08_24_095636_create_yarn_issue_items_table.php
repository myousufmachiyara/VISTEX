<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yarn_issue_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('yarn_issue_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 3);
            $table->decimal('rate', 15, 4);
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('yarn_issue_id', 'yii_issue_fk')->references('id')->on('yarn_issues')->onDelete('cascade');
            $table->foreign('product_id', 'yii_product_fk')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void { Schema::dropIfExists('yarn_issue_items'); }
};