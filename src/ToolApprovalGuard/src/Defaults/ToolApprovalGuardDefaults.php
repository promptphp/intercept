<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard\Defaults;

final class ToolApprovalGuardDefaults
{
    /**
     * Get the default Tool Approval Guard config.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            'action'         => 'block',
            'allowed_tools'  => [],
            'denied_tools'   => [],
            'scan_pii'       => true,
            'scan_injection' => false,
            'entities'       => [
                'credit_card',
                'api_key',
                'bearer_token',
            ],
            'block_entities' => [
                'credit_card',
                'api_key',
                'bearer_token',
            ],
            'log_preview' => false,
        ];
    }
}
