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

];
