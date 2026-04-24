<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchBatchItem extends Model
{
    protected $fillable = ['dispatch_batch_id', 'design_id', 'size', 'quantity'];

    public function batch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DispatchBatch::class, 'dispatch_batch_id');
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
