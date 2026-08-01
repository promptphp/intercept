<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard\Enums;

/**
 * The types of action to take when a proposed tool call is flagged.
 *
 * Supported actions:
 * - block: stop the run and throw an exception before the approval is surfaced.
 * - log: log the finding and let the pending approval through.
 *
 * There is deliberately no mutating action. A proposed tool call is part of the paused turn
 * the provider recorded, and rewriting it would desynchronise the run when it is resumed.
 */
enum ActionTypes: string
{
    case BLOCK = 'block';
    case LOG   = 'log';
}
