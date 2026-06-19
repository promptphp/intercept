<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use PromptPHP\Intercept\Support\InterceptServiceProvider;

it('merges the default intercept config', function (): void {
    expect(config('intercept.middleware.injection_guard.action'))->toBe('block');
    expect(config('intercept.middleware.injection_guard.patterns'))->toBe([]);
    expect(config('intercept.middleware.injection_guard.merge_patterns'))->toBeTrue();
    expect(config('intercept.middleware.injection_guard.normalise_prompt'))->toBeTrue();
    expect(config('intercept.middleware.injection_guard.log_prompt_preview'))->toBeFalse();
});

it('registers the intercept config publish path', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        InterceptServiceProvider::class,
        'intercept-config',
    );

    expect($paths)->toHaveCount(1);

    $source = array_key_first($paths);

    expect(realpath($source))->toBe(realpath(__DIR__.'/../config/intercept.php'));
    expect($paths[$source])->toBe(config_path('intercept.php'));
});
