<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdcs', function (Blueprint $table) {
            $table->id();
            $table->string('pdc_no', 30)->unique(); // V26-PDC-00001

            $table->string('party_type', 20); // 'vendor' | 'customer' — PDC can be payable OR receivable
            $table->unsignedBigInteger('party_id');

            // What this PDC is against — a Purchase Receiving / GRN for now,
            // kept generic (reference_type/reference_id) so it can later
            // point at other documents (Job, Sale Invoice, etc.)
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->decimal('amount', 15, 2);
            $table->date('due_date'); // GRN date (cash) or GRN date + credit days

            $table->string('status', 20)->default('Pending');
            // Pending | Created | Signed | Issued | Cleared | Bounced

            // ── Created stage ──
            $table->unsignedBigInteger('bank_account_id')->nullable(); // from Chart of Accounts
            $table->string('cheque_no', 50)->nullable();
            $table->string('unsigned_cheque_image')->nullable();

            // ── Signed stage ──
            $table->string('signed_cheque_image')->nullable();

            // ── Issued stage ──
            $table->string('issue_method', 20)->nullable(); // handed_to_vendor | bank_deposit
            $table->string('receiver_name')->nullable();
            $table->string('receiver_contact', 50)->nullable();
            $table->string('receiver_cnic', 30)->nullable();
            $table->string('receipt_signed_image')->nullable(); // handed_to_vendor path
            $table->string('bank_slip_image')->nullable();       // bank_deposit path
            $table->date('issued_date')->nullable();

            // ── Cleared / Bounced stage ──
            $table->date('cleared_date')->nullable();
            $table->date('bounced_date')->nullable();
            $table->text('bounced_reason')->nullable();

            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('bank_account_id', 'pdc_bank_fk')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('created_by', 'pdc_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'pdc_updated_by_fk')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_pdc_status');
            $table->index(['party_type', 'party_id'], 'idx_pdc_party');
            $table->index(['reference_type', 'reference_id'], 'idx_pdc_reference');
        });
    }

    public function down(): void { Schema::dropIfExists('pdcs'); }
};