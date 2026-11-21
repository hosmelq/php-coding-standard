<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\ConvergeFixer;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use Symplify\EasyCodingStandard\Config\ECSConfig;

function convergeFixture(string $name, string $source, string $configuration): array
{
    $directory = __DIR__.'/../.cache/converge/'.$name;

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    $sourcePath = $directory.'/example.php';
    $configurationPath = $directory.'/ecs.php';

    file_put_contents($sourcePath, $source);
    file_put_contents($configurationPath, $configuration);

    return [$sourcePath, $configurationPath];
}

it('converges multi-pass formatting interactions in a single ECS run', function (
    string $name,
    string $source,
): void {
    $configuration = <<<'PHP_WRAP'
        <?php

        declare(strict_types=1);

        use HosmelQ\PhpCodingStandard\CodingStandard;
        use Symplify\EasyCodingStandard\Config\ECSConfig;

        return ECSConfig::configure()->withSets([CodingStandard::ECS_PHP]);
        PHP_WRAP;

    [$file, $config] = convergeFixture($name, $source, $configuration);

    $first = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--fix',
        '--clear-cache',
        '--no-progress-bar',
    ]);
    $formatted = file_get_contents($file);

    expect($first->isSuccessful())->toBeTrue($first->getOutput().$first->getErrorOutput())
        ->and($formatted)->not->toBe($source);

    $second = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--clear-cache',
        '--no-progress-bar',
    ]);

    expect($second->isSuccessful())->toBeTrue($second->getOutput().$second->getErrorOutput())
        ->and(file_get_contents($file))->toBe($formatted);
})->with([
    'docblock after a long signature before braces' => [
        'signature-docblock',
        <<<'PHP'
            <?php

            declare(strict_types=1);

            function veryLongFunctionName(string $firstLongParameterName, string $secondLongParameterName): void /** note */ {
                return;
            }
            PHP,
    ],
    'heredoc moved by line wrapping with blank line inside' => [
        'heredoc-wrapped',
        <<<'PHP'
            <?php

            declare(strict_types=1);

            function example(): void
            {
                sendNotification('first_very_long_argument_value', 'second_very_long_argument_value', <<<'TEXT'
                first line
                  
                second line
                TEXT);
            }
            PHP,
    ],
    'array item starting on the closing line of a multiline closure' => [
        'closure-followed-by-array',
        <<<'PHP'
            <?php

            declare(strict_types=1);

            run('handles long call with closure and array', function (): void {
                doSomething();
            }, ['first_key' => 'first_long_value', 'second_key' => 'second_long_value', 'third_key' => 'third_long_value']);
            PHP,
    ],
]);

it('reports the converged diff in dry-run mode without modifying the file', function (): void {
    $source = <<<'PHP'
        <?php

        declare(strict_types=1);

        function veryLongFunctionName(string $firstLongParameterName, string $secondLongParameterName): void /** note */ {
            return;
        }
        PHP;
    $configuration = <<<'PHP_WRAP'
        <?php

        declare(strict_types=1);

        use HosmelQ\PhpCodingStandard\CodingStandard;
        use Symplify\EasyCodingStandard\Config\ECSConfig;

        return ECSConfig::configure()->withSets([CodingStandard::ECS_PHP]);
        PHP_WRAP;

    [$file, $config] = convergeFixture('dry-run', $source, $configuration);

    $check = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--clear-cache',
        '--no-progress-bar',
    ]);

    expect($check->isSuccessful())->toBeFalse()
        ->and(file_get_contents($file))->toBe($source)
        ->and($check->getOutput())->toContain('+): void { /** note */')
        ->and($check->getOutput())->not->toContain('+{}');
});

