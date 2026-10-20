<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\LineLengthFixer;
use PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;

/**
 * @return list<array{null|int|string, string}>
 */
function significantTokens(string $code): array
{
    $tokens = Tokens::fromCode($code);
    $result = [];

    foreach ($tokens as $index => $token) {
        $next = $tokens->getNextMeaningfulToken($index);
        $trailingComma = $token->equals(',') && $next !== null
            && in_array($tokens[$next]->getContent(), [')', ']'], true);

        if (! $token->isWhitespace() && ! $trailingComma) {
            $result[] = [$token->getId(), $token->getContent()];
        }
    }

    return $result;
}

function formatLines(string $code, int $lineLength = 100): string
{
    $fixer = new LineLengthFixer();
    $fixer->configure(['line_length' => $lineLength]);

    $tokens = Tokens::fromCode($code);
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    return $tokens->generateCode();
}

it('formats fixtures idempotently without changing code', function (string $fixture): void {
    [$input, $expected] = array_pad(explode("\n-----\n", file_get_contents($fixture), 2), 2, null);
    $expected ??= $input;
    $output = formatLines($input);

    PhpToken::tokenize($output, TOKEN_PARSE);

    expect($output)->toBe($expected)
        ->and(significantTokens($output))->toBe(significantTokens($input))
        ->and(formatLines($output))->toBe($output);
})->with(function (): iterable {
    foreach (glob(__DIR__.'/Fixtures/LineLength/*/*.php.inc') as $fixture) {
        yield basename(dirname($fixture)).'/'.basename($fixture, '.php.inc') => [$fixture];
    }
});

