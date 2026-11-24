<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Cashier\DashboardController;
use App\Http\Controllers\Cashier\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\StorefrontController::class, 'index'])->name('home');
Route::get('/canteens', [\App\Http\Controllers\StorefrontController::class, 'canteens'])->name('shop.canteens');
Route::post('/canteens/{canteen:slug}', [\App\Http\Controllers\StorefrontController::class, 'chooseCanteen'])->name('shop.canteens.choose');
Route::get('/menu', [\App\Http\Controllers\StorefrontController::class, 'index'])->name('shop.menu');
Route::get('/cart', [\App\Http\Controllers\StorefrontController::class, 'basket'])->name('shop.cart');
Route::post('/cart', [\App\Http\Controllers\StorefrontController::class, 'update'])->name('shop.cart.update');
// The campus pass: the QR code posted at school leads to /pass/{secret}.
Route::get('/pass', [\App\Http\Controllers\PassController::class, 'show'])->name('pass.show');
Route::post('/pass', [\App\Http\Controllers\PassController::class, 'store'])->middleware('throttle:10,1')->name('pass.store');
Route::get('/pass/{secret}', [\App\Http\Controllers\PassController::class, 'claim'])->middleware('throttle:20,1')->name('pass.claim');

Route::get('/checkout', [\App\Http\Controllers\StorefrontController::class, 'checkout'])->middleware('campus_pass')->name('shop.checkout');
Route::post('/checkout', [\App\Http\Controllers\StorefrontController::class, 'purchase'])->middleware(['campus_pass', 'throttle:10,1'])->block(10, 10)->name('shop.purchase');
Route::get('/my-profile', [\App\Http\Controllers\StorefrontController::class, 'profile'])->name('shop.profile');
Route::post('/my-profile', [\App\Http\Controllers\StorefrontController::class, 'updateProfile'])->middleware('throttle:10,1')->name('shop.profile.update');
Route::get('/my-orders', [\App\Http\Controllers\StorefrontController::class, 'orders'])->name('shop.orders');
Route::get('/my-orders/{order}/pay/{method}', [\App\Http\Controllers\StorePaymentController::class, 'show'])->whereIn('method', ['gcash', 'maya'])->name('shop.pay');
Route::post('/my-orders/{order}/pay/{method}', [\App\Http\Controllers\StorePaymentController::class, 'store'])->whereIn('method', ['gcash', 'maya'])->middleware('throttle:10,1')->name('shop.pay.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    // Registration is a two-step flow: details first, then the 6-digit code that was emailed.
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/register/verify', [RegisterController::class, 'verifyForm'])->name('register.verify');
    Route::post('/register/verify', [RegisterController::class, 'verify'])->middleware('throttle:20,1')->name('register.verify.store');
    Route::post('/register/resend', [RegisterController::class, 'resend'])->middleware('throttle:6,1')->name('register.resend');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Cashier screens: the cashier's own work, also open to the canteen owner and the admin.
    Route::middleware('role:cashier,owner,admin')->group(function () {
        // The cashier home page IS the incoming-orders board.
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::redirect('/cashier/incoming-orders', '/dashboard')->name('cashier.incoming');

        Route::get('/orders/{order}/payment', [\App\Http\Controllers\Cashier\PaymentController::class, 'show'])->name('orders.payment');
        Route::get('/orders/{order}/receipt', [\App\Http\Controllers\Cashier\PaymentController::class, 'receipt'])->name('orders.receipt');
        Route::post('/orders/{order}/payment', [\App\Http\Controllers\Cashier\PaymentController::class, 'store'])->name('orders.payment.store');
        Route::get('/cashier/transactions', [\App\Http\Controllers\Cashier\TransactionController::class, 'index'])->name('cashier.transactions');
        Route::get('/cashier/payment-records', [\App\Http\Controllers\Cashier\TransactionController::class, 'paymentRecords'])->name('cashier.payment-records');

        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        // Prank / no-show orders: cancel the order and block the phone number.
        Route::post('/orders/{order}/report-fake', [\App\Http\Controllers\Cashier\FakeOrderController::class, 'report'])->name('orders.report-fake');
        Route::post('/orders/{order}/block-phone', [\App\Http\Controllers\Cashier\FakeOrderController::class, 'block'])->name('orders.block-phone');
    });

    // Runner screen: deliveries for the runner's canteen (owner and admin may look too).
    Route::middleware('role:runner,owner,admin')->prefix('runner')->name('runner.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Runner\DashboardController::class, 'index'])->name('dashboard');
    });

    // Back office: the admin runs the whole system, an owner runs only their own canteen.
    Route::middleware('role:admin,owner')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::patch('foods/{food}/availability', [Admin\FoodController::class, 'toggleAvailability'])->name('foods.availability');
        Route::resource('foods', Admin\FoodController::class)->except('show');

        Route::resource('categories', Admin\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::post('orders/{order}/refund', [\App\Http\Controllers\Cashier\PaymentController::class, 'refund'])->name('orders.refund');
        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');

        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');

        // Delivery: buildings, floors and fees (admin and owner). Every change goes into the audit trail.
        Route::get('delivery', [Admin\DeliveryZoneController::class, 'index'])->name('delivery.index');
        Route::put('delivery/settings', [Admin\DeliveryZoneController::class, 'settings'])->name('delivery.settings');
        Route::post('delivery/zones', [Admin\DeliveryZoneController::class, 'store'])->middleware('throttle:30,1')->name('delivery.zones.store');
        Route::put('delivery/zones/{zone}', [Admin\DeliveryZoneController::class, 'update'])->middleware('throttle:60,1')->name('delivery.zones.update');
        Route::delete('delivery/zones/{zone}', [Admin\DeliveryZoneController::class, 'destroy'])->middleware('throttle:30,1')->name('delivery.zones.destroy');

        Route::patch('staff/{user}/active', [Admin\StaffController::class, 'toggleActive'])->name('staff.active');

        // Administrator only: canteens, and every account in the system with its role.
        Route::middleware('super_admin')->group(function () {
            Route::patch('users/{user}/active', [Admin\UserController::class, 'toggleActive'])->name('users.active');
            Route::resource('users', Admin\UserController::class)->except(['show', 'destroy']);

            // Order security: the campus pass (QR code posted at school) and blocked phone numbers.
            Route::get('order-security', [Admin\OrderSecurityController::class, 'index'])->name('order-security.index');
            Route::get('order-security/poster', [Admin\OrderSecurityController::class, 'poster'])->name('order-security.poster');
            Route::patch('order-security/pass', [Admin\OrderSecurityController::class, 'toggle'])->name('order-security.pass');
            Route::post('order-security/pass/regenerate', [Admin\OrderSecurityController::class, 'regenerate'])->name('order-security.regenerate');
            Route::post('order-security/blocked', [Admin\OrderSecurityController::class, 'block'])->name('order-security.block');
            Route::delete('order-security/blocked/{blockedPhone}', [Admin\OrderSecurityController::class, 'unblock'])->name('order-security.unblock');
            Route::delete('order-security/pauses/{suspension}', [Admin\OrderSecurityController::class, 'lift'])->name('order-security.lift');
            Route::patch('order-security/school-id', [Admin\OrderSecurityController::class, 'schoolId'])->name('order-security.school-id');

            // The school list (students and employees) that ID tap checks against.
            Route::get('school-ids', [Admin\SchoolMemberController::class, 'index'])->name('school-ids.index');
            Route::post('school-ids', [Admin\SchoolMemberController::class, 'store'])->name('school-ids.store');
            Route::post('school-ids/import', [Admin\SchoolMemberController::class, 'import'])->name('school-ids.import');
            Route::patch('school-ids/{member}/block', [Admin\SchoolMemberController::class, 'block'])->name('school-ids.block');
            Route::delete('school-ids/{member}', [Admin\SchoolMemberController::class, 'destroy'])->name('school-ids.destroy');

            Route::get('canteens', [Admin\CanteenController::class, 'index'])->name('canteens.index');
            Route::post('canteens', [Admin\CanteenController::class, 'store'])->name('canteens.store');
            Route::post('canteens/{canteen:slug}/switch', [Admin\CanteenController::class, 'switch'])->name('canteens.switch');
        });

        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

        Route::resource('staff', Admin\StaffController::class)->except('show')->parameters(['staff' => 'user']);
    });
});
