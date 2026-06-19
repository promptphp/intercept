<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\InjectionGuard\Exceptions;

use Exception;

class PromptInjectionGuardException extends Exception
{
    public function __construct(string $message = 'Prompt injection attempt detected.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
