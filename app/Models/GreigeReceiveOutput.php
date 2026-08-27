<?php
// app/Models/GreigeReceiveOutput.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GreigeReceiveOutput extends Model
{
    protected $table = 'greige_receive_outputs';
    protected $fillable = ['greige_receive_id', 'greige_product_id', 'quantity_output', 'weaving_rate', 'weaving_charge'];
    protected $casts = ['quantity_output' => 'decimal:3', 'weaving_rate' => 'decimal:4', 'weaving_charge' => 'decimal:2'];

    public function greigeReceive()  { return $this->belongsTo(GreigeReceive::class, 'greige_receive_id'); }
    public function greigeProduct()  { return $this->belongsTo(Product::class, 'greige_product_id'); }
}