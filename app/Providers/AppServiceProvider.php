<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mobile-app sign-in (/wp-json/*). Once uaa.ae points at the new site these requests arrive through
        // Vercel, so every phone shares one IP: limit per account name (stops password guessing) and keep only
        // a generous overall cap, instead of 10 attempts per IP for everybody together.
        RateLimiter::for('wpjson-login', function (Request $request) {
            $who = strtolower(trim((string) ($request->input('email') ?: $request->input('username'))));

            return [
                Limit::perMinute(8)->by('wpjson-login|'.sha1($who)),
                Limit::perMinute(400)->by('wpjson-login-all'),
            ];
        });
    }
}
