<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challans', function (Blueprint $table) {
            $table->id();
            $table->string('challan_no', 30)->unique(); // V26-CHL-00001

            $table->string('entry_type', 20)->default('po'); // po | direct

            // PO-based entries
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->string('vendor_challan_no', 50)->nullable(); // vendor's own challan #, if any

            // Direct (no-PO) entries
            $table->unsignedBigInteger('vendor_id')->nullable();

            $table->date('received_date');
            $table->json('challan_images'); // photo(s) of the physical challan document

            $table->string('status', 20)->default('AwaitingInspection');
            // AwaitingInspection | Processed

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('received_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchase_order_id', 'chl_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('vendor_id', 'chl_vendor_fk')->references('id')->on('vendors')->onDelete('set null');
            $table->foreign('received_by', 'chl_received_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'chl_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'chl_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_chl_status');
            $table->index('entry_type', 'idx_chl_type');
            $table->index('purchase_order_id', 'idx_chl_po');
        });

        Schema::create('challan_direct_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('challan_id');
            $table->string('description');
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->unsignedBigInteger('expense_account_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('challan_id', 'cdi_challan_fk')->references('id')->on('challans')->onDelete('cascade');
            $table->foreign('expense_account_id', 'cdi_account_fk')->references('id')->on('chart_of_accounts')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challan_direct_items');
        Schema::dropIfExists('challans');
    }
};