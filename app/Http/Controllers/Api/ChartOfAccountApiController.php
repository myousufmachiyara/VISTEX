<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccounts;
use App\Http\Resources\ChartOfAccountResource;

class ChartOfAccountApiController extends Controller
{
    // GET /api/chart-of-accounts  (read-only list for mobile)
    public function index()
    {
        $accounts = ChartOfAccounts::with('subHeadOfAccount.headOfAccount')
            ->orderBy('account_code')
            ->get();

        return ChartOfAccountResource::collection($accounts);
    }
}