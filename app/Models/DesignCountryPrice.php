<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignCountryPrice extends Model
{
    protected $fillable = ['design_id', 'country', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
