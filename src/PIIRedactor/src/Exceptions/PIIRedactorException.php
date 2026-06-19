<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Exceptions;

use RuntimeException;

class PIIRedactorException extends RuntimeException
{
    /**
     * Create a new PII Redactor exception.
     */
    public function __construct(string $message = 'PII detected in agent prompt.')
    {
        parent::__construct($message);
    }
}