<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\MeasurementUnit;
use Illuminate\Support\Str;

class ImportProductsService
{
    // Xero InventoryAssetAccount code -> our product_category code
    private const ACCOUNT_TO_CATEGORY = [
        '35000' => 'yarn',
        '35001' => 'greige',
        '35003' => 'packaging',
        '35005' => 'leftover',
        '35006' => 'rejection',
        '35007' => 'sku',
    ];

    // categories explicitly excluded from import (Cut Pcs / per-job WIP)
    private const SKIP_ACCOUNTS = ['35004'];

    public function import(string $filePath, ?int $userId = null): array
    {
        $handle = fopen($filePath, 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $header = fgetcsv($handle);
        $header = array_map(fn($h) => trim($h, "\xEF\xBB\xBF "), $header);

        $categories = ProductCategory::pluck('id', 'code');
        $units = MeasurementUnit::pluck('id', 'shortcode');

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $usedSlugs = [];

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            if ($data === false) continue;

            $itemCode = trim($data['*ItemCode'] ?? ($data['ItemCode'] ?? ''));
            $itemName = trim($data['ItemName'] ?? '');
            $assetAccount = trim($data['InventoryAssetAccount'] ?? '');

            if (!$itemCode || !$itemName) { $skipped++; continue; }
            if (in_array($assetAccount, self::SKIP_ACCOUNTS)) { $skipped++; continue; }
            if (!isset(self::ACCOUNT_TO_CATEGORY[$assetAccount])) {
                $skipped++;
                $errors[] = "'{$itemCode}': unknown asset account '{$assetAccount}'.";
                continue;
            }

            $categoryCode = self::ACCOUNT_TO_CATEGORY[$assetAccount];
            $categoryId = $categories[$categoryCode] ?? null;
            if (!$categoryId) {
                $errors[] = "'{$itemCode}': category '{$categoryCode}' not found in system.";
                $skipped++;
                continue;
            }

            $sku = $this->generateUniqueSlug($itemName, $usedSlugs);
            $quantity = (float) str_replace(',', '', trim($data['Quantity'] ?? '0'));
            $unitShortcode = $this->determineUnit($categoryCode, $itemName);
            $unitId = $units[$unitShortcode] ?? $units['pcs'] ?? null;

            try {
                Product::create([
                    'category_id'      => $categoryId,
                    'name'             => $itemName,
                    'sku'              => $sku,
                    'description'      => $data['PurchasesDescription'] ?? null,
                    'attributes'       => [],
                    'opening_stock'    => $quantity,
                    'selling_price'    => (float) str_replace(',', '', trim($data['SalesUnitPrice'] ?? '0')),
                    'measurement_unit' => $unitId,
                    'is_active'        => 1,
                    'track_lots'       => 0,
                    'created_by'       => $userId,
                    'updated_by'       => $userId,
                ]);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "'{$itemCode}': " . $e->getMessage();
            }
        }

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function generateUniqueSlug(string $name, array &$usedSlugs): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, $usedSlugs) || Product::where('sku', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        $usedSlugs[] = $slug;
        return $slug;
    }

    private function determineUnit(string $categoryCode, string $itemName): string
    {
        $lower = strtolower($itemName);

        return match ($categoryCode) {
            'yarn'       => 'lbs',
            'greige'     => 'm',
            'leftover'   => 'm',
            'rejection'  => 'm',
            'sku'        => 'yd',
            'packaging'  => match (true) {
                str_contains($lower, 'yard') || str_contains($lower, 'yrd') => 'yd',
                str_contains($lower, 'mtr') || str_contains($lower, 'meter') => 'm',
                default => 'pcs',
            },
            default => 'pcs',
        };
    }
}