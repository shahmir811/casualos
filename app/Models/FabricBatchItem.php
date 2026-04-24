<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FabricBatchItem extends Model
{
    protected $fillable = ['fabric_batch_id', 'design_id', 'quantity'];

    public function batch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FabricBatch::class, 'fabric_batch_id');
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
