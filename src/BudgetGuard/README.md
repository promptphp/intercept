## Introduction

`BudgetGuard` is a Laravel AI SDK agent middleware that estimates the cost of a generation step before the provider is called, and enforces per-request, per-session, and per-user-per-day spending budgets.

It can block, log, or truncate the prompt down to size, or fully delegate handling to a custom callback.

> [!Important]
> This middleware is part of the [Intercept middleware collection](https://github.com/promptphp/intercept). It estimates cost from the step it is about to send and the usage the provider reports back. It does not meter the provider's own bill, which can differ slightly depending on how a provider rounds or bundles usage.

## Why this matters

A runaway tool-calling loop, a model switched to something far pricier, or simple abuse can turn one agent into an open-ended bill. `BudgetGuard` is the gate that sits just before the provider call and stops a request before it adds to that bill, rather than reporting on the damage afterwards.

## Quick start

### Installation

```sh
composer require promptphp/intercept-budget-guard
```

You may publish the config or not, the middleware works out of the box, though every budget is disabled until you set a limit.

```sh
php artisan vendor:publish --tag=intercept-config
```

### Usage

Return the `BudgetGuard` middleware on an agent's middleware method.

> [!Important]
> To add middleware to an agent, implement the `HasMiddleware` interface and define a middleware method that returns an array of middleware classes.

```php
use Laravel\Ai\Contracts\HasMiddleware;
use PromptPHP\Intercept\BudgetGuard\BudgetGuard;

class SupportAgent implements Agent, HasMiddleware
{
    public function middleware(): array
    {
        return [
            new BudgetGuard(
                maxCostPerRequest: 0.50,
            ),
        ];
    }
}
```

### Setting more than one budget

```php
new BudgetGuard(
    maxCostPerRequest: 0.50,
    maxCostPerSession: 10.00,
    maxCostPerUserPerDay: 5.00,
)
```

Each budget is independent and optional. The first one a step would exceed is the one that is reported.

### Observing before enforcing

```php
new BudgetGuard(
    maxCostPerRequest: 0.50,
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
