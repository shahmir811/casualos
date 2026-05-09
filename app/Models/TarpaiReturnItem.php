<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarpaiReturnItem extends Model
{
    protected $fillable = ['tarpai_return_id', 'design_id', 'size', 'quantity'];

    public function return(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TarpaiReturn::class, 'tarpai_return_id');
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
