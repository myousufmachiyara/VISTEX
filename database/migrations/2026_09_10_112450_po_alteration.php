<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renames — only if old name exists and new name doesn't already
        if (Schema::hasColumn('purchase_orders', 'weft_shrinkage_pct') && !Schema::hasColumn('purchase_orders', 'weft_conversion_pct')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->renameColumn('weft_shrinkage_pct', 'weft_conversion_pct');
            });
        }

        if (Schema::hasColumn('purchase_orders', 'weaving_rate') && !Schema::hasColumn('purchase_orders', 'weaving_cost_per_meter')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->renameColumn('weaving_rate', 'weaving_cost_per_meter');
            });
        }

        if (Schema::hasColumn('purchase_orders', 'sizing_per_meter') && !Schema::hasColumn('purchase_orders', 'sizing_rate_per_meter')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->renameColumn('sizing_per_meter', 'sizing_rate_per_meter');
            });
        }

        // 2. Drop dead columns — only if they still exist
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'warp_shrinkage_pct')) {
                $table->dropColumn('warp_shrinkage_pct');
            }
            if (Schema::hasColumn('purchase_orders', 'rate_per_meter')) {
                $table->dropColumn('rate_per_meter');
            }
        });

        // 3. Add new columns — only if missing
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'reed')) {
                $table->decimal('reed', 10, 4)->nullable()->after('greige_product_id');
            }
            if (!Schema::hasColumn('purchase_orders', 'reed_space')) {
                $table->decimal('reed_space', 12, 4)->nullable()->after('reed_count');
            }
            if (!Schema::hasColumn('purchase_orders', 'warping')) {
                $table->decimal('warping', 10, 4)->nullable()->default(1)->after('sizing_lbs');
            }
            if (!Schema::hasColumn('purchase_orders', 'warp_yarn_cost_price')) {
                $table->decimal('warp_yarn_cost_price', 15, 4)->nullable()->after('weft_conversion_pct');
            }
            if (!Schema::hasColumn('purchase_orders', 'weft_yarn_cost_price')) {
                $table->decimal('weft_yarn_cost_price', 15, 4)->nullable()->after('warp_yarn_cost_price');
            }
            if (!Schema::hasColumn('purchase_orders', 'warp_yarn_rate')) {
                $table->decimal('warp_yarn_rate', 15, 2)->nullable()->after('weft_yarn_cost_price');
            }
            if (!Schema::hasColumn('purchase_orders', 'weft_yarn_rate')) {
                $table->decimal('weft_yarn_rate', 15, 2)->nullable()->after('warp_yarn_rate');
            }
            if (!Schema::hasColumn('purchase_orders', 'warp_gsm')) {
                $table->decimal('warp_gsm', 10, 3)->nullable()->after('gsm');
            }
            if (!Schema::hasColumn('purchase_orders', 'weft_gsm')) {
                $table->decimal('weft_gsm', 10, 3)->nullable()->after('warp_gsm');
            }
            if (!Schema::hasColumn('purchase_orders', 'gsm_kg')) {
                $table->decimal('gsm_kg', 10, 4)->nullable()->after('weft_gsm');
            }
            if (!Schema::hasColumn('purchase_orders', 'weaving_per_meter')) {
                $table->decimal('weaving_per_meter', 15, 4)->nullable()->after('sizing_rate_per_meter');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach ([
                'reed', 'reed_space', 'warping',
                'warp_yarn_cost_price', 'weft_yarn_cost_price',
                'warp_yarn_rate', 'weft_yarn_rate',
                'warp_gsm', 'weft_gsm', 'gsm_kg',
                'weaving_per_meter',
            ] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (!Schema::hasColumn('purchase_orders', 'warp_shrinkage_pct')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->decimal('warp_shrinkage_pct', 10, 6)->nullable();
            });
        }
        if (!Schema::hasColumn('purchase_orders', 'rate_per_meter')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->decimal('rate_per_meter', 15, 4)->nullable();
            });
        }

        if (Schema::hasColumn('purchase_orders', 'weft_conversion_pct') && !Schema::hasColumn('purchase_orders', 'weft_shrinkage_pct')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->renameColumn('weft_conversion_pct', 'weft_shrinkage_pct');
            });
        }
        if (Schema::hasColumn('purchase_orders', 'weaving_cost_per_meter') && !Schema::hasColumn('purchase_orders', 'weaving_rate')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->renameColumn('weaving_cost_per_meter', 'weaving_rate');
            });
        }
        if (Schema::hasColumn('purchase_orders', 'sizing_rate_per_meter') && !Schema::hasColumn('purchase_orders', 'sizing_per_meter')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->renameColumn('sizing_rate_per_meter', 'sizing_per_meter');
            });
        }
    }
};