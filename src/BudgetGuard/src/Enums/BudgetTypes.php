<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\Enums;

/**
 * The budgets a request can exceed.
 *
 * - request: the whole run, including the cost already accrued by its earlier steps.
 * - session: the spend recorded against the current session key, within the session window.
 * - user_daily: the spend recorded against the current user key, for the calendar day.
 *
 * The request budget is measured from the usage the SDK reports for the run in progress. The
 * session and daily budgets are measured from the spend the middleware records after each step,
 * so they span runs.
 */
enum BudgetTypes: string
{
    case REQUEST    = 'request';
    case SESSION    = 'session';
    case USER_DAILY = 'user_daily';
}
