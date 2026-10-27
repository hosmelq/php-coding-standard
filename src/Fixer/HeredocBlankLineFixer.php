<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use Override;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class HeredocBlankLineFixer extends AbstractFixer
{
    #[Override]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Empty heredoc and nowdoc lines that only hold whitespace removed by the closing '
                .'marker indentation.',
            [new CodeSample("<?php\n\$text = <<<'TEXT'\n    first\n  \n    second\n    TEXT;\n")],
        );
    }

    #[Override]
    public function getName(): string
    {
        return 'HosmelQ/heredoc_blank_line';
    }

    #[Override]
    public function getPriority(): int
    {
        // Clean up after heredoc_indentation, which can leave these lines for a second run.
        return -27;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_START_HEREDOC);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if (! $token->isGivenKind(T_END_HEREDOC)) {
                continue;
            }

            $closing = $token->getContent();
            $indentation = mb_strlen($closing) - mb_strlen(mb_ltrim($closing, " \t"));
            $start = $tokens->getPrevTokenOfKind($index, [[T_START_HEREDOC]]);

            if ($start === null) {
                continue;
            }

            for ($part = $start + 1; $part < $index; ++$part) {
                if (! $tokens[$part]->isGivenKind([T_ENCAPSED_AND_WHITESPACE])) {
                    continue;
                }

                $content = $tokens[$part]->getContent();
                $lines = explode("\n", $content);

                foreach ($lines as $line => $text) {
                    // Only whole lines inside the body: not the text before the first line break.
                    if ($line === 0 && ! $this->startsLine($tokens, $part)) {
                        continue;
                    }

                    if ($text !== '' && mb_trim($text, " \t") === ''
                        && mb_strlen($text) <= $indentation
                        && $line < count($lines) - 1) {
                        $lines[$line] = '';
                    }
                }

                $fixed = implode("\n", $lines);

                if ($fixed !== $content) {
                    $tokens[$part] = new Token([T_ENCAPSED_AND_WHITESPACE, $fixed]);
                }
            }
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function startsLine(Tokens $tokens, int $index): bool
    {
        $previous = $tokens[$index - 1];

        return $previous->isGivenKind(T_START_HEREDOC)
            || str_ends_with($previous->getContent(), "\n");
    }
}
