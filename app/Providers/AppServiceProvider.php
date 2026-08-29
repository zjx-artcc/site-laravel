<?php

namespace App\Providers;

use App\Services\Socialite\VatsimProvider;
use GuzzleHttp\Client;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bounded Guzzle client used by the Discord log channel (marvinlabs/laravel-discord-logger)
        // so a slow/unreachable Discord webhook can't hang or re-throw out of a Log:: call.
        $this->app->singleton(Client::class, function () {
            return new Client([
                'timeout' => 5,
                'connect_timeout' => 3,
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Staging and production share one IP, and VATSIM allows 10 API
        // requests per minute per IP. The default leaves room for both
        // environments' online-controller polls and other VATSIM API calls.
        RateLimiter::for('vatsim-atc-sessions', fn () => Limit::perMinute(config('app.vatsim_stats_sync_rate_limit'))->by('vatsim-atc-sessions'));

        /*Socialite::extend('vatsim', function($app) {
            $config = $app['config']['services.vatsim'];
            return Socialite::buildProvider(VatsimProvider::class, $config);
        });*/

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');

        $socialite->extend('vatsim', function ($app) use ($socialite) {
            $config = $app['config']['services.vatsim'];

            return $socialite->buildProvider(VatsimProvider::class, $config);
        });

        // Force IPv4 for every outbound Http:: facade call (VATUSA/VATSIM/GitHub) —
        // rules out flaky/blackholed IPv6 routing as a source of intermittent connect timeouts.
        Http::globalOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ]);
    }
}
