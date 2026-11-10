# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.5.0 - 2026-11-10

- Lay out long method chains of three or more calls that were split by hand with several calls on a line the same way as single-line chains. Chains with one call per line or that form an arrow function's body keep their layout, and long Pest `expect()` chains keep their manual `->and()` grouping.
- Keep a property before a call on that call's line when splitting a chain, as in `->each->delete()`.
- Require `laravel/pao` so AI agents get compact JSON output from PHPStan, Rector, Pest, PHPUnit, and Paratest.
- Stop Rector from adding a blank line between consecutive assignments, which ECS removes again, so both tools agree on the same code.

## v1.4.0 - 2026-11-04

- Separate statement groups with a blank line. Assignments, `new` expressions, statements on the same variable, static calls, and function calls each form a group, and assignments are also separated from any other statement.
- Treat assignments to properties, static properties, and array items, such as `$this->value = 1;` and `$items[] = $item;`, as part of an assignment group.

## v1.3.0 - 2026-10-27

- Rejoin nested method chains after outer constructs move them so one ECS run produces the final layout.
- Break long two-call chains before the last call when it fits, preserve hugged arguments, and attach the last call to a wrapped head's closing parenthesis when it fits.

## v1.2.0 - 2026-10-27

- Report overriding methods without `#[\Override]` in PHPStan instead of adding the attribute with Rector's deprecated `AddOverrideAttributeToOverriddenMethodsRector`.

## v1.1.0 - 2026-10-20

- Make `pestphp/pest-plugin-rector` optional so packages can use Pest 4 and test Laravel 12. Require it to use `RECTOR_PEST`.

## v1.0.0 - 2026-10-20

- Initial release.
