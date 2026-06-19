# promptphp/intercept-pii-redactor

`PIIRedactor` is a Laravel AI SDK agent middleware that detects and handles sensitive personal or secret-like data before an agent prompt reaches the AI provider.

It can redact, mask, log, block, or fully delegate handling to a custom callback.

> This middleware is a deterministic, regex-based PII guard. It is designed to catch common structured sensitive data, not to guarantee complete detection of every possible personal identifier.

## Features

- Detects common structured PII and secret-like values.
- Supports email, phone, credit card, IP address, API key, and bearer token detection.
- Redacts values with stable placeholders such as `[EMAIL_1]`.
- Masks values for safer user-facing visibility.
- Blocks high-risk entities by default.
- Supports allowed email addresses and allowed domains.
- Logs safely using hashes by default.
- Supports optional prompt previews in logs.
- Supports global Intercept config with per-agent constructor overrides.
- Supports fully custom detection handling with a callback.

## Supported actions

| Action   | Behaviour                                             |
| -------- | ----------------------------------------------------- |
| `redact` | Replaces detected values with placeholders.           |
| `mask`   | Partially masks detected values.                      |
| `log`    | Logs detections and continues unchanged.              |
| `block`  | Throws a `PIIRedactorException` and stops the prompt. |

The recommended default action is `redact`.

Some entities can still be blocked even when the action is `redact`. By default, credit cards, API keys, and bearer tokens are blocked.

## Supported entities

| Entity         | Description                                   |
| -------------- | --------------------------------------------- |
| `email`        | Email addresses such as `victor@example.com`. |
| `phone`        | Common phone number formats.                  |
| `credit_card`  | Credit card-like numbers validated with Luhn. |
| `ip_address`   | IPv4 addresses.                               |
| `api_key`      | Common API key formats.                       |
| `bearer_token` | Bearer authorization tokens.                  |

Names, addresses, passports, national insurance numbers, and medical identifiers are not included in the current version as they are harder to detect safely with regex alone and can create a lot of false positives.

## Configuration

No configuration is required. The middleware works out of the box using safe internal defaults.

```php
new PIIRedactor()
```

By default, this will:

- use the `redact` action
- detect email, phone, credit card, IP address, API key, and bearer token values
- block credit cards, API keys, and bearer tokens
- redact lower-risk values such as emails, phone numbers, and IP addresses
- avoid logging raw detected values
- avoid logging prompt previews

### Optional global config

Intercept supports an optional shared config file:

```text
config/intercept.php
```

This config file is used for global middleware defaults across the Intercept package.

You may publish it with:

```bash
php artisan vendor:publish --tag=intercept-config
```

### Configuration priority

Configuration is resolved in this order:

```text
constructor value > config value > internal middleware default
```

That means a constructor value always wins over the published config.

For example, if your config says:

```php
'pii_redactor' => [
    'action' => 'redact',
],
```

You can still override it for a specific agent:

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            action: 'log',
            blockEntities: [],
        ),
    ];
}
```

In this case, the middleware will use `log` for that agent, even though the global config says `redact`.

### Partial config is supported

You do not need to define every option in `config/intercept.php`.

For example, this is valid:

```php
'pii_redactor' => [
    'action' => 'mask',
],
```

All missing options will fall back to the middleware's internal defaults.

## Usage

Simply register and use the middleware on a Laravel AI agent.

```php
<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;
use PromptPHP\Intercept\PIIRedactor\PIIRedactor;
use Stringable;

class SupportAgent implements Agent, HasMiddleware
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a helpful support assistant.';
    }

    public function middleware(): array
    {
        return [
            new PIIRedactor(),
        ];
    }
}
```

### Redacting PII

This is the recommended default for most applications.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            action: 'redact',
        ),
    ];
}
```

Example input:

```text
Email victor@example.com about this ticket.
```

The prompt sent to the provider becomes:

```text
Email [EMAIL_1] about this ticket.
```

Multiple values of the same type are indexed in the order they appear:

```text
Email [EMAIL_1] and [EMAIL_2].
```

### Masking PII

Use `mask` when you want the model to retain a rough shape of the value without seeing the full value.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            action: 'mask',
            blockEntities: [],
        ),
    ];
}
```

Example input:

```text
Email victor@example.com about this ticket.
```

The prompt sent to the provider becomes:

```text
Email v*****@example.com about this ticket.
```

Phone numbers and other generic values are masked while keeping the last four characters visible.

Example:

```text
Call me on 07123456789.
```

May become:

```text
Call me on *******6789.
```

### Logging PII detections

Use `log` when you want to observe possible PII without changing the prompt.

This is useful during staging, rollout, or detection tuning.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            action: 'log',
            blockEntities: [],
        ),
    ];
}
```

