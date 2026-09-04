<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 30)->unique(); // V26-RET-00001
            $table->unsignedBigInteger('purchase_receiving_id');
            $table->date('return_date');
            $table->text('remarks')->nullable();
            $table->json('proof_images')->nullable(); // photo of goods being handed back / courier receipt

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchase_receiving_id', 'pret_receiving_fk')->references('id')->on('purchase_receivings')->onDelete('cascade');
            $table->foreign('created_by', 'pret_created_by_fk')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id');
            $table->unsignedBigInteger('purchase_receiving_item_id');
            $table->decimal('quantity_returned', 15, 3);
            $table->timestamps();

            $table->foreign('purchase_return_id', 'preti_return_fk')->references('id')->on('purchase_returns')->onDelete('cascade');
            $table->foreign('purchase_receiving_item_id', 'preti_receiving_item_fk')->references('id')->on('purchase_receiving_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};