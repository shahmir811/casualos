<?php

namespace App\Http\Controllers;

use App\Models\PieceTag;

class PieceTagController extends Controller
{
    public function scan(string $barcode)
    {
        $tag = PieceTag::with(['order.customer', 'order.catalogue', 'design'])
            ->where('barcode', $barcode)
            ->first();

        return view('public.tag-scan', compact('tag'));
    }
}
