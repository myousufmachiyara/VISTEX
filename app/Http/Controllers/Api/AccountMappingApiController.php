<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountMappingService;
use Illuminate\Http\Request;

class AccountMappingApiController extends Controller
{
    public function __construct(private AccountMappingService $service) {}

    public function index()
    {
        return response()->json($this->service->all());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mappings'   => ['required', 'array'],
            'mappings.*' => ['nullable', 'exists:chart_of_accounts,id'],
        ]);

        $this->service->saveAll($data['mappings']);

        return response()->json(['success' => true]);
    }
}