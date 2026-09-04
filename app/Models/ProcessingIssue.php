<?php
// app/Models/ProcessingIssue.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessingIssue extends Model
{
    use SoftDeletes;

    protected $table = 'processing_issues';

    protected $fillable = [
        'issue_no', 'purchase_order_id', 'location_id', 'lot_no',
        'issue_date', 'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'attachments' => 'array',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function location()      { return $this->belongsTo(Location::class, 'location_id'); }
    public function items()         { return $this->hasMany(ProcessingIssueItem::class, 'processing_issue_id'); }
}