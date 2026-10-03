<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\FluentChainFixer;
use PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException;
use PhpCsFixer\Fixer\Operator\NoSpaceAroundDoubleColonFixer;
use PhpCsFixer\Fixer\Operator\ObjectOperatorWithoutWhitespaceFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use PhpCsFixer\Fixer\Whitespace\StatementIndentationFixer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use Symfony\Component\Process\Process;

it('formats fluent chains and preserves all non-whitespace tokens', function (
    string $input,
    string $expected,
): void {
    $fixer = new FluentChainFixer();
    $file = new SplFileInfo('example.php');
    $tokens = Tokens::fromCode($input);
    $original = array_values(array_map(
        fn (Token $token): array => [$token->getId(), $token->getContent()],
        array_filter(
            iterator_to_array($tokens),
            fn (Token $token): bool => ! $token->isWhitespace(),
        ),
    ));

    $fixer->fix($file, $tokens);

    expect($tokens->generateCode())->toBe($expected)
        ->and(array_values(array_map(
            fn (Token $token): array => [$token->getId(), $token->getContent()],
            array_filter(
                iterator_to_array($tokens),
                fn (Token $token): bool => ! $token->isWhitespace(),
            ),
        )))->toBe($original);

    $tokens = Tokens::fromCode($tokens->generateCode());
    $fixer->fix($file, $tokens);

    expect($tokens->generateCode())->toBe($expected);
})->with([
    'dynamic methods' => [
        "<?php\n\$query->{\$method}()->second()->third();\n",
        "<?php\n\$query\n    ->{\$method}()\n    ->second()\n    ->third();\n",
    ],
    'existing multiline chain' => [
        "<?php\n\$query\n    ->first()->second()->third();\n",
        "<?php\n\$query\n    ->first()->second()->third();\n",
    ],
    'for header semicolons' => [
        "<?php\nfor (\$i = 0; \$query->first()->second()->third(); ++\$i) {}\n",
        "<?php\nfor (\$i = 0; \$query\n    ->first()\n    ->second()\n    ->third(); ++\$i) {}\n",
    ],
    'four calls' => [
        "<?php\n\$query->first()->second()->third()->fourth();\n",
        "<?php\n\$query\n    ->first()\n    ->second()\n    ->third()\n    ->fourth();\n",
    ],
    'global factory is not a method' => [
        "<?php\nfactory()->first()->second();\n",
        "<?php\nfactory()->first()->second();\n",
    ],
    'later static access is untouched' => [
        "<?php\nQuery::first()?->second()::third()->fourth();\n",
        "<?php\nQuery::first()?->second()::third()->fourth();\n",
    ],
    'mixed object operators' => [
        "<?php\n\$query->first()?->second()->third();\n",
        "<?php\n\$query\n    ->first()\n    ?->second()\n    ->third();\n",
    ],
    'multiline argument' => [
        "<?php\n\$query->first(\n    true,\n)->second()->third();\n",
        "<?php\n\$query->first(\n    true,\n)->second()->third();\n",
    ],
    'multiline array argument' => [
        "<?php\n\$query->first([\n    'value' => 1,\n])->second()->third();\n",
        "<?php\n\$query\n    ->first([\n        'value' => 1,\n    ])\n    ->second()\n    ->third();\n",
    ],
    'multiple statements retain comments' => [
        "<?php\nfirst(); /* keep */ \$query->first()->second()->third();\n",
        "<?php\nfirst();\n/* keep */ \$query\n    ->first()\n    ->second()\n    ->third();\n",
    ],
    'nullsafe calls' => [
        "<?php\n\$query?->first()?->second()?->third();\n",
        "<?php\n\$query\n    ?->first()\n    ?->second()\n    ?->third();\n",
    ],
    'one call' => ["<?php\n\$query->first();\n", "<?php\n\$query->first();\n"],
    'property accesses are not calls' => [
        "<?php\n\$query->first()->property->second();\n",
        "<?php\n\$query->first()->property->second();\n",
    ],
    'property root' => [
        "<?php\n\$query->builder->first()->second()->third();\n",
        "<?php\n\$query->builder\n    ->first()\n    ->second()\n    ->third();\n",
    ],
    'root call with an inline closure' => [
        "<?php\nfactory(function () { first(); return true; })->first()->second()->third();\n",
        "<?php\nfactory(function () { first(); return true; })\n    ->first()\n    ->second()\n    ->third();\n",
    ],
    'static calls only are untouched' => [
        "<?php\nQuery::first()::second()::third();\n",
        "<?php\nQuery::first()::second()::third();\n",
    ],
    'static first call' => [
        "<?php\nQuery::first()->second()->third();\n",
        "<?php\nQuery::first()\n    ->second()\n    ->third();\n",
    ],
    'static first with nullsafe continuation' => [
        "<?php\nQuery::first()?->second()->third();\n",
        "<?php\nQuery::first()\n    ?->second()\n    ->third();\n",
    ],
    'static short chain' => [
        "<?php\nQuery::first()->second();\n",
        "<?php\nQuery::first()->second();\n",
    ],
    'three calls' => [
        "<?php\n\$query->first()->second()->third();\n",
        "<?php\n\$query\n    ->first()\n    ->second()\n    ->third();\n",
    ],
    'two calls' => ["<?php\n\$query->first()->second();\n", "<?php\n\$query->first()->second();\n"],
]);

