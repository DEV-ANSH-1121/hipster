<?php

namespace Hipster\UserDiscounts;

use Illuminate\Support\ServiceProvider;

class UserDiscountsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        $this->publishes([
            __DIR__ . '/../config/user-discounts.php' => config_path('user-discounts.php'),
        ], 'user-discounts-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/user-discounts.php',
            'user-discounts'
        );
    }
}

