<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\Expense;
use App\Policies\ExpensePolicy;
use Illuminate\Support\Facades\Gate;

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
        Gate::policy(Expense::class, ExpensePolicy::class);
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input('email');

            $email = is_string($email)
                ? Str::lower(trim($email))
                : '';

            $accountKey = hash(
                'sha256',
                $email . '|' . $request->ip()
            );

            return [
                Limit::perMinute(5)
                    ->by('login-account:' . $accountKey),

                Limit::perMinute(30)
                    ->by('login-ip:' . $request->ip()),
            ];
        });
    }
}
