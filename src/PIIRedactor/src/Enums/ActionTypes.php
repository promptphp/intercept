<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Enums;

/**
 * The types of action to take when an PII entity is detected.
 *
 * Supported actions:
 * - block: stop the prompt and throw an exception.
 * - log: log the detection and continue.
 * - mask: mask the detected PII entity.
 * - redact: redact the detected PII entity.
 */
enum ActionTypes: string
{
    case BLOCK  = 'block';
    case LOG    = 'log';
    case MASK   = 'mask';
    case REDACT = 'redact';
}