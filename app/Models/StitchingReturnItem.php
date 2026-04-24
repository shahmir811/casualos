<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StitchingReturnItem extends Model
{
    protected $fillable = ['stitching_return_id', 'size', 'quantity'];

    public function stitchingReturn(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StitchingReturn::class);
    }
}
