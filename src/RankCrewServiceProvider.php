<?php

namespace RankCrew\Laravel;

use Illuminate\Support\ServiceProvider;

class RankCrewServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
