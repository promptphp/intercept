<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PromptPHP\Intercept\Support\InterceptServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param mixed $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            InterceptServiceProvider::class,
        ];
    }
}