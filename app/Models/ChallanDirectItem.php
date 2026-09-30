<?php // app/Models/ChallanDirectItem.php  (full replacement)
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ChallanDirectItem extends Model
{
    protected $table = 'challan_direct_items';
    protected $fillable = ['challan_id', 'description', 'quantity', 'unit', 'unit_price', 'amount', 'treatment',
                           'expense_account_id', 'product_category_id', 'product_id'];
    protected $casts = ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];

    public function challan()         { return $this->belongsTo(Challan::class, 'challan_id'); }
    public function expenseAccount()  { return $this->belongsTo(ChartOfAccounts::class, 'expense_account_id'); }
    public function product()         { return $this->belongsTo(Product::class, 'product_id'); }
    public function productCategory() { return $this->belongsTo(ProductCategory::class, 'product_category_id'); }
}