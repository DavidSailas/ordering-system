<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency Symbol
    |--------------------------------------------------------------------------
    |
    | The symbol shown in front of every price on the cashier dashboard.
    |
    */

    'currency' => env('RESTAURANT_CURRENCY', '₱'),

    /*
    |--------------------------------------------------------------------------
    | Receipt Details
    |--------------------------------------------------------------------------
    |
    | Shown at the top and bottom of every printed receipt.
    |
    */

    'name' => env('RESTAURANT_NAME', 'Cecilian Bites'),
    'address' => env('RESTAURANT_ADDRESS'),
    'phone' => env('RESTAURANT_PHONE'),
    'receipt_footer' => env('RESTAURANT_RECEIPT_FOOTER', 'Thank you for dining with us!'),

    /*
    |--------------------------------------------------------------------------
    | Admin Registration Code
    |--------------------------------------------------------------------------
    |
    | Anyone can register as a cashier, but creating an admin account from the
    | public register page requires this secret code. Leave it empty to turn
    | public admin registration off completely (admins can then only be created
    | by another admin from the Staff page).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Customer Payment Accounts
    |--------------------------------------------------------------------------
    |
    | The store's GCash / Maya numbers shown to customers who choose to pay
    | online. A method with no number set is hidden from the customer.
    |
    */

    'payments' => [
        'gcash' => ['label' => 'GCash', 'number' => env('STORE_GCASH_NUMBER'), 'name' => env('STORE_GCASH_NAME', env('RESTAURANT_NAME', 'Cecilian Bites'))],
        'maya' => ['label' => 'Maya', 'number' => env('STORE_MAYA_NUMBER'), 'name' => env('STORE_MAYA_NAME', env('RESTAURANT_NAME', 'Cecilian Bites'))],
    ],

    'admin_registration_code' => env('ADMIN_REGISTRATION_CODE'),
    'public_staff_registration' => env('PUBLIC_STAFF_REGISTRATION', false),

];
