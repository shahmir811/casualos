<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Advance Credit Auto-Confirm Threshold
    |--------------------------------------------------------------------------
    |
    | When a customer's advance credit is automatically applied to a new order
    | on submission, orders where the applied amount exceeds this threshold
    | skip the "received" review step and go straight to "confirmed".
    |
    */

    'advance_credit_auto_confirm_threshold' => (float) env('ADVANCE_CREDIT_AUTO_CONFIRM_THRESHOLD', 50000),

    /*
    |--------------------------------------------------------------------------
    | Staff Mobile Login
    |--------------------------------------------------------------------------
    |
    | web_app_url is where the mobile app's embedded WebView is pointed after
    | a staff member (admin/accountant/production_manager/creative_head)
    | authenticates via Api\AuthController::verify(). staff_mobile_login_token_ttl
    | is how many seconds the single-use handoff token minted for that trip
    | (MobileLoginController::consume()) stays valid before it expires unused.
    |
    */

    'web_app_url' => env('WEB_APP_URL', 'https://casualiteos.com'),

    'staff_mobile_login_token_ttl' => (int) env('STAFF_MOBILE_LOGIN_TOKEN_TTL', 90),

];
