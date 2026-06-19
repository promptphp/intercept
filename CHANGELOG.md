# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Removed

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
