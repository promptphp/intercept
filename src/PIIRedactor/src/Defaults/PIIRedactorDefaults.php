<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Defaults;

final class PIIRedactorDefaults
{
    /**
     * Get the default PII Redactor config.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            'action'   => 'redact',
            'entities' => [
                'email',
                'phone',
                'credit_card',
                'ip_address',
                'api_key',
                'bearer_token',
                'mac_address',
            ],
            'block_entities' => [
                'credit_card',
                'api_key',
                'bearer_token',
            ],
            'allowed_emails'     => [],
            'allowed_domains'    => [],
            'replacement_format' => '[{{TYPE}}_{{INDEX}}]',
            'mask_character'     => '*',
            'log_detections'     => true,
            'log_preview'        => false,
        ];
    }
}
