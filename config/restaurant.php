<?php

/*
|--------------------------------------------------------------------------
| Canteen configuration
|--------------------------------------------------------------------------
|
| These are the *defaults*. Almost everything below can be changed by the
| admin at runtime from  Admin → Settings  (the values are stored in the
| `settings` table and override these defaults on every request).
|
*/

return [

    // Philippine peso. The currency is fixed in code on purpose: a stray
    // RESTAURANT_CURRENCY=$ in .env can no longer turn prices into dollars.
    // Admins can still change it from Admin → Settings.
    'currency' => '₱',

    'name' => env('RESTAURANT_NAME', 'Cecilian Bites'),
    'address' => env('RESTAURANT_ADDRESS'),
    'phone' => env('RESTAURANT_PHONE'),
    'receipt_footer' => env('RESTAURANT_RECEIPT_FOOTER', 'Thank you for dining with us!'),

    // Path (on the "public" disk) of the uploaded logo. Null = built-in mark.
    'logo_path' => null,

    // When false the storefront still shows the menu but refuses new orders.
    'accepting_orders' => true,

    // Buildings customers can pick at checkout. Empty = free-text field.
    'buildings' => [],

    // Customer online payments — numbers can be set in Admin → Settings.
    'payments' => [
        'gcash' => ['label' => 'GCash', 'number' => env('STORE_GCASH_NUMBER'), 'name' => env('STORE_GCASH_NAME', env('RESTAURANT_NAME', 'Cecilian Bites'))],
        'maya' => ['label' => 'Maya', 'number' => env('STORE_MAYA_NUMBER'), 'name' => env('STORE_MAYA_NAME', env('RESTAURANT_NAME', 'Cecilian Bites'))],
    ],

    'admin_registration_code' => env('ADMIN_REGISTRATION_CODE'),
    'public_staff_registration' => env('PUBLIC_STAFF_REGISTRATION', false),

    // Email verification code used when a customer registers.
    'registration_code' => [
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_seconds' => 60,
    ],

];
