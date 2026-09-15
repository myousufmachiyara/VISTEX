<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class PurchaseOrderApiController extends Controller
{
    public function index(Request $request)
    {
        $orders = PurchaseOrder::with('vendor', 'category')
            ->visibleTo($request->user())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->type))
            ->orderByDesc('order_date')
            ->get();

        return response()->json($orders->map(fn($o) => [
            'id' => $o->id, 'order_no' => $o->order_no, 'type' => $o->type, 'status' => $o->status,
            'vendor_name' => $o->vendor->name ?? '', 'total_amount' => $o->total_amount,
            'order_date' => $o->order_date->format('Y-m-d'),
        ]));
    }

    public function show($id)
    {
        $o = PurchaseOrder::with('vendor', 'category', 'items.product', 'broker', 'tax')->findOrFail($id);

        return response()->json([
            'id' => $o->id, 'order_no' => $o->order_no, 'type' => $o->type, 'status' => $o->status,
            'vendor_name' => $o->vendor->name ?? '', 'category_name' => $o->category->name ?? '',
            'order_date' => $o->order_date->format('Y-m-d'),
            'expected_date' => $o->expected_date?->format('Y-m-d'),
            'subtotal' => $o->subtotal, 'gst_amount' => $o->gst_amount, 'total_amount' => $o->total_amount,
            'broker_name' => $o->broker->name ?? null,
            'payment_term_type' => $o->payment_term_type, 'payment_term_days' => $o->payment_term_days,
            'items' => $o->items->map(fn($i) => [
                'product_name' => $i->product->name ?? $i->description ?? '',
                'quantity' => $i->quantity, 'rate' => $i->rate, 'amount' => $i->amount,
                'quantity_received' => $i->quantity_received,
            ]),
        ]);
    }
}