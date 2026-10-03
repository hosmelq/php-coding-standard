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
final class LongPromotedConstructorFixer extends AbstractFixer implements
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
            'Split long promoted constructors into one parameter per line.',
            [new CodeSample(
                '<?php class Example { public function __construct(public string $firstProperty, '
                .'public string $secondProperty) {} }'."\n",
            )],
        );
    }

    public function getName(): string
    {
        return 'HosmelQ/long_promoted_constructor';
    }

    public function getPriority(): int
    {
        return 42;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_FUNCTION);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $configuration = $this->configuration;

        if ($configuration === null) {
            throw new LogicException(
                'The fixer must be configured before formatting constructors.',
            );
        }

        for ($index = $tokens->count() - 1; $index >= 0; --$index) {
            if (! $tokens[$index]->isGivenKind(T_FUNCTION)) {
                continue;
            }

            $name = $tokens->getNextMeaningfulToken($index);

            if ($name !== null && $tokens[$name]->getContent() === '&') {
                $name = $tokens->getNextMeaningfulToken($name);
            }

            if ($name === null || ! $tokens[$name]->isGivenKind(T_STRING)
                || mb_strtolower($tokens[$name]->getContent()) !== '__construct') {
                continue;
            }

            $start = $tokens->getNextMeaningfulToken($name);

            if ($start === null || ! $tokens[$start]->equals('(')) {
                continue;
            }

            $end = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $start);
            $promoted = array_any(
                TokenLayout::topLevelTokens($tokens, $start, $end),
                fn (int $parameter) => $tokens[$parameter]->isGivenKind([
                    CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE,
                    CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED,
                    CT::T_CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC,
                    T_FINAL,
                    T_PRIVATE_SET,
                    T_PROTECTED_SET,
                    T_PUBLIC_SET,
                    T_READONLY,
                ]),
            );

            if (! $promoted || ! TokenLayout::isLongSingleLine(
                $tokens,
                $start,
                $end,
                $configuration['line_length'],
            )) {
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
            new FixerOptionBuilder('line_length', 'Maximum code line length for a constructor.')
                ->setAllowedTypes(['int'])
                ->setAllowedValues([static fn (mixed $value): bool => is_int($value) && $value > 0])
                ->setDefault($this->lineLength->value)
                ->getOption(),
        ]);
    }
}
