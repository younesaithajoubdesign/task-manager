<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password gate (SPEC §5.1)
    |--------------------------------------------------------------------------
    |
    | A bcrypt hash of the single password that opens the app. Set it with
    | `php artisan gate:password`. Left empty, the gate stays open — it is a
    | curtain for a local, single-user app, not a security boundary.
    |
    */

    'password_hash' => env('APP_PASSWORD_HASH'),

];
