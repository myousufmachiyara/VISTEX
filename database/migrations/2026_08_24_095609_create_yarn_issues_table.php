<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yarn_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_no', 30)->unique();
            $table->unsignedBigInteger('cpo_id');
            $table->date('issue_date');
            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cpo_id', 'yi_cpo_fk')->references('id')->on('conversion_purchase_orders')->onDelete('cascade');
            $table->foreign('created_by', 'yi_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'yi_updated_by_fk')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void { Schema::dropIfExists('yarn_issues'); }
};