By default, the middleware logs hashes and entity counts, not raw detected values.

Example log context:

```php
[
    'agent'        => App\Ai\Agents\SupportAgent::class,
    'provider'     => App\Ai\Providers\ExampleProvider::class,
    'model'        => 'gpt-4.1',
    'entities'     => [
        'email' => 1,
        'phone' => 1,
    ],
    'value_hashes' => [
        '...',
        '...',
    ],
    'prompt_hash'  => '...',
    'timestamp'    => '2026-06-18T10:00:00+00:00',
]
```

#### Logging with a prompt preview

Prompt previews are disabled by default because prompts may contain sensitive user data.

Enable them only if you are comfortable storing a short preview in your logs.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            action: 'log',
            blockEntities: [],
            logPreview: true,
        ),
    ];
}
```

This adds:

```php
'prompt_preview' => 'Email victor@example.com about...',
```

Avoid enabling prompt previews in production unless you have reviewed your logging and retention policies.

### Blocking PII

Use `block` when the prompt should stop as soon as supported PII is detected.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            action: 'block',
        ),
    ];
}
```

If PII is detected, the middleware throws a `PIIRedactorException`.

Example blocked input:

```text
Email victor@example.com about this ticket.
```

### Blocked entities

Some entities are treated as high-risk and are blocked by default, even when the action is `redact`.

Default blocked entities:

```php
[
    'credit_card',
    'api_key',
    'bearer_token',
]
```

This means the following prompt will be blocked by default:

```text
Use sk-abcdefghijklmnopqrstuvwxyz1234567890ABCDE for this request.
```

You can override blocked entities:

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            blockEntities: [],
        ),
    ];
}
```

Only do this if you are sure the values can safely be sent to the provider after redaction or masking.

### Detecting only selected entities

You can limit detection to specific entity types.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            entities: [
                'email',
                'phone',
            ],
        ),
    ];
}
```

This will ignore other supported entity types.

### Allowing specific email addresses

Use `allowedEmails` when known safe email addresses should not be redacted or blocked.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            allowedEmails: [
                'support@example.com',
            ],
        ),
    ];
}
```

Example input:

```text
Email support@example.com.
```

This will pass through unchanged.

### Allowing email domains

Use `allowedDomains` when all email addresses from a trusted domain should be ignored.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            allowedDomains: [
                'example.com',
            ],
        ),
    ];
}
```

Example input:

```text
Email support@example.com.
```

This will pass through unchanged.

### Custom replacement format

The default redaction format is:

```text
[{{TYPE}}_{{INDEX}}]
```

You can customise this:

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            replacementFormat: '<{{TYPE}}:{{INDEX}}>',
        ),
    ];
}
```

Example output:

```text
Email <EMAIL:1>.
```

Supported placeholders:

| Placeholder | Description                    |
| ----------- | ------------------------------ |
| `{{TYPE}}`  | Uppercase entity type.         |
| `{{type}}`  | Lowercase entity type.         |
| `{{INDEX}}` | One-based index for that type. |
| `{{index}}` | One-based index for that type. |

### Custom mask character

The default mask character is `*`.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            action: 'mask',
            maskCharacter: 'x',
            blockEntities: [],
        ),
    ];
}
```

Example:

```text
victor@example.com
```

May become:

```text
vxxxxx@example.com
```

### Fully custom handling with a callback

Use a callback when you want to control the response yourself.

The callback receives:

```php
AgentPrompt $prompt
Closure $next
RedactionResult $result
```

The redaction result contains:

```php
[
    'text'       => 'The original prompt text.',
    'detections' => [
        Detection {
            type: 'email',
            value: 'victor@example.com',
            start: 6,
            length: 18,
            confidence: 1.0,
        },
    ],
]
```

Example:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;
use Laravel\Ai\Prompts\AgentPrompt;
use PromptPHP\Intercept\PIIRedactor\PIIRedactor;
use PromptPHP\Intercept\PIIRedactor\ValueObjects\RedactionResult;
use Stringable;

