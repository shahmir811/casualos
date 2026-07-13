<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PieceTag extends Model
{
    protected $fillable = ['order_id', 'design_id', 'size', 'barcode', 'country', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // Barcode is derived from the row's own id, so it can only be assigned after the insert.
    // Kept purely numeric (Code128C) so it stays narrow enough to fit a 2"x1" label.
    protected static function booted(): void
    {
        static::created(function (PieceTag $tag) {
            if (empty($tag->barcode)) {
                $tag->barcode = str_pad((string) $tag->id, 10, '0', STR_PAD_LEFT);
                $tag->saveQuietly();
            }
        });
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
