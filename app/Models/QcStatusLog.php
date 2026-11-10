<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** History of QC line status changes (who, when, from → to, remarks). */
class QcStatusLog extends Model
{
    protected $fillable = ['qc_inspection_id', 'qc_inspection_item_id', 'from_status', 'to_status', 'remarks', 'changed_by'];

    public function inspection() { return $this->belongsTo(QcInspection::class, 'qc_inspection_id'); }
    public function item()       { return $this->belongsTo(QcInspectionItem::class, 'qc_inspection_item_id'); }
    public function user()       { return $this->belongsTo(User::class, 'changed_by'); }
}