it('formats realistic fixtures without changing their tokens and is idempotent', function (
    string $fixture,
): void {
    $input = file_get_contents(__DIR__.'/Fixtures/FluentChain/'.$fixture.'.input.php.inc');
    $expected = file_get_contents(__DIR__.'/Fixtures/FluentChain/'.$fixture.'.expected.php.inc');
    $tokens = Tokens::fromCode($input);
    $fixer = new FluentChainFixer();
    $file = new SplFileInfo('example.php');
    $significant = static fn (string $code): array => array_values(array_map(
        static fn (array|string $token): array|string => is_array($token) ? [
            $token[0],
            $token[1],
        ] : $token,
        array_filter(
            token_get_all($code),
            static fn (array|string $token): bool => ! is_array(
                $token,
            ) || $token[0] !== T_WHITESPACE,
        ),
    ));

    $fixer->fix($file, $tokens);
    $output = $tokens->generateCode();

    expect($output)->toBe($expected)
        ->and($significant($output))->toBe($significant($input));

    $tokens = Tokens::fromCode($output);
    $fixer->fix($file, $tokens);

    expect($tokens->generateCode())->toBe($expected);
})->with(['comments', 'migrations', 'nested', 'strings']);

it('uses configured indentation and line endings', function (): void {
    $fixer = new FluentChainFixer();
    $fixer->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\r\n"));

    $tokens = Tokens::fromCode("<?php\r\n\t\$query->first()->second()->third();\r\n");

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe(
        "<?php\r\n\t\$query\r\n\t\t->first()\r\n\t\t->second()\r\n\t\t->third();\r\n",
    );
});

it('provides the custom fixer contract', function (): void {
    $fixer = new FluentChainFixer();

    expect($fixer->getName())->toBe('HosmelQ/fluent_chain')
        ->and($fixer->getDefinition()->getCodeSamples())->not->toBeEmpty()
        ->and($fixer->getPriority())->toBeGreaterThan(0)
        ->and($fixer->isRisky())->toBeFalse()
        ->and($fixer->isCandidate(Tokens::fromCode('<?php $value = 1;')))->toBeFalse();
});

it('converges with the native chain and operator fixers', function (
    string $input,
    string $expected,
): void {
    $fixers = [
        new FluentChainFixer(),
        new NoSpaceAroundDoubleColonFixer(),
        new ObjectOperatorWithoutWhitespaceFixer(),
        new MethodChainingIndentationFixer(),
        new StatementIndentationFixer(),
    ];
    usort($fixers, fn ($first, $second): int => $second->getPriority() <=> $first->getPriority());
    $file = new SplFileInfo('example.php');
    $tokens = Tokens::fromCode($input);

    for ($pass = 0; $pass < 3; ++$pass) {
        foreach ($fixers as $fixer) {
            $fixer->fix($file, $tokens);
            $tokens->clearEmptyTokens();
        }

        expect($tokens->generateCode())->toBe($expected);
    }
})->with([
    'initial static' => [
        "<?php\nQuery :: first()->second()?->third();\n",
        "<?php\nQuery::first()\n    ->second()\n    ?->third();\n",
    ],
    'later static' => [
        "<?php\nQuery::first()?->second() :: third()->fourth();\n",
        "<?php\nQuery::first()?->second()::third()->fourth();\n",
    ],
    'object' => [
        "<?php\n\$query -> first()->second()?->third();\n",
        "<?php\n\$query\n    ->first()\n    ->second()\n    ?->third();\n",
    ],
]);