it('formats method chains', function (string $input, string $expected): void {
    $output = formatLines($input);

    expect($output)->toBe($expected)
        ->and(significantTokens($output))->toBe(significantTokens($input))
        ->and(formatLines($output))->toBe($output);
})->with([
    'chains at the line length stay inline' => [
        "<?php\n\$packages = \$organization->packages()->where('status', 'active')->orderBy('created_at_date')->get();\n",
        "<?php\n\$packages = \$organization->packages()->where('status', 'active')->orderBy('created_at_date')->get();\n",
    ],
    'chains past the line length are split' => [
        "<?php\n\$packages = \$organization->packages()->where('status', 'active')->orderBy('created_at_dates')->get();\n",
        "<?php\n\$packages = \$organization\n    ->packages()\n    ->where('status', 'active')\n    ->orderBy('created_at_dates')\n    ->get();\n",
    ],
    'dynamic methods' => [
        "<?php\n\$query->{\$method}('first argument value')->second('second argument value')->third('third argument value');\n",
        "<?php\n\$query\n    ->{\$method}('first argument value')\n    ->second('second argument value')\n    ->third('third argument value');\n",
    ],
    'for header semicolons' => [
        "<?php\nfor (\$i = 0; \$query->first('first argument')->second('second argument')->third('third argument'); ++\$i) {}\n",
        "<?php\nfor (\$i = 0; \$query\n    ->first('first argument')\n    ->second('second argument')\n    ->third('third argument'); ++\$i) {}\n",
    ],
    'global factory is not a method' => [
        "<?php\nfactory()->first()->second();\n",
        "<?php\nfactory()->first()->second();\n",
    ],
    'joins chains that fit' => [
        "<?php\n\$query\n    ->first()\n    ->second()\n    ->third();\n",
        "<?php\n\$query->first()->second()->third();\n",
    ],
    'joins partially split chains' => [
        "<?php\n\$query\n    ->first()->second()->third();\n",
        "<?php\n\$query->first()->second()->third();\n",
    ],
    'joins single calls' => [
        "<?php\nresolve(EnsureOrganizationRoles::class)\n    ->handle(\$invitation->organization);\n",
        "<?php\nresolve(EnsureOrganizationRoles::class)->handle(\$invitation->organization);\n",
    ],
    'joins static chains' => [
        "<?php\n\$defaultAddress = OwnerContact::factory()\n    ->for(\$owner)\n    ->createOne(['is_default' => true]);\n",
        "<?php\n\$defaultAddress = OwnerContact::factory()->for(\$owner)->createOne(['is_default' => true]);\n",
    ],
    'keeps chains that do not fit split' => [
        "<?php\n\$packages = \$organization->packages()\n    ->where('status', 'active')\n    ->orderBy('created_at_dates')\n    ->get();\n",
        "<?php\n\$packages = \$organization->packages()\n    ->where('status', 'active')\n    ->orderBy('created_at_dates')\n    ->get();\n",
    ],
    'multiline argument' => [
        "<?php\n\$query->first(\n    true,\n)->second()->third();\n",
        "<?php\n\$query->first(\n    true,\n)->second()->third();\n",
    ],
    'multiline array argument' => [
        "<?php\n\$query\n    ->first([\n        'value' => 1,\n    ])\n    ->second()\n    ->third();\n",
        "<?php\n\$query\n    ->first([\n        'value' => 1,\n    ])\n    ->second()\n    ->third();\n",
    ],
    'multiple statements retain comments' => [
        "<?php\nfirst(); /* keep */ \$query->first('first argument value')->second('second argument value')->third('third argument value');\n",
        "<?php\nfirst();\n/* keep */ \$query\n    ->first('first argument value')\n    ->second('second argument value')\n    ->third('third argument value');\n",
    ],
    'nullsafe calls' => [
        "<?php\n\$query?->first('first argument value')?->second('second argument value')?->third('third argument value');\n",
        "<?php\n\$query\n    ?->first('first argument value')\n    ?->second('second argument value')\n    ?->third('third argument value');\n",
    ],
    'property accesses are not calls' => [
        "<?php\n\$query->first()->property->second();\n",
        "<?php\n\$query->first()->property->second();\n",
    ],
    'property root' => [
        "<?php\n\$query->builder->first('first argument value')->second('second argument value')->third('third argument value');\n",
        "<?php\n\$query->builder\n    ->first('first argument value')\n    ->second('second argument value')\n    ->third('third argument value');\n",
    ],
    'root call with an inline closure' => [
        "<?php\nfactory(function () { first(); return true; })->first('first argument')->second('second argument')->third('third');\n",
        "<?php\nfactory(function () { first(); return true; })\n    ->first('first argument')\n    ->second('second argument')\n    ->third('third');\n",
    ],
    'static calls only are untouched' => [
        "<?php\nQuery::first()::second()::third();\n",
        "<?php\nQuery::first()::second()::third();\n",
    ],
    'static first with nullsafe continuation' => [
        "<?php\nQuery::first('first argument value')?->second('second argument value')->third('third argument value');\n",
        "<?php\nQuery::first('first argument value')\n    ?->second('second argument value')\n    ->third('third argument value');\n",
    ],
    'two long calls break the last call' => [
        "<?php\n\$query->first('first argument value that is long')->second('second argument value that is long too');\n",
        "<?php\n\$query->first('first argument value that is long')->second(\n    'second argument value that is long too',\n);\n",
    ],
]);

it('uses configured indentation and line endings', function (
    string $input,
    string $expected,
): void {
    $fixer = new LineLengthFixer();
    $fixer->configure(['line_length' => 80]);
    $fixer->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\r\n"));

    $tokens = Tokens::fromCode($input);
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe($expected);
})->with([
    'arrays' => [
        "<?php\r\nif (\$enabled) {\r\n\t\$values = ['first_option_with_a_long_name' => 1, 'second_option_with_a_long_name' => 2];\r\n}\r\n",
        "<?php\r\nif (\$enabled) {\r\n\t\$values = [\r\n\t\t'first_option_with_a_long_name' => 1,\r\n\t\t'second_option_with_a_long_name' => 2,\r\n\t];\r\n}\r\n",
    ],
    'chains' => [
        "<?php\r\nif (\$enabled) {\r\n\t\$query->first('first argument value')->second('second argument value')->third('third');\r\n}\r\n",
        "<?php\r\nif (\$enabled) {\r\n\t\$query\r\n\t\t->first('first argument value')\r\n\t\t->second('second argument value')\r\n\t\t->third('third');\r\n}\r\n",
    ],
    'constructors' => [
        "<?php\r\nclass Example {\r\n\tpublic function __construct(public int \$valueWithALongNameForTheConstructorProperty) {}\r\n}\r\n",
        "<?php\r\nclass Example {\r\n\tpublic function __construct(\r\n\t\tpublic int \$valueWithALongNameForTheConstructorProperty,\r\n\t) {}\r\n}\r\n",
    ],
    'destructuring' => [
        "<?php\r\nif (\$enabled) {\r\n\t[\$customerIdentifier, \$customerDisplayName, \$customerBillingAddress] = \$customerDetails;\r\n}\r\n",
        "<?php\r\nif (\$enabled) {\r\n\t[\r\n\t\t\$customerIdentifier,\r\n\t\t\$customerDisplayName,\r\n\t\t\$customerBillingAddress,\r\n\t] = \$customerDetails;\r\n}\r\n",
    ],
]);

