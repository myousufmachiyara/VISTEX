<?php
// app/Models/SubHeadOfAccounts.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubHeadOfAccounts extends Model
{
    protected $table = 'sub_head_of_accounts';
    protected $fillable = ['hoa_id', 'name'];

    public function head()   { return $this->belongsTo(HeadOfAccounts::class, 'hoa_id'); }
    public function accounts() { return $this->hasMany(ChartOfAccounts::class, 'shoa_id'); }
}