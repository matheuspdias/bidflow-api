<?php

namespace App\Providers;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqSetupCommand;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CommandBus::class);
        $this->app->singleton(QueryBus::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });

        RateLimiter::for('bid-placement', function ($request) {
            return Limit::perMinute(20)->by($request->user()->id ?? $request->ip());
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RabbitMqSetupCommand::class]);
        }
    }
}
