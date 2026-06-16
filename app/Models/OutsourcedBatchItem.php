<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutsourcedBatchItem extends Model
{
    protected $fillable = ['outsourced_batch_id', 'design_id', 'size', 'quantity', 'original_quantity'];

    public function batch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OutsourcedBatch::class, 'outsourced_batch_id');
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
