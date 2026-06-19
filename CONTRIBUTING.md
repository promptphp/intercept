# Contributing

Thank you for your interest in contributing to Intercept by PromptPHP.

Intercept is a modular middleware collection for Laravel AI agents. The goal is to help Laravel AI-native developers protect, observe, and govern AI agent behaviour through small, composable packages.

Current packages:

- `promptphp/intercept-injection-guard`
- `promptphp/intercept-pii-redactor`

Future packages are tracked in the roadmap.

## Contribution philosophy

Intercept packages should be:

- small, focused and address a particular use case
- installable independently
- useful without published config
- safe logging with prompt hashes if there's a log action
- explicit about limitations and clearly documented
- well tesed and easy to test
- Laravel AI-native where possible
- strictly focused on middleware-level governance, not replacing the Laravel AI SDK

Avoid building large, tightly coupled features when a small middleware package would solve the problem better.

## Ways to contribute

You can help by:

- improving documentation
- adding tests
- reporting false positives
- improving detection patterns
- suggesting safer defaults
- reviewing package APIs
- contributing new middleware packages
- opening issues for real-world AI agent risks

## Before you start

For small fixes, feel free to open a pull request directly.

For larger changes, please open an issue or discussion first, especially for:

- new middleware packages
- new public APIs
- config shape changes
- default behaviour changes
- security-related changes
- changes that affect package splitting or releases

When proposing a new package, please include:

- the problem being solved
- proposed package name
- expected middleware behaviour
- default action
- config shape
- safety considerations
- examples of real-world usage
- likely false positives or failure modes

## Local setup

Clone the repository:

```bash
git clone https://github.com/promptphp/intercept.git
cd intercept
```

Install dependencies:

```bash
composer install
```

Dump autoload files:

```bash
composer dump-autoload
```

Run the test suite:

```bash
composer test
```

Run package-specific tests:

```bash
composer test:injection-guard
composer test:pii-redactor
composer test:support
```

Run architecture tests:

```bash
composer test:architecture
```

Run lint checks:

```bash
composer test:lint
```

Format the code:

```bash
composer format
```

Run static analysis:

```bash
composer test:types
```

## Repository structure

The repository is organised as a monorepo with split packages.

```text
src/
├── InjectionGuard/
│   ├── src/
│   ├── tests/
│   ├── README.md
│   └── composer.json
│
├── PIIRedactor/
│   ├── src/
│   ├── tests/
│   ├── README.md
│   └── composer.json
│
├── Support/
│   ├── src/
│   ├── config/
│   ├── tests/
│   ├── README.md
│   └── composer.json
│
├── tests/
├── composer.json
└── README.md
```

## Package rules

Each middleware package should:

- have its own namespace
- have its own tests
- have its own README
- have its own `composer.json`
- depend on `promptphp/intercept-support` when shared config is needed
- avoid publishing its own config file
- work even when its config section is missing

Please review the structure of the existing packages if unsure.

Shared support code belongs in:

```text
src/Support/
```

Middleware-specific code belongs in that middleware package.

## Configuration rules

Intercept uses one shared config file:

```text
config/intercept.php
```

The shared config is published by `promptphp/intercept-support` using the tag:

```bash
php artisan vendor:publish --tag=intercept-config
```

Middleware packages should not publish their own config files.

Config should always resolve in this order:

```text
constructor value > config value > internal middleware default
```

This means:

- users do not need to publish config
- partial config is supported
- missing config sections are safe
- constructor values always win for per-agent overrides

Each middleware package should define its own internal defaults.

Middleware packages should only require their own config section when the user wants to customise global behaviour.

Example:

```php
$config = InterceptConfig::middleware(
    'pii_redactor',
    PIIRedactorDefaults::values(),
);
```

## Middleware behaviour

Middleware should be conservative by default.

For security and privacy packages:

- avoid logging raw prompts by default
- log hashes or summaries instead
- make risky behaviour opt-in
- document false positives
- document limitations clearly
- avoid pretending a heuristic check is complete protection

Supported actions should be explicit and predictable.

Examples:

```text
block
log
warn
sanitize
redact
mask
truncate
fallback
```

Do not add a new action unless it has a clear behaviour and test coverage.

## Code style

This project uses strict types.

Every PHP file should start with:

```php
<?php

declare(strict_types=1);
```

Use clear class names, typed properties, and explicit return types where possible.

Prefer small value objects over large arrays when the data shape matters.

Category-type data must be defined in ENUM classes.

Use Laravel conventions where they make sense.

Avoid clever abstractions unless they remove real duplication.

## Tests

All new behaviour should include tests.

For middleware packages, test at least:

- safe prompts pass through unchanged
- detection works
- configured actions work
- constructor values override config
- config values are used when constructor values are omitted
- missing config falls back to internal defaults
- unsafe input does not call the next middleware when blocked
- logs do not expose sensitive values by default
- invalid config values throw clear exceptions

Use package-specific test fixtures where needed.

Example structure:

```text
src/ExamplePackage/tests/
├── ExamplePackageTest.php
└── Fixtures/
```

After adding or moving classes, run:

```bash
composer dump-autoload
```

Then run the relevant package tests.

## Documentation

Every package should include a `README` with:

- what the package does
- what it does not guarantee
- features
- supported actions
- configuration
- usage example
- action-specific examples
- customisation examples
- production rollout guidance
- security notes
- exception handling
- limitations

Keep documentation honest. If a package uses heuristics or regex detection, say so clearly.

## Adding a new middleware package

Before adding a new middleware package, open an issue or discussion.

A new package should include:

```text
src/PackageName/
├── src/
├── tests/
├── README.md
└── composer.json
```

The root `composer.json` should be updated with:

- PSR-4 autoload mapping
- dev autoload mapping for tests
- replace entry
- test script if appropriate

The package `composer.json` should include its own PSR-4 mapping.

If the package needs shared config support, require:

```json
"promptphp/intercept-support": "^0.1"
```

Do not add a package-specific config publisher unless there is a strong reason.

## Pull request checklist

Before opening a pull request, please check:

- tests pass
- package-specific tests pass
- code is formatted
- static analysis passes where applicable
- public APIs are documented
- README examples are updated
- config changes are documented
- new defaults are safe
- false positives or limitations are documented
- no raw secrets or sensitive values are logged by default

Useful commands:

```bash
composer test
composer test:lint
composer test:types
```

## Security issues

Please do not open public issues for vulnerabilities.

If you find a security issue, please email Victor Ukam at [victorjohnukam@gmail.com](victorjohnukam@gmail.com). All security vulnerabilities will be addressed promptly.

Security-related reports should include:

- affected package
- affected version or commit
- reproduction steps
- expected behaviour
- actual behaviour
- possible impact
- suggested fix if known

## False positives and false negatives

Security and privacy middleware may produce false positives or false negatives.

When reporting one, please include:

- the package name
- the input that triggered or bypassed detection
- expected behaviour
- actual behaviour
- relevant config
- whether the input is safe to share publicly

Do not share real secrets, personal data, API keys, or private customer content in issues.

Use fake examples where possible.

## Roadmap contributions

The roadmap is open to discussion.

Good roadmap proposals should explain:

- why the package is needed
- what problem it solves today
- how it fits Intercept
- why it should be middleware
- what the v1 scope should be
- what should be deliberately left out

The current priority is to stabilise the first release:

- `promptphp/intercept-injection-guard`
- `promptphp/intercept-pii-redactor`

Future packages should build on real Laravel AI SDK usage and production pain points.

## License

By contributing to Intercept, you agree that your contributions will be licensed under the MIT license.
