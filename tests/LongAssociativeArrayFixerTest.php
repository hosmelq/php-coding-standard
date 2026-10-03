<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\LongAssociativeArrayFixer;
use PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;

it('formats array fixtures idempotently and preserves tokens', function (string $fixture): void {
    $fixer = new LongAssociativeArrayFixer();
    $input = file_get_contents($fixture);
    $expected = file_get_contents(str_replace('.input.php', '.expected.php', $fixture));
    $tokens = Tokens::fromCode($input);
    $fixer->fix(new SplFileInfo($fixture), $tokens);

    expect($tokens->generateCode())->toBe($expected);

    $preserved = static function (Tokens $source): array {
        $result = [];

        foreach ($source as $index => $token) {
            $next = $source->getNextMeaningfulToken($index);
            $trailingComma = $token->equals(',') && $next !== null
                && in_array($source[$next]->getContent(), [')', ']'], true);

            if (! $token->isWhitespace() && ! $trailingComma) {
                $result[] = [$token->getId(), $token->getContent()];
            }
        }

        return $result;
    };

    expect($preserved($tokens))->toBe($preserved(Tokens::fromCode($input)));

    $again = Tokens::fromCode($tokens->generateCode());
    $fixer->fix(new SplFileInfo($fixture), $again);

    expect($again->generateCode())->toBe($expected);
})->with(function (): iterable {
    foreach (glob(__DIR__.'/Fixtures/LongAssociativeArray/*.input.php*') as $fixture) {
        yield basename($fixture) => [$fixture];
    }
});

it('uses the configured maximum code line length', function (): void {
    $fixer = new LongAssociativeArrayFixer();
    $fixer->configure(['line_length' => 30]);

    $tokens = Tokens::fromCode("<?php\n\$values = ['first' => 1, 'second' => 2];\n");
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe(
        "<?php\n\$values = [\n    'first' => 1,\n    'second' => 2,\n];\n",
    );
});

it('honors configured indentation and line endings', function (): void {
    $fixer = new LongAssociativeArrayFixer();
    $fixer->configure(['line_length' => 20]);
    $fixer->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\r\n"));

    $tokens = Tokens::fromCode("<?php\r\n\t\$values = ['first' => 1, 'second' => 2];\r\n");
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe(
        "<?php\r\n\t\$values = [\r\n\t\t'first' => 1,\r\n\t\t'second' => 2,\r\n\t];\r\n",
    );
});

it('rejects invalid line lengths', function (mixed $length): void {
    expect(fn () => new LongAssociativeArrayFixer()->configure(['line_length' => $length]))
        ->toThrow(InvalidFixerConfigurationException::class);
})->with([0, -1, '100']);

it(
    'measures nested items at the projected line boundary',
    function (int $length, string $item): void {
        $fixer = new LongAssociativeArrayFixer();
        $fixer->configure(['line_length' => $length]);

        $input = "<?php\n\$config = ['outer' => ['value' => 'sample'], 'last' => 1];\n";
        $expected = "<?php\n\$config = [\n".$item."\n    'last' => 1,\n];\n";

        for ($pass = 0; $pass < 2; ++$pass) {
            $tokens = Tokens::fromCode($input);
            $fixer->fix(new SplFileInfo('example.php'), $tokens);

            expect($tokens->generateCode())->toBe($expected);

            $input = $tokens->generateCode();
        }
    },
)->with([
    'at the limit' => [37, "    'outer' => ['value' => 'sample'],"],
    'over the limit' => [36, "    'outer' => [\n        'value' => 'sample',\n    ],"],
]);
