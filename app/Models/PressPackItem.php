<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PressPackItem extends Model
{
    protected $table = 'press_pack_record_items';

    protected $fillable = ['press_pack_record_id', 'size', 'quantity'];

    public function record(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PressPack::class, 'press_pack_record_id');
    }
}
