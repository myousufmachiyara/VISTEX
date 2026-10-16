<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrderLine extends Model
{
    protected $fillable = [
        'production_order_id', 'job_item_id', 'input_product_id', 'lot_no', 'grade', 'input_qty', 'put_up_product_id',
        'output_product_id', 'output_qty', 'leftover_qty', 'rejected_qty', 'input_amount', 'packaging_amount', 'output_amount',
    ];
    protected $casts = [
        'input_qty' => 'float', 'output_qty' => 'float', 'leftover_qty' => 'float', 'rejected_qty' => 'float',
        'input_amount' => 'float', 'packaging_amount' => 'float', 'output_amount' => 'float',
    ];

    public function order()         { return $this->belongsTo(ProductionOrder::class, 'production_order_id'); }
    public function inputProduct()  { return $this->belongsTo(Product::class, 'input_product_id'); }
    public function outputProduct() { return $this->belongsTo(Product::class, 'output_product_id'); }
    public function putUp()         { return $this->belongsTo(Product::class, 'put_up_product_id'); }
    public function jobItem()       { return $this->belongsTo(JobItem::class, 'job_item_id'); }
    public function consumptions()  { return $this->hasMany(ProductionConsumption::class, 'production_order_line_id'); }
}
