<?php

declare(strict_types=1);

use PromptPHP\Intercept\Support\InterceptConfig;

it('returns defaults when middleware config is missing', function (): void {
    config()->set('intercept.middleware', []);

    $config = InterceptConfig::middleware('missing_middleware', [
        'action'           => 'block',
        'patterns'         => [],
        'merge_patterns'   => true,
        'normalise_prompt' => true,
    ]);

    expect($config)->toBe([
        'action'           => 'block',
        'patterns'         => [],
        'merge_patterns'   => true,
        'normalise_prompt' => true,
    ]);
});

it('merges middleware config over defaults', function (): void {
    config()->set('intercept.middleware.injection_guard', [
        'action'             => 'log',
        'log_prompt_preview' => true,
    ]);

    $config = InterceptConfig::middleware('injection_guard', [
        'action'             => 'block',
        'patterns'           => [],
        'merge_patterns'     => true,
        'normalise_prompt'   => true,
        'log_prompt_preview' => false,
    ]);

    expect($config)->toBe([
        'action'             => 'log',
        'patterns'           => [],
        'merge_patterns'     => true,
        'normalise_prompt'   => true,
        'log_prompt_preview' => true,
    ]);
});

it('recursively merges nested middleware config over defaults', function (): void {
    config()->set('intercept.middleware.example', [
        'logging' => [
            'preview' => true,
        ],
    ]);

    $config = InterceptConfig::middleware('example', [
        'action'  => 'block',
        'logging' => [
            'enabled' => true,
            'preview' => false,
        ],
    ]);

    expect($config)->toBe([
        'action'  => 'block',
        'logging' => [
            'enabled' => true,
            'preview' => true,
        ],
    ]);
});

it('returns defaults when middleware config is not an array', function (): void {
    config()->set('intercept.middleware.injection_guard', 'invalid');

    $config = InterceptConfig::middleware('injection_guard', [
        'action' => 'block',
    ]);

    expect($config)->toBe([
        'action' => 'block',
    ]);
});

it('returns configured values when no defaults are provided', function (): void {
    config()->set('intercept.middleware.injection_guard', [
        'action' => 'warn',
    ]);

    $config = InterceptConfig::middleware('injection_guard');

    expect($config)->toBe([
        'action' => 'warn',
    ]);
});

it('replaces list arrays instead of merging them by index', function (): void {
    config()->set('intercept.middleware.pii_redactor', [
        'entities' => [
            'email',
        ],
    ]);

    $config = InterceptConfig::middleware('pii_redactor', [
        'entities' => [
            'email',
            'phone',
            'credit_card',
        ],
    ]);

    expect($config)->toBe([
        'entities' => [
            'email',
        ],
    ]);
});