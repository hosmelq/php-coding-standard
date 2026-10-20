<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\HeredocBlankLineFixer;
use PhpCsFixer\Fixer\Whitespace\HeredocIndentationFixer;
use PhpCsFixer\Tokenizer\Tokens;

it('empties whitespace lines that the closing marker indentation removes', function (
    string $input,
    string $expected,
): void {
    $tokens = Tokens::fromCode($input);

    new HeredocBlankLineFixer()->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe($expected);
})->with([
    'deeper whitespace is content' => [
        "<?php\n\$text = <<<'TEXT'\n    first\n      \n    second\n    TEXT;\n",
        "<?php\n\$text = <<<'TEXT'\n    first\n      \n    second\n    TEXT;\n",
    ],
    'interpolation' => [
        "<?php\n\$text = <<<TEXT\n    {\$first}  \n  \n    second\n    TEXT;\n",
        "<?php\n\$text = <<<TEXT\n    {\$first}  \n\n    second\n    TEXT;\n",
    ],
    'nowdoc' => [
        "<?php\n\$text = <<<'TEXT'\n    first\n  \n    second\n    TEXT;\n",
        "<?php\n\$text = <<<'TEXT'\n    first\n\n    second\n    TEXT;\n",
    ],
]);

it('lets heredoc indentation converge in one run', function (): void {
    $source = "<?php\n\$value = [\n    <<<'PHP'\n    <?php\n    \n    declare(strict_types=1);\n    PHP,\n];\n";
    $format = static function (string $code): string {
        $tokens = Tokens::fromCode($code);
        new HeredocIndentationFixer()->fix(new SplFileInfo('example.php'), $tokens);
        $tokens->clearEmptyTokens();
        new HeredocBlankLineFixer()->fix(new SplFileInfo('example.php'), $tokens);

        return $tokens->generateCode();
    };
    $formatted = $format($source);

    expect($formatted)->toBe(
        "<?php\n\$value = [\n    <<<'PHP'\n        <?php\n\n        declare(strict_types=1);\n        PHP,\n];\n",
    )->and($format($formatted))->toBe($formatted);
});

it('provides the custom fixer contract', function (): void {
    $fixer = new HeredocBlankLineFixer();

    expect($fixer->getName())->toBe('HosmelQ/heredoc_blank_line')
        ->and($fixer->getPriority())->toBeLessThan(new HeredocIndentationFixer()->getPriority())
        ->and($fixer->isRisky())->toBeFalse()
        ->and($fixer->isCandidate(Tokens::fromCode('<?php echo 1;')))->toBeFalse();
});
