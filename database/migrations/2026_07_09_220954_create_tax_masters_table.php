<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_masters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);       // "GST 18%", "GST 15%", "No Tax"
            $table->decimal('rate', 5, 2);     // 18.00, 15.00, 0.00
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('tax_masters'); }
};