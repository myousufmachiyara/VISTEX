<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderTerm extends Model
{
    protected $table = 'purchase_order_terms';
    public $timestamps = true;

    protected $fillable = ['purchase_order_id', 'term_id', 'title', 'description'];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function term()          { return $this->belongsTo(TermAndCondition::class, 'term_id'); }
}