<?php // extend_challan_direct_items_table
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        // Incharge now picks the account at review time, so it can't be required at entry.
        DB::statement('ALTER TABLE challan_direct_items MODIFY expense_account_id BIGINT UNSIGNED NULL');

        Schema::table('challan_direct_items', function (Blueprint $table) {
            $table->string('unit', 30)->nullable()->after('quantity');
            $table->string('treatment', 10)->default('pending')->after('amount'); // pending | stock | expense
            $table->unsignedBigInteger('product_category_id')->nullable()->after('treatment');
            $table->unsignedBigInteger('product_id')->nullable()->after('product_category_id');

            $table->foreign('product_category_id', 'cdi_category_fk')->references('id')->on('product_categories')->nullOnDelete();
            $table->foreign('product_id', 'cdi_product_fk')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('challan_direct_items', function (Blueprint $table) {
            $table->dropForeign('cdi_category_fk');
            $table->dropForeign('cdi_product_fk');
            $table->dropColumn(['unit', 'treatment', 'product_category_id', 'product_id']);
        });
    }
};