it('respects per-file skips during convergence passes', function (): void {
    $source = <<<'PHP'
        <?php

        declare(strict_types=1);

        function veryLongFunctionName(string $firstLongParameterName, string $secondLongParameterName): void /** note */ {
            return;
        }
        PHP;
    $configuration = <<<'PHP_WRAP'
        <?php

        declare(strict_types=1);

        use HosmelQ\PhpCodingStandard\CodingStandard;
        use PhpCsFixer\Fixer\Basic\BracesPositionFixer;
        use Symplify\EasyCodingStandard\Config\ECSConfig;

        return ECSConfig::configure()
            ->withSets([CodingStandard::ECS_PHP])
            ->withSkip([
                BracesPositionFixer::class => [__DIR__.'/example.php'],
            ]);
        PHP_WRAP;

    [$file, $config] = convergeFixture('per-file-skip', $source, $configuration);

    $first = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--fix',
        '--clear-cache',
        '--no-progress-bar',
    ]);
    $formatted = file_get_contents($file);

    expect($first->isSuccessful())->toBeTrue($first->getOutput().$first->getErrorOutput())
        ->and($formatted)->toContain('): void /** note */ {}');

    $second = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--clear-cache',
        '--no-progress-bar',
    ]);

    expect($second->isSuccessful())->toBeTrue($second->getOutput().$second->getErrorOutput());
});

it('does not execute unregistered fixer dependencies during convergence', function (): void {
    $directory = __DIR__.'/../.cache/converge/unregistered-dependency';

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    $fixerPath = $directory.'/DependencyHoldingFixer.php';

    file_put_contents(
        $fixerPath,
        <<<'PHP_WRAP'
            <?php

            declare(strict_types=1);

            use PhpCsFixer\AbstractFixer;
            use PhpCsFixer\Fixer\Alias\PowToExponentiationFixer;
            use PhpCsFixer\FixerDefinition\CodeSample;
            use PhpCsFixer\FixerDefinition\FixerDefinition;
            use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
            use PhpCsFixer\Tokenizer\Tokens;

            final class DependencyHoldingFixer extends AbstractFixer
            {
                public function __construct(private readonly PowToExponentiationFixer $unused)
                {
                    parent::__construct();
                }

                public function getDefinition(): FixerDefinitionInterface
                {
                    return new FixerDefinition('Hold an unregistered fixer dependency.', [
                        new CodeSample("<?php\n\$value = pow(2, 3);\n"),
                    ]);
                }

                public function getName(): string
                {
                    return 'Test/dependency_holding';
                }

                public function isCandidate(Tokens $tokens): bool
                {
                    return false;
                }

                protected function applyFix(SplFileInfo $file, Tokens $tokens): void {}
            }
            PHP_WRAP,
    );

    $source = "<?php\n\$value = pow(2, 3);\n";
    $configuration = <<<'PHP_WRAP'
        <?php

        declare(strict_types=1);

        require_once __DIR__.'/DependencyHoldingFixer.php';

        use HosmelQ\PhpCodingStandard\CodingStandard;
        use Symplify\EasyCodingStandard\Config\ECSConfig;

        return ECSConfig::configure()
            ->withSets([CodingStandard::ECS_PHP])
            ->withRules([DependencyHoldingFixer::class]);
        PHP_WRAP;

    [$file, $config] = convergeFixture('unregistered-dependency', $source, $configuration);

    $result = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--fix',
        '--clear-cache',
        '--no-progress-bar',
    ]);

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and(file_get_contents($file))->toContain('pow(2, 3)')
        ->and(file_get_contents($file))->not->toContain('2 ** 3');
});

