<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\InjectionGuard\Exceptions;

use PromptPHP\Intercept\Support\Exceptions\InterceptException;

/**
 * Class PromptInjectionGuardException.
 *
 * This exception is thrown when a prompt injection attempt is detected and the configured action is `block`.
 */
class PromptInjectionGuardException extends InterceptException
{
    public function __construct(string $message = 'Prompt injection attempt detected.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
