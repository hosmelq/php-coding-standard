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

final class NoBlankLineBetweenAssignmentsFixer extends AbstractFixer implements
    WhitespacesAwareFixerInterface
{
    #[Override]
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Remove blank lines between consecutive assignment statements.',
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
        return $tokens->isAnyTokenKindsFound([CT::T_DYNAMIC_VAR_BRACE_OPEN, T_VARIABLE]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    #[Override]
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $assignments = Statements::assignments($tokens);
        $ends = array_flip($assignments);

        foreach (array_keys($assignments) as $start) {
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
}