it('invalidates the cache when a fixer throws during convergence', function (): void {
    $directory = __DIR__.'/../.cache/converge/exception-cache';

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    file_put_contents(
        $directory.'/ThrowingPassFixers.php',
        <<<'PHP_WRAP'
            <?php

            declare(strict_types=1);

            use PhpCsFixer\AbstractFixer;
            use PhpCsFixer\FixerDefinition\CodeSample;
            use PhpCsFixer\FixerDefinition\FixerDefinition;
            use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
            use PhpCsFixer\Tokenizer\Token;
            use PhpCsFixer\Tokenizer\Tokens;

            final class RejectBetaFixer extends AbstractFixer
            {
                public function getDefinition(): FixerDefinitionInterface
                {
                    return new FixerDefinition('Fail on $b.', [new CodeSample("<?php\n\$a = 1;\n")]);
                }

                public function getName(): string
                {
                    return 'Test/reject_beta';
                }

                public function getPriority(): int
                {
                    return 10;
                }

                public function isCandidate(Tokens $tokens): bool
                {
                    return $tokens->isTokenKindFound(T_VARIABLE);
                }

                protected function applyFix(SplFileInfo $file, Tokens $tokens): void
                {
                    foreach ($tokens as $token) {
                        if ($token->isGivenKind(T_VARIABLE) && $token->getContent() === '$b') {
                            throw new RuntimeException('Rejected $b');
                        }
                    }
                }
            }

            final class AlphaToBetaFixer extends AbstractFixer
            {
                public function getDefinition(): FixerDefinitionInterface
                {
                    return new FixerDefinition('Rename $a to $b.', [new CodeSample("<?php\n\$a = 1;\n")]);
                }

                public function getName(): string
                {
                    return 'Test/alpha_to_beta';
                }

                public function getPriority(): int
                {
                    return 5;
                }

                public function isCandidate(Tokens $tokens): bool
                {
                    return $tokens->isTokenKindFound(T_VARIABLE);
                }

                protected function applyFix(SplFileInfo $file, Tokens $tokens): void
                {
                    foreach ($tokens as $index => $token) {
                        if ($token->isGivenKind(T_VARIABLE) && $token->getContent() === '$a') {
                            $tokens[$index] = new Token([T_VARIABLE, '$b']);
                        }
                    }
                }
            }
            PHP_WRAP,
    );

    $source = "<?php\n\n\$a = 1;\n";
    $configuration = <<<'PHP_WRAP'
        <?php

        declare(strict_types=1);

        require_once __DIR__.'/ThrowingPassFixers.php';

        use HosmelQ\PhpCodingStandard\Fixer\ConvergeFixer;
        use Symplify\EasyCodingStandard\Config\ECSConfig;

        return ECSConfig::configure()
            ->withCache(__DIR__.'/cache')
            ->withRules([RejectBetaFixer::class, AlphaToBetaFixer::class, ConvergeFixer::class]);
        PHP_WRAP;

    [$file, $config] = convergeFixture('exception-cache', $source, $configuration);

    $first = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--fix',
        '--clear-cache',
        '--no-progress-bar',
    ]);
    $second = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--fix',
        '--no-progress-bar',
    ]);

    expect($first->isSuccessful())->toBeFalse()
        ->and($first->getOutput().$first->getErrorOutput())->toContain('RejectBetaFixer')
        ->and($second->isSuccessful())->toBeFalse()
        ->and($second->getOutput().$second->getErrorOutput())->toContain('RejectBetaFixer');
});

