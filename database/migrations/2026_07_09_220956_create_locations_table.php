<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable(); // null = our own warehouse
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('in_charge_user_id')->nullable();
            $table->boolean('is_default')->default(false); // the default "our" warehouse
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('in_charge_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('vendor_id', 'idx_locations_vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};