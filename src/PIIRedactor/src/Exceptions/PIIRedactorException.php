<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Exceptions;

use PromptPHP\Intercept\Support\Exceptions\InterceptException;

/**
 * Class PIIRedactorException.
 *
 * This exception is thrown when PII is detected in the agent prompt and the configured action is `block`.
 */
class PIIRedactorException extends InterceptException
{
    /**
     * Create a new PII Redactor exception.
     */
    public function __construct(string $message = 'PII detected in agent prompt.')
    {
        parent::__construct($message);
    }
}
