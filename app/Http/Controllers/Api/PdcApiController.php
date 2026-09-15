<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pdc;
use Illuminate\Http\Request;

class PdcApiController extends Controller
{
    public function index(Request $request)
    {
        $pdcs = Pdc::with('party', 'cheques')->orderByDesc('due_date')->get();

        return response()->json($pdcs->map(fn($p) => [
            'id' => $p->id, 'pdc_no' => $p->pdc_no, 'party_name' => $p->party->name ?? '',
            'amount' => $p->amount, 'pending_amount' => $p->pending_amount,
            'due_date' => $p->due_date->format('Y-m-d'),
        ]));
    }

    public function show($id)
    {
        $p = Pdc::with('party', 'cheques.bankAccount')->findOrFail($id);

        return response()->json([
            'id' => $p->id, 'pdc_no' => $p->pdc_no, 'party_name' => $p->party->name ?? '',
            'amount' => $p->amount, 'pending_amount' => $p->pending_amount,
            'due_date' => $p->due_date->format('Y-m-d'),
            'cheques' => $p->cheques->map(fn($c) => [
                'id' => $c->id, 'sequence_no' => $c->sequence_no, 'amount' => $c->amount,
                'status' => $c->status, 'cheque_no' => $c->cheque_no,
                'bank_name' => $c->bankAccount->name ?? null,
            ]),
        ]);
    }
}