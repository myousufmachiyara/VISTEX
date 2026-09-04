<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermAndCondition extends Model
{
    protected $table = 'terms_and_conditions';

    protected $fillable = [
        'title', 'description', 'applies_to', 'is_default_checked',
        'sort_order', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_default_checked' => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function scopeForType($q, string $type)
    {
        return $q->where(function ($q2) use ($type) {
            $q2->where('applies_to', 'all')->orWhere('applies_to', $type);
        });
    }
}