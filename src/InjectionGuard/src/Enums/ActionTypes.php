<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\InjectionGuard\Enums;

/**
 * The types of action to take when an injection is detected.
 *
 * Supported actions:
 * - block: stop the prompt and throw an exception.
 * - log: log the detection and continue.
 * - sanitize: remove the matched injection content, prepend a warning, and continue.
 * - warn: prepend a security warning and continue.
 */
enum ActionTypes: string
{
    case BLOCK = 'block';
    case LOG = 'log';
    case SANITIZE = 'sanitize';
    case WARN = 'warn';
}