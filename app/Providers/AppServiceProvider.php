<?php

namespace App\Providers;

use App\Services\EnsureDefaultAdminService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Use custom pagination view across the entire project
        Paginator::defaultView('pagination.custom');

        try {
            if (Schema::hasTable('users')) {
                app(EnsureDefaultAdminService::class)->run();
            }
        } catch (\Throwable) {
            // Database may not be ready during install or migrate.
        }
    }
}