it('converges with the complete shared ECS set', function (
    string $expression,
    string $formatted,
): void {
    $directory = __DIR__.'/../.cache/fluent-chain/'.getmypid();

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $configuration = <<<'PHP_WRAP'
    <?php
    use HosmelQ\PhpCodingStandard\CodingStandard;
    use Symplify\EasyCodingStandard\Config\ECSConfig;

    return ECSConfig::configure()
        ->withSets([CodingStandard::ECS_PHP]);
    PHP_WRAP;
    $input = "<?php\n\ndeclare(strict_types=1);\n\n".$expression."\n";
    $expected = "<?php\n\ndeclare(strict_types=1);\n\n".$formatted."\n";
    $config = $directory.'/ecs.php';
    $file = $directory.'/example.php';
    file_put_contents($config, $configuration);
    file_put_contents($file, $input);

    for ($pass = 0; $pass < 3; ++$pass) {
        $process = new Process([
            PHP_BINARY,
            __DIR__.'/../vendor/bin/ecs',
            'check',
            $file,
            '--config',
            $config,
            '--fix',
            '--no-progress-bar',
        ], dirname(__DIR__));
        $process->setTimeout(120);
        $process->run();

        expect($process->isSuccessful())->toBeTrue(
            $process->getOutput().$process->getErrorOutput(),
        )
            ->and(file_get_contents($file))->toBe($expected);
    }
})->with([
    'later static access' => [
        'Query::first()?->second() :: third()->fourth();',
        'Query::first()?->second()::third()->fourth();',
    ],
    'long initial static chain' => [
        "Query::where('status', 'active')->where('visibility', 'public')"
            ."->orderBy('created_at')->get();",
        "Query::where('status', 'active')\n"
            ."    ->where('visibility', 'public')\n"
            ."    ->orderBy('created_at')\n"
            .'    ->get();',
    ],
    'mixed object chain' => [
        '$query->first()?->second()->third();',
        "\$query\n    ->first()\n    ?->second()\n    ->third();",
    ],
    'multiple statements' => [
        '$d = $first->a()->b()->c(); $e = $second->a()->b()->c();',
        "\$d = \$first\n    ->a()\n    ->b()\n    ->c();\n\$e = \$second\n    ->a()\n    ->b()\n    ->c();",
    ],
    'positional list keeps native layout' => [
        '$g = [$a->b()->c()->d(), $x->y()->z()->w()];',
        "\$g = [\$a\n    ->b()\n    ->c()\n    ->d(), \$x\n    ->y()\n    ->z()\n    ->w()];",
    ],
]);

it('preserves migration chains through the configured code line boundary', function (
    string $method,
    int $length,
    string $formatted,
): void {
    $fixer = new FluentChainFixer();
    $fixer->configure(['line_length' => $length]);

    $prefix = "<?php\nSchema::".$method."('users', function (Blueprint \$table): void {\n";
    $suffix = " // This comment must not count toward the code line length.\n});\n";
    $tokens = Tokens::fromCode($prefix."    \$table->string('name')->nullable()->index();".$suffix);

    for ($pass = 0; $pass < 2; ++$pass) {
        $fixer->fix(new SplFileInfo('example.php'), $tokens);

        expect($tokens->generateCode())->toBe($prefix.$formatted.$suffix);
    }
})->with(['create', 'table'])->with([
    'at the limit' => [48, "    \$table->string('name')->nullable()->index();"],
    'over the limit' => [
        47,
        "    \$table\n        ->string('name')\n        ->nullable()\n        ->index();",
    ],
]);

it('rejects invalid line lengths', function (mixed $length): void {
    expect(fn () => new FluentChainFixer()->configure(['line_length' => $length]))
        ->toThrow(InvalidFixerConfigurationException::class);
})->with([0, -1, '100']);
