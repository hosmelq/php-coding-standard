<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use Override;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class NoBlankLineBetweenAssignmentsFixer extends AbstractFixer implements
    WhitespacesAwareFixerInterface
{
    private const array ASSIGNMENT_OPERATORS = [
        '=',
        [T_AND_EQUAL],
        [T_COALESCE_EQUAL],
        [T_CONCAT_EQUAL],
        [T_DIV_EQUAL],
        [T_MINUS_EQUAL],
        [T_MOD_EQUAL],
        [T_MUL_EQUAL],
        [T_OR_EQUAL],
        [T_PLUS_EQUAL],
        [T_POW_EQUAL],
        [T_SL_EQUAL],
        [T_SR_EQUAL],
        [T_XOR_EQUAL],
    ];

    #[Override]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Remove blank lines between consecutive variable assignment statements.',
            [
                new CodeSample(
                    "<?php\n\$owner = createOwner();\n\n\$address = createAddress(\$owner);\n",
                ),
            ],
        );
    }

    #[Override]
    public function getName(): string
    {
        return 'HosmelQ/no_blank_line_between_assignments';
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_VARIABLE);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $starts = [];
        $ends = [];

        foreach ($tokens as $index => $token) {
            if (! $token->isGivenKind(T_VARIABLE) || ! $this->startsAssignment($tokens, $index)) {
                continue;
            }

            $end = $this->statementEnd($tokens, $index);

            if ($end !== null) {
                $starts[] = $index;
                $ends[$end] = true;
            }
        }

        foreach ($starts as $start) {
            $previous = $tokens->getPrevNonWhitespace($start);

            if ($previous === null || ! isset($ends[$previous])) {
                continue;
            }

            $whitespace = $tokens[$start - 1]->getContent();

            if (mb_substr_count($whitespace, "\n") < 2) {
                continue;
            }

            $tokens[$start - 1] = new Token([
                T_WHITESPACE,
                $this->whitespacesConfig->getLineEnding()
                    .mb_substr($whitespace, (int) mb_strrpos($whitespace, "\n") + 1),
            ]);
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function startsAssignment(Tokens $tokens, int $index): bool
    {
        $previous = $tokens->getPrevMeaningfulToken($index);
        $next = $tokens->getNextMeaningfulToken($index);

        return $previous !== null
            && $next !== null
            && ($tokens[$previous]->equalsAny([';', '{', '}']) || $tokens[$previous]->isGivenKind(
                T_OPEN_TAG,
            ))
            && $tokens[$next]->equalsAny(self::ASSIGNMENT_OPERATORS);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function statementEnd(Tokens $tokens, int $start): null|int
    {
        for ($index = $start; $index < $tokens->count(); ++$index) {
            $token = $tokens[$index];

            if ($token->equals(';')) {
                return $index;
            }

            $block = Tokens::detectBlockType($token);

            if ($block === null) {
                if ($token->isGivenKind(T_CLOSE_TAG)) {
                    return null;
                }

                continue;
            }

            if (! $block['isStart']) {
                return null;
            }

            $index = $tokens->findBlockEnd($block['type'], $index);
        }

        return null;
    }
}
