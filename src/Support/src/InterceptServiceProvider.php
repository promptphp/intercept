<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use PromptPHP\Intercept\Support\Listeners\InspectApprovalDecisions;

final class InterceptServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/intercept.php',
            'intercept',
        );

        $this->app->scoped(ApprovalDecisionLedger::class);
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/intercept.php' => config_path('intercept.php'),
        ], 'intercept-config');

        // The dispatcher matches listeners by class and interface, not by parent class, so
        // the streaming event needs its own registration.
        Event::listen(PromptingAgent::class, InspectApprovalDecisions::class);
        Event::listen(StreamingAgent::class, InspectApprovalDecisions::class);
    }
}
