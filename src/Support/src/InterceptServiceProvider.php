<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support;

use Illuminate\Support\ServiceProvider;

final class InterceptServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     * 
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/intercept.php',
            'intercept',
        );
    }

    /**
     * Bootstrap the application services.
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/intercept.php' => config_path('intercept.php'),
        ], 'intercept-config');
    }
}