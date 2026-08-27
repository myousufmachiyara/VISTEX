<?php
// app/Models/GreigeReceiveYarnConsumed.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GreigeReceiveYarnConsumed extends Model
{
    protected $table = 'greige_receive_yarn_consumed';
    protected $fillable = ['greige_receive_id', 'product_id', 'quantity', 'rate', 'amount'];
    protected $casts = ['quantity' => 'decimal:3', 'rate' => 'decimal:4', 'amount' => 'decimal:2'];

    public function greigeReceive() { return $this->belongsTo(GreigeReceive::class, 'greige_receive_id'); }
    public function product()       { return $this->belongsTo(Product::class, 'product_id'); }
}