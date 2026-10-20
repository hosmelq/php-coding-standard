# PHP Coding Standard

Opinionated ECS, Rector, and PHPStan presets for PHP and Laravel packages and
applications, with custom fixers that lay out code by line width.

## Requirements

- PHP 8.4+

## Installation

Allow the PHPStan extension installer, then install PHP Coding Standard with
Composer:

```bash
composer config allow-plugins.phpstan/extension-installer true
composer require --dev hosmelq/php-coding-standard
```

The package includes every tool and extension the presets use, except Larastan.
Laravel packages and applications require it themselves:

```bash
composer require --dev larastan/larastan
```

## Choose a preset

Use the same row in every tool:

| Project | ECS | Rector | PHPStan |
| --- | --- | --- | --- |
| PHP package or application | `ECS_PHP` | `RECTOR_PHP` | `phpstan-php.neon` |
| Laravel package | `ECS_LARAVEL_PACKAGE` | `RECTOR_LARAVEL_PACKAGE` | `phpstan-laravel-package.neon` |
| Laravel application | `ECS_LARAVEL_APP` | `RECTOR_LARAVEL_APP` | `phpstan-laravel-app.neon` |

ECS and Rector presets are constants on `HosmelQ\PhpCodingStandard\CodingStandard`.
PHPStan presets are files in the package's `config` directory. Each Laravel
preset builds on the one above it. Larastan boots Laravel packages with Orchestra
Testbench, which they already require for their tests.

## Configure the tools

Create `ecs.php`:

```php
<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([CodingStandard::ECS_PHP])
    ->withPaths([__DIR__.'/src', __DIR__.'/tests']);
```

Create `rector.php`. To apply Pest's coding style rules with Pest 5, require
`pestphp/pest-plugin-rector` and add `CodingStandard::RECTOR_PEST`:

```bash
composer require --dev pestphp/pest-plugin-rector
```

```php
<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([CodingStandard::RECTOR_PHP, CodingStandard::RECTOR_PEST])
    ->withPaths([__DIR__.'/src', __DIR__.'/tests']);
```

Create `phpstan.neon`:

```neon
includes:
  - vendor/hosmelq/php-coding-standard/config/phpstan-php.neon

parameters:
  paths:
    - src
```

Run the tools:

```bash
vendor/bin/rector process
vendor/bin/ecs check --fix
vendor/bin/phpstan analyse
```

Run Rector before ECS so ECS lays out Rector's changes. One ECS run is enough. PHPStan runs at level `max` with strict, deprecation,
safe function, disallowed call, and full type coverage rules. The Laravel
application preset also analyses `app`, `bootstrap`, `config`, `database`, and
`routes`. PHPStan caches its results in `.cache/phpstan`, so add `.cache` to
`.gitignore`.

## Lay out code

The ECS presets keep code within 100 columns. Chains that fit join onto one
line, and long lines break their outermost construct first:

```php
$invoice = Invoice::factory()
    ->for($customer)
    ->createOne();

$response = $this->actingAs($user)->post(route('invoices.store'), ['number' => 'INV-001', 'currency' => 'USD', 'paid' => true]);

if ($subscription->status === SubscriptionStatus::PastDue && $user->canManageBilling($organization)) {
```

Becomes:

```php
$invoice = Invoice::factory()->for($customer)->createOne();
$response = $this->actingAs($user)->post(route('invoices.store'), [
    'number' => 'INV-001',
    'currency' => 'USD',
    'paid' => true,
]);

if (
    $subscription->status === SubscriptionStatus::PastDue
    && $user->canManageBilling($organization)
) {
```

- Long method chains with three or more calls put each call on its own line.
- Arguments, parameters, and arrays put each item on its own line with a
  trailing comma. A call's last associative array, or its only array, opens
  inside the call.
- Long conditions break before `&&`, `||`, `??`, `.`, `?`, and `:`.
- Long arrow functions move their body to the next line, starting with `=>`.
- Lists with `//` comments between items put each item on its own line.
- Test calls such as `it('title', function () {})` keep their title and closure
  on one line.
- Blank lines between consecutive variable assignments are removed.

Strings, comments, comparisons, and long test titles can stay longer than the
line length.

## Change the line length

Configure `LineLengthFixer` in `ecs.php`. The line length must be at least 80:

```php
use HosmelQ\PhpCodingStandard\Fixer\LineLengthFixer;

return ECSConfig::configure()
    ->withSets([CodingStandard::ECS_PHP])
    ->withConfiguredRule(LineLengthFixer::class, ['line_length' => 120]);
```

## Customize presets

Call `withConfiguredRule()`, `withRules()`, or `withSkip()` after `withSets()`
in ECS and Rector:

```php
use PhpCsFixer\Fixer\Strict\StrictParamFixer;

return ECSConfig::configure()
    ->withSets([CodingStandard::ECS_PHP])
    ->withSkip([StrictParamFixer::class]);
```

In PHPStan, project `parameters` override the preset and lists are merged. Add
`!` after a key, such as `paths!`, to replace a list.

## Review risky changes

Some rules change behavior, such as `DeclareStrictTypesFixer`,
`MbStrFunctionsFixer`, `StrictComparisonFixer`, and `StrictParamFixer`, and the
Laravel presets convert dates to Carbon. Review these changes, or skip a rule
with `withSkip()`.

## Add runtime dependencies

The package is a development dependency. If your code calls `Safe\` functions or
uses Carbon, require `thecodingmachine/safe` or `nesbot/carbon` directly.

## Development

Run the test suite with:

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

## Contributing

Pull requests are welcome. Please run the test suite before submitting changes.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
