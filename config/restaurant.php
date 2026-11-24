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

    // Delivery by the student runners. Each canteen switches it on or off (and sets the fees) on
    // Admin → Delivery & fees; this is only the default.
    'delivery_enabled' => true,

    // Buildings customers can pick at checkout. Empty = free-text field.
    'buildings' => [],

    // Customer online payments — numbers can be set in Admin → Settings.
    'payments' => [
        'gcash' => ['label' => 'GCash', 'number' => env('STORE_GCASH_NUMBER'), 'name' => env('STORE_GCASH_NAME', env('RESTAURANT_NAME', 'Cecilian Bites'))],
        'maya' => ['label' => 'Maya', 'number' => env('STORE_MAYA_NUMBER'), 'name' => env('STORE_MAYA_NAME', env('RESTAURANT_NAME', 'Cecilian Bites'))],
    ],

    // Campus pass (QR code posted at school): how long a scanned pass keeps working. The administrator
    // switches the pass on or off, and replaces the QR code, on the "Order security" page.
    'campus_pass' => [
        'days' => (int) env('CAMPUS_PASS_DAYS', 30),
    ],

    // Pay first, then we cook: an order waits for payment, and cancels itself if it is not paid in time.
    'unpaid_expiry_minutes' => (int) env('ORDER_UNPAID_EXPIRY_MINUTES', 15),
    // If the customer already sent a GCash / Maya reference, the cashier gets longer to check it.
    'unpaid_review_minutes' => (int) env('ORDER_UNPAID_REVIEW_MINUTES', 60),

    // Customers must tap / scan their school ID (plus last name) to order. The administrator can switch this on
    // under Admin → Order security; this is only the starting value.
    'require_school_id' => (bool) env('ORDER_REQUIRE_SCHOOL_ID', false),

    // Missed orders ("strikes"): an order nobody pays for in time counts against the phone number.
    // After `max` of them the number is paused for `pause_days`, then it can order again by itself.
    // Set ORDER_STRIKES_MAX=0 to switch strikes off.
    'strikes' => [
        'max' => (int) env('ORDER_STRIKES_MAX', 3),
        'window_days' => (int) env('ORDER_STRIKES_WINDOW_DAYS', 30),
        'pause_days' => (int) env('ORDER_STRIKES_PAUSE_DAYS', 3),
    ],

    // Stops prank orders. Set a value to 0 to switch that limit off.
    'order_limits' => [
        // Unpaid orders one phone number may have in progress at the same time.
        'max_open_unpaid' => (int) env('ORDER_MAX_OPEN_UNPAID', 2),
        // Unpaid orders in progress from one internet connection (a whole school can share one, so this is generous).
        'max_open_unpaid_per_ip' => (int) env('ORDER_MAX_OPEN_UNPAID_PER_IP', 12),
        // Biggest order (in pesos) that may be paid in cash; bigger ones are paid by GCash / Maya first.
        'max_cash_total' => (int) env('ORDER_MAX_CASH_TOTAL', 500),
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
