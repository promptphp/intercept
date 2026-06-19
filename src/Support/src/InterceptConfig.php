<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\Support;

final class InterceptConfig
{
    /**
     * Get the config for a middleware.
     *
     * @param string               $middleware Middleware name.
     * @param array<string, mixed> $defaults   Default middleware config.
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

        return self::merge($defaults, $config);
    }

    /**
     * Merge config over defaults.
     *
     * Associative arrays are merged recursively. List arrays are replaced.
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    protected static function merge(array $defaults, array $config): array
    {
        foreach ($config as $key => $value) {
            if (
                is_array($value)
                && isset($defaults[$key])
                && is_array($defaults[$key])
                && ! array_is_list($value)
                && ! array_is_list($defaults[$key])
            ) {
                $defaults[$key] = self::merge($defaults[$key], $value);

                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}
