<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\Exceptions;

use PromptPHP\Intercept\Support\Exceptions\InterceptException;

/**
 * Class BudgetGuardException.
 *
 * This exception is thrown when a request would exceed a configured budget and the action is
 * `block`, or `truncate` could not bring the request back under budget.
 */
class BudgetGuardException extends InterceptException
{
    /**
     * Create a new budget guard exception.
     *
     * @param string   $message    The exception message.
     * @param int      $code       The exception code.
     * @param int|null $retryAfter Seconds until the breached budget's window resets, for a
     *                             windowed budget. Null for the per-request budget, which has no
     *                             window, and when the store cannot report one.
     */
    public function __construct(
        string $message = 'Request would exceed its configured budget.',
        int $code = 0,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $code);
    }
}
