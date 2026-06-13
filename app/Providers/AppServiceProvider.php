<?php

namespace App\Providers;

use App\Services\EnsureDefaultAdminService;
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
        try {
            if (Schema::hasTable('users')) {
                app(EnsureDefaultAdminService::class)->run();
            }
        } catch (\Throwable) {
            // Database may not be ready during install or migrate.
        }
    }
}
