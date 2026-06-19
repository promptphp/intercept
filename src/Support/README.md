# promptphp/intercept-support

This package offers shared support utilities and configuration for the Intercept AI Agent middleware collection.
It is used internally by Intercept middleware packages.

Most users do not need to install or use this package directly. It is installed automatically when required by an Intercept middleware package.

## What this package provides

`intercept-support` provides shared infrastructure used across the Intercept ecosystem:

* the shared `config/intercept.php` configuration file
* the `intercept-config` publish tag
* the `InterceptConfig` helper for resolving middleware config
* the `InterceptServiceProvider` for Laravel package registration

## Installation

You usually do not need to install this package directly.

It is installed automatically when installing an Intercept middleware package:

```bash
composer require promptphp/intercept-injection-guard
```

If needed, it can be installed directly:

```bash
composer require promptphp/intercept-support
```

## Publishing the config

Intercept uses one shared configuration file:

```text
config/intercept.php
```

You may publish it with:

```bash
php artisan vendor:publish --tag=intercept-config
```

Publishing config is optional. Every Intercept middleware package has internal defaults and will continue to work even if its config section is missing.

## Configuration

The published config file contains global defaults for Intercept middleware.

Example:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'injection_guard' => [
            'action'             => 'block',
            'patterns'           => [],
            'merge_patterns'     => true,
            'normalise_prompt'   => true,
            'log_prompt_preview' => false,
        ],
    ],
];
```

Each middleware resolves configuration in this order:

```text
constructor value > config value > internal middleware default
```

This means users can define global defaults in `config/intercept.php`, but still override behaviour per agent when creating a middleware instance.

## Reading middleware config

Middleware packages should use `InterceptConfig::middleware()` to read their config safely.

```php
use PromptPHP\Intercept\Support\InterceptConfig;

$config = InterceptConfig::middleware('injection_guard', [
    'action'             => 'block',
    'patterns'           => [],
    'merge_patterns'     => true,
    'normalise_prompt'   => true,
    'log_prompt_preview' => false,
]);
```

The helper merges the published config section with the middleware's internal defaults.

If the section is missing, the defaults are returned.

## Missing config sections

Missing config sections are safe.

For example, if a user publishes `config/intercept.php` while only using Injection Guard, and later installs PII Redactor, the existing config file may not contain a `pii_redactor` section.

That is fine. PII Redactor will still work using its internal defaults.

Users only need to add a middleware section to `config/intercept.php` when they want to customise its global behaviour.

## Service provider

This package registers the shared Intercept service provider:

```php
PromptPHP\Intercept\Support\InterceptServiceProvider::class
```

The provider:

* merges the default `intercept` config
* exposes the `intercept-config` publish tag

Laravel package auto-discovery should register this provider automatically.

## For middleware package authors

Each Intercept middleware package should:

* require `promptphp/intercept-support`
* define its own internal defaults
* read global config through `InterceptConfig::middleware()`
* work even if its config section is missing
* avoid publishing its own separate config file

Example:

```php
$config = InterceptConfig::middleware(
    'pii_redactor',
    PIIRedactorDefaults::values(),
);
```

This keeps the Intercept ecosystem consistent while allowing each middleware package to remain independently installable.

## License

MIT
