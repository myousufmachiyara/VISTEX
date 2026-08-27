<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $table = 'service_types';
    protected $fillable = ['name', 'service_cost_account_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function costAccount() { return $this->belongsTo(ChartOfAccounts::class, 'service_cost_account_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}