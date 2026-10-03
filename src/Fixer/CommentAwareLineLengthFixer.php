<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use HosmelQ\PhpCodingStandard\LineLength;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Symplify\CodingStandard\Fixer\AbstractSymplifyFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;

/**
 * @implements ConfigurableFixerInterface<array<string, mixed>, array<string, mixed>>
 */
final class CommentAwareLineLengthFixer extends AbstractSymplifyFixer implements
    ConfigurableFixerInterface
{
    public function __construct(
        private readonly LineLengthFixer $lineLengthFixer,
        private readonly LineLength $lineLength = new LineLength(),
    ) {
        $this->configure([]);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
        $this->lineLengthFixer->configure(
            $this->getConfigurationDefinition()->resolve($configuration),
        );
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function fix(SplFileInfo $fileInfo, Tokens $tokens): void
    {
        /** @var array<int, Token> $originals */
        $originals = [];

        foreach ($tokens as $index => $token) {
            if (! $token->isGivenKind(T_COMMENT)) {
                continue;
            }

            $comment = $token->getContent();

            if (! str_starts_with($comment, '//') && ! str_starts_with($comment, '#')) {
                continue;
            }

            // Keep the comment token so upstream retains its comment safety checks.
            $marker = new Token([T_COMMENT, "\n"]);
            $originals[spl_object_id($marker)] = $token;
            $tokens[$index] = $marker;
            $previous = $index - 1;

            if ($previous < 0 || ! $tokens[$previous]->isWhitespace()) {
                continue;
            }

            if (str_contains($tokens[$previous]->getContent(), "\n")) {
                continue;
            }

            $marker = new Token([T_WHITESPACE, "\n"]);
            $originals[spl_object_id($marker)] = $tokens[$previous];
            $tokens[$previous] = $marker;
        }

        try {
            $this->lineLengthFixer->fix($fileInfo, $tokens);
        } finally {
            foreach ($tokens as $index => $token) {
                if (isset($originals[spl_object_id($token)])) {
                    $tokens[$index] = $originals[spl_object_id($token)];
                }
            }
        }
    }

    public function getConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            new FixerOptionBuilder(
                'break_long_lines',
                'Wrap lines longer than the configured width.',
            )
                ->setAllowedTypes(['bool'])
                ->setDefault(true)
                ->getOption(),
            new FixerOptionBuilder(
                'inline_short_lines',
                'Inline blocks that fit the configured width.',
            )
                ->setAllowedTypes(['bool'])
                ->setDefault(true)
                ->getOption(),
            new FixerOptionBuilder(
                'line_length',
                'Maximum code line length without trailing comments.',
            )
                ->setAllowedTypes(['int'])
                ->setAllowedValues([static fn (mixed $value): bool => is_int($value) && $value > 0])
                ->setDefault($this->lineLength->value)
                ->getOption(),
        ]);
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return $this->lineLengthFixer->getDefinition();
    }

    public function getPriority(): int
    {
        return $this->lineLengthFixer->getPriority();
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $this->lineLengthFixer->isCandidate($tokens);
    }
}
