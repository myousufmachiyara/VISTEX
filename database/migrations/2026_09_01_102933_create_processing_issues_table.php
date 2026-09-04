<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_no', 30)->unique();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('location_id');
            $table->date('issue_date');
            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchase_order_id', 'pi_po_fk')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('location_id', 'pi_location_fk')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('created_by', 'pi_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'pi_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('purchase_order_id', 'idx_pi_po');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_issues');
    }
};