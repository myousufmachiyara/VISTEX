<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YarnIssue extends Model
{
    use SoftDeletes;

    protected $table = 'yarn_issues';

    protected $fillable = [
        'issue_no', 'purchase_order_id', 'issue_date', 'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'attachments' => 'array',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function items()         { return $this->hasMany(YarnIssueItem::class, 'yarn_issue_id'); }
}