<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarpaiSendItem extends Model
{
    protected $fillable = ['tarpai_send_id', 'size', 'quantity'];

    public function send(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TarpaiSend::class, 'tarpai_send_id');
    }
}
