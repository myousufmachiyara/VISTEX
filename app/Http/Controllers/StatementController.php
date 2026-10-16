<?php

namespace App\Http\Controllers;

use App\Services\StatementService;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    public function __construct(private StatementService $service) {}

    public function index(Request $request)
    {
        $request->validate(['party_type' => 'nullable|in:customer,vendor,broker', 'party_id' => 'nullable|integer', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $type = $request->input('party_type', 'customer');
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $parties = $this->service->parties($type);
        $result = $request->filled('party_id') ? $this->service->build($type, (int) $request->party_id, $from, $to) : null;
        $view = $request->boolean('print') ? 'statements.print' : 'statements.index';
        return view($view, compact('type', 'from', 'to', 'parties', 'result'));
    }
}
