<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueHdImage extends Model
{
    protected $fillable = [
        'catalogue_id', 's3_path', 'thumbnail_path', 'original_filename',
        'file_size', 'mime_type', 'uploaded_by',
    ];

    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
