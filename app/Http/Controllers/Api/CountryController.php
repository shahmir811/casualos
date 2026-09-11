<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;

/**
 * Serves Customer::COUNTRIES — the same list CustomerController's `in:`
 * validation and the web create/edit forms already read directly (PHP has
 * no network hop to make). This endpoint exists so the mobile app (a
 * separate codebase, casualite-app) never has to hardcode its own copy —
 * it was doing exactly that in app/(auth)/signup.tsx before this endpoint
 * existed, which meant adding a country required a coordinated edit in two
 * repos and an app store release. Now the app fetches this list at runtime;
 * adding a country only ever means editing Customer::COUNTRIES here.
 *
 * Public, no auth — the signup screen (the app's only consumer so far)
 * renders before a customer has any credential to authenticate with.
 */
class CountryController extends Controller
{
    public function index()
    {
        return response()->json([
            'countries' => Customer::COUNTRIES,
        ]);
    }
}
