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
        $o = PurchaseOrder::with([
            'vendor', 'category', 'fromLocation', 'dropOffLocation', 'broker', 'tax',
            'items.product.measurementUnit', 'items.forecast',
            'warpProduct', 'weftProduct', 'greigeProduct',
        ])->findOrFail($id);

        $base = [
            'id' => $o->id, 'order_no' => $o->order_no, 'type' => $o->type, 'status' => $o->status,
            'revision_no' => $o->revision_no,

            'category_name' => $o->category->name ?? null,
            'vendor_name' => $o->vendor->name ?? null,
            'from_location_name' => $o->fromLocation->name ?? null,
            'drop_off_location_name' => $o->dropOffLocation->name ?? null,

            'order_date' => $o->order_date->format('d-M-Y'),
            'expected_date' => $o->expected_date?->format('d-M-Y'),

            'broker_name' => $o->broker->name ?? null,
            'broker_commission_amount' => $o->broker_commission_amount,

            'payment_term_type' => $o->payment_term_type,
            'payment_term_days' => $o->payment_term_days,
            'payment_term_note' => $o->payment_term_note,

            'gst_applicable' => $o->gst_applicable,
            'tax_name' => $o->tax->name ?? null,
            'gst_rate' => $o->gst_rate,

            'attachments' => collect($o->attachments ?? [])->map(fn($p) => asset('storage/' . $p))->values(),
            'remarks' => $o->remarks,

            'subtotal' => $o->subtotal,
            'gst_amount' => $o->gst_amount,
            'broker_commission' => $o->broker_commission_amount,
            'total_amount' => $o->total_amount,
        ];

        if ($o->type === 'weaving') {
            return response()->json(array_merge($base, [
                'warp_product_name' => $o->warpProduct->name ?? null,
                'weft_product_name' => $o->weftProduct->name ?? null,
                'greige_product_name' => $o->greigeProduct->name ?? 'Not specified yet',

                'warp_count' => $o->warp_count, 'weft_count' => $o->weft_count,
                'reed' => $o->reed, 'pick' => $o->pick, 'width' => $o->width,
                'reed_count' => $o->reed_count, 'reed_space' => $o->reed_space,
                'total_meters_required' => $o->total_meters_required,
                'rate_per_pick' => $o->rate_per_pick, 'sizing_lbs' => $o->sizing_lbs,
                'warping' => $o->warping,
                'warp_conversion_pct' => $o->warp_conversion_pct, 'weft_conversion_pct' => $o->weft_conversion_pct,
                'warp_yarn_cost_price' => $o->warp_yarn_cost_price, 'weft_yarn_cost_price' => $o->weft_yarn_cost_price,

                // Calculated Preview values
                'item_name' => $o->item_name,
                'warp_gsm' => $o->warp_gsm, 'weft_gsm' => $o->weft_gsm, 'gsm' => $o->gsm, 'gsm_kg' => $o->gsm_kg,
                'warp_consumption' => $o->warp_consumption, 'weft_consumption' => $o->weft_consumption,
                'total_yarn_weight_consumed' => $o->total_yarn_weight_consumed,
                'warp_yarn_rate' => $o->warp_yarn_rate, 'weft_yarn_rate' => $o->weft_yarn_rate,
                'total_yarn_cost_per_meter' => $o->total_yarn_cost_per_meter,
                'weaving_cost_per_meter' => $o->weaving_cost_per_meter,
                'sizing_rate_per_meter' => $o->sizing_rate_per_meter,
                'weaving_per_meter' => $o->weaving_per_meter,
                'fabric_cost' => $o->fabric_cost,
                'weaving_cost' => $o->weaving_cost,
            ]));
        }

        // purchase / processing — item grid
        return response()->json(array_merge($base, [
            'items' => $o->items->map(fn($i) => [
                'product_name' => $i->product->name ?? $i->description ?? '',
                'unit' => $i->product->measurementUnit->shortcode ?? $i->measurement_unit,
                'forecast_no' => $i->forecast->forecast_no ?? null,
                'quantity' => $i->quantity, 'rate' => $i->rate, 'amount' => $i->amount,
                'quantity_received' => $i->quantity_received,
            ]),
        ]));
    }
}