<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Canteen;
use App\Models\DeliveryZone;
use App\Models\SchoolMember;
use App\Models\User;
use App\Support\SchoolId;
use App\Support\Strikes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * php artisan canteen:preflight
 *
 * A quick safety check before the system goes live (or before a demo): is debug off, is the timezone right,
 * are the demo passwords gone, does every canteen have delivery buildings and a payment account?
 * FAIL means "fix this first" (the command then exits with an error); WARN means "worth a look".
 */
class Preflight extends Command
{
    protected $signature = 'canteen:preflight';

    protected $description = 'Check that the settings are safe before going live';

    private int $fails = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        // The command object is reused if it runs twice in one process (as in the tests), so start from zero.
        $this->fails = 0;
        $this->warnings = 0;

        $this->line('<options=bold>Cecilian Bites: safety check</>');
        $this->newLine();

        $this->settings();
        $this->accounts();
        $this->canteens();
        $this->security();

        $this->newLine();
        $this->line('<fg=gray>Reminder: the server needs this cron job so unpaid orders are cancelled on time:</>');
        $this->line('<fg=gray>  * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1</>');
        $this->newLine();

        if ($this->fails > 0) {
            $this->line("<fg=red;options=bold>{$this->fails} problem(s) to fix first.</> ({$this->warnings} warning(s))");

            return self::FAILURE;
        }

        $this->line("<fg=green;options=bold>Ready.</> {$this->warnings} warning(s).");

        return self::SUCCESS;
    }

    private function settings(): void
    {
        blank(config('app.key')) ? $this->problem('APP_KEY is empty. Run: php artisan key:generate') : $this->pass('APP_KEY is set');

        config('app.debug')
            ? $this->problem('APP_DEBUG is on. When something breaks, visitors would see code and settings. Set APP_DEBUG=false.')
            : $this->pass('APP_DEBUG is off');

        config('app.env') === 'production'
            ? $this->pass('APP_ENV is production')
            : $this->caution('APP_ENV is "'.config('app.env').'". For the live site use APP_ENV=production.');

        str_starts_with((string) config('app.url'), 'https://')
            ? $this->pass('APP_URL uses https')
            : $this->caution('APP_URL is not https. Live sites should use https.');

        config('app.timezone') === 'UTC'
            ? $this->caution('APP_TIMEZONE is UTC, so times show 8 hours behind the Philippines (and "today" ends at 8 AM). Set APP_TIMEZONE=Asia/Manila.')
            : $this->pass('Timezone is '.config('app.timezone'));

        config('session.secure')
            ? $this->pass('Session cookies are secure-only')
            : $this->caution('SESSION_SECURE_COOKIE is not true. On an https site, set SESSION_SECURE_COOKIE=true.');

        config('session.encrypt')
            ? $this->pass('Sessions are encrypted')
            : $this->caution('SESSION_ENCRYPT is false. Set SESSION_ENCRYPT=true on the live site.');

        config('mail.default') === 'log'
            ? $this->caution('MAIL_MAILER=log: sign-up codes are only written to the log file, not emailed. Set up real email for the live site.')
            : $this->pass('Email is sent with '.config('mail.default'));

        if (config('app.env') === 'production' && config('database.default') === 'sqlite') {
            $this->caution('The database is SQLite. Fine for a demo; use MySQL or PostgreSQL for the live site.');
        }
    }

    /** Demo accounts all use the password "password"; they must not exist on a live site. */
    private function accounts(): void
    {
        $weak = User::query()
            ->where(fn ($query) => $query->where('role', '!=', UserRole::Customer->value)->orWhere('email', 'student@example.com'))
            ->get()
            ->filter(fn (User $user) => Hash::check('password', (string) $user->password));

        $weak->isNotEmpty()
            ? $this->problem($weak->count().' account(s) still use the demo password "password" ('.$weak->take(3)->pluck('email')->implode(', ').($weak->count() > 3 ? ', …' : '').'). Change or delete them.')
            : $this->pass('No account uses the demo password');
    }

    private function canteens(): void
    {
        foreach (Canteen::query()->orderBy('id')->get() as $canteen) {
            DeliveryZone::withoutGlobalScopes()->where('canteen_id', $canteen->id)->where('is_active', true)->exists()
                ? $this->pass($canteen->name.': delivery buildings are set')
                : $this->caution($canteen->name.' has no delivery buildings, so only pickup works (Admin → Delivery & fees).');

            filled($canteen->gcash_number) || filled($canteen->maya_number)
                ? $this->pass($canteen->name.': has a GCash / Maya account')
                : $this->caution($canteen->name.' has no GCash or Maya number, so delivery and online payment are hidden (Admin → Settings).');
        }
    }

    private function security(): void
    {
        Strikes::enabled()
            ? $this->pass('Automatic pauses: '.Strikes::max().' missed orders = '.Strikes::pauseDays().' days')
            : $this->caution('Automatic pauses (strikes) are switched off.');

        if (SchoolId::required() && SchoolMember::query()->doesntExist()) {
            $this->problem('A school ID is required to order, but the school list is empty, so nobody can order (Admin → School IDs).');
        } else {
            $this->pass('School ID at checkout is '.(SchoolId::required() ? 'required' : 'optional').'; '.SchoolMember::query()->count().' people on the school list');
        }
    }

    private function pass(string $message): void
    {
        $this->line('  <fg=green>OK  </> '.$message);
    }

    private function caution(string $message): void
    {
        $this->warnings++;
        $this->line('  <fg=yellow>WARN</> '.$message);
    }

    private function problem(string $message): void
    {
        $this->fails++;
        $this->line('  <fg=red>FAIL</> '.$message);
    }
}
