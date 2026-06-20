<?php

declare(strict_types=1);

use PromptPHP\Intercept\Support\Exceptions\InterceptException;
use RuntimeException;

it('is a runtime exception', function () {
    expect(new InterceptException('Intercept failed.'))
        ->toBeInstanceOf(RuntimeException::class);
});
