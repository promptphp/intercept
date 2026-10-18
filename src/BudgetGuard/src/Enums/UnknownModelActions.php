<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\BudgetGuard\Enums;

/**
 * What to do when no pricing is known for the model a step runs on.
 *
 * - allow: log the gap and let the request through unmetered.
 * - block: stop the run, so an unpriced model can never spend against a budget.
 *
 * Pricing changes faster than this package releases, so an unpriced model is expected rather
 * than exceptional. `allow` keeps a model upgrade from taking an application down.
 */
enum UnknownModelActions: string
{
    case ALLOW = 'allow';
    case BLOCK = 'block';
}
