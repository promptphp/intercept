<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard\Exceptions;

use PromptPHP\Intercept\Support\Exceptions\InterceptException;

/**
 * Class ToolApprovalGuardException.
 *
 * This exception is thrown when a proposed tool call is flagged and the configured action is `block`.
 */
class ToolApprovalGuardException extends InterceptException
{
    public function __construct(string $message = 'Unsafe tool call proposed for approval.', int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
