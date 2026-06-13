<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default administrator (created on first boot if missing)
    |--------------------------------------------------------------------------
    */
    'default_admin' => [
        'email'    => env('DEFAULT_ADMIN_EMAIL', 'admin@blc.edu.mm'),
        'password' => env('DEFAULT_ADMIN_PASSWORD', 'password'),
        'name'     => env('DEFAULT_ADMIN_NAME', 'BLC Administrator'),
    ],

];
