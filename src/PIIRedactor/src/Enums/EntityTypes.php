<?php

declare(strict_types=1);

namespace PromptPHP\Intercept\PIIRedactor\Enums;

/**
 * The types of PII entities that can be detected and redacted.
 *
 * Supported entity types:
 * - api_key: API keys.
 * - bearer_token: Bearer tokens.
 * - credit_card: Credit card numbers.
 * - email: Email addresses.
 * - ip_address: IP addresses.
 * - phone: Phone numbers.
 */
enum EntityTypes: string
{
    case API_KEY      = 'api_key';
    case BEARER_TOKEN = 'bearer_token';
    case CREDIT_CARD  = 'credit_card';
    case EMAIL        = 'email';
    case IP_ADDRESS   = 'ip_address';
    case PHONE        = 'phone';
    case MAC_ADDRESS  = 'mac_address';
    case URL          = 'url';
}
