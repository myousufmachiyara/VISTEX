<?php
// app/Models/ProcessingIssueItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessingIssueItem extends Model
{
    protected $table = 'processing_issue_items';
    protected $fillable = ['processing_issue_id', 'purchase_order_item_id', 'product_id', 'quantity', 'rate', 'amount'];
    protected $casts = ['quantity' => 'decimal:3', 'rate' => 'decimal:4', 'amount' => 'decimal:2'];

    public function processingIssue()  { return $this->belongsTo(ProcessingIssue::class, 'processing_issue_id'); }
    public function purchaseOrderItem(){ return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id'); }
    public function product()          { return $this->belongsTo(Product::class, 'product_id'); }
}