# PHP Coding Standard

Shared ECS, Rector and PHPStan presets for PHP packages, Laravel packages and Laravel apps.

## Features

- **Three Presets** - PHP package, Laravel package and Laravel app presets. Each Laravel preset builds on the one before it.
- **Native Configuration** - Presets load through each tool's own configuration API, so projects extend them like any other set.
- **Fluent Chains** - Chains of three or more method calls written on one line, including ones that start with a static call, are split into one call per line.
- **Comment-Aware Line Length** - Long calls, signatures and arrays wrap at a 100-column target, and trailing `//` or `#` comments don't count toward it.
- **Long Arrays and Constructors** - Associative arrays and promoted constructors get one item per line when their code line passes the target. Short nested arrays stay inline.
- **Laravel Migrations** - `$table` chains in `Schema::create` and `Schema::table` callbacks stay on one line while they fit.
- **Rector Sets** - Name imports, PHP version sets based on the `php` constraint in `composer.json`, and the code quality, coding style, dead code, early return, instanceof, type declaration and Rector preset sets. Closures are not converted to arrow functions.
- **Strict Analysis** - PHPStan at max level with strict, deprecation, disallowed call (dangerous, execution, insecure and loose), Safe function, full type coverage and type-perfect rules (`no_mixed`, `narrow_param`, `narrow_return`, `null_over_false`), plus `checkBenevolentUnionTypes`.

## Requirements

- PHP 8.4+

## Installation

```bash
composer config allow-plugins.phpstan/extension-installer true
composer require --dev hosmelq/php-coding-standard
```

The PHPStan extensions are registered by `phpstan/extension-installer`, which only runs when Composer allows it.

## Usage

Pick the preset that matches the project and use the same row in each tool:

| Project         | ECS                   | Rector                   | PHPStan                        |
|-----------------|-----------------------|--------------------------|--------------------------------|
| PHP package     | `ECS_PHP`             | `RECTOR_PHP`             | `phpstan-php.neon`             |
| Laravel package | `ECS_LARAVEL_PACKAGE` | `RECTOR_LARAVEL_PACKAGE` | `phpstan-laravel-package.neon` |
| Laravel app     | `ECS_LARAVEL_APP`     | `RECTOR_LARAVEL_APP`     | `phpstan-laravel-app.neon`     |

ECS and Rector presets are constants on `HosmelQ\PhpCodingStandard\CodingStandard`. PHPStan presets are files in the package's `config` directory. The Laravel presets need extra packages, see [Laravel Presets](#laravel-presets).

### ECS

Create `ecs.php` in the project root:

```php
<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([CodingStandard::ECS_PHP])
    ->withPaths([__DIR__.'/src', __DIR__.'/tests']);
```

Run it with `vendor/bin/ecs check --fix`.

### Rector

Create `rector.php` in the project root:

```php
<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([CodingStandard::RECTOR_PHP])
    ->withPaths([__DIR__.'/src', __DIR__.'/tests']);
```

Run it with `vendor/bin/rector process`.

### PHPStan

Create `phpstan.neon` in the project root:

```neon
includes:
  - vendor/hosmelq/php-coding-standard/config/phpstan-php.neon

parameters:
  paths:
    - src
```

Run it with `vendor/bin/phpstan analyse`.

## Customizing

Presets are regular tool configuration. In ECS and Rector, call `withRules()`, `withConfiguredRule()` or `withSkip()` after `withSets()`. In PHPStan, values under `parameters` in the project file override the preset, but lists are merged; add `!` after a key to replace its list instead.

The custom fixers live in `HosmelQ\PhpCodingStandard\Fixer`. `FluentChainFixer`, `LongAssociativeArrayFixer` and `LongPromotedConstructorFixer` accept a `line_length` option. `CommentAwareLineLengthFixer` accepts `break_long_lines`, `inline_short_lines` and `line_length`; the presets turn `inline_short_lines` off.

All four fixers share a 100-column default through `HosmelQ\PhpCodingStandard\LineLength`. To change it for all of them, register the service in a local set file:

```php
<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\LineLength;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->service(LineLength::class, static fn (): LineLength => new LineLength(120));
};
```

Then add it after the preset: `->withSets([CodingStandard::ECS_PHP, __DIR__.'/ecs-line-length.php'])`. A `line_length` set on a single fixer takes precedence over the shared width. Widths must be positive integers.

## Laravel Presets

- **PHPStan** - Both Laravel presets need `larastan/larastan`, which boots the application to analyse it; packages also need `orchestra/testbench`. The package preset enables Larastan's config type, request-scope auth call, Octane compatibility, `env()` outside config and collection `toArray()` checks. The app preset adds model property checks and excludes `bootstrap/cache`.
- **Rector** - Both Laravel presets add Rector's Carbon set, so the project needs `nesbot/carbon` at runtime. The app preset also needs `driftingly/rector-laravel`: it adds Laravel code quality, facade alias, container string, helper and Eloquent sets, picks Laravel upgrade sets from the installed version, and skips `bootstrap/cache` and a few rules.
- **ECS** - Both Laravel presets add `DateTimeImmutableFixer` and `MbStrFunctionsFixer`. The app preset currently matches the package preset.

## Pest

`CodingStandard::RECTOR_PEST` adds Pest's coding style rules. It needs `pestphp/pest-plugin-rector` and is added next to a preset: `->withSets([CodingStandard::RECTOR_PHP, CodingStandard::RECTOR_PEST])`.

## Safe Functions

PHPStan reports native functions that return `false` on failure and suggests their `Safe\` replacements from `thecodingmachine/safe`. This package is a dev dependency, so if your code calls `Safe\` functions, add `thecodingmachine/safe` to `require`.

## Risky Rules

Some ECS rules can change behavior: `ArrayPushFixer`, `DeclareStrictTypesFixer`, `ModernizeTypesCastingFixer`, `OrderedTraitsFixer`, `SelfAccessorFixer` and `StrictParamFixer`. The Laravel presets add `DateTimeImmutableFixer`, `MbStrFunctionsFixer` and Rector's Carbon conversions. Review their changes, or turn any of them off with `withSkip()`.

## Known Limits

- The 100-column width is a formatting target, not a hard limit. Lists with arrow functions, destructuring, abstract and interface signatures, and multiline calls with comments can stay longer.
- Only associative arrays get one item per line. Short positional lists keep the line length fixer's layout, which can leave several items on one line when the surrounding call wraps.
- Fluent chains that continue with a static access are left as they are.
- The migration exception only covers direct chains on the callback's first parameter when it is `Blueprint $table`. Long chains and their array or call arguments can still be split.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

## Credits

- [Hosmel Quintana](https://github.com/hosmelq)
- [All Contributors](https://github.com/hosmelq/php-coding-standard/contributors)

**Built on:**
- [ECS](https://github.com/easy-coding-standard/easy-coding-standard)
- [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
- [PHPStan](https://github.com/phpstan/phpstan)
- [Rector](https://github.com/rectorphp/rector)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
