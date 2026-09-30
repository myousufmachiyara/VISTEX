<?php // add_direct_purchase_fields_to_challans_table
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('entry_type');
            $table->string('direct_vendor_name')->nullable()->after('vendor_id');
            $table->string('objection_voice_note')->nullable()->after('objection_remarks');
            $table->unsignedBigInteger('payable_vendor_id')->nullable()->after('reviewed_at');
            $table->unsignedBigInteger('payable_account_id')->nullable()->after('payable_vendor_id');
            $table->unsignedBigInteger('paid_from_account_id')->nullable()->after('payable_account_id');

            $table->foreign('category_id', 'chl_category_fk')->references('id')->on('product_categories')->nullOnDelete();
            $table->foreign('payable_vendor_id', 'chl_pay_vendor_fk')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('payable_account_id', 'chl_pay_acc_fk')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('paid_from_account_id', 'chl_paidfrom_acc_fk')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            foreach (['chl_category_fk', 'chl_pay_vendor_fk', 'chl_pay_acc_fk', 'chl_paidfrom_acc_fk'] as $fk) $table->dropForeign($fk);
            $table->dropColumn(['category_id', 'direct_vendor_name', 'objection_voice_note', 'payable_vendor_id', 'payable_account_id', 'paid_from_account_id']);
        });
    }
};