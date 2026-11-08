<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the first two canteens to their final names (Cecilian Café and Cecilian
 * Eatery) on installs that already ran the earlier rename with other names.
 *
 * Only canteens that still have a placeholder name are touched ("1", "2",
 * "Canteen 1", "Canteen 2", or the earlier demo names). A canteen the owner
 * has already named themselves is left exactly as it is.
 */
return new class extends Migration
{
    /** @var list<array<string, string>> */
    private array $profiles = [
        [
            'name' => 'Cecilian Café',
            'slug' => 'cecilian-cafe',
            'tagline' => 'Milk tea, iced coffee, snacks and drinks.',
            'accent' => '#e25806',
        ],
        [
            'name' => 'Cecilian Eatery',
            'slug' => 'cecilian-eatery',
            'tagline' => 'Rice meals, snacks, drinks and everyday essentials.',
            'accent' => '#d62d1a',
        ],
    ];

    /** @var list<string> Descriptions written by the earlier demo data. */
    private array $oldTaglines = [
        'Filipino home-style meals, cooked fresh every day.',
        'Burgers, pasta, snacks and coffee.',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canteens')) {
            return;
        }

        $canteens = DB::table('canteens')->orderBy('sort_order')->orderBy('id')->limit(2)->get();

        foreach ($canteens as $index => $canteen) {
            $profile = $this->profiles[$index] ?? null;

            if (! $profile || ! $this->isPlaceholderName((string) $canteen->name)) {
                continue;
            }

            $slugTaken = DB::table('canteens')
                ->where('slug', $profile['slug'])
                ->where('id', '!=', $canteen->id)
                ->exists();

            $update = [
                'name' => $profile['name'],
                'accent' => $profile['accent'],
                'updated_at' => now(),
            ];

            if (! $slugTaken) {
                $update['slug'] = $profile['slug'];
            }

            if (blank($canteen->tagline) || in_array($canteen->tagline, $this->oldTaglines, true)) {
                $update['tagline'] = $profile['tagline'];
            }

            // Payment account names default to the canteen name, so keep them in step.
            foreach (['gcash_name', 'maya_name'] as $column) {
                if (blank($canteen->{$column}) || $this->isPlaceholderName((string) $canteen->{$column})) {
                    $update[$column] = $profile['name'];
                }
            }

            DB::table('canteens')->where('id', $canteen->id)->update($update);
        }
    }

    public function down(): void
    {
        // Names are data the owner may have edited since; nothing to undo safely.
    }

    private function isPlaceholderName(string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return true;
        }

        // "1", "2", "#1", "Canteen 1", "Canteen #2", "Stall 3" ...
        if (preg_match('/^(canteen|stall|shop|store)?\s*#?\s*\d+$/i', $name)) {
            return true;
        }

        return in_array(mb_strtolower($name), ['cecilian kitchen', 'brew & bites', 'brew and bites', 'cecilian brew & bites', 'cecilian brew and bites'], true);
    }
};
