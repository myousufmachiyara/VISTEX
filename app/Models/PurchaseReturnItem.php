<?php
// app/Models/PurchaseReturnItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $table = 'purchase_return_items';
    protected $fillable = ['purchase_return_id', 'purchase_receiving_item_id', 'quantity_returned'];
    protected $casts = ['quantity_returned' => 'decimal:3'];

    public function purchaseReturn()      { return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id'); }
    public function purchaseReceivingItem() { return $this->belongsTo(PurchaseReceivingItem::class, 'purchase_receiving_item_id'); }
}