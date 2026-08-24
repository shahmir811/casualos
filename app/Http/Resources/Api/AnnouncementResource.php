<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Wraps an Illuminate\Notifications\DatabaseNotification row written by
 * AnnouncementNotification::toDatabase(). $this->data is already an array
 * (DatabaseNotification casts the `data` column to array).
 */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Rows written before multi-image support (2026-08-19) stored a
        // single `image_path` string; newer rows store `image_paths` (array).
        // Normalize both into one list here so the API contract never has
        // to distinguish old vs. new sends.
        $imagePaths = $this->data['image_paths']
            ?? (isset($this->data['image_path']) ? [$this->data['image_path']] : []);

        return [
            'id'         => $this->id,
            'title'      => $this->data['title'] ?? null,
            'body'       => $this->data['body'] ?? null,
            // Kept for backward compatibility with app builds that only read
            // a single image; always the first of `image_urls`.
            'image_url'  => isset($imagePaths[0]) ? Storage::url($imagePaths[0]) : null,
            'image_urls' => array_map(fn ($path) => Storage::url($path), $imagePaths),
            'sent_at'    => $this->created_at,
            'read_at'    => $this->read_at,
        ];
    }
}
