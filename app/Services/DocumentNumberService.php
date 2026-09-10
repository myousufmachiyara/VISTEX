<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    // Generates V{YY}-{CODE}-{NNNNN}, atomically incrementing per module+year.
    // public function next(string $moduleKey, string $table, string $column, ?string $prefixCode = null): string
    // {
    //     return DB::transaction(function () use ($moduleKey, $table, $column, $prefixCode) {

    //         $code = $prefixCode ?? $this->defaultCode($moduleKey);
    //         $year = now()->format('y'); // 26 for 2026

    //         $prefix = "V{$year}-{$code}-";

    //         $existing = DB::table($table)
    //             ->where($column, 'like', "{$prefix}%")
    //             ->lockForUpdate()
    //             ->orderByDesc($column)
    //             ->value($column);

    //         $nextSeq = 1;
    //         if ($existing) {
    //             $parts = explode('-', $existing);
    //             $lastNum = (int) end($parts);
    //             $nextSeq = $lastNum + 1;
    //         }

    //         return sprintf('%s%05d', $prefix, $nextSeq);
    //     });
    // }

    public function next(string $moduleKey, string $table, string $column, ?string $prefixCode = null): string
    {
        return DB::transaction(function () use ($moduleKey, $table, $column, $prefixCode) {

            $code = $prefixCode ?? $this->defaultCode($moduleKey);
            $year = now()->format('y');

            $prefix = "VTX-{$code}-";
            $suffix = "-{$year}";

            // Match existing numbers with this prefix AND this year's suffix,
            // so sequence resets per year while keeping VTX-{CAT} constant.
            $pattern = "{$prefix}%{$suffix}";

            $existing = DB::table($table)
                ->where($column, 'like', $pattern)
                ->lockForUpdate()
                ->get([$column])
                ->map(function ($row) use ($column, $prefix, $suffix) {
                    $val = $row->{$column};
                    $middle = substr($val, strlen($prefix), -strlen($suffix));
                    return (int) $middle;
                })
                ->max();

            $nextSeq = ($existing ?? 0) + 1;

            return sprintf('%s%05d%s', $prefix, $nextSeq, $suffix);
        });
    }

    private function defaultCode(string $moduleKey): string
    {
        $map = [
            'voucher_journal'  => 'JV',
            'voucher_payment'  => 'PV',
            'voucher_receipt'  => 'RV',
            'voucher_contra'   => 'CV',
            'voucher_system'   => 'SV',
            'purchase_order'   => 'PO',   // type=purchase
            'weaving_order'    => 'CPO',  // type=weaving
            'processing_order' => 'PPO',  // type=processing
            'purchase_receiving' => 'GRN',
            'yarn_issue'       => 'YI',
            'greige_receive'   => 'GR',
            'forecast'         => 'FC',
            'job'              => 'JOB',
            'pdc'              => 'PDC',
            'challan'          => 'CHL',   // ← add to DocumentNumberService::defaultCode() map
            'stock_movement'   => 'SM',   // ← DocumentNumberService map
            'processing_issue' => 'PPI',   // ← DocumentNumberService map
        ];

        return $map[$moduleKey] ?? strtoupper(substr($moduleKey, 0, 3));
    }
}