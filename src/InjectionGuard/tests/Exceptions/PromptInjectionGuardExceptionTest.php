<?php

declare(strict_types=1);

use PromptPHP\Intercept\InjectionGuard\Exceptions\PromptInjectionGuardException;
use PromptPHP\Intercept\Support\Exceptions\InterceptException;

it('extends the shared intercept exception', function () {
    expect(new PromptInjectionGuardException('Prompt blocked.'))
        ->toBeInstanceOf(InterceptException::class);
});
