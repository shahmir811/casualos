<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton — there is only ever one row, representing the current global
 * size-chart image. Use SizeChart::current() rather than ::create() so a
 * second row is never accidentally introduced.
 */
class SizeChart extends Model
{
    protected $table = 'size_chart';

    protected $fillable = [
        'image_path', 'original_filename', 'file_size', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
