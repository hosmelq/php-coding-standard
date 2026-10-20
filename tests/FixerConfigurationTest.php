<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\CodingStandard;
use HosmelQ\PhpCodingStandard\Fixer\LineLengthFixer;
use HosmelQ\PhpCodingStandard\LineLength;
use PhpCsFixer\Tokenizer\Tokens;
use Symplify\EasyCodingStandard\Config\ECSConfig;

it('shares one native ECS width while preserving fixer overrides', function (string $source): void {
    $format = static function (int $width, null|int $override = null) use ($source): string {
        $builder = ECSConfig::configure()->withSets([CodingStandard::ECS_PHP]);
        $configuration = new ECSConfig();
        $configuration->service(
            LineLength::class,
            static fn (): LineLength => new LineLength($width),
        );

        if ($override !== null) {
            $builder->withConfiguredRule(LineLengthFixer::class, ['line_length' => $override]);
        }

        $builder($configuration);

        $fixer = $configuration->make(LineLengthFixer::class);
        $tokens = Tokens::fromCode($source);
        $fixer->fix(new SplFileInfo('example.php'), $tokens);

        return $tokens->generateCode();
    };

    expect($format(100))->not->toBe($source);
    expect($format(200))->toBe($source);
    expect($format(200, 100))->toBe($format(100));
})->with([
    'arrays' => "<?php\n\$values = ['first_long_option' => 'first_long_value', 'second_long_option' => 'second_long_value', 'third' => true];\n",
    'arrow lists' => "<?php\n\$callbacks = [fn (\$customer) => \$customer->billingAddress, fn (\$customer) => \$customer->preferredLanguage];\n",
    'calls' => "<?php\nrenderExample('first_long_argument_value', 'second_long_argument_value', 'third_long_argument_value', 'last_value');\n",
    'chains' => "<?php\n\$packages = \$organization->packages()->where('status', 'active')->orderBy('created_at_dates')->get();\n",
    'commented calls' => "<?php\nsendReport(\$destinationDirectoryWithAVeryLongName, /* required */ \$generatedReportFilenameWithAVeryLongName, \$includeArchivedRecords);\n",
    'constructors' => "<?php\nclass Example { public function __construct(public string \$firstLongValue, public string \$secondLongValue, public string \$thirdLongValue) {} }\n",
    'destructuring' => "<?php\n[\$customerIdentifier, \$customerDisplayName, \$customerBillingAddress, \$customerPreferredLanguage] = \$customerDetails;\n",
]);

it('accepts the minimum shared width and rejects smaller widths', function (): void {
    expect(new LineLength(80)->value)->toBe(80)
        ->and(fn (): LineLength => new LineLength(79))->toThrow(InvalidArgumentException::class);
});

it('applies the shared default without explicit configuration', function (): void {
    $fixer = new ECSConfig()->make(LineLengthFixer::class);
    $source = "<?php\nrenderExample('first_long_argument_value', 'second_long_argument_value', 'third_long_argument_value', 'last_value');\n";
    $tokens = Tokens::fromCode($source);
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toContain("renderExample(\n");
});
