<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Broker extends Model
{
    use SoftDeletes;

    protected $table = 'brokers';

    protected $fillable = [
        'name', 'phone', 'notes', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function toLookup(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}