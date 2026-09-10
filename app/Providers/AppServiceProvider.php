<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Dates are value objects here: a stray ->addMinutes() must never mutate
        // a model attribute that something else still holds a reference to.
        Date::use(CarbonImmutable::class);

        // Turns silent N+1 queries and typos in attribute names into failures
        // during development and in the test suite.
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)->by(
            $request->ip() ?? 'unknown'
        ));
    }
}
