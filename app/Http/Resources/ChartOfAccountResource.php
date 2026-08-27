<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'account_code'    => $this->account_code,
            'name'            => $this->name,
            'account_type'    => $this->account_type,
            'sub_head'        => $this->subHeadOfAccount?->name,
            'head'            => $this->subHeadOfAccount?->headOfAccount?->name,
            'opening_balance' => (float) $this->opening_balance,
            'opening_date'    => optional($this->opening_date)->toDateString(),
            'remarks'         => $this->remarks,
        ];
    }
}