it('preserves re-registered rule order during convergence', function (): void {
    $directory = __DIR__.'/../.cache/converge/duplicate-rule-order';

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    file_put_contents(
        $directory.'/DoubleQuoteFixer.php',
        <<<'PHP_WRAP'
            <?php

            declare(strict_types=1);

            use PhpCsFixer\AbstractFixer;
            use PhpCsFixer\FixerDefinition\CodeSample;
            use PhpCsFixer\FixerDefinition\FixerDefinition;
            use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
            use PhpCsFixer\Tokenizer\Token;
            use PhpCsFixer\Tokenizer\Tokens;

            final class DoubleQuoteFixer extends AbstractFixer
            {
                public function getDefinition(): FixerDefinitionInterface
                {
                    return new FixerDefinition('Convert single quotes to double quotes.', [
                        new CodeSample("<?php\necho 'abc';\n"),
                    ]);
                }

                public function getName(): string
                {
                    return 'Test/double_quote';
                }

                public function getPriority(): int
                {
                    return 10;
                }

                public function isCandidate(Tokens $tokens): bool
                {
                    return $tokens->isTokenKindFound(T_CONSTANT_ENCAPSED_STRING);
                }

                protected function applyFix(SplFileInfo $file, Tokens $tokens): void
                {
                    foreach ($tokens as $index => $token) {
                        if ($token->isGivenKind(T_CONSTANT_ENCAPSED_STRING) && $token->getContent() === "'abc'") {
                            $tokens[$index] = new Token([T_CONSTANT_ENCAPSED_STRING, '"abc"']);
                        }
                    }
                }
            }
            PHP_WRAP,
    );

    $source = '<?php'."\n\n".'echo "abc";';
    $configuration = <<<'PHP_WRAP'
        <?php

        declare(strict_types=1);

        require_once __DIR__.'/DoubleQuoteFixer.php';

        use HosmelQ\PhpCodingStandard\CodingStandard;
        use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
        use Symplify\EasyCodingStandard\Config\ECSConfig;

        return ECSConfig::configure()
            ->withSets([CodingStandard::ECS_PHP])
            ->withRules([DoubleQuoteFixer::class, SingleQuoteFixer::class]);
        PHP_WRAP;

    [$file, $config] = convergeFixture('duplicate-rule-order', $source, $configuration);

    $first = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--fix',
        '--clear-cache',
        '--no-progress-bar',
    ]);
    $formatted = file_get_contents($file);

    expect($first->isSuccessful())->toBeTrue($first->getOutput().$first->getErrorOutput())
        ->and($formatted)->toContain("echo 'abc';");

    $second = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--clear-cache',
        '--no-progress-bar',
    ]);

    expect($second->isSuccessful())->toBeTrue($second->getOutput().$second->getErrorOutput())
        ->and(file_get_contents($file))->toBe($formatted);
});

it('skips extra passes when the first pass made no changes', function (): void {
    $directory = __DIR__.'/../.cache/converge/unchanged';

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    $file = $directory.'/example.php';
    $source = "<?php\n\n\$value = 1;\n";

    file_put_contents($file, $source);

    $spy = new class () extends AbstractFixer {
        public static int $calls = 0;

        public function getDefinition(): FixerDefinitionInterface
        {
            return new FixerDefinition(
                'Count fixer calls.',
                [new CodeSample("<?php\n\$value = 1;\n")],
            );
        }

        public function getName(): string
        {
            return 'Test/spy';
        }

        /**
         * @param Tokens<Token> $tokens
         */
        public function isCandidate(Tokens $tokens): bool
        {
            return true;
        }

        /**
         * @param Tokens<Token> $tokens
         */
        protected function applyFix(SplFileInfo $file, Tokens $tokens): void
        {
            ++self::$calls;
        }
    };
    $spy::$calls = 0;
    $ecsConfig = new ECSConfig();

    $ecsConfig->service(ECSConfig::class, static fn (): ECSConfig => $ecsConfig);
    $ecsConfig->rules([$spy::class, ConvergeFixer::class]);
    $ecsConfig->boot();

    $converge = $ecsConfig->make(ConvergeFixer::class);
    $tokens = Tokens::fromCode($source);

    $converge->fix(new SplFileInfo($file), $tokens);

    expect($spy::$calls)->toBe(0)->and($tokens->isChanged())->toBeFalse();
});

