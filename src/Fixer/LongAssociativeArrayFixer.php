<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use HosmelQ\PhpCodingStandard\LineLength;
use LogicException;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * @implements ConfigurableFixerInterface<array{line_length?: int}, array{line_length: int}>
 */
final class LongAssociativeArrayFixer extends AbstractFixer implements
    ConfigurableFixerInterface,
    WhitespacesAwareFixerInterface
{
    /** @use ConfigurableFixerTrait<array{line_length?: int}, array{line_length: int}> */
    use ConfigurableFixerTrait;

    public function __construct(private readonly LineLength $lineLength = new LineLength())
    {
        parent::__construct();
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Split long associative arrays into one item per line with a trailing comma.',
            [new CodeSample(
                "<?php\n\$values = ['first' => 1, 'second' => 2];\n",
                ['line_length' => 30],
            )],
        );
    }

    public function getName(): string
    {
        return 'HosmelQ/long_associative_array';
    }

    public function getPriority(): int
    {
        // Measure after fluent chains and native array indentation, before native line wrapping.
        return 28;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_DOUBLE_ARROW);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $configuration = $this->configuration;

        if ($configuration === null) {
            throw new LogicException('The fixer must be configured before formatting arrays.');
        }

        for ($index = 0; $index < $tokens->count(); ++$index) {
            $token = $tokens[$index];

            if (! $token->isGivenKind([CT::T_ARRAY_BRACKET_OPEN, T_ARRAY])) {
                continue;
            }

            $start = $token->isGivenKind(T_ARRAY)
                ? $tokens->getNextMeaningfulToken($index)
                : $index;

            if ($start === null) {
                continue;
            }

            $type = $token->isGivenKind(T_ARRAY)
                ? Tokens::BLOCK_TYPE_PARENTHESIS
                : Tokens::BLOCK_TYPE_ARRAY_BRACKET;
            $end = $tokens->findBlockEnd($type, $start);
            $associative = false;
            $arrowFunction = false;

            foreach (TokenLayout::topLevelTokens($tokens, $start, $end) as $item) {
                if ($tokens[$item]->isGivenKind(T_FN)) {
                    $arrowFunction = true;
                }

                if ($tokens[$item]->isGivenKind(T_DOUBLE_ARROW)) {
                    if ($arrowFunction) {
                        $arrowFunction = false;

                        continue;
                    }

                    $associative = true;

                    break;
                }
            }

            if (! $associative) {
                continue;
            }

            $inlineItems = false;
            $literalNewline = false;

            foreach ([$start, ...TokenLayout::topLevelTokens($tokens, $start, $end)] as $item) {
                if (! $tokens[$item]->isWhitespace()
                    && strpbrk($tokens[$item]->getContent(), "\r\n") !== false) {
                    $literalNewline = true;
                }

                if ($item !== $start && ! $tokens[$item]->equals(',')) {
                    continue;
                }

                $next = $tokens->getNextMeaningfulToken($item);

                if ($next !== null && $next < $end && strpbrk(
                    $tokens->generatePartialCode($item + 1, $next),
                    "\r\n",
                ) === false) {
                    $inlineItems = true;
                }
            }

            $multilineValue = strpbrk($tokens->generatePartialCode($start, $end), "\r\n") !== false;

            if ($literalNewline || ! $inlineItems || (! $multilineValue && ! TokenLayout::isLongSingleLine(
                $tokens,
                $start,
                $end,
                $configuration['line_length'],
            ))) {
                continue;
            }

            TokenLayout::split(
                $tokens,
                $start,
                $end,
                TokenLayout::indentation($tokens, $start),
                $this->whitespacesConfig->getIndent(),
                $this->whitespacesConfig->getLineEnding(),
            );
        }
    }

    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            new FixerOptionBuilder('line_length', 'Maximum code line length for an inline array.')
                ->setAllowedTypes(['int'])
                ->setAllowedValues([static fn (mixed $value): bool => is_int($value) && $value > 0])
                ->setDefault($this->lineLength->value)
                ->getOption(),
        ]);
    }
}
