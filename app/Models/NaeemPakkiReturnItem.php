<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NaeemPakkiReturnItem extends Model
{
    protected $fillable = ['naeem_pakki_return_id', 'size', 'quantity'];

    public function return(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NaeemPakkiReturn::class, 'naeem_pakki_return_id');
    }
}
