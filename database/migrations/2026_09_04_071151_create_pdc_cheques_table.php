<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdc_cheques', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pdc_id');
            $table->integer('sequence_no'); // 1, 2, 3... per PDC

            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('Created'); // Created | Signed | Issued | Cleared | Bounced

            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('cheque_no', 50)->nullable(); // the physical bank cheque number
            $table->string('unsigned_cheque_image')->nullable();
            $table->string('signed_cheque_image')->nullable();

            $table->string('issue_method', 20)->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_contact', 50)->nullable();
            $table->string('receiver_cnic', 30)->nullable();
            $table->string('receipt_signed_image')->nullable();
            $table->string('bank_slip_image')->nullable();
            $table->date('issued_date')->nullable();

            $table->date('cleared_date')->nullable();
            $table->date('bounced_date')->nullable();
            $table->text('bounced_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('pdc_id', 'pc_pdc_fk')->references('id')->on('pdcs')->onDelete('cascade');
            $table->foreign('bank_account_id', 'pc_bank_fk')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('created_by', 'pc_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'pc_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_pc_status');
            $table->index('pdc_id', 'idx_pc_pdc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdc_cheques');
        Schema::table('pdcs', function (Blueprint $table) {
            $table->string('status', 20)->default('Pending');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('cheque_no', 50)->nullable();
            $table->string('unsigned_cheque_image')->nullable();
            $table->string('signed_cheque_image')->nullable();
            $table->string('issue_method', 20)->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_contact', 50)->nullable();
            $table->string('receiver_cnic', 30)->nullable();
            $table->string('receipt_signed_image')->nullable();
            $table->string('bank_slip_image')->nullable();
            $table->date('issued_date')->nullable();
            $table->date('cleared_date')->nullable();
            $table->date('bounced_date')->nullable();
            $table->text('bounced_reason')->nullable();
        });
    }
};