it('preserves configured rule overrides during convergence passes', function (): void {
    $source = <<<'PHP'
        <?php

        declare(strict_types=1);

        function veryLongFunctionName(string $firstLongParameterName, string $secondLongParameterName): void /** note */ {
            return;
        }
        PHP;
    $configuration = <<<'PHP_WRAP'
        <?php

        declare(strict_types=1);

        use HosmelQ\PhpCodingStandard\CodingStandard;
        use HosmelQ\PhpCodingStandard\Fixer\LineLengthFixer;
        use Symplify\EasyCodingStandard\Config\ECSConfig;

        return ECSConfig::configure()
            ->withSets([CodingStandard::ECS_PHP])
            ->withConfiguredRule(LineLengthFixer::class, ['line_length' => 140]);
        PHP_WRAP;

    [$file, $config] = convergeFixture('configured-override', $source, $configuration);

    $first = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--fix',
        '--clear-cache',
        '--no-progress-bar',
    ]);
    $formatted = file_get_contents($file);

    expect($first->isSuccessful())->toBeTrue($first->getOutput().$first->getErrorOutput())
        ->and($formatted)->toContain(
            'function veryLongFunctionName(string $firstLongParameterName, string $secondLongParameterName): void',
        )
        ->and($formatted)->toContain("): void /** note */\n{}");

    $second = runConfiguredTool('ecs', [
        'check',
        $file,
        '--config',
        $config,
        '--clear-cache',
        '--no-progress-bar',
    ]);

    expect($second->isSuccessful())->toBeTrue($second->getOutput().$second->getErrorOutput());
});

it('stops safely when two rules oscillate instead of looping forever', function (): void {
    $oscillatorA = new class () extends AbstractFixer {
        public function getDefinition(): FixerDefinitionInterface
        {
            return new FixerDefinition('Swap a to b.', [new CodeSample("<?php\n\$a = 1;\n")]);
        }

        public function getName(): string
        {
            return 'Test/oscillator_a';
        }

        public function getPriority(): int
        {
            return 10;
        }

        /**
         * @param Tokens<Token> $tokens
         */
        public function isCandidate(Tokens $tokens): bool
        {
            return $tokens->isTokenKindFound(T_VARIABLE);
        }

        /**
         * @param Tokens<Token> $tokens
         */
        protected function applyFix(SplFileInfo $file, Tokens $tokens): void
        {
            foreach ($tokens as $index => $token) {
                if ($token->isGivenKind(T_VARIABLE) && $token->getContent() === '$a') {
                    $tokens[$index] = new Token([T_VARIABLE, '$b']);
                }
            }
        }
    };
    $oscillatorB = new class () extends AbstractFixer {
        public function getDefinition(): FixerDefinitionInterface
        {
            return new FixerDefinition('Swap b to c and c back to a.', [
                new CodeSample("<?php\n\$b = 1;\n"),
            ]);
        }

        public function getName(): string
        {
            return 'Test/oscillator_b';
        }

        public function getPriority(): int
        {
            return 5;
        }

        /**
         * @param Tokens<Token> $tokens
         */
        public function isCandidate(Tokens $tokens): bool
        {
            return $tokens->isTokenKindFound(T_VARIABLE);
        }

        /**
         * @param Tokens<Token> $tokens
         */
        protected function applyFix(SplFileInfo $file, Tokens $tokens): void
        {
            foreach ($tokens as $index => $token) {
                if ($token->isGivenKind(T_VARIABLE) && $token->getContent() === '$b') {
                    $tokens[$index] = new Token([T_VARIABLE, '$c']);
                } elseif ($token->isGivenKind(T_VARIABLE) && $token->getContent() === '$c') {
                    $tokens[$index] = new Token([T_VARIABLE, '$a']);
                }
            }
        }
    };
    $directory = __DIR__.'/../.cache/converge/oscillate';

    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    $file = $directory.'/example.php';

    file_put_contents($file, "<?php\n\n\$a = 1;\n");

    $ecsConfig = new ECSConfig();

    $ecsConfig->service(ECSConfig::class, static fn (): ECSConfig => $ecsConfig);
    $ecsConfig->rules([$oscillatorA::class, $oscillatorB::class, ConvergeFixer::class]);
    $ecsConfig->boot();

    $tokens = Tokens::fromCode("<?php\n\n\$c = 1;\n");
    $converge = $ecsConfig->make(ConvergeFixer::class);

    $converge->fix(new SplFileInfo($file), $tokens);

    expect($tokens->generateCode())->toBe("<?php\n\n\$c = 1;\n")
        ->and($tokens->isChanged())->toBeFalse();
});
