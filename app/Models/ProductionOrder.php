<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    public const TYPES = ['cutting' => 'Cutting', 'packing' => 'Packing'];

    protected $fillable = ['order_no', 'type', 'location_id', 'job_id', 'order_date', 'remarks', 'created_by'];
    protected $casts = ['order_date' => 'date'];

    public function lines()        { return $this->hasMany(ProductionOrderLine::class); }
    public function consumptions() { return $this->hasMany(ProductionConsumption::class); }
    public function location()     { return $this->belongsTo(Location::class); }
    public function job()          { return $this->belongsTo(Job::class, 'job_id'); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }

    public function getTypeLabelAttribute(): string { return self::TYPES[$this->type] ?? $this->type; }
}
