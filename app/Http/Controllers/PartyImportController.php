<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PartyImportController extends Controller
{
    public function form(string $type)
    {
        abort_unless(in_array($type, ['customer', 'vendor']), 404);
        return view('parties.import', compact('type'));
    }

    public function import(Request $request, string $type)
    {
        abort_unless(in_array($type, ['customer', 'vendor']), 404);

        $request->validate([
            'import_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $modelClass = $type === 'customer' ? Customer::class : Vendor::class;

        $handle = fopen($request->file('import_file')->getRealPath(), 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) ? "\t" : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'Could not read a header row from the file.');
        }
        $header = array_map(fn($h) => strtolower(trim($h, "\xEF\xBB\xBF \"")), $header);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $rowNum   = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim($row[0]) === '') continue;

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
                'payment_days'          => (int) ($data['payment_days'] ?? 30),
                'currency'              => $this->clean($data['currency'] ?? null) ?? 'PKR',
                'opening_balance'       => (float) ($data['opening_balance'] ?? 0),
                'opening_type'          => $this->clean($data['opening_type'] ?? null) ?? ($type === 'customer' ? 'receivable' : 'payable'),
                'opening_balance_date'  => $this->cleanDate($data['opening_balance_date'] ?? null),
                'notes'                 => $this->clean($data['notes'] ?? null),
                'is_active'             => isset($data['is_active']) ? (bool) ((int) $data['is_active']) : true,
                'created_by'            => auth()->id(),
                'updated_by'            => auth()->id(),
            ];

            if ($type === 'vendor') {
                $payload['vendor_type'] = $this->clean($data['vendor_type'] ?? null) ?? 'other';
            } else {
                $payload['credit_limit'] = (float) ($data['credit_limit'] ?? 0);
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

        Log::info('[PartyImport] Completed', ['type' => $type, 'imported' => $imported, 'skipped' => $skipped, 'by' => auth()->id()]);

        $message = "Imported {$imported} " . ($type === 'customer' ? 'customers' : 'vendors') . ". Skipped {$skipped}.";
        $redirectRoute = $type === 'customer' ? 'customers.index' : 'vendors.index';

        return redirect()->route($redirectRoute)->with('success', $message)->with('import_errors', $errors);
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