<?php

namespace Darvis\ApiLinkedin;

use Darvis\ApiLinkedin\Services\LinkedInOAuth;
use Darvis\ApiLinkedin\Services\LinkedInPublisher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LinkedInServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/linkedin.php', 'linkedin');

        $this->app->singleton(LinkedInOAuth::class);
        $this->app->singleton(LinkedInPublisher::class);
        $this->app->singleton(LinkedInManager::class);
        $this->app->alias(LinkedInManager::class, 'linkedin');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/linkedin.php' => config_path('linkedin.php'),
        ], 'linkedin-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'linkedin-migrations');

        $this->registerRoutes();
    }

    /**
     * Registreer de ingebouwde OAuth-routes wanneer die zijn ingeschakeld.
     */
    protected function registerRoutes(): void
    {
        if (! config('linkedin.routes.enabled', true)) {
            return;
        }

        Route::prefix(config('linkedin.routes.prefix', 'linkedin'))
            ->middleware(config('linkedin.routes.middleware', ['web']))
            ->group(__DIR__.'/../routes/web.php');
    }
}
