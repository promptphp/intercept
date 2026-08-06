<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\ToolApprovalGuard\Defaults;

final class ToolApprovalGuardDefaults
{
    /**
     * Get the default Tool Approval Guard config.
     *
     * These lists deliberately diverge from the PII Redactor defaults, and must not be
     * derived from them. The two middleware read the same detectors in different contexts:
     *
     * - In a prompt, an email address is user data on its way to a model. Worth redacting.
     * - In a proposed tool argument, an email address is usually the function signature.
     *   `SendCustomerEmail(to: ...)` cannot work without one.
     *
     * Exfiltration is about destination, not presence, and the destination cannot be judged
     * from the value alone. So the default entity list is narrowed to values that are
     * essentially never a legitimate tool argument: a Luhn-valid card number, an API key,
     * a bearer token. Contact data and locators (email, phone, url, ip_address, mac_address)
     * are still supported, but opt-in.
     *
     * `scan_injection` is off by default for the same reason. Prose a model writes for a
     * human reader routinely contains "you are now", "from now on" and "system:", none of
     * which indicate manipulation in that context.
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
