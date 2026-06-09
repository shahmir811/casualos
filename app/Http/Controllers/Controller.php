<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    protected function denyCreativeHead(): void
    {
        if (Auth::user()->role === 'creative_head') {
            abort(403, 'Read-only access.');
        }
    }
}
