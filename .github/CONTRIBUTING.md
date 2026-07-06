# Contributing

Contributions are **welcome** and accepted via pull requests against the **monorepo** ([`promptphp/intercept`](https://github.com/promptphp/intercept)) and will be fully **credited**.

> [!IMPORTANT]
> The per-tool packages (`promptphp/intercept-*`) are **read-only mirrors** with issues and pull requests disabled.
> Never push a tag against them; everything flows from this monorepo. Open all contributions here instead.

Please read and understand the contribution guide before creating an issue or pull request.

## Etiquette

This project is open source, and as such, the maintainers give their free time to build and maintain the source code held within. They make the code freely available in the hope that it will be of use to other developers. It would be extremely unfair for them to suffer abuse or anger for their hard work.

Please be considerate towards maintainers when raising issues or presenting pull requests. Let's show the world that developers are civilized and selfless people.

It's the duty of the maintainer to ensure that all submissions to the project are of sufficient quality to benefit the project. Many developers have different skillsets, strengths, and weaknesses. Respect the maintainer's decision, and do not be upset or abusive if your submission is not used.

## Viability

When requesting or submitting new features, first consider whether it might be useful to others. Open source projects are used by many developers, who may have entirely different needs to your own. Think about whether or not your feature is likely to be used by other users of the project.

## Procedure

Before filing an issue,

- attempt to replicate the problem, to ensure that it wasn't a coincidental incident.
- check to make sure your feature suggestion isn't already present within the project.
- check the pull requests tab to ensure that the bug doesn't have a fix in progress.
- check the pull requests tab to ensure that the feature isn't already in progress.

Before submitting a pull request,

- check the codebase and documentation to ensure that your feature doesn't already exist.
- check the pull requests to ensure that another person hasn't already submitted the feature or fix.

### Contribution philosophy

Intercept packages should be

- small, focused and address a particular use case
- installable independently
- useful without published config
- safe logging with prompt hashes if there's a log action
- explicit about limitations and clearly documented
- well tesed and easy to test

### Before you start

For small fixes, feel free to open a pull request directly. For larger changes, please open an issue or discussion first, especially for

- new middleware packages
- config shape changes
- default behaviour changes
- security-related changes
- changes that affect package splitting or releases

When proposing a new package, please include

- the problem being solved
- proposed package name
- expected middleware behaviour
- default action
- config shape
- safety considerations
- examples of real-world usage
- likely false positives or failure modes

## Branching Strategy & Commits

### Branching

We follow a trunk-based development approach with feature branches.

- `main` is the primary branch and should always be deployable
- Create feature branches from `main` using the format: `feature/description-of-change`
- Bug fix branches should use: `fix/description-of-bug`
- Release branches use: `release/version-number`

### Commit Messages

We follow [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/) for clear, machine and human-readable commit history. Each commit message should be structured as follows:

```txt
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

Types include

- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation only changes
- `style`: Changes that do not affect the meaning of the code
- `refactor`: A code change that neither fixes a bug nor adds a feature
- `perf`: A code change that improves performance
- `test`: Adding missing tests or correcting existing tests
- `chore`: Changes to the build process or auxiliary tools

Examples

```txt
feat(middleware): add injection guard middleware
fix(config): correct indentation
docs(readme): update installation steps
```

Breaking changes must include a `!` after the type/scope and `BREAKING CHANGE:` in the footer

```txt
feat(middleware)!: change action key

BREAKING CHANGE: block action is now renamed to stop
```

#### Code style

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

## Local setup

Clone the repository

```bash
git clone https://github.com/promptphp/intercept.git
cd intercept
```

Install dependencies

```bash
composer install
```

Testing the library

```bash
#Run the test suite
composer test

# Run package-specific tests
composer test:injection-guard
composer test:pii-redactor
composer test:support

# Run architecture tests
composer test:architecture

# Run lint checks
composer test:lint

# Code formatting
composer format

# static analysis
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
├── tools/
├── tests/
├── composer.json
└── README.md
```

> [!IMPORTANT]
> Each `src/<Middleware>/` is split into its own read-only mirror repo and published to Packagist, requiring only both `promptphp/intercept-support` and `laravel/ai`. You develop and test everything in the monorepo; the mirrors are generated, never edited.

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

The shared config is published by `promptphp/intercept-support` using the tag

```bash
php artisan vendor:publish --tag=intercept-config
```

Middleware packages should not publish their own config files.

Config should always resolve in this order:

```text
constructor value > config value > internal middleware default
```

This means

- users do not need to publish config
- partial config is supported
- missing config sections are safe
- constructor values always win for per-agent overrides

Each middleware package should define its own internal defaults.

Middleware packages should only require their own config section when the user wants to customise global behaviour.

Example

```php
$config = InterceptConfig::middleware(
    'pii_redactor',
    PIIRedactorDefaults::values(),
);
```

## Middleware behaviour

Middleware should be conservative by default.

For security and privacy packages

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

The package `composer.json` should include its own PSR-4 mapping, require `promptphp/intercept-support` and `laravel/ai`.

Do not add a package-specific config publisher.
