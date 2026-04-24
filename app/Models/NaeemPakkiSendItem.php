<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NaeemPakkiSendItem extends Model
{
    protected $fillable = ['naeem_pakki_send_id', 'size', 'quantity'];

    public function send(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NaeemPakkiSend::class, 'naeem_pakki_send_id');
    }
}
