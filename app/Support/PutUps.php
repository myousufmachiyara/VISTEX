<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Vistex put-ups (sellable SKUs) and the fabric each piece consumes.
 * Used by the seed migration and DatabaseSeeder; safe to run any number of times.
 */
class PutUps
{
    // [sku, name, group, type, meters per piece, size in inches]
    public static function all(): array
    {
        $rows = [];
        foreach ([6 => 5.4864, 8 => 7.3152, 10 => 9.1440, 12 => 10.9728, 15 => 13.7160, 16 => 14.6304, 18 => 16.4592, 25 => 22.8600] as $yd => $m) {
            $rows[] = [sprintf('PU-DR-%02dYD', $yd), "$yd yd D/R", 'Bolts', 'D/R', $m, null];
        }
        $rows[] = ['PU-ROT-60YD', '60 yd ROT', 'Bolts', 'ROT', 54.8640, null];

        foreach ([1 => 0.9144, 2 => 1.8288, 3 => 2.7432, 4 => 3.6576, 5 => 4.5720] as $yd => $m) {
            $rows[] = [sprintf('PU-FF-%02dYD', $yd), "$yd yd Flat Fold", 'Flat Fold', 'Flat Fold', $m, null];
        }

        for ($pc = 1; $pc <= 24; $pc++) {
            $rows[] = [sprintf('PU-FQ-%02dPC', $pc), "$pc PC FQ", 'Fat Quarter Bundles', 'FQ', round($pc * 0.23, 4), null];
        }

        $rows[] = ['PU-PC-CHARM-42', 'Charm Square (42 pc)',         'Other Precuts', 'Precut', 0.7600, '5x5'];
        $rows[] = ['PU-PC-JELLY-40', 'Jelly Roll / Roll Up (40 pc)', 'Other Precuts', 'Precut', 2.6400, '2.5x42'];
        $rows[] = ['PU-PC-TENSQ-42', 'Ten Square (42 pc)',           'Other Precuts', 'Precut', 2.6900, '10x10'];

        return $rows;
    }

    /** Adds missing put-ups to the SKU category. Returns how many were added (0 if the SKU category or Pieces unit doesn't exist yet). */
    public static function seed(): int
    {
        $categoryId = DB::table('product_categories')->where('code', 'sku')->value('id');
        $unitId = DB::table('measurement_units')->whereIn('shortcode', ['pcs', 'pc'])->value('id')
            ?? DB::table('measurement_units')->where('name', 'like', 'Piece%')->value('id');
        if (!$categoryId || !$unitId) return 0;

        $now = now();
        $added = 0;
        foreach (self::all() as [$sku, $name, $group, $type, $meters, $size]) {
            if (DB::table('products')->where('sku', $sku)->exists()) continue;

            DB::table('products')->insert([
                'category_id'      => $categoryId,
                'name'             => $name,
                'sku'              => $sku,
                'description'      => "Put-up: {$group} — " . rtrim(rtrim(number_format($meters, 4, '.', ''), '0'), '.') . ' m per piece' . ($size ? " ({$size}\")" : ''),
                'attributes'       => json_encode(array_filter([
                    'put_up_group'    => $group,
                    'put_up_type'     => $type,
                    'meters_per_unit' => number_format($meters, 4, '.', ''),
                    'size_inches'     => $size,
                ], fn($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'opening_stock'    => 0,
                'selling_price'    => 0,
                'measurement_unit' => $unitId,
                'is_active'        => true,
                'track_lots'       => false,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            $added++;
        }
        return $added;
    }
}
