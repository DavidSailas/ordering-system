<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Cashier\DashboardController;
use App\Http\Controllers\Cashier\OrderController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\StorefrontController::class, 'index'])->name('home');
Route::get('/menu', [\App\Http\Controllers\StorefrontController::class, 'index'])->name('shop.menu');
Route::get('/cart', [\App\Http\Controllers\StorefrontController::class, 'basket'])->name('shop.cart');
Route::post('/cart', [\App\Http\Controllers\StorefrontController::class, 'update'])->name('shop.cart.update');
Route::get('/checkout', [\App\Http\Controllers\StorefrontController::class, 'checkout'])->name('shop.checkout');
Route::post('/checkout', [\App\Http\Controllers\StorefrontController::class, 'purchase'])->middleware('throttle:10,1')->block(10, 10)->name('shop.purchase');
Route::get('/my-profile', [\App\Http\Controllers\StorefrontController::class, 'profile'])->name('shop.profile');
Route::post('/my-profile', [\App\Http\Controllers\StorefrontController::class, 'updateProfile'])->middleware('throttle:10,1')->name('shop.profile.update');
Route::get('/my-orders', [\App\Http\Controllers\StorefrontController::class, 'orders'])->name('shop.orders');
Route::get('/my-orders/{order}/pay/{method}', [\App\Http\Controllers\StorePaymentController::class, 'show'])->whereIn('method', ['gcash', 'maya'])->name('shop.pay');
Route::post('/my-orders/{order}/pay/{method}', [\App\Http\Controllers\StorePaymentController::class, 'store'])->whereIn('method', ['gcash', 'maya'])->middleware('throttle:10,1')->name('shop.pay.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('role:cashier,admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/cashier/incoming-orders', [DashboardController::class, 'incoming'])->name('cashier.incoming');

        Route::get('/orders/{order}/payment', [\App\Http\Controllers\Cashier\PaymentController::class, 'show'])->name('orders.payment');
        Route::get('/orders/{order}/receipt', [\App\Http\Controllers\Cashier\PaymentController::class, 'receipt'])->name('orders.receipt');
        Route::post('/orders/{order}/payment', [\App\Http\Controllers\Cashier\PaymentController::class, 'store'])->name('orders.payment.store');
        Route::get('/cashier/transactions', [\App\Http\Controllers\Cashier\TransactionController::class, 'index'])->name('cashier.transactions');
        Route::get('/cashier/payment-records', [\App\Http\Controllers\Cashier\TransactionController::class, 'paymentRecords'])->name('cashier.payment-records');

        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::patch('foods/{food}/availability', [Admin\FoodController::class, 'toggleAvailability'])->name('foods.availability');
        Route::resource('foods', Admin\FoodController::class)->except('show');

        Route::resource('categories', Admin\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('tables', Admin\TableController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::post('orders/{order}/refund', [\App\Http\Controllers\Cashier\PaymentController::class, 'refund'])->name('orders.refund');
        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');

        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');

        Route::resource('staff', Admin\StaffController::class)->except('show')->parameters(['staff' => 'user']);
    });
});
