<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\LongPromotedConstructorFixer;
use PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;

it(
    'formats constructor fixtures idempotently and preserves tokens',
    function (string $fixture): void {
        $fixer = new LongPromotedConstructorFixer();
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
    },
)->with(function (): iterable {
    foreach (glob(__DIR__.'/Fixtures/LongPromotedConstructor/*.input.php') as $fixture) {
        yield basename($fixture) => [$fixture];
    }
});

it('honors constructor indentation and line endings', function (): void {
    $fixer = new LongPromotedConstructorFixer();
    $fixer->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\r\n"));

    $input = "<?php\r\nclass Example {\r\n\tpublic function __construct("
        .'public string $firstPropertyWithALongName, public string $secondPropertyWithALongName'
        .") {}\r\n}\r\n";
    $tokens = Tokens::fromCode($input);
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe(
        "<?php\r\nclass Example {\r\n\tpublic function __construct(\r\n"
        ."\t\tpublic string \$firstPropertyWithALongName,\r\n"
        ."\t\tpublic string \$secondPropertyWithALongName,\r\n\t) {}\r\n}\r\n",
    );
});

it('uses the configured constructor line length', function (): void {
    $fixer = new LongPromotedConstructorFixer();
    $fixer->configure(['line_length' => 45]);

    $tokens = Tokens::fromCode(
        "<?php\nclass Example {\n    public function __construct(public int \$value) {}\n}\n",
    );
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toContain("__construct(\n        public int \$value,\n    )");
});

it('rejects invalid constructor line lengths', function (mixed $length): void {
    expect(fn () => new LongPromotedConstructorFixer()->configure(['line_length' => $length]))
        ->toThrow(InvalidFixerConfigurationException::class);
})->with([0, -1, '100']);

it('wraps promotions without explicit read visibility', function (string $modifier): void {
    $input = "<?php\nclass Example {\n    public function __construct("
        .$modifier.' string $firstPropertyWithALongName, '
        .$modifier.' string $secondPropertyWithALongName'
        .") {}\n}\n";
    $expected = "<?php\nclass Example {\n    public function __construct(\n        "
        .$modifier.' string $firstPropertyWithALongName,'
        ."\n        ".$modifier.' string $secondPropertyWithALongName,'
        ."\n    ) {}\n}\n";
    $fixer = new LongPromotedConstructorFixer();

    for ($pass = 0; $pass < 2; ++$pass) {
        $tokens = Tokens::fromCode($input);
        $fixer->fix(new SplFileInfo('example.php'), $tokens);

        expect($tokens->generateCode())->toBe($expected);

        $input = $tokens->generateCode();
    }
})->with(function (): iterable {
    if (PHP_VERSION_ID >= 80500) {
        yield 'final' => ['final'];
    }

    yield from ['private(set)', 'protected(set)', 'public(set)', 'readonly'];
});
