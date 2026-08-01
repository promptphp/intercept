<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard\Defaults;

use PromptPHP\Intercept\PIIRedactor\Defaults\PIIRedactorDefaults;

final class ToolApprovalGuardDefaults
{
    /**
     * Get the default Tool Approval Guard config.
     *
     * The entity lists track the PII Redactor defaults so both middleware agree on what
     * counts as sensitive and what counts as high risk.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $pii = PIIRedactorDefaults::values();

        return [
            'action'         => 'block',
            'allowed_tools'  => [],
            'denied_tools'   => [],
            'scan_pii'       => true,
            'scan_injection' => true,
            'entities'       => $pii['entities'],
            'block_entities' => $pii['block_entities'],
            'log_preview'    => false,
        ];
    }
}
