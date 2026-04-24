<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionAssignmentItem extends Model
{
    protected $fillable = ['production_assignment_id', 'size', 'quantity'];

    public function assignment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductionAssignment::class, 'production_assignment_id');
    }
}
