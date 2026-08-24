<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ResetDuskLoginThrottleCommand extends Command
{
    protected $signature = 'dusk:reset-login-throttle';

    protected $description = 'Clear the login rate limiter for the seeded demo accounts, so a Dusk run is never throttled by a previous run\'s login attempts.';

    public function handle(): int
    {
        $emails = [
            'user1@email.com',
            'user2@email.com',
            'user3@email.com',
            'user4@email.com',
        ];

        $ips = ['127.0.0.1', '::1', '172.18.0.1'];

        foreach ($emails as $email) {
            foreach ($ips as $ip) {
                $throttleKey = Str::transliterate(Str::lower($email)).'|'.$ip;

                // Two separate throttles stack on the login route: the
                // throttle:login route middleware (RateLimiter::for('login', ...)
                // in FortifyServiceProvider, hashed as md5('login'.$key) since
                // ThrottleRequests::$shouldHashKeys defaults true) and Fortify's
                // own EnsureLoginIsNotThrottled pipeline action (Laravel\Fortify\
                // LoginRateLimiter, which hits the unhashed "email|ip" key
                // directly). Confirmed by inspecting real cache table rows -
                // only the unhashed key was actually ever written, so that's
                // the one that needs clearing; the hashed variant is cleared
                // too in case a future Laravel/Fortify version changes which
                // path is active.
                RateLimiter::clear($throttleKey);
                RateLimiter::clear(md5('login'.$throttleKey));
            }
        }

        $this->info('Cleared the login throttle for '.count($emails).' seeded accounts.');

        return self::SUCCESS;
    }
}
