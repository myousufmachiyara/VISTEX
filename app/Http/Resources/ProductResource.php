<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'sku'              => $this->sku,
            'description'      => $this->description,
            'category_id'      => $this->category_id,
            'category'         => $this->category?->name,
            'subcategory_id'   => $this->subcategory_id,
            'subcategory'      => $this->subcategory?->name,
            'unit_id'          => $this->measurement_unit,
            'unit_name'        => $this->measurementUnit?->name,
            'unit_shortcode'   => $this->measurementUnit?->shortcode,
            'opening_stock'    => (float) $this->opening_stock,
            'current_stock'    => (float) $this->current_stock,
            'selling_price'    => (float) $this->selling_price,
            'weighted_avg_cost'=> (float) $this->weighted_average_cost,
            'is_active'        => (bool) $this->is_active,
        ];
    }
}