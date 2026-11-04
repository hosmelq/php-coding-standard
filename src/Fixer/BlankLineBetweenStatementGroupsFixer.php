<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use Override;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class BlankLineBetweenStatementGroupsFixer extends AbstractFixer implements
    WhitespacesAwareFixerInterface
{
    private const array BLOCK_BOUNDARIES = [
        ')',
        '}',
        [T_CLOSE_TAG],
        [T_ELSE],
        [T_ELSEIF],
        [T_ENDDECLARE],
        [T_ENDFOR],
        [T_ENDFOREACH],
        [T_ENDIF],
        [T_ENDSWITCH],
        [T_ENDWHILE],
    ];

    #[Override]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Separate consecutive statements with different subjects by a blank line.',
            [
                new CodeSample(
                    "<?php\n\$generator = new ClassMapGenerator();\n\$generator->avoidDuplicateScans();\n",
                ),
            ],
        );
    }

    #[Override]
    public function getName(): string
    {
        return 'HosmelQ/blank_line_between_statement_groups';
    }

    #[Override]
    public function getPriority(): int
    {
        // LineLengthFixer can split statements after a closing brace; separate them afterwards.
        return -44;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([
            CT::T_DYNAMIC_VAR_BRACE_OPEN,
            T_VARIABLE,
            T_NEW,
            T_STRING,
            T_NS_SEPARATOR,
            T_STATIC,
        ]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $statements = Statements::find($tokens);
        $ends = [];

        foreach ($statements as $statement) {
            $ends[$statement['end']] = true;
        }

        foreach ($statements as $index => $statement) {
            $previous = $tokens->getPrevNonWhitespace($index);

            if ($statement['subject'] === 'assignment'
                && $previous !== null && ! isset($ends[$previous])
                && $tokens[$previous]->equalsAny([';', '}'])) {
                $this->separate($tokens, $index);
            }

            $next = $tokens->getNextNonWhitespace($statement['end']);

            if ($next === null || $tokens[$next]->isComment()
                || $tokens[$next]->equalsAny(self::BLOCK_BOUNDARIES)) {
                continue;
            }

            $subject = $statements[$next]['subject'] ?? null;

            if (($subject !== null && $subject !== $statement['subject'])
                || ($subject === null && $statement['subject'] === 'assignment')) {
                $this->separate($tokens, $next);
            }
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function separate(Tokens $tokens, int $start): void
    {
        $whitespace = $tokens[$start - 1];

        if (
            ! $whitespace->isWhitespace()
            || mb_substr_count($whitespace->getContent(), "\n") !== 1
        ) {
            return;
        }

        $tokens[$start - 1] = new Token([
            T_WHITESPACE,
            $this->whitespacesConfig->getLineEnding().$whitespace->getContent(),
        ]);
    }
}
