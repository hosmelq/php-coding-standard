# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Rejoin nested method chains after outer constructs move them so one ECS run produces the final layout.
- Break long two-call chains before the last call when it fits, preserve hugged arguments, and attach the last call to a wrapped head's closing parenthesis when it fits.

## v1.2.0 - 2026-10-27

- Report overriding methods without `#[\Override]` in PHPStan instead of adding the attribute with Rector's deprecated `AddOverrideAttributeToOverriddenMethodsRector`.

## v1.1.0 - 2026-10-20

- Make `pestphp/pest-plugin-rector` optional so packages can use Pest 4 and test Laravel 12. Require it to use `RECTOR_PEST`.

## v1.0.0 - 2026-10-20

- Initial release.
