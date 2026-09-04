<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    // Generates V{YY}-{CODE}-{NNNNN}, atomically incrementing per module+year.
    public function next(string $moduleKey, string $table, string $column, ?string $prefixCode = null): string
    {
        return DB::transaction(function () use ($moduleKey, $table, $column, $prefixCode) {

            $code = $prefixCode ?? $this->defaultCode($moduleKey);
            $year = now()->format('y'); // 26 for 2026

            $prefix = "V{$year}-{$code}-";

            $existing = DB::table($table)
                ->where($column, 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByDesc($column)
                ->value($column);

            $nextSeq = 1;
            if ($existing) {
                $parts = explode('-', $existing);
                $lastNum = (int) end($parts);
                $nextSeq = $lastNum + 1;
            }

            return sprintf('%s%05d', $prefix, $nextSeq);
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
            'weaving_order'    => 'WPO',  // type=weaving
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