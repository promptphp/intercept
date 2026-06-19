# Monorepo Tools

This directory contains helper scripts for managing Intercept's monorepo and split-package workflow.

The monorepo remains the source of truth. Package repositories such as `promptphp/intercept-injection-guard`, `promptphp/intercept-pii-redactor`, and `promptphp/intercept-support` are read-only subtree splits.

## Requirements

The scripts expect these tools to be available locally or in CI:

```bash
git
gh
jq
```

You must also be authenticated with GitHub CLI:

```bash
gh auth status
```

## Available scripts

### `validate-packages.sh`

Validates that each package under `src/*` is correctly wired for the monorepo and split-package setup.

It checks:

- package `composer.json`
- package `README.md`
- package `src/` directory
- package `tests/` directory
- root Composer `replace` entries
- root Composer PSR-4 mappings
- package Composer PSR-4 mappings

Run:

```bash
composer packages:validate
```

### `create-repos.sh`

Creates missing GitHub mirror repositories for each package under `src/*`.

Run:

```bash
composer mirrors:create
```

This should normally only be needed when a new package is added.

### `update-repo-descriptions.sh`

Syncs GitHub mirror repository metadata from the package composer files.

It updates:

- repository description
- repository homepage URL
- repository topics

Run:

```bash
composer mirrors:sync
```

The default homepage URL is:

```text
https://intercept.promptphp.com
```

### `split.sh`

Splits package folders under `src/*` into their matching read-only mirror repositories using `git subtree split`.

Run changed packages only:

```bash
composer split
```

Run all packages:

```bash
composer split:all
```

### `release.sh`

Creates Git tags and GitHub releases for split repositories.

Run changed packages only:

```bash
composer release
```

Run all packages:

```bash
composer release:all
```

For the first public release:

```bash
RELEASE_TYPE=minor composer release:all
```

This creates `v0.1.0` when no previous tags exist.

## Package name overrides

Repository names are configured in:

```text
split-overrides.json
```

Example:

```json
{
  "Support": "intercept-support",
  "InjectionGuard": "intercept-injection-guard",
  "PIIRedactor": "intercept-pii-redactor"
}
```

If a package is not listed there, the tooling falls back to a kebab-case version of the folder name.

## Useful workflow

For a full manual release:

```bash
composer test
composer test:lint
composer packages:validate
composer mirrors:create
composer mirrors:sync
composer split:all
RELEASE_TYPE=minor composer release:all
```

For normal patch releases after the first release:

```bash
composer test
composer test:lint
composer packages:validate
composer split
composer release
```

## Troubleshooting

### Missing `jq`

Install `jq` before running the tooling:

```bash
sudo apt update
sudo apt install jq
```

A future improvement may replace `jq` usage with PHP helpers to reduce contributor setup requirements.

### Package validation says Composer mappings are missing

If the mappings look correct in `composer.json`, check namespace escaping in `tools/validate-packages.sh`.

Use:

```bash
namespace="PromptPHP\\Intercept\\${folder}\\"
test_namespace="PromptPHP\\Intercept\\${folder}\\Tests\\"
```

Do not use over-escaped namespace values.

### Class not found after adding a file

Check that the file path matches the namespace.

For example:

```php
PromptPHP\Intercept\PIIRedactor\Detectors\RegexDetector
```

must live at:

```text
src/PIIRedactor/src/Detectors/RegexDetector.php
```

Then run:

```bash
composer dump-autoload -o
```

You can verify with:

```bash
php -r "require 'vendor/autoload.php'; var_dump(class_exists('PromptPHP\\Intercept\\PIIRedactor\\Detectors\\RegexDetector'));"
```

### GitHub CLI authentication issues

Check:

```bash
gh auth status
```

If needed, re-authenticate:

```bash
gh auth login
```

### Mirror push failures

If pushing to split repositories fails, check that:

- the mirror repositories exist
- you have write access
- `MIRROR_TOKEN` is set if using token-based HTTPS pushes
- the token has permission to push to the mirror repositories

### Packagist publishing

These scripts do not register packages on Packagist.

Register each split package manually once. After that, Packagist can read future versions from Git tags.
