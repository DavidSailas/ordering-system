<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// php artisan dishes:normalize-images
// One-time clean-up: crops/resizes every existing dish photo to the same 4:3 size.
Artisan::command('dishes:normalize-images', function () {
    $converted = 0;
    $skipped = 0;

    \App\Models\MenuItem::query()->whereNotNull('image_path')->each(function ($item) use (&$converted, &$skipped) {
        $new = \App\Support\DishImage::normalize($item->image_path);

        if ($new === null) {
            $skipped++;

            return;
        }

        $item->update(['image_path' => $new]);
        $converted++;
    });

    $this->info("Dish photos normalized: {$converted}. Skipped: {$skipped}.");
})->purpose('Resize and crop all dish photos to a consistent 4:3 size');

// php artisan canteens:make-overall-admin you@example.com
// Turns an existing admin into the overall administrator who can see every canteen.
Artisan::command('canteens:make-overall-admin {email}', function (string $email) {
    $user = \App\Models\User::query()->where('email', $email)->where('role', 'admin')->first();

    if (! $user) {
        $this->error("No admin account found for {$email}.");

        return 1;
    }

    $user->forceFill(['canteen_id' => null])->save();
    $this->info("{$user->name} is now the overall administrator.");

    return 0;
})->purpose('Make an admin the overall administrator (sees all canteens)');

// Guest carts that were not touched for 30 days are removed (signed-in customers' carts are kept).
Schedule::call(function () {
    \App\Models\CartItem::query()->whereNull('user_id')->where('updated_at', '<', now()->subDays(30))->delete();
})->daily()->name('prune-old-guest-carts');