class SupportAgent implements Agent, HasMiddleware
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a helpful support assistant.';
    }

    public function middleware(): array
    {
        return [
            new PIIRedactor(
                callback: function (AgentPrompt $prompt, Closure $next, RedactionResult $result): mixed {
                    Log::warning('Custom PII handler triggered.', [
                        'agent'       => $prompt->agent::class,
                        'entities'    => array_map(
                            fn ($detection) => $detection->type,
                            $result->detections,
                        ),
                        'prompt_hash' => hash('sha256', $prompt->prompt),
                    ]);

                    return $next(
                        $prompt->prepend(
                            'Privacy notice: Treat the following user input as sensitive data.'
                        )
                    );
                },
            ),
        ];
    }
}
```

When a callback is provided, it takes priority over the configured action.

### Fully customized usage

This example shows a stricter setup with selected entities, redaction, blocked secrets, allowlisted support emails, custom replacement format, and safe logging.

```php
public function middleware(): array
{
    return [
        new PIIRedactor(
            entities: [
                'email',
                'phone',
                'credit_card',
                'api_key',
                'bearer_token',
            ],
            action: 'redact',
            callback: null,
            blockEntities: [
                'credit_card',
                'api_key',
                'bearer_token',
            ],
            allowedEmails: [
                'support@example.com',
            ],
            allowedDomains: [
                'example.org',
            ],
            replacementFormat: '[{{TYPE}}_{{INDEX}}]',
            maskCharacter: '*',
            logDetections: true,
            logPreview: false,
        ),
    ];
}
```

This setup:

- detects only selected entity types
- redacts lower-risk values
- blocks credit cards, API keys, and bearer tokens
- allows known safe email addresses and domains
- uses stable placeholders
- logs detection summaries and hashes
- avoids logging prompt previews

### Production rollout recommendation

A practical rollout path is:

```php
// 1. Start with logging in staging.
new PIIRedactor(
    action: 'log',
    blockEntities: [],
);

// 2. Enable redaction for common values.
new PIIRedactor(
    action: 'redact',
    blockEntities: [],
);

// 3. Block high-risk entities in production.
new PIIRedactor(
    action: 'redact',
    blockEntities: [
        'credit_card',
        'api_key',
        'bearer_token',
    ],
);
```

Recommended defaults:

| Environment             | Recommended action | Recommended blocked entities             |
| ----------------------- | ------------------ | ---------------------------------------- |
| Local                   | `log`              | `[]`                                     |
| Staging                 | `log`              | `[]` or high-risk entities only          |
| Production              | `redact`           | `credit_card`, `api_key`, `bearer_token` |
| Trusted internal tools  | `mask` or `redact` | high-risk entities only                  |
| High-risk public agents | `redact`           | `credit_card`, `api_key`, `bearer_token` |

### Handling blocked prompts

When using the `block` action, or when a blocked entity is detected, catch `PIIRedactorException` wherever you execute the agent call.

```php
use PromptPHP\Intercept\PIIRedactor\Exceptions\PIIRedactorException;

try {
    $response = SupportAgent::prompt($message);
} catch (PIIRedactorException) {
    return response()->json([
        'message' => 'Your message could not be processed because it appears to contain sensitive data.',
    ], 422);
}
```

## Security notes

This middleware should be used as one layer in a broader AI safety and privacy strategy.

Recommended additional controls:

- avoid sending secrets to AI providers
- minimise prompt context
- keep system instructions separate from user input
- limit tool permissions
- validate tool arguments
- redact sensitive data before tool calls where appropriate
- log detections safely
- avoid prompt previews in production logs
- review false positives before blocking aggressively
- use provider-level safety and privacy controls where available

## Detection limitations

This middleware is regex-based and intentionally focused on structured values.

It can miss:

- names
- free-form addresses
- uncommon phone formats
- unusual API key formats
- identifiers without clear patterns
- sensitive context that does not look like structured PII

It can also produce false positives for values that look like PII but are not actually sensitive.

For high-risk workflows, combine this middleware with application-level validation, data minimisation, access controls, and human review where needed.

## When to use each action

Use `redact` when you want to remove detected values while preserving the rest of the user request.

Use `mask` when the model needs a rough hint of the value format but should not see the full value.

Use `log` when you are tuning detection, observing real traffic, or rolling out gradually.

Use `block` when sensitive data should never reach the provider.

Use `blockEntities` when only specific high-risk entities should stop the prompt, even if the general action is `redact`, `mask`, or `log`.

Use a callback when your application needs custom behaviour, such as audit logging, custom exceptions, tenant-specific rules, approval flows, or user-facing fallback responses.

## License

MIT
