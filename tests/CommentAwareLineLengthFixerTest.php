<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\CommentAwareLineLengthFixer;
use PhpCsFixer\Tokenizer\Tokens;
use Symplify\EasyCodingStandard\Config\ECSConfig;

it('wraps code independently of trailing comments', function (string $before, string $after): void {
    $container = new ECSConfig();
    $fixer = $container->make(CommentAwareLineLengthFixer::class);
    $fixer->configure([
        'break_long_lines' => true,
        'inline_short_lines' => false,
        'line_length' => 100,
    ]);
    $tokens = Tokens::fromCode($before);
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    $significant = static function (string $code): array {
        $source = Tokens::fromCode($code);
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

    expect($tokens->generateCode())->toBe($after)
        ->and($significant($tokens->generateCode()))->toBe($significant($before));

    $tokens = Tokens::fromCode($after);
    $fixer->fix(new SplFileInfo('example.php'), $tokens);

    expect($tokens->generateCode())->toBe($after);
})->with(function (): iterable {
    foreach (glob(__DIR__.'/Fixtures/CommentAwareLineLength/*.php.inc') as $path) {
        $fixture = file_get_contents($path);
        [$before, $after] = array_pad(explode("\n-----\n", $fixture, 2), 2, $fixture);

        yield basename($path) => [$before, $after];
    }
});

it(
    'inlines short code without swallowing comments',
    function (string $before, string $after): void {
        $fixer = new ECSConfig()->make(CommentAwareLineLengthFixer::class);
        $fixer->configure([
            'break_long_lines' => true,
            'inline_short_lines' => true,
            'line_length' => 100,
        ]);
        $significant = static function (string $code): array {
            $source = Tokens::fromCode($code);
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
        $original = $significant($before);

        for ($pass = 0; $pass < 2; ++$pass) {
            $tokens = Tokens::fromCode($before);
            $fixer->fix(new SplFileInfo('example.php'), $tokens);

            expect($tokens->generateCode())->toBe($after)
                ->and($significant($tokens->generateCode()))->toBe($original);

            $before = $tokens->generateCode();
        }
    },
)->with([
    'hash comment' => [
        "<?php\nrun(\n    'first', # keep this comment\n    'second',\n);\n",
        "<?php\nrun(\n    'first', # keep this comment\n    'second',\n);\n",
    ],
    'slash comment' => [
        "<?php\nrun(\n    'first', // keep this comment\n    'second',\n);\n",
        "<?php\nrun(\n    'first', // keep this comment\n    'second',\n);\n",
    ],
    'without comments' => [
        "<?php\nrun(\n    'first',\n    'second',\n);\n",
        "<?php\nrun('first', 'second',);\n",
    ],
]);
