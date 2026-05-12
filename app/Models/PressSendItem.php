<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PressSendItem extends Model
{
    protected $fillable = ['press_send_id', 'design_id', 'size', 'quantity'];

    public function send(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PressSend::class, 'press_send_id');
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
