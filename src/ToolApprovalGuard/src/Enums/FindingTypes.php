<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard\Enums;

/**
 * The reasons a proposed tool call can be flagged.
 *
 * - denied_tool: the tool is on the deny list, or outside a non-empty allow list.
 * - pii: a proposed argument carries personal or secret-like data, suggesting exfiltration.
 * - injection: a proposed argument matches a prompt injection pattern, suggesting the model
 *   was manipulated by content the middleware never saw.
 */
enum FindingTypes: string
{
    case DENIED_TOOL = 'denied_tool';
    case INJECTION   = 'injection';
    case PII         = 'pii';
}
