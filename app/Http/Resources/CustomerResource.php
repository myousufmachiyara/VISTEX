<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'contact_person'        => $this->contact_person,
            'phone'                 => $this->phone,
            'email'                 => $this->email,
            'address'               => $this->address,
            'city'                  => $this->city,
            'ntn'                   => $this->ntn,
            'opening_balance'       => (float) $this->opening_balance,
            'opening_type'          => $this->opening_type,
            'opening_balance_date'  => optional($this->opening_balance_date)->toDateString(),
            'credit_limit'          => (float) $this->credit_limit,
            'notes'                 => $this->notes,
            'is_active'             => (bool) $this->is_active,
            'balance'               => (float) $this->balance,
        ];
    }
}