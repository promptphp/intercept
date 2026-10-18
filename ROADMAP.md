# Roadmap

This roadmap outlines the planned direction for Intercept by PromptPHP.

Intercept's goal is to help Laravel AI-native developers protect, observe, and govern AI agent behaviour through small, installable packages.

Packages are prioritised based on current AI application risks, Laravel AI SDK fit, likely production value, and implementation resource overhead.

> [!Important]
> This roadmap is not a promise of exact delivery order. It is a working direction for the Intercept ecosystem.
>
> The priority is to stabilise current packages.

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

### `promptphp/intercept-tool-approval-guard`

Inspects the tool calls an agent proposes while pausing for human approval, before they are surfaced for review.

This is the first middleware to act on the response rather than the prompt, because the tool calls it guards are proposed by the model rather than supplied by the user.

Current focus:

- tool allow and deny lists
- secret detection in proposed tool arguments, as an exfiltration signal; contact data and
  locators are supported but opt-in, because they are usually the tool's own parameters
- prompt injection detection in proposed tool arguments
- `block` and `log` actions, with blocked entities overriding the action
- safe logging with value hashes and tool call provenance
- optional global config through `config/intercept.php`

### `promptphp/intercept-budget-guard`

Estimates the cost of a generation step before the AI provider is called, and enforces per-request, per-session, and per-user-per-day spending budgets.

Current focus:

- per-request budget, including the cost already accrued by earlier steps of the same run
- per-session and per-user-per-day budgets, tracked in the cache with a rolling window
- a built-in pricing table for common Anthropic, OpenAI, Google, xAI, DeepSeek, and Mistral models, with family matching for dated and gateway-prefixed model ids
- custom or overriding pricing
- `block`, `log`, and `truncate` actions; `truncate` degrades to `block` for a session or daily breach, since shrinking the prompt cannot undo spend already recorded
- configurable behaviour for an unpriced model: `allow` (unmetered) or `block`
- safe logging with dollar amounts, never the session or user key spend was tracked against
- optional global config through `config/intercept.php`

## Proposed package roadmap

I have a few ideas in mind and I've tried to prioritize them based on value and ease of implementation. This file will receieve updates as they are implemented and more ideas are conceived.

## Contributing

Contributions are welcome. Please see [contributing guide](contributing.md) for more .

---
