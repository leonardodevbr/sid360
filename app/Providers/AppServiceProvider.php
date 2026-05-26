<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Sale;
use App\Models\User;
use App\Observers\SaleObserver;
use Illuminate\Support\Facades\Gate;
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
        Sale::observe(SaleObserver::class);

        Gate::before(function ($user, $ability) {
            if ($user instanceof User && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });
    }
}
