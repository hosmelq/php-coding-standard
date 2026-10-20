<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\NoBlankLineBetweenAssignmentsFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;

it('removes blank lines between consecutive variable assignments', function (
    string $input,
    string $expected,
): void {
    $fixer = new NoBlankLineBetweenAssignmentsFixer();
    $tokens = Tokens::fromCode($input);

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe($expected);
})->with([
    'assignments' => [
        "<?php\n\$owner = Owner::factory()->createOne();\n\n\$address = \$owner->addresses()->create(['label' => 'Home']);\n",
        "<?php\n\$owner = Owner::factory()->createOne();\n\$address = \$owner->addresses()->create(['label' => 'Home']);\n",
    ],
    'compound assignments' => [
        "<?php\n\$total = 0;\n\n\$total += 1;\n\n\$label .= 'suffix';\n\n\$value ??= 'default';\n",
        "<?php\n\$total = 0;\n\$total += 1;\n\$label .= 'suffix';\n\$value ??= 'default';\n",
    ],
    'comments keep the blank line' => [
        "<?php\n\$first = 1;\n\n// Second group.\n\$second = 2;\n",
        "<?php\n\$first = 1;\n\n// Second group.\n\$second = 2;\n",
    ],
    'indented statements' => [
        "<?php\nfunction example(): void\n{\n    \$first = 1;\n\n\n    \$second = 2;\n}\n",
        "<?php\nfunction example(): void\n{\n    \$first = 1;\n    \$second = 2;\n}\n",
    ],
    'multiline assignments' => [
        "<?php\n\$callback = function (): int {\n    \$inner = 1;\n\n    \$other = 2;\n\n    return \$inner + \$other;\n};\n\n\$result = \$callback();\n",
        "<?php\n\$callback = function (): int {\n    \$inner = 1;\n    \$other = 2;\n\n    return \$inner + \$other;\n};\n\$result = \$callback();\n",
    ],
    'other statements keep the blank line' => [
        "<?php\n\$first = 1;\n\nconsume(\$first);\n\n\$second = 2;\n",
        "<?php\n\$first = 1;\n\nconsume(\$first);\n\n\$second = 2;\n",
    ],
    'properties and array items are not variables' => [
        "<?php\n\$first = 1;\n\n\$this->value = 2;\n\n\$values['key'] = 3;\n\n\$second = 4;\n",
        "<?php\n\$first = 1;\n\n\$this->value = 2;\n\n\$values['key'] = 3;\n\n\$second = 4;\n",
    ],
    'comparisons are not assignments' => [
        "<?php\n\$first = 1;\n\n\$first === 1;\n",
        "<?php\n\$first = 1;\n\n\$first === 1;\n",
    ],
    'for headers are untouched' => [
        "<?php\nfor (\$index = 0; \$index < 3; ++\$index) {\n}\n",
        "<?php\nfor (\$index = 0; \$index < 3; ++\$index) {\n}\n",
    ],
]);

it('uses the configured line ending', function (): void {
    $fixer = new NoBlankLineBetweenAssignmentsFixer();
    $fixer->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\r\n"));

    $tokens = Tokens::fromCode("<?php\r\n\t\$first = 1;\r\n\r\n\t\$second = 2;\r\n");

    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe("<?php\r\n\t\$first = 1;\r\n\t\$second = 2;\r\n");
});

it('provides the custom fixer contract', function (): void {
    $fixer = new NoBlankLineBetweenAssignmentsFixer();

    expect($fixer->getName())->toBe('HosmelQ/no_blank_line_between_assignments')
        ->and($fixer->getDefinition()->getCodeSamples())->not->toBeEmpty()
        ->and($fixer->isRisky())->toBeFalse()
        ->and($fixer->isCandidate(Tokens::fromCode('<?php echo 1;')))->toBeFalse();
});
