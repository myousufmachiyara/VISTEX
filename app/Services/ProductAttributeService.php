<?php

namespace App\Services;

use App\Models\ProductCategory;

class ProductAttributeService
{
    public function schemaFor(?int $categoryId): array
    {
        if (!$categoryId) return [];

        $category = ProductCategory::find($categoryId);
        if (!$category) return [];

        return config('product_attributes.' . $category->code, []);
    }

    public function schemaForCode(string $categoryCode): array
    {
        return config('product_attributes.' . $categoryCode, []);
    }

    // Validation rules for dynamic fields — all optional/nullable text,
    // since these are descriptive spec fields, not business-critical numbers
    public function validationRules(array $schema): array
    {
        $rules = [];
        foreach ($schema as $field) {
            $rules['attributes.' . $field['key']] = 'nullable|string|max:255';
        }
        return $rules;
    }
}