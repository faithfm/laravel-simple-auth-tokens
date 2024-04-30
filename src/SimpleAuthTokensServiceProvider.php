<?php

namespace FaithFM\SimpleAuthTokens;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class SimpleAuthTokensServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // Load database migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }    
}
