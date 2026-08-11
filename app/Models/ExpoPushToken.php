<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpoPushToken extends Model
{
    protected $fillable = ['customer_id', 'token', 'platform'];

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
