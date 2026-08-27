<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderObjection;
use Illuminate\Http\Request;

class PurchaseOrderObjectionController extends Controller
{
    public function index(Request $request)
    {
        $objections = PurchaseOrderObjection::with('purchaseOrder.vendor', 'raisedBy', 'resolvedBy')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->get();

        return view('purchase_orders.objections', compact('objections'));
    }
}