it('rejects invalid line lengths', function (mixed $lineLength): void {
    expect(fn () => new LineLengthFixer()->configure(['line_length' => $lineLength]))
        ->toThrow(InvalidFixerConfigurationException::class);
})->with([79, '100']);

it('measures nested items at their final indentation', function (
    int $lineLength,
    string $item,
): void {
    $input = "<?php\n\$config = ['outer' => ['value' => 'sample_value_long_enough_for_the_line_length_boundary'], 'last' => 1];\n";
    $expected = "<?php\n\$config = [\n".$item."\n    'last' => 1,\n];\n";

    expect(formatLines($input, $lineLength))->toBe($expected)
        ->and(formatLines($expected, $lineLength))->toBe($expected);
})->with([
    'at the limit' => [
        84,
        "    'outer' => ['value' => 'sample_value_long_enough_for_the_line_length_boundary'],",
    ],
    'over the limit' => [
        83,
        "    'outer' => [\n        'value' => 'sample_value_long_enough_for_the_line_length_boundary',\n    ],",
    ],
]);

it('wraps promotions without explicit read visibility', function (string $modifier): void {
    $input = "<?php\nclass Example {\n    public function __construct("
        .$modifier.' string $firstPropertyWithALongName, '
        .$modifier.' string $secondPropertyWithALongName'
        .") {}\n}\n";
    $expected = "<?php\nclass Example {\n    public function __construct(\n        "
        .$modifier.' string $firstPropertyWithALongName,'
        ."\n        ".$modifier.' string $secondPropertyWithALongName,'
        ."\n    ) {}\n}\n";

    expect(formatLines($input))->toBe($expected)->and(formatLines($expected))->toBe($expected);
})->with(function (): iterable {
    if (PHP_VERSION_ID >= 80_500) {
        yield 'final' => ['final'];
    }

    yield from ['private(set)', 'protected(set)', 'public(set)', 'readonly'];
});

it('ignores the width of trailing line comments', function (string $statement): void {
    $source = "<?php\n".$statement.' // '.str_repeat('comment ', 20)."\n";

    expect(formatLines($source))->toBe($source);
})->with([
    'arrow list' => '$callbacks = [fn ($item) => $item->name];',
    'commented call' => 'sendReport($path, /* required */ $filename);',
    'destructuring' => '[$name, $address] = $customer;',
]);

it('breaks a line only past the configured width', function (): void {
    $source = "<?php\n\n[\$customerIdentifier, \$customerDisplayName, \$customerBillingAddress, \$customerPreferredLanguage] = \$customerDetails;\n";
    $width = mb_strlen(explode("\n", $source)[2]);

    expect(formatLines($source, $width))->toBe($source)
        ->and(formatLines($source, $width - 1))->toContain("[\n    \$customerIdentifier,\n");
});

it('puts the body brace after multiline parameters', function (): void {
    $source = "<?php\nclass Example\n{\n    public function handle(Organization \$organization, CreateBillingProfileInput \$input): BillingProfile\n    {\n    }\n}\n";

    expect(formatLines($source))->toBe(
        "<?php\nclass Example\n{\n    public function handle(\n        Organization \$organization,\n        CreateBillingProfileInput \$input,\n    ): BillingProfile {\n    }\n}\n",
    );
});

it('indents closure bodies and heredocs inside wrapped arguments', function (): void {
    $source = "<?php\nrun('allows reusing names from soft deleted billing profiles at the database level', function (): void {\n    \$sql = <<<'SQL'\n        SELECT 1\n        SQL;\n});\n";
    $output = formatLines($source);

    PhpToken::tokenize($output, TOKEN_PARSE);

    expect($output)->toBe(
        "<?php\nrun(\n    'allows reusing names from soft deleted billing profiles at the database level',\n    function (): void {\n        \$sql = <<<'SQL'\n            SELECT 1\n            SQL;\n    },\n);\n",
    );
});

it('breaks a last associative array argument instead of the arguments', function (
    string $input,
    string $expected,
): void {
    expect(formatLines($input))->toBe($expected)->and(formatLines($expected))->toBe($expected);
})->with([
    'last argument' => [
        "<?php\nassertDatabaseHas(BillingProfile::class, ['id' => \$replacement->id, 'name' => 'Pro Monthly', 'active' => true]);\n",
        "<?php\nassertDatabaseHas(BillingProfile::class, [\n    'id' => \$replacement->id,\n    'name' => 'Pro Monthly',\n    'active' => true,\n]);\n",
    ],
    'only item' => [
        "<?php\n\$settings = [['alpha' => 'sample value', 'beta' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx']];\n",
        "<?php\n\$settings = [[\n    'alpha' => 'sample value',\n    'beta' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',\n]];\n",
    ],
    'positional arrays stay compact' => [
        "<?php\nsendReportToConfiguredDestination(\$destinationDirectoryWithAVeryLongName, ['draft', 'published', 'archived']);\n",
        "<?php\nsendReportToConfiguredDestination(\n    \$destinationDirectoryWithAVeryLongName,\n    ['draft', 'published', 'archived'],\n);\n",
    ],
]);

it('provides the custom fixer contract', function (): void {
    $fixer = new LineLengthFixer();

    expect($fixer->getName())->toBe('HosmelQ/line_length')
        ->and($fixer->getDefinition()->getCodeSamples())->not->toBeEmpty()
        ->and($fixer->isRisky())->toBeFalse()
        ->and($fixer->isCandidate(Tokens::fromCode('<?php $value = 1;')))->toBeFalse();
});

it('converges with the complete shared ECS set', function (
    string $expression,
    string $formatted,
): void {
    $directory = __DIR__.'/../.cache/line-length/'.getmypid();

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    $configuration = <<<'PHP_WRAP'
        <?php
        use HosmelQ\PhpCodingStandard\CodingStandard;
        use Symplify\EasyCodingStandard\Config\ECSConfig;

        return ECSConfig::configure()->withSets([CodingStandard::ECS_PHP]);
        PHP_WRAP;
    $config = $directory.'/ecs.php';
    $file = $directory.'/example.php';
    file_put_contents($config, $configuration);
    file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n".$expression."\n");

    for ($pass = 0; $pass < 2; ++$pass) {
        $result = runConfiguredTool(
            'ecs',
            ['check', $file, '--config', $config, '--fix', '--no-progress-bar'],
        );

        expect($result->isSuccessful())->toBeTrue(
            $result->getOutput().$result->getErrorOutput(),
        )
            ->and(file_get_contents($file))->toBe(
                "<?php\n\ndeclare(strict_types=1);\n\n".$formatted."\n",
            );
    }
})->with([
    'later static access' => [
        'Query::first()?->second() :: third()->fourth();',
        'Query::first()?->second()::third()->fourth();',
    ],
    'long initial static chain' => [
        "Query :: where('status', 'active')->where('visibility', 'public')"
            ."->orderBy('created_at_date')->get(['id']);",
        "Query::where('status', 'active')\n"
            ."    ->where('visibility', 'public')\n"
            ."    ->orderBy('created_at_date')\n"
            ."    ->get(['id']);",
    ],
    'mixed object chain' => [
        '$query -> first()?->second()->third();',
        '$query->first()?->second()->third();',
    ],
    'multiple statements' => [
        '$d = $first->a()->b()->c(); $e = $second->a()->b()->c();',
        "\$d = \$first->a()->b()->c();\n\$e = \$second->a()->b()->c();",
    ],
    'positional list of chains' => [
        "\$g = [\$first->where('status', 'active')->orderBy('name')->get(), \$second->where('type', 1)->orderBy('id')->get()];",
        "\$g = [\n    \$first->where('status', 'active')->orderBy('name')->get(),\n    \$second->where('type', 1)->orderBy('id')->get(),\n];",
    ],
]);
