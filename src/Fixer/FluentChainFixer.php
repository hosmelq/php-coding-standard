<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use HosmelQ\PhpCodingStandard\LineLength;
use HosmelQ\PhpCodingStandard\TokenAnalyzer\SchemaCallbackAnalyzer;
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
final class FluentChainFixer extends AbstractFixer implements
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
            'Put inline object/nullsafe chains of three or more method calls on separate '
                .'lines, allowing an initial static call and multiline array arguments. Preserve '
                .'direct $table chains in '
                .'Schema::create/table callbacks with a first Blueprint $table parameter only '
                .'when their code line fits the configured line length.',
            [new CodeSample("<?php\n\$query->where('active', true)->orderBy('name')->get();\n")],
        );
    }

    public function getName(): string
    {
        return 'HosmelQ/fluent_chain';
    }

    public function getPriority(): int
    {
        // Split chains before native line-length and indentation fixers.
        return 40;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([
            T_DOUBLE_COLON,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_OBJECT_OPERATOR,
        ]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $configuration = $this->configuration;

        if ($configuration === null) {
            throw new LogicException('The fixer must be configured before formatting chains.');
        }

        $chains = [];
        $claimed = [];
        $schemaCallbacks = new SchemaCallbackAnalyzer();
        $scopes = $schemaCallbacks->callbackScopes($tokens);

        foreach ($tokens as $index => $token) {
            if (isset($claimed[$index])) {
                continue;
            }

            if ($token->isGivenKind([T_START_HEREDOC]) || $token->equalsAny(['"', '`'])) {
                $end = $this->stringEnd($tokens, $index);

                for ($ignored = $index; $ignored <= $end; ++$ignored) {
                    $claimed[$ignored] = true;
                }
            }

            if (isset($claimed[$index]) || ! $this->isOperator($token)) {
                continue;
            }

            $chain = $this->chain($tokens, $index);

            foreach ($chain['members'] as $member) {
                $claimed[$member] = true;
            }

            if (count($chain['calls']) < 3) {
                continue;
            }

            // Later static access is outside the rule and conflicts with native :: whitespace.
            $hasStaticContinuation = false;

            foreach ($chain['members'] as $member) {
                if ($member !== $index && $tokens[$member]->isGivenKind(T_DOUBLE_COLON)) {
                    $hasStaticContinuation = true;

                    break;
                }
            }

            if ($hasStaticContinuation) {
                continue;
            }

            $root = $tokens->getPrevMeaningfulToken($index);

            if ($root === null) {
                continue;
            }

            $multilineArray = false;
            $singleLine = true;

            for ($part = $root; $part <= $chain['end']; ++$part) {
                if ($tokens[$part]->isGivenKind([CT::T_ARRAY_BRACKET_OPEN, T_ARRAY])) {
                    $arrayStart = $tokens[$part]->isGivenKind(T_ARRAY)
                        ? $tokens->getNextMeaningfulToken($part)
                        : $part;

                    if ($arrayStart === null || ! $tokens[$arrayStart]->equalsAny(
                        ['(', [CT::T_ARRAY_BRACKET_OPEN]],
                    )) {
                        continue;
                    }

                    $arrayType = $tokens[$part]->isGivenKind(T_ARRAY)
                        ? Tokens::BLOCK_TYPE_PARENTHESIS
                        : Tokens::BLOCK_TYPE_ARRAY_BRACKET;
                    $arrayEnd = $tokens->findBlockEnd($arrayType, $arrayStart);
                    $multilineArray = $multilineArray || strpbrk(
                        $tokens->generatePartialCode($part, $arrayEnd),
                        "\r\n",
                    ) !== false;
                    $part = $arrayEnd;

                    continue;
                }

                if (strpbrk($tokens[$part]->getContent(), "\r\n") !== false) {
                    $singleLine = false;

                    break;
                }
            }

            if ($singleLine && ! (
                ! $multilineArray
                && $chain['calls'][0]['operator'] === $index
                && $schemaCallbacks->isMigration($tokens, $root, $scopes)
                && ! TokenLayout::isLongSingleLine(
                    $tokens,
                    $root,
                    $chain['end'],
                    $configuration['line_length'],
                )
            )) {
                $chains[] = ['root' => $root, ...$chain];
            }
        }

        $edits = [];
        $forHeaders = [];

        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(T_FOR)) {
                $open = $tokens->getNextMeaningfulToken($index);

                if ($open !== null) {
                    $forHeaders[$open] = $tokens->findBlockEnd(
                        Tokens::BLOCK_TYPE_PARENTHESIS,
                        $open,
                    );
                }
            }
        }

        foreach ($chains as $chain) {
            $statementStart = $this->statementStart($tokens, $chain['root']);

            foreach ($forHeaders as $open => $close) {
                if ($open < $chain['root'] && $chain['root'] < $close) {
                    $statementStart = null;
                }
            }

            if ($statementStart !== null) {
                $edits[$statementStart] = $this->whitespacesConfig->getLineEnding()
                    .$this->lineIndent($tokens, $statementStart);
            }

            $depth = 1;

            foreach ($chains as $parent) {
                foreach ($parent['calls'] as $call) {
                    if ($call['open'] < $chain['root'] && $chain['end'] < $call['end']) {
                        ++$depth;
                    }
                }
            }

            $originalIndent = $this->lineIndent($tokens, $chain['root']);
            $indent = $originalIndent
                .str_repeat($this->whitespacesConfig->getIndent(), $depth);

            foreach ($chain['calls'] as $offset => $call) {
                if ($offset === 0 && $tokens[$call['operator']]->isGivenKind(T_DOUBLE_COLON)) {
                    continue;
                }

                $edits[$call['operator']] = $this->whitespacesConfig->getLineEnding().$indent;

                for ($part = $call['open'] + 1; $part < $call['end']; ++$part) {
                    $content = $tokens[$part]->getContent();
                    $newline = strrpos($content, "\n");

                    if (! $tokens[$part]->isWhitespace() || $newline === false) {
                        continue;
                    }

                    $suffix = substr($content, $newline + 1);

                    if (str_starts_with($suffix, $originalIndent)) {
                        $edits[$part + 1] = substr($content, 0, $newline + 1).$indent
                            .substr($suffix, strlen($originalIndent));
                    }
                }
            }
        }

        krsort($edits);

        foreach ($edits as $index => $whitespace) {
            $tokens->ensureWhitespaceAtIndex($index - 1, 1, $whitespace);
        }
    }

    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            new FixerOptionBuilder(
                'line_length',
                'Maximum code line length for an inline migration chain.',
            )
                ->setAllowedTypes(['int'])
                ->setAllowedValues([static fn (mixed $value): bool => is_int($value) && $value > 0])
                ->setDefault($this->lineLength->value)
                ->getOption(),
        ]);
    }

    /**
     * @param Tokens<Token> $tokens
     *
     * @return array{
     *     calls: list<array{end: int, open: int, operator: int}>,
     *     end: int,
     *     members: list<int>
     * }
     */
    private function chain(Tokens $tokens, int $index): array
    {
        $calls = [];
        $end = $index;
        $members = [];

        while ($this->isOperator($tokens[$index])) {
            $members[] = $index;
            $name = $tokens->getNextMeaningfulToken($index);

            if ($name === null) {
                break;
            }

            $block = Tokens::detectBlockType($tokens[$name]);

            if ($block !== null && $block['isStart']) {
                $name = $tokens->findBlockEnd($block['type'], $name);
            } elseif (! $tokens[$name]->isGivenKind([T_STRING, T_VARIABLE])) {
                break;
            }

            $next = $tokens->getNextMeaningfulToken($name);
            $end = $name;

            if ($next !== null && $tokens[$next]->equals('(')) {
                $end = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $next);
                $calls[] = ['end' => $end, 'open' => $next, 'operator' => $index];
            }

            $next = $tokens->getNextMeaningfulToken($end);

            if ($next === null || ! $this->isOperator($tokens[$next])) {
                break;
            }

            $index = $next;
        }

        return ['calls' => $calls, 'end' => $end, 'members' => $members];
    }

    private function isOperator(Token $token): bool
    {
        return $token->isGivenKind([
            T_DOUBLE_COLON,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_OBJECT_OPERATOR,
        ]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function lineIndent(Tokens $tokens, int $index): string
    {
        $line = '';

        for ($cursor = $index; $cursor >= 0; --$cursor) {
            $content = $tokens[$cursor]->getContent();
            $line = $content.$line;

            if (strpbrk($content, "\r\n") !== false) {
                break;
            }
        }

        $start = max((int) mb_strrpos($line, "\n"), (int) mb_strrpos($line, "\r"));
        $line = mb_substr($line, $start + 1);

        return mb_substr($line, 0, strspn($line, " \t"));
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function statementStart(Tokens $tokens, int $root): null|int
    {
        for ($index = $root; $index >= 0; --$index) {
            if (strpbrk($tokens[$index]->getContent(), "\r\n") !== false) {
                return null;
            }

            if ($tokens[$index]->equals(';')) {
                return $tokens->getNextNonWhitespace($index);
            }

            if ($tokens[$index]->equals('}')) {
                $next = $tokens->getNextMeaningfulToken($index);

                if ($next !== null && $tokens[$next]->isGivenKind([
                    T_NEW,
                    T_RETURN,
                    T_STRING,
                    T_THROW,
                    T_VARIABLE,
                ])) {
                    return $tokens->getNextNonWhitespace($index);
                }
            }

            $block = Tokens::detectBlockType($tokens[$index]);

            if ($block !== null && ! $block['isStart']) {
                $index = $tokens->findBlockStart($block['type'], $index);
            }
        }

        return null;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function stringEnd(Tokens $tokens, int $index): int
    {
        $heredoc = $tokens[$index]->isGivenKind(T_START_HEREDOC);
        $delimiter = $tokens[$index]->getContent();
        $counter = count($tokens);

        for ($cursor = $index + 1; $cursor < $counter; ++$cursor) {
            if (($heredoc && $tokens[$cursor]->isGivenKind(T_END_HEREDOC))
                || (! $heredoc && $tokens[$cursor]->equals($delimiter))) {
                return $cursor;
            }

            $block = Tokens::detectBlockType($tokens[$cursor]);

            if ($block !== null && $block['isStart']) {
                $cursor = $tokens->findBlockEnd($block['type'], $cursor);
            }
        }

        return count($tokens) - 1;
    }
}
