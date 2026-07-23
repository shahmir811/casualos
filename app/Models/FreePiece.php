<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreePiece extends Model
{
    protected $fillable = ['catalogue_id', 'design_id', 'size', 'quantity'];

    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
