# Roadmap

This roadmap outlines the planned direction for Intercept by PromptPHP.

Intercept is a modular middleware collection for Laravel AI agents. The goal is to help Laravel AI-native developers protect, observe, and govern AI agent behaviour through small, installable packages.

The roadmap is intentionally practical. Packages are prioritised based on current AI application risks, Laravel AI SDK fit, likely production value, and implementation resource overhead.

## Current packages

These packages have been tested and will continue to receive required support.

### `promptphp/intercept-injection-guard`

Detects common prompt injection attempts before the prompt reaches the AI provider.

Current focus:

- prompt injection detection
- custom regex patterns
- `block`, `log`, `warn`, and `sanitize` actions
- safe logging with prompt hashes
- optional prompt previews
- optional global config through `config/intercept.php`

### `promptphp/intercept-pii-redactor`

Detects and handles common structured PII and secret-like values before the prompt reaches the AI provider.

Current focus:

- email, phone, credit card, IP address, API key, and bearer token detection
- `redact`, `mask`, `log`, and `block` actions
- blocked high-risk entities
- allowed emails and domains
- safe logging with hashes
- optional global config through `config/intercept.php`

## Proposed package roadmap

I have a few ideas in mind and I've tried to prioritize them based on value and ease of implementation. This file will receieve updates as they are implemented and more ideas are conceived.

### `promptphp/intercept-budget-guard`

Status: proposed next package.

Budget Guard will look to protect applications from expensive, oversized, or abusive prompts before they reach the AI provider.

Why this matters:

AI requests can become expensive quickly. Your app should have validations in place for interesting scenarios but there's a need for that final gate just before hitting the AI provider. Budget Guard will look to provide a simple way to stop expensive requests before they hit the provider.

Planned features:

- maximum prompt characters
- estimated token budget
- estimated cost budget
- per-agent limits
- per-model limits
- optional tenant or user budget keys
- `block`, `log`, and `truncate` actions
- configurable limits through `config/intercept.php`

## Contributing

Contributions are welcome. Please see [contributing guide](contributing.md) for more .

---

## Roadmap status

This roadmap is not a promise of exact delivery order. It is a working direction for the Intercept ecosystem.

The current priority is to release and stabilise:

- `promptphp/intercept-injection-guard`
- `promptphp/intercept-pii-redactor`

Future packages will be shaped by real Laravel AI SDK usage, production pain points, and community contributions.
