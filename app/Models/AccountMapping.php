<?php
// app/Models/AccountMapping.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountMapping extends Model
{
    protected $table = 'account_mappings';
    protected $fillable = ['role_key', 'account_id'];

    public function account() { return $this->belongsTo(ChartOfAccounts::class, 'account_id'); }
}