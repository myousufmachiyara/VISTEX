<?php

namespace App\Services;

use App\Models\AccountMapping;

class AccountMappingService
{
    public function accountId(string $roleKey): ?int
    {
        return AccountMapping::where('role_key', $roleKey)->value('account_id');
    }
}