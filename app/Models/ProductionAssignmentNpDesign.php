<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionAssignmentNpDesign extends Model
{
    protected $fillable = ['production_assignment_id', 'design_id', 'quantity', 'per_piece_price'];

    protected $casts = ['per_piece_price' => 'decimal:2'];

    public function assignment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductionAssignment::class, 'production_assignment_id');
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function totalCost(): float
    {
        return $this->quantity * (float) $this->per_piece_price;
    }
}
