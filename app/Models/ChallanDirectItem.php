<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChallanDirectItem extends Model
{
    protected $table = 'challan_direct_items';
    protected $fillable = ['challan_id', 'description', 'quantity', 'unit_price', 'expense_account_id', 'amount'];
    protected $casts = ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];

    public function challan()        { return $this->belongsTo(Challan::class, 'challan_id'); }
    public function expenseAccount() { return $this->belongsTo(ChartOfAccounts::class, 'expense_account_id'); }
}