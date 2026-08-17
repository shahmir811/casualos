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
        return [
            'id'        => $this->id,
            'title'     => $this->data['title'] ?? null,
            'body'      => $this->data['body'] ?? null,
            'image_url' => isset($this->data['image_path']) ? Storage::url($this->data['image_path']) : null,
            'sent_at'   => $this->created_at,
            'read_at'   => $this->read_at,
        ];
    }
}
