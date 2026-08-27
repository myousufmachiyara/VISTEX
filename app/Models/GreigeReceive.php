<?php
// app/Models/GreigeReceive.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GreigeReceive extends Model
{
    use SoftDeletes;

    protected $table = 'greige_receives';

    protected $fillable = [
        'receive_no', 'cpo_id', 'receive_date', 'vendor_challan_no', 'is_final_receiving',
        'yarn_cost_amount', 'weaving_charge_amount', 'total_amount',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
        'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'receive_date'          => 'date',
        'is_final_receiving'    => 'boolean',
        'approved_at'           => 'datetime',
        'yarn_cost_amount'      => 'decimal:2',
        'weaving_charge_amount' => 'decimal:2',
        'total_amount'          => 'decimal:2',
        'attachments'           => 'array',
    ];

    public function cpo()          { return $this->belongsTo(ConversionPurchaseOrder::class, 'cpo_id'); }
    public function yarnConsumed() { return $this->hasMany(GreigeReceiveYarnConsumed::class, 'greige_receive_id'); }
    public function outputs()      { return $this->hasMany(GreigeReceiveOutput::class, 'greige_receive_id'); }
    public function approver()     { return $this->belongsTo(User::class, 'approved_by'); }

    // Greige category in-charge or superadmin approves
    public function canBeApprovedBy(User $user): bool
    {
        if ($user->hasRole('superadmin')) return true;

        $greigeCategory = ProductCategory::where('code', 'greige')->first();
        if (!$greigeCategory) return false;

        return CategoryIncharge::where('product_category_id', $greigeCategory->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}