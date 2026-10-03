<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use HosmelQ\PhpCodingStandard\Fixer\CommentAwareLineLengthFixer;
use HosmelQ\PhpCodingStandard\Fixer\FluentChainFixer;
use HosmelQ\PhpCodingStandard\Fixer\LongAssociativeArrayFixer;
use HosmelQ\PhpCodingStandard\Fixer\LongPromotedConstructorFixer;
use HosmelQ\PhpCodingStandard\LineLength;
use PhpCsFixer\Tokenizer\Tokens;
use Rector\Config\RectorConfig;
use Symfony\Component\Process\Process;
use Symplify\EasyCodingStandard\Config\ECSConfig;

it('shares one native ECS width while preserving per-fixer overrides', function (
    string $fixerClass,
    string $source,
): void {
    $format = static function (int $width, null|int $override = null) use (
        $fixerClass,
        $source,
    ): string {
        $configuration = new ECSConfig();
        $configuration->sets([CodingStandard::ECS_PHP]);
        $configuration->service(
            LineLength::class,
            static fn (): LineLength => new LineLength($width),
        );

        if ($override !== null) {
            $configuration->ruleWithConfiguration($fixerClass, ['line_length' => $override]);
        }

        $fixer = $configuration->make($fixerClass);
        $tokens = Tokens::fromCode($source);
        $fixer->fix(new SplFileInfo('example.php'), $tokens);

        return $tokens->generateCode();
    };

    expect($format(100))->not->toBe($source);
    expect($format(200))->toBe($source);
    expect($format(200, 100))->toBe($format(100));
})->with([
    'arrays' => [
        LongAssociativeArrayFixer::class,
        "<?php\n\$values = ['first_long_option' => 'first_long_value', 'second_long_option' => 'second_long_value', 'third' => true];\n",
    ],
    'calls' => [
        CommentAwareLineLengthFixer::class,
        "<?php\nrenderExample('first_long_argument_value', 'second_long_argument_value', 'third_long_argument_value', 'last_value');\n",
    ],
    'constructors' => [
        LongPromotedConstructorFixer::class,
        "<?php\nclass Example { public function __construct(public string \$firstLongValue, public string \$secondLongValue, public string \$thirdLongValue) {} }\n",
    ],
    'migration chains' => [
        FluentChainFixer::class,
        "<?php\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\nSchema::table('records', function (Blueprint \$table): void {\n    \$table->string('an_extremely_long_column_identifier_for_the_line_length_boundary')->nullable()->index();\n});\n",
    ],
]);

it('rejects invalid shared widths', function (int $width): void {
    expect(fn (): LineLength => new LineLength($width))->toThrow(InvalidArgumentException::class);
})->with([0, -1]);

it(
    'keeps native dates and byte string operations in the PHP preset',
    function (string $tool): void {
        $directory = __DIR__.'/../.cache/preset-configuration/'.$tool;

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $file = $directory.'/example.php';
        file_put_contents($file, <<<'CODE'
<?php

declare(strict_types=1);

function dateLabel(string $value): string
{
    $date = new DateTime();
    $date->modify('+1 day');

    return date('Y-m-d', $date->getTimestamp()).strlen($value).strtoupper($value).trim($value);
}
CODE);
        $configuration = $directory.'/configuration.php';
        $class = $tool === 'ecs' ? ECSConfig::class : RectorConfig::class;
        $constant = $tool === 'ecs' ? 'ECS_PHP' : 'RECTOR_PHP';
        file_put_contents(
            $configuration,
            '<?php return '.$class.'::configure()->withSets(['.CodingStandard::class.'::'.$constant.']);',
        );
        $arguments = $tool === 'ecs' ? ['check', '--fix=true'] : ['process'];
        $process = new Process([
            PHP_BINARY,
            __DIR__.'/../vendor/bin/'.$tool,
            ...$arguments,
            $file,
            '--config',
            $configuration,
            '--no-progress-bar',
        ], dirname(__DIR__));
        $process->mustRun();

        expect($process->getOutput().$process->getErrorOutput())->not->toMatch(
            '/warning|deprecated/i',
        );
        expect(file_get_contents($file))
            ->toContain(
                'new DateTime(',
                "->modify('+1 day')",
                "date('Y-m-d'",
                'strlen(',
                'strtoupper(',
                'trim(',
            )
            ->not->toContain(
                'Carbon',
                'DateTimeImmutable',
                'mb_strlen',
                'mb_strtoupper',
                'mb_trim',
            );
    },
)->with(['ecs', 'rector']);

it('loads PHPStan extensions once without warnings through the installer', function (): void {
    $directory = __DIR__.'/../.cache/preset-configuration/phpstan';

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $file = $directory.'/example.php';
    file_put_contents(
        $file,
        "<?php\nfunction label(string \$value): string { return trim(\$value); }\n",
    );
    $config = $directory.'/phpstan.neon';
    file_put_contents($config, 'includes:'."\n  - ".__DIR__.'/../config/phpstan-php.neon'."\n");
    $process = new Process([
        PHP_BINARY,
        __DIR__.'/../vendor/bin/phpstan',
        'analyse',
        $file,
        '--configuration',
        $config,
        '--no-progress',
    ], dirname(__DIR__));
    $process->mustRun();

    expect($process->getOutput().$process->getErrorOutput())
        ->toContain('No errors')
        ->not->toMatch('/warning|deprecated|included multiple times/i');
});

it('applies the shared default without explicit comment-aware configuration', function (): void {
    $fixer = new ECSConfig()->make(CommentAwareLineLengthFixer::class);
    $source = "<?php\nrenderExample('first_long_argument_value', 'second_long_argument_value', 'third_long_argument_value', 'last_value');\n";
    $tokens = Tokens::fromCode($source);
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->not->toBe($source)->toContain("renderExample(\n");
});

it('rejects invalid comment-aware line length overrides', function (mixed $width): void {
    $fixer = new ECSConfig()->make(CommentAwareLineLengthFixer::class);

    expect(fn () => $fixer->configure(['line_length' => $width]))
        ->toThrow(InvalidArgumentException::class);
})->with([0, -1, '100']);
