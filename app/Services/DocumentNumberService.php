<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    // Generates VTX-{CODE}-{NNNNN}, atomically incrementing per module.
    // $moduleKey: internal key, e.g. 'purchase_order', 'voucher_journal'
    // $table/$column: where to check the highest existing number, for safety
    public function next(string $moduleKey, string $table, string $column, ?string $prefixCode = null): string
    {
        return DB::transaction(function () use ($moduleKey, $table, $column, $prefixCode) {

            $code = $prefixCode ?? $this->defaultCode($moduleKey);

            $existing = DB::table($table)
                ->where($column, 'like', "VTX-{$code}-%")
                ->lockForUpdate()
                ->orderByDesc($column)
                ->value($column);

            $nextSeq = 1;
            if ($existing) {
                $parts = explode('-', $existing);
                $lastNum = (int) end($parts);
                $nextSeq = $lastNum + 1;
            }

            return sprintf('VTX-%s-%05d', $code, $nextSeq);
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
            'purchase_order'   => 'PO',
            'purchase_receiving' => 'PR',
            'cpo'              => 'CPO',
            'yarn_issue'       => 'YI',
            'greige_receive'   => 'GR',
        ];

        return $map[$moduleKey] ?? strtoupper(substr($moduleKey, 0, 3));
    }
}