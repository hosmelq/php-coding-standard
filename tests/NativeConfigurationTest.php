<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function runConfiguredTool(string $tool, array $arguments): Process
{
    $process = new Process([PHP_BINARY, __DIR__.'/../vendor/bin/'.$tool, ...$arguments], dirname(
        __DIR__,
    ));
    $process->setTimeout(120);
    $process->run();

    return $process;
}

function configurationFixture(string $name, string $source, string $configuration): array
{
    $directory = __DIR__.'/../.cache/native-configuration/'.$name;

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $sourcePath = $directory.'/example.php';
    $configurationPath = $directory.'/configuration.'.($name === 'phpstan' ? 'neon' : 'php');
    file_put_contents($sourcePath, $source);
    file_put_contents($configurationPath, $configuration);

    return [$sourcePath, $configurationPath];
}

it('loads ECS sets and applies native local rule configuration and skips', function (): void {
    $source = "<?php\n\nfunction label(?string \$name): ?string\n{\n    return \$name;\n}\n";
    $base = <<<PHP_WRAP
    <?php
    use HosmelQ\\PhpCodingStandard\\CodingStandard;
    use Symplify\\EasyCodingStandard\\Config\\ECSConfig;
    return ECSConfig::configure()->withSets([CodingStandard::ECS_PHP])
    PHP_WRAP;
    [$file, $config] = configurationFixture('ecs', $source, $base.';');
    $result = runConfiguredTool(
        'ecs',
        ['check', $file, '--config', $config, '--fix', '--no-progress-bar'],
    );

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and(file_get_contents($file))->toContain('declare(strict_types=1);', 'null|string');

    $overrides = <<<'PHP'
    ->withConfiguredRule(PhpCsFixer\Fixer\LanguageConstruct\NullableTypeDeclarationFixer::class, [
        'syntax' => 'question_mark',
    ])
    ->withSkip([PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer::class]);
PHP;
    [$file, $config] = configurationFixture('ecs', $source, $base.$overrides);
    $result = runConfiguredTool(
        'ecs',
        ['check', $file, '--config', $config, '--fix', '--no-progress-bar'],
    );

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and(file_get_contents($file))->toContain('?string')->not->toContain(
            'declare(strict_types=1);',
        );
});

it('loads Rector sets and applies native local rules and skips', function (): void {
    $source = <<<'PHP'
<?php

final class Example
{
    protected const TOKEN = 'example';

    public function token(): string
    {
        return self::TOKEN;
    }

    public function isEnabled(bool $value): bool
    {
        return $value === true;
    }
}
PHP;
    $base = <<<'PHP_WRAP'
    <?php
    use HosmelQ\PhpCodingStandard\CodingStandard;
    use Rector\Config\RectorConfig;
    return RectorConfig::configure()->withSets([CodingStandard::RECTOR_PHP])
    PHP_WRAP;
    [$file, $config] = configurationFixture('rector', $source, $base.';');
    $result = runConfiguredTool(
        'rector',
        ['process', $file, '--config', $config, '--no-progress-bar'],
    );

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and(file_get_contents($file))->toContain('protected const')->not->toContain('=== true');

    $overrides = <<<'PHP'
    ->withRules([Rector\Privatization\Rector\ClassConst\PrivatizeFinalClassConstantRector::class])
    ->withSkip([Rector\CodeQuality\Rector\Identical\SimplifyBoolIdenticalTrueRector::class]);
PHP;
    [$file, $config] = configurationFixture('rector', $source, $base.$overrides);
    $result = runConfiguredTool(
        'rector',
        ['process', $file, '--config', $config, '--no-progress-bar'],
    );

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and(file_get_contents($file))->toContain('private const', '=== true')
        ->and($result->getOutput().$result->getErrorOutput())
        ->not->toContain('WARNING', 'deprecated', 'Deprecated');
});

