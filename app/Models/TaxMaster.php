<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxMaster extends Model
{
    protected $table = 'tax_masters';
    protected $fillable = ['name', 'rate', 'is_default', 'is_active'];
    protected $casts = ['rate' => 'decimal:2', 'is_default' => 'boolean', 'is_active' => 'boolean'];

    public function scopeActive($q) { return $q->where('is_active', true); }
}