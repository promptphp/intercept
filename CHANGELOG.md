# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Removed

## [0.3.1] - 2026-08-06

### Fixed

- Narrowed the Tool Approval Guard default entity list to `credit_card`, `api_key` and
  `bearer_token`. It was derived from the PII Redactor list, so with the default `block` action any
  agent owning a mail, SMS or HTTP tool had legitimate calls blocked. `email`, `phone`, `url`,
  `ip_address` and `mac_address` remain supported but are now opt-in.

  If you published `config/intercept.php`, this fix does not reach you automatically. Update `tool_approval_guard.entities`
  to ['credit_card', 'api_key', 'bearer_token']`and set`tool_approval_guard.scan_injection`to`false` by hand.

- Changed the Tool Approval Guard `scan_injection` default to `false`. Prose written for a human
  reader routinely matches the injection patterns. Enable it when a proposed argument feeds another
  model or agent rather than a person.
- `ToolApprovalGuardDefaults` no longer derives its entity lists from `PIIRedactorDefaults`, with an
  architecture test pinning them apart.

Both changes only loosen defaults, so nothing that worked on v0.3.0 stops working. `block_entities`
is unchanged and still stops the run regardless of the configured action.

### Changed

- Documented that `action: 'log'` is not observe-only on its own, because `block_entities` stops the
  run whatever the action is set to. A dry run needs an empty `block_entities` as well.
- Corrected README, docs and roadmap wording implying that any sensitive-looking value in an
  outbound tool argument is an exfiltration signal.

## [0.3.0] - 2026-08-06

- Added `promptphp/intercept-tool-approval-guard`, which inspects the tool calls an agent proposes
  while pausing for human approval, before they are surfaced for review. Intercept already scans
  what a human edited when resolving a paused run but trusts whatever the model proposed.
- Added tool allow and deny lists, PII and secret detection, and prompt injection detection over
  proposed tool arguments. A secret in an outbound tool argument is an exfiltration signal; an
  injection pattern suggests the model was manipulated by content Intercept never saw.
- Added `block` and `log` actions, `block_entities` that stop the run regardless of the action, and
  a custom callback receiving `ApprovalFinding` value objects.
- Added `PIIRedactor\Detectors\DefaultDetectors::all()` and `InjectionGuardDefaults::patterns()` so
  the detector set and injection patterns can be reused without duplication. Detection behaviour is
  unchanged and the pattern strings are byte-identical.
- Added `pendingApprovalSegments()` to the `ScansApprovalDecisions` concern, which walks proposed
  tool arguments using the same dot-path extraction as edited arguments.

### Changed

- This is the first Intercept middleware to act on the response rather than the prompt, since the
  tool calls it guards are proposed by the model.
- On a streamed run the guard cannot block, because `$next()` returns before the model has proposed
  anything and the caller has received the streamed text by the time approvals are known. The
  `block` action degrades to logging there, recorded as `degraded_from`. The tool has still not
  executed, so a logged proposal continues to require human approval before anything happens.
- Updated the security notes: proposed tool calls are now inspected, but tool results, attachments,
  and conversation history still are not, and only approval-gated tools are covered.
- Moved the credit card Luhn check into the credit card detector's own validator, matching how the
  URL detectors already validate. The detector previously emitted every 13 to 19 digit run and
  relied on `PIIRedactor` filtering the failures afterwards, which meant it could not be reused on
  its own. Detection results are unchanged.

### Removed

- Removed the protected `PIIRedactor::passesLuhn()` method, now that the check lives in the credit
  card detector. This only affects code that subclassed `PIIRedactor` and called it directly.

## [0.2.0] - 2026-07-31

### Added

- Added tool approval decision scanning to `PromptInjectionGuard` and `PIIRedactor`.
  When a paused agent run is resumed with `Decisions`, the prompt text is empty and the only new
  content is what a human supplied while resolving the pending tool calls. Edited tool arguments
  and rejection results previously reached the AI provider unscanned.
- Added the `ScansApprovalDecisions` concern and the `ApprovalDecisionSegment` value object to the
  Support package, so both middleware extract decision content identically.
- Added a `scan_approval_decisions` option to both middleware, enabled by default and configurable
  through `config/intercept.php` or the middleware constructors.
- Added documentation for tool approval resumes, including what Intercept can and cannot inspect.

### Changed

- Blocked resumed runs now report the offending tool call and field in the
  `PromptInjectionGuardException` message. The matched text is never included.
- Resumed prompts are immutable by design, because a paused turn must replay verbatim against the
  provider that recorded it. The `redact`, `mask`, `sanitize`, and `warn` actions therefore degrade
  to logging on that path, recorded in logs as `degraded_from`. Blocked entities and the `block`
  action still stop the run.
- `promptphp/intercept-support` now requires `laravel/ai`, since the shared concern reads the
  SDK's approval decision types. Both middleware packages already required it.
- Corrected the supported entity list in the configuration reference, which was missing
  `mac_address` and `url`.

### Fixed

### Removed

## [0.1.9] - 2026-07-31

### Fixed

- Fixed `PromptInjectionGuard` failing to detect more common prompt injection phrasings.
  `ignore all previous instructions`, `disregard all previous instructions`,
  `ignore the previous instructions`, `disregard the previous instructions`, and
  `ignore all previous prompts` were not matched by any built-in pattern, because the `ignore`
  and `disregard` patterns required the noun to follow the qualifier immediately.
- Aligned the `ignore` and `disregard` patterns with the existing `forget` pattern structure,
  which already handled these forms correctly. The two narrower `prior`/`earlier` patterns are
  now redundant and have been folded into the corrected patterns.
- Note that the built-in pattern strings are surfaced in logs and passed to custom callbacks.
  Anything asserting on the exact pattern text for `ignore` or `disregard` needs updating.

## [0.1.8] - 2026-07-23

### Added

- Support PHP 8.3 across middleware collection.

## [0.1.7] - 2026-07-18

### Added

- Added URL detection to `PIIRedactor`.
- URLs can now be redacted, masked, logged, or blocked alongside the existing supported entity types.
- Added support for all versions of `laravel/ai`.

### Changed

- Updated `PIIRedactorTestProvider` and `PromptInjectionTestProvider` to implement all methods required by the latest `laravel/ai` `TextProvider` / `Provider` interfaces, resolving CI failures on fresh installs.
- Updated documentation to include URL detection and tests.

## [0.1.6] - 2026-07-16

### Added

- Added MAC address detection to `PIIRedactor`.
- MAC addresses can now be redacted, masked, logged, or blocked alongside the existing supported entity types.

### Changed

- Updated documentation to include MAC address detection and tests.

## [0.1.5] - 2026-07-14

### Added

- Added contribution guidelines to help developers contribute consistently to the Intercept middleware collection.

### Changed

- Hardened built-in prompt injection detection patterns in `PromptInjectionGuard`.
- Updated the project roadmap and contribution documentation.

### Removed

- Removed the redundant `SECURITY.md` file.

## [0.1.4] - 2026-06-24

### Added

- Added a shared `InterceptException` base exception in the Support package.
- Added inheritance tests to confirm middleware-specific exceptions extend the shared Intercept exception.
- Added documentation for handling blocked prompts through the shared Intercept exception.
- Added initial Mintlify-ready documentation structure and MDX docs for Intercept.

### Changed

- Updated `PromptInjectionGuardException` to extend the shared `InterceptException`.
- Updated `PIIRedactorException` to extend the shared `InterceptException`.
- Improved exception handling DX by allowing applications to catch one shared Intercept exception while keeping middleware-specific exceptions available.
- Updated release title generation to use the release tag consistently across repositories.

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
