# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Removed

## [0.1.3] - 2026-06-20

### Fixed

- Added Laravel package discovery metadata to the root `promptphp/intercept` package so the shared support service provider is auto-discovered when installing the meta package.
- Fixed `php artisan vendor:publish --tag=intercept-config` returning no publishable resources after installing `promptphp/intercept`.
- Updated split release tooling to respect `RELEASE_VERSION`, allowing all split packages to be released with the same explicit version.
- Added root release tooling for creating project-level GitHub releases on the main `promptphp/intercept` repository.

### Changed

- Standardised the release flow so the root package and split packages can stay version-aligned.
- Updated GitHub mirror repository homepage metadata to use `https://intercept.promptphp.com`.

## [0.1.2] - 2026-06-20

### Fixed

- Added the missing `promptphp/intercept-support` split package release.
- Added the missing `autoload-dev` test namespace mapping for `promptphp/intercept-support`.
- Ensured the Support package is included in validation, mirror creation, metadata sync, splitting, and release workflows.

## [0.1.1] - 2026-06-20

### Fixed

- Release housekeeping for the initial split package publishing flow.

## [0.1.0] - 2026-06-20

### Added

- Initial release of Intercept as a modular middleware collection for Laravel AI agents.
- Added `promptphp/intercept-support` for shared config and support utilities.
- Added shared `config/intercept.php` support through the `intercept-config` publish tag.
- Added `InterceptConfig` for safe middleware config resolution.
- Added `promptphp/intercept-injection-guard`.
- Added prompt injection detection with `block`, `log`, `warn`, and `sanitize` actions.
- Added custom prompt injection patterns with optional merging of built-in patterns.
- Added prompt normalisation for injection detection.
- Added safe prompt injection logging with prompt hashes.
- Added `promptphp/intercept-pii-redactor`.
- Added PII detection for emails, phone numbers, credit cards, IP addresses, API keys, and bearer tokens.
- Added PII `redact`, `mask`, `log`, and `block` actions.
- Added blocked high-risk PII entities for credit cards, API keys, and bearer tokens.
- Added allowed email and allowed domain support for PII redaction.
- Added safe PII logging with value hashes.
- Added package README documentation for Support, Injection Guard, and PII Redactor.
- Added roadmap and contribution guidelines.
- Added Pest test coverage for Support, Injection Guard, and PII Redactor.
- Added monorepo split-package structure and Composer replace mappings.
