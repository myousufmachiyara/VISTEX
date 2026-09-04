<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_and_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('applies_to', 20)->default('all'); // all | purchase | weaving | processing
            $table->boolean('is_default_checked')->default(false); // pre-ticked when creating a PO
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by', 'tnc_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'tnc_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('applies_to', 'idx_tnc_applies_to');
        });

        // Pivot — which T&Cs were actually ticked on a given PO (snapshot,
        // so editing the master later never changes what a past PO agreed to)
        Schema::create('purchase_order_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('term_id')->nullable(); // nullable: master row may later be deleted
            $table->string('title'); // snapshot text, survives master edits/deletes
            $table->text('description'); // snapshot text
            $table->timestamps();

            $table->foreign('purchase_order_id', 'pot_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('term_id', 'pot_term_fk')->references('id')->on('terms_and_conditions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_terms');
        Schema::dropIfExists('terms_and_conditions');
    }
};