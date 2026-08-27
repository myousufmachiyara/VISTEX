<?php
// app/Models/HeadOfAccounts.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeadOfAccounts extends Model
{
    protected $table = 'head_of_accounts';
    protected $fillable = ['name'];

    public function subHeads() { return $this->hasMany(SubHeadOfAccounts::class, 'hoa_id'); }
}