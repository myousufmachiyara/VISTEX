<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brokers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by', 'brk_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'brk_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('is_active', 'idx_brokers_active');
        });
    }

    public function down(): void { Schema::dropIfExists('brokers'); }
};