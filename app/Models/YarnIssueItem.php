<?php
// app/Models/YarnIssueItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YarnIssueItem extends Model
{
    protected $table = 'yarn_issue_items';
    protected $fillable = ['yarn_issue_id', 'product_id', 'quantity', 'rate', 'amount'];
    protected $casts = ['quantity' => 'decimal:3', 'rate' => 'decimal:4', 'amount' => 'decimal:2'];

    public function yarnIssue() { return $this->belongsTo(YarnIssue::class, 'yarn_issue_id'); }
    public function product()  { return $this->belongsTo(Product::class, 'product_id'); }
}