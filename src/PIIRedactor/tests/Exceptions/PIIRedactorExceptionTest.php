<?php

declare(strict_types=1);

use PromptPHP\Intercept\PIIRedactor\Exceptions\PIIRedactorException;
use PromptPHP\Intercept\Support\Exceptions\InterceptException;

it('extends the shared intercept exception', function () {
    expect(new PIIRedactorException('Sensitive data detected.'))
        ->toBeInstanceOf(InterceptException::class);
});
