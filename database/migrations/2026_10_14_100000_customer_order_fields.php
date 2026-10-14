<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Order (Job) — Part 2:
 *  - header: currency + exchange rate, consignee (ship-to name)
 *  - lines: collection, pattern #, design, colour scheme; packaging (put-up SKU) becomes optional
 *  - customer-order Terms & Conditions (snapshot rows, like PO terms)
 *  - repairs job_order_items.job_id, which pointed at Laravel's queue `jobs` table
 * Safe to re-run: every step checks first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('job_orders', 'currency'))       $t->string('currency', 3)->default('PKR')->after('expected_date');
            if (!Schema::hasColumn('job_orders', 'exchange_rate'))  $t->decimal('exchange_rate', 12, 4)->default(1)->after('currency');
            if (!Schema::hasColumn('job_orders', 'consignee_name')) $t->string('consignee_name', 191)->nullable()->after('buyer_name');
        });

        Schema::table('job_order_items', function (Blueprint $t) {
            if (!Schema::hasColumn('job_order_items', 'collection'))   $t->string('collection', 60)->nullable()->after('job_id');
            if (!Schema::hasColumn('job_order_items', 'pattern_code')) $t->string('pattern_code', 120)->nullable()->after('collection');
            if (!Schema::hasColumn('job_order_items', 'design'))       $t->string('design', 191)->nullable()->after('pattern_code');
            if (!Schema::hasColumn('job_order_items', 'color_scheme')) $t->string('color_scheme', 191)->nullable()->after('design');
        });

        // job_id must point at job_orders, not the queue table
        $fk = $this->foreignKey('job_order_items', 'job_id');
        if ($fk && $this->referencedTable('job_order_items', $fk) !== 'job_orders') {
            Schema::table('job_order_items', fn(Blueprint $t) => $t->dropForeign($fk));
            $fk = null;
        }
        if (!$fk) {
            DB::table('job_order_items')->whereNotIn('job_id', DB::table('job_orders')->select('id'))->delete(); // orphans block the FK
            Schema::table('job_order_items', fn(Blueprint $t) => $t->foreign('job_id', 'ji_job_order_fk')->references('id')->on('job_orders')->cascadeOnDelete());
        }

        // Packaging (put-up SKU) is optional: the customer PO may not name it yet
        $pfk = $this->foreignKey('job_order_items', 'product_id');
        if ($pfk) Schema::table('job_order_items', fn(Blueprint $t) => $t->dropForeign($pfk));
        Schema::table('job_order_items', fn(Blueprint $t) => $t->unsignedBigInteger('product_id')->nullable()->change());
        Schema::table('job_order_items', fn(Blueprint $t) => $t->foreign('product_id', 'ji_product_fk')->references('id')->on('products')->nullOnDelete());

        if (!Schema::hasTable('job_order_terms')) {
            Schema::create('job_order_terms', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('job_id');
                $t->unsignedBigInteger('term_id')->nullable();
                $t->string('title');
                $t->text('description')->nullable();
                $t->timestamps();
                $t->foreign('job_id', 'jot_job_fk')->references('id')->on('job_orders')->cascadeOnDelete();
                $t->foreign('term_id', 'jot_term_fk')->references('id')->on('terms_and_conditions')->nullOnDelete();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_terms');
        Schema::table('job_order_items', function (Blueprint $t) {
            foreach (['collection', 'pattern_code', 'design', 'color_scheme'] as $c) if (Schema::hasColumn('job_order_items', $c)) $t->dropColumn($c);
        });
        Schema::table('job_orders', function (Blueprint $t) {
            foreach (['currency', 'exchange_rate', 'consignee_name'] as $c) if (Schema::hasColumn('job_orders', $c)) $t->dropColumn($c);
        });
    }

    private function foreignKey(string $table, string $column): ?string
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');
    }

    private function referencedTable(string $table, string $constraint): ?string
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)->value('REFERENCED_TABLE_NAME');
    }
};
