<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Providers;

use App\Modules\Dashboard\Infrastructure\Console\BroadcastBusinessMetricsCommand;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BroadcastBusinessMetricsCommand::class,
            ]);
        }
    }
}