it('loads PHPStan explicitly and applies native local parameter overrides', function (): void {
    $set = __DIR__.'/../config/phpstan-php.neon';
    $source = "<?php\n\nfunction accept(\$value): mixed\n{\n    return \$value;\n}\n";
    $base = "includes:\n  - $set\n\nparameters:\n  errorFormat: json\n";
    [$file, $config] = configurationFixture('phpstan', $source, $base);
    $result = runConfiguredTool(
        'phpstan',
        ['analyse', $file, '--configuration', $config, '--no-progress'],
    );

    expect($result->getExitCode())->toBe(1, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toContain('missingType.parameter');

    $overrides = <<<'NEON'
  level: 0
  type_coverage:
    param: 0
  type_perfect:
    narrow_param: false
    narrow_return: false
    no_mixed: false
NEON;
    [$file, $config] = configurationFixture('phpstan', $source, $base.$overrides);
    $result = runConfiguredTool(
        'phpstan',
        ['analyse', $file, '--configuration', $config, '--no-progress'],
    );

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and(json_decode($result->getOutput(), true, flags: JSON_THROW_ON_ERROR)['totals'])
        ->toBe(['errors' => 0, 'file_errors' => 0])
        ->and($result->getOutput().$result->getErrorOutput())
        ->not->toContain('WARNING', 'deprecated', 'Deprecated');
});

it(
    'wraps long migration statements and converges with the complete ECS set',
    function (string $method): void {
        $source = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('records', function (Blueprint $table): void {
    $table->string('name')->nullable()->index();
    $table->string('an_extremely_long_column_identifier_used_for_testing_the_line_length_limit', 255);
    $table->foreignId('an_extremely_long_foreign_identifier_used_for_testing')->nullable()->constrained()->cascadeOnDelete();
    $table->json('options')->default(['first_very_long_option_name' => 'first_value', 'second_very_long_option_name' => 'second_value'])->nullable();
});
PHP;
        $source .= "\n";
        $expected = <<<'PHP'
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('records', function (Blueprint $table): void {
    $table->string('name')->nullable()->index();
    $table->string(
        'an_extremely_long_column_identifier_used_for_testing_the_line_length_limit',
        255,
    );
    $table
        ->foreignId('an_extremely_long_foreign_identifier_used_for_testing')
        ->nullable()
        ->constrained()
        ->cascadeOnDelete();
    $table
        ->json('options')
        ->default([
            'first_very_long_option_name' => 'first_value',
            'second_very_long_option_name' => 'second_value',
        ])
        ->nullable();
});
PHP;
        $expected .= "\n";
        $source = str_replace('Schema::table', 'Schema::'.$method, $source);
        $expected = str_replace('Schema::table', 'Schema::'.$method, $expected);
        $configuration = <<<'PHP_WRAP'
        <?php
        use HosmelQ\PhpCodingStandard\CodingStandard;
        use Symplify\EasyCodingStandard\Config\ECSConfig;
        return ECSConfig::configure()->withSets([CodingStandard::ECS_PHP]);
        PHP_WRAP;
        [$file,
            $config] = configurationFixture('migration-'.$method, $source, $configuration);

        for ($pass = 0; $pass < 2; ++$pass) {
            $result = runConfiguredTool(
                'ecs',
                ['check', $file, '--config', $config, '--fix', '--no-progress-bar'],
            );

            expect($result->isSuccessful())->toBeTrue(
                $result->getOutput().$result->getErrorOutput(),
            )
                ->and(file_get_contents($file))->toBe($expected);
        }
    },
)->with(['create', 'table']);

it('converges for nested assertion chains and arrays', function (
    string $source,
    array $snippets = [],
): void {
    $configuration = <<<'PHP_WRAP'
    <?php
    use HosmelQ\PhpCodingStandard\CodingStandard;
    use Symplify\EasyCodingStandard\Config\ECSConfig;
    return ECSConfig::configure()->withSets([CodingStandard::ECS_PHP]);
    PHP_WRAP;
    [$file, $config] = configurationFixture('nested', $source, $configuration);
    $result = runConfiguredTool(
        'ecs',
        ['check', $file, '--config', $config, '--fix', '--no-progress-bar'],
    );
    $formatted = file_get_contents($file);

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and($formatted)->not->toBe($source);

    foreach ($snippets as $snippet) {
        expect($formatted)->toContain($snippet);
    }

    $result = runConfiguredTool('ecs', ['check', $file, '--config', $config, '--no-progress-bar']);

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and(file_get_contents($file))->toBe($formatted);
})->with([
    'array containing a ternary chain' => [
        <<<'PHP'
<?php
$nested = ['callback' => $enabled ? $first->where('a', 1)->orderBy('b')->get() : $second->where('a', 1)->orderBy('b')->get(), 'other' => 'value'];
PHP,
        ["    'other' => 'value',\n"],
    ],
    'array containing an arrow chain' => [
        <<<'PHP'
<?php
$nested = ['callback' => fn ($q) => $q->where('a', 1)->orderBy('b')->get(), 'other' => 'value value value'];
PHP,
        ["'callback' => fn (\$q) => \$q\n", "    'other' => 'value value value',\n"],
    ],
    'asymmetric promotions' => [
        <<<'PHP'
<?php
class Example
{
    public function __construct(private(set) string $firstPropertyWithALongName, protected(set) string $secondPropertyWithALongName) {}
}
PHP,
        ["__construct(\n", "private(set) string \$firstPropertyWithALongName,\n"],
    ],
    'chain after a closed block' => [
        <<<'PHP'
<?php
if ($enabled) { consume(); } $next = $query->first()->second()->third();
PHP,
        ["}\n", "\$next = \$query\n    ->first()\n"],
    ],
    'indented multiple statements' => [
        <<<'PHP'
<?php
if ($enabled) {
    $d = $first->a()->b()->c(); $e = $second->a()->b()->c();
}
PHP,
        ["    \$e = \$second\n        ->a()\n"],
    ],
    'late chain indentation' => [
        <<<'PHP'
<?php
function example(): void
{
    expect($record->first()->second()['items'])->toBe([
        ['alpha' => 'sample value', 'beta' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
    ])->and($other)->toBeTrue();
}
PHP,
        ["                'alpha' => 'sample value',\n"],
    ],
    'late nested array indentation' => [
        <<<'PHP'
<?php
function example(): void
{
    expect($record)->toBe([
        'settings' => [['alpha' => 'sample value', 'beta' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx']],
    ])->and($other)->toBeTrue();
}
PHP,
        ["'settings' => [[\n", "'alpha' => 'sample value',\n"],
    ],
    'late nested chain indentation' => [
        <<<'PHP'
<?php
function example(): void
{
    expect($other)->toBeTrue()
        ->and($record->first()->second()->third())->toBe([
            ['alpha' => 'sample value', 'beta' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
        ]);
}
PHP,
        ["                'alpha' => 'sample value',\n"],
    ],
    'long syntax array arguments' => [
        <<<'PHP'
<?php
$query->first(array('first_very_long_option_name' => 'first_value', 'second_very_long_option_name' => 'second_value'))->second()->third();
PHP,
        ["\$query\n    ->first(array(\n", "    ->second()\n    ->third();"],
    ],
    'nested arrays' => <<<'PHP'
<?php

it('lists questions', function (): void {
    expect($record->modules()->pluck('title')->all())->toBe(['First', 'Second'])
        ->and($record->questions()->get(['answer', 'question'])->toArray())->toBe([
            ['answer' => 'The default option is enabled.', 'question' => 'Which option is enabled?'],
            ['answer' => 'The result contains a sample value.', 'question' => 'What does the result contain?'],
        ]);
});
PHP,
    'nested chains' => <<<'PHP'
<?php

it(
    'records entries',
    function (): void {
        expect($calls)->toBe(3)->and($record->entries()->pluck('start_index')->all())->toBe(
            [0, 1500],
        )
            ->and($record->entries()->pluck('run_id')->unique()->all())->toBe([$job->runId]);
    },
);
PHP,
    'positional array chains' => [
        <<<'PHP'
<?php
$values = [$first->one()->two()->three(), $second->one()->two()->three()];
PHP,
        ["    ->three(), \$second\n    ->one()\n"],
    ],
    'readonly promotions' => [
        <<<'PHP'
<?php
class Example
{
    public function __construct(readonly string $firstPropertyWithALongName, readonly string $secondPropertyWithALongName) {}
}
PHP,
        ["__construct(\n", "readonly string \$firstPropertyWithALongName,\n"],
    ],
    'short nested arrays' => [
        <<<'PHP'
<?php
$config = ['short' => ['a' => 1], 'list' => [1, 2, 3], 'long' => 'value value value value value value value'];
PHP,
        ["    'short' => ['a' => 1],\n"],
    ],
]);
