<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support;

final class InterceptConfig
{
    /**
     * Get the config for a middleware.
     *
     * @param  string               $middleware Middleware name.
     * @param  array<string, mixed> $defaults   Default middleware config.
     * 
     * @return array<string, mixed>
     */
    public static function middleware(string $middleware, array $defaults = []): array
    {
        if (! function_exists('config')) {
            return $defaults;
        }

        $config = config("intercept.middleware.{$middleware}", []);

        if (! is_array($config)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $config);
    }
}