<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Snapshot of a Terms & Conditions row as it was when the customer order was saved
class JobOrderTerm extends Model
{
    protected $table = 'job_order_terms';
    protected $fillable = ['job_id', 'term_id', 'title', 'description'];

    public function job()  { return $this->belongsTo(Job::class, 'job_id'); }
    public function term() { return $this->belongsTo(TermAndCondition::class, 'term_id'); }
}
