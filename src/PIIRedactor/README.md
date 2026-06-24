## Introduction

`PIIRedactor` is a Laravel AI SDK agent middleware that detects and handles sensitive personal or secret-like data before an agent prompt reaches the AI provider.

It can redact, mask, log, block, or fully delegate handling to a custom callback.

> [!Important]
> This middleware is part of the [Intercept middleware collection](https://github.com/promptphp/intercept). It is designed to catch common structured sensitive data, not to guarantee complete detection of every possible personal identifier.

## Quick start

### Installation

```sh
composer require promptphp/intercept-pii-redactor
```

You may publish the config or not, the middleware works out of the box.

```sh
php artisan vendor:publish --tag=intercept-config
```

### Usage

Return the `PIIRedactor` middleware on an agent's middleware method.

> [!Important]
> To add middleware to an agent, implement the `HasMiddleware` interface and define a middleware method that returns an array of middleware classes.

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
            new PIIRedactor,
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

> [!Important]
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