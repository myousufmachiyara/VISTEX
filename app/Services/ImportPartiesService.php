<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Vendor;

class ImportPartiesService
{
    // Maps lowercase header name -> our field name. Extra columns in the
    // file (like Xero's "source_accounts") are simply ignored.
    private const FIELD_MAP = [
        'name'                  => 'name',
        'vendor_type'           => 'vendor_type',
        'phone'                 => 'phone',
        'email'                 => 'email',
        'contact_person'        => 'contact_person',
        'address'               => 'address',
        'city'                  => 'city',
        'tax_id_number'         => 'tax_id_number',
        'payment_terms_type'    => 'payment_terms_type',
        'payment_days'          => 'payment_days',
        'currency'              => 'currency',
        'opening_balance'       => 'opening_balance',
        'opening_type'          => 'opening_type',
        'opening_balance_date'  => 'opening_balance_date',
        'notes'                 => 'notes',
        'is_active'             => 'is_active',
    ];

    public function import(string $filePath, string $type, ?int $userId = null): array
    {
        $modelClass = $type === 'customer' ? Customer::class : Vendor::class;

        $handle = fopen($filePath, 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) ? "\t" : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            throw new \Exception('Could not read a header row from the file.');
        }
        $header = array_map(fn($h) => strtolower(trim($h, "\xEF\xBB\xBF \"")), $header);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $rowNum   = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim($row[0]) === '') continue; // blank line

            $data = array_combine(array_slice($header, 0, count($row)), $row);
            if ($data === false) { $skipped++; continue; }

            $name = trim($data['name'] ?? '');
            if (!$name) { $skipped++; continue; }

            if ($modelClass::where('name', $name)->exists()) {
                $skipped++;
                $errors[] = "Row {$rowNum}: '{$name}' already exists — skipped.";
                continue;
            }

            $payload = [
                'name'                  => $name,
                'phone'                 => $this->clean($data['phone'] ?? null),
                'email'                 => $this->clean($data['email'] ?? null),
                'contact_person'        => $this->clean($data['contact_person'] ?? null),
                'address'               => $this->clean($data['address'] ?? null),
                'city'                  => $this->clean($data['city'] ?? null),
                'tax_id_number'         => $this->clean($data['tax_id_number'] ?? null),
                'payment_terms_type'    => $this->clean($data['payment_terms_type'] ?? null) ?? 'days_after_invoice',
                'payment_days'          => (int) ($data['payment_days'] ?? 0),
                'currency'              => $this->clean($data['currency'] ?? null) ?? 'PKR',
                'opening_balance'       => (float) ($data['opening_balance'] ?? 0),
                'opening_type'          => $this->clean($data['opening_type'] ?? null) ?? ($type === 'customer' ? 'receivable' : 'payable'),
                'opening_balance_date'  => $this->cleanDate($data['opening_balance_date'] ?? null),
                'notes'                 => $this->clean($data['notes'] ?? null),
                'is_active'             => isset($data['is_active']) ? (bool) ((int) $data['is_active']) : true,
                'created_by'            => $userId,
                'updated_by'            => $userId,
            ];

            if ($type === 'vendor') {
                $payload['vendor_type'] = $this->clean($data['vendor_type'] ?? null) ?? 'other';
            } else {
                $payload['credit_limit'] = 0;
            }

            try {
                $modelClass::create($payload);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Row {$rowNum} ('{$name}'): " . $e->getMessage();
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function clean(?string $v): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    private function cleanDate(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        try {
            return \Carbon\Carbon::parse($v)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }
}