<?php

namespace App\Support;

/**
 * The store's own GCash / Maya accounts that customers send payments to.
 * Set the numbers in .env (STORE_GCASH_NUMBER, STORE_MAYA_NUMBER, ...); a method with no number is hidden.
 */
class StorePayments
{
    /**
     * @return array<string, array{label: string, number: string, name: string}>
     */
    public static function available(): array
    {
        return collect(config('restaurant.payments', []))
            ->filter(fn ($account) => filled($account['number'] ?? null))
            ->all();
    }

    /** URL of an optional QR image placed in public/payment-qr/{method}.(png|jpg|jpeg|webp). */
    public static function qr(string $method): ?string
    {
        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            if (file_exists(public_path("payment-qr/$method.$ext"))) {
                return asset("payment-qr/$method.$ext");
            }
        }

        return null;
    }
}
