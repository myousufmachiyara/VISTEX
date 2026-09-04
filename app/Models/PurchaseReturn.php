<?php
// app/Models/PurchaseReturn.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use SoftDeletes;
    protected $table = 'purchase_returns';
    protected $fillable = ['return_no', 'purchase_receiving_id', 'return_date', 'remarks', 'proof_images', 'created_by'];
    protected $casts = ['return_date' => 'date', 'proof_images' => 'array'];

    public function purchaseReceiving() { return $this->belongsTo(PurchaseReceiving::class, 'purchase_receiving_id'); }
    public function items()             { return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id'); }
}