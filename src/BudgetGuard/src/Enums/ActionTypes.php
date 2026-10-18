<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\Enums;

/**
 * The types of action to take when a request would exceed its budget.
 *
 * Supported actions:
 * - block: stop the run and throw an exception before the provider is called.
 * - log: log the breach and let the request through.
 * - truncate: shorten the newest user message until the request fits the remaining budget,
 *   and block when no prompt short enough would fit.
 */
enum ActionTypes: string
{
    case BLOCK    = 'block';
    case LOG      = 'log';
    case TRUNCATE = 'truncate';
}
