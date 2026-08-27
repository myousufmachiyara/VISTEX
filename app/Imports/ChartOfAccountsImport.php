<?php

namespace App\Imports;

use App\Models\ChartOfAccounts;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ChartOfAccountsImport implements
    ToModel,
    WithHeadingRow,
    WithValidation
{
    /**
     * Import statistics.
     */
    public int $imported = 0;

    public int $skipped = 0;

    public array $errors = [];


    /**
     * Normalize values before validation.
     */
    public function prepareForValidation($data, $index)
    {
        /*
        |--------------------------------------------------------------------------
        | Account Code
        |--------------------------------------------------------------------------
        */

        if (
            isset($data['account_code']) &&
            $data['account_code'] !== null
        ) {
            $data['account_code'] = trim(
                (string) $data['account_code']
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Sub Head ID
        |--------------------------------------------------------------------------
        */

        if (
            isset($data['shoa_id']) &&
            $data['shoa_id'] !== ''
        ) {
            $data['shoa_id'] = (int) $data['shoa_id'];
        }


        /*
        |--------------------------------------------------------------------------
        | Text Fields
        |--------------------------------------------------------------------------
        */

        foreach ([
            'name',
            'account_type',
            'remarks',
            'address',
            'contact_no',
            'source_client_code',
        ] as $field) {

            if (
                isset($data[$field]) &&
                $data[$field] !== null
            ) {
                $data[$field] = trim(
                    (string) $data[$field]
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Decimal Fields
        |--------------------------------------------------------------------------
        */

        foreach ([
            'receivables',
            'payables',
            'credit_limit',
            'opening_balance',
        ] as $field) {

            if (
                isset($data[$field]) &&
                $data[$field] !== ''
            ) {
                $data[$field] = (float) str_replace(
                    ',',
                    '',
                    (string) $data[$field]
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Opening Date
        |--------------------------------------------------------------------------
        */

        if (
            !isset($data['opening_date']) ||
            $data['opening_date'] === ''
        ) {
            $data['opening_date'] =
                now()->format('Y-m-d');
        }


        return $data;
    }


    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [

            'account_code' => [
                'required',
            ],

            'shoa_id' => [
                'required',
                'integer',
                'exists:sub_head_of_accounts,id',
            ],

            'name' => [
                'required',
            ],

            'account_type' => [
                'nullable',
            ],

            'receivables' => [
                'nullable',
                'numeric',
            ],

            'payables' => [
                'nullable',
                'numeric',
            ],

            'credit_limit' => [
                'nullable',
                'numeric',
            ],

            'opening_balance' => [
                'nullable',
                'numeric',
            ],

            'opening_date' => [
                'nullable',
                'date',
            ],

            'remarks' => [
                'nullable',
            ],

            'address' => [
                'nullable',
            ],

            'contact_no' => [
                'nullable',
            ],
        ];
    }


    /**
     * Create account.
     */
    public function model(array $row)
    {
        /*
        |--------------------------------------------------------------------------
        | Normalize Account Code
        |--------------------------------------------------------------------------
        */

        $accountCode = trim(
            (string) ($row['account_code'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | Empty Account Code
        |--------------------------------------------------------------------------
        */

        if ($accountCode === '') {

            $this->skipped++;

            $this->errors[] = [
                'row' => 'unknown',
                'account_code' => '',
                'message' => 'Account code is empty.',
            ];

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Check Existing Account
        |--------------------------------------------------------------------------
        */

        $existing = ChartOfAccounts::withTrashed()
            ->where('account_code', $accountCode)
            ->first();


        if ($existing) {

            $this->skipped++;

            $this->errors[] = [
                'row' => 'unknown',
                'account_code' => $accountCode,
                'message' => 'Account code already exists.',
            ];

            Log::warning(
                '[COA Import] Account already exists',
                [
                    'account_code' => $accountCode,
                    'existing_id' => $existing->id,
                    'existing_name' => $existing->name,
                ]
            );

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Create Account
        |--------------------------------------------------------------------------
        */

        $account = new ChartOfAccounts([

            'account_code' => $accountCode,

            'shoa_id' => (int) $row['shoa_id'],

            'name' => trim(
                (string) $row['name']
            ),

            'account_type' =>
                !empty($row['account_type'])
                    ? trim((string) $row['account_type'])
                    : null,

            'receivables' =>
                $this->decimal(
                    $row['receivables'] ?? 0
                ),

            'payables' =>
                $this->decimal(
                    $row['payables'] ?? 0
                ),

            'credit_limit' =>
                $this->decimal(
                    $row['credit_limit'] ?? 0
                ),

            'opening_balance' =>
                $this->decimal(
                    $row['opening_balance'] ?? 0
                ),

            'opening_date' =>
                !empty($row['opening_date'])
                    ? $row['opening_date']
                    : now()->format('Y-m-d'),

            'remarks' =>
                !empty($row['remarks'])
                    ? trim((string) $row['remarks'])
                    : null,

            'address' =>
                !empty($row['address'])
                    ? trim((string) $row['address'])
                    : null,

            'contact_no' =>
                !empty($row['contact_no'])
                    ? trim((string) $row['contact_no'])
                    : null,

            'created_by' => auth()->id(),

            'updated_by' => auth()->id(),
        ]);


        /*
        |--------------------------------------------------------------------------
        | Count Successful Import
        |--------------------------------------------------------------------------
        */

        $this->imported++;


        return $account;
    }


    /**
     * Convert decimal safely.
     */
    private function decimal($value): float
    {
        if (
            $value === null ||
            $value === ''
        ) {
            return 0;
        }

        return (float) str_replace(
            ',',
            '',
            (string) $value
        );
    }
}