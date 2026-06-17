<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use Illuminate\Support\Facades\Storage;

class OgImageController extends Controller
{
    public function show(string $token)
    {
        $catalogue = Catalogue::where('order_token', $token)->firstOrFail();

        $path = $catalogue->cover_photo_og ?? $catalogue->cover_photo;

        abort_if(! $path, 404);

        $contents = Storage::get($path);

        abort_if(! $contents, 404);

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png';

        return response($contents, 200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
