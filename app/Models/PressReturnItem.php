<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PressReturnItem extends Model
{
    protected $fillable = ['press_return_id', 'design_id', 'size', 'quantity', 'original_quantity'];

    public function pressReturn(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PressReturn::class, 'press_return_id');
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
