<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use SoftDeletes;

    protected $table = 'locations';

    protected $fillable = [
        'vendor_id', 'name', 'address', 'contact_person', 'phone',
        'in_charge_user_id', 'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function vendor()   { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function inCharge() { return $this->belongsTo(User::class, 'in_charge_user_id'); }

    public function scopeActive($q)   { return $q->where('is_active', true); }
    public function scopeOwn($q)      { return $q->whereNull('vendor_id'); }
    public function scopeVendorSide($q) { return $q->whereNotNull('vendor_id'); }

    public function isOwnWarehouse(): bool
    {
        return is_null($this->vendor_id);
    }

    public static function defaultId(): ?int
    {
        return static::where('is_default', true)->value('id');
    }
}