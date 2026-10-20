<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use HosmelQ\PhpCodingStandard\LineLength;
use LogicException;
use Override;
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
 * Lays out code by line width in a single pass. Chains that fit are joined first. Then each
 * line that is too long breaks its outermost construct, and the lines below it are measured
 * with their final indentation.
 *
 * @implements ConfigurableFixerInterface<array{line_length?: int}, array{line_length: int}>
 */
final class LineLengthFixer extends AbstractFixer implements
    ConfigurableFixerInterface,
    WhitespacesAwareFixerInterface
{
    /** @use ConfigurableFixerTrait<array{line_length?: int}, array{line_length: int}> */
    use ConfigurableFixerTrait;

    /**
     * The or, xor, and, ternary, ??, ||, && and concatenation precedences.
     */
    private const array BREAKABLE_PRECEDENCES = [1, 2, 3, 4, 5, 6, 7, 12];

    public function __construct(private readonly LineLength $lineLength = new LineLength())
    {
        parent::__construct();
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Join method chains that fit the line length, and break long lines from the '
                .'outermost method chain, argument, parameter, array or destructuring list inward.',
            [new CodeSample(
                "<?php\n\$query\n    ->where('active', true)\n    ->get();\n"
                    .'sendReport($destinationDirectory, $generatedReportFilename, '
                    ."\$includeArchivedRecords);\n",
                ['line_length' => 80],
            )],
        );
    }

    #[Override]
    public function getName(): string
    {
        return 'HosmelQ/line_length';
    }

    #[Override]
    public function getPriority(): int
    {
        // Measure after every fixer that changes code on a line, so the widths are final.
        return -43;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([
            '(',
            CT::T_ARRAY_BRACKET_OPEN,
            CT::T_DESTRUCTURING_BRACKET_OPEN,
        ]);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $configuration = $this->configuration;

        if ($configuration === null) {
            throw new LogicException('The fixer must be configured before formatting lines.');
        }

        $this->joinTestCalls($tokens);
        $this->joinChains($tokens, $configuration['line_length']);

        // Keep block comments next to the argument they describe.
        foreach (array_keys($tokens->findGivenKind(T_COMMENT)) as $index) {
            $open = $this->enclosingParenthesis($tokens, $index);

            if ($open !== null && $this->listEnd($tokens, $open) !== null) {
                $this->joinArgumentComments(
                    $tokens,
                    $open,
                    $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open),
                    $configuration['line_length'],
                );
            }
        }

        for ($first = 0; $first < $tokens->count();) {
            $last = $first;

            while ($last < $tokens->count() - 1
                && ! str_contains($tokens[$last]->getContent(), "\n")) {
                ++$last;
            }

            // A broken line is measured again, so it can break its next construct.
            if (! $this->breakLine($tokens, $first, $last, $configuration['line_length'])) {
                $first = $last + 1;
            }
        }
    }

    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            new FixerOptionBuilder('line_length', 'Maximum code line length.')
                ->setAllowedTypes(['int'])
                ->setAllowedValues(
                    [static fn (mixed $value): bool => is_int($value) && $value >= 80],
                )
                ->setDefault($this->lineLength->value)
                ->getOption(),
        ]);
    }

    /**
     * Finds the arrow functions whose arrow is on the line, after code on the same line.
     *
     * @param Tokens<Token> $tokens
     *
     * @return list<array{chain: null, end: int, kind: 'arrow', start: int}>
     */
    private function arrowFunctionsOnLine(Tokens $tokens, int $first, int $last): array
    {
        $candidates = [];

        for ($index = $first; $index <= $last; ++$index) {
            if (! $tokens[$index]->isGivenKind(T_FN)) {
                continue;
            }

            $open = $tokens->getNextTokenOfKind($index, ['(']);
            $arrow = $open === null ? null : $tokens->getNextTokenOfKind(
                $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open),
                [[T_DOUBLE_ARROW]],
            );

            if ($arrow === null || $arrow > $last || $this->startsLine($tokens, $arrow)) {
                continue;
            }

            $static = $tokens->getPrevMeaningfulToken($index);

            $candidates[] = [
                'chain' => null,
                'end' => $this->expressionEnd($tokens, $arrow + 1),
                'kind' => 'arrow',
                'start' => $static !== null && $tokens[$static]->isGivenKind(T_STATIC)
                    ? $static
                    : $index,
            ];
        }

        return $candidates;
    }

    /**
     * @param Tokens<Token> $tokens
     *
     * @return Tokens::BLOCK_TYPE_*
     */
    private function blockType(Tokens $tokens, int $index): int
    {
        return match (true) {
            $tokens[$index]->isGivenKind(
                CT::T_ARRAY_BRACKET_OPEN,
            ) => Tokens::BLOCK_TYPE_ARRAY_BRACKET,
            $tokens[$index]->isGivenKind(
                CT::T_DESTRUCTURING_BRACKET_OPEN,
            ) => Tokens::BLOCK_TYPE_DESTRUCTURING_BRACKET,
            default => Tokens::BLOCK_TYPE_PARENTHESIS,
        };
    }

    /**
     * Puts an arrow function's body on the next line, starting with the arrow. When the
     * parameters alone are too long, they break instead and the body stays after them.
     *
     * @param Tokens<Token> $tokens
     */
    private function breakArrowFunction(Tokens $tokens, int $start, int $end, int $lineLength): void
    {
        $open = (int) $tokens->getNextTokenOfKind($start, ['(']);
        $close = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open);
        $arrow = $tokens->getNextTokenOfKind($close, [[T_DOUBLE_ARROW]]);

        if ($arrow === null) {
            return;
        }

        if ($this->lineWidth(
            $tokens,
            $start,
            (int) $tokens->getPrevMeaningfulToken($arrow),
        ) > $lineLength
            && $this->isBreakableList($tokens, $open, $close)) {
            TokenLayout::split(
                $tokens,
                $open,
                $close,
                $this->whitespacesConfig->getIndent(),
                $this->whitespacesConfig->getLineEnding(),
            );

            return;
        }

        $indent = $this->lineIndent($tokens, $start).$this->whitespacesConfig->getIndent();

        TokenLayout::reindent(
            $tokens,
            $arrow,
            $end,
            TokenLayout::indentation($tokens, $arrow),
            $indent,
        );
        $tokens->ensureWhitespaceAtIndex(
            $arrow - 1,
            1,
            $this->whitespacesConfig->getLineEnding().$indent,
        );
    }

    /**
     * Puts each operator of the expression's lowest precedence at the start of a line. An
     * expression that fills a condition or grouping parentheses also moves onto its own lines,
     * with the closing parenthesis on the last one, as PSR-12 lays out conditions.
     *
     * @param Tokens<Token> $tokens
     */
    private function breakExpression(Tokens $tokens, int $start, int $end): void
    {
        $operators = $this->lowestOperators($tokens, $start, $end);

        if ($operators === null) {
            return;
        }

        $lineEnding = $this->whitespacesConfig->getLineEnding();
        $open = $tokens->getPrevMeaningfulToken($start);
        $close = $tokens->getNextMeaningfulToken($end);

        if ($open !== null && $close !== null && $this->isGroupingParenthesis($tokens, $open)
            && $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open) === $close
            && ! $this->startsLine($tokens, $start)) {
            $outer = $this->lineIndent($tokens, $open);
            $indent = $outer.$this->whitespacesConfig->getIndent();

            TokenLayout::reindent($tokens, $start, $end, $outer, $indent);
            $tokens->ensureWhitespaceAtIndex($close - 1, 1, $lineEnding.$outer);

            foreach (array_reverse($operators) as $operator) {
                $tokens->ensureWhitespaceAtIndex($operator - 1, 1, $lineEnding.$indent);
            }

            $tokens->ensureWhitespaceAtIndex($start - 1, 1, $lineEnding.$indent);

            return;
        }

        $indent = $this->lineIndent($tokens, $start).$this->whitespacesConfig->getIndent();
        $operands = [...$operators, $end + 1];

        // Indent every operand before the line breaks move the indexes.
        foreach ($operators as $position => $operator) {
            if (! $this->startsLine($tokens, $operator)) {
                TokenLayout::reindent(
                    $tokens,
                    $operator,
                    (int) $tokens->getPrevMeaningfulToken($operands[$position + 1]),
                    TokenLayout::indentation($tokens, $operator),
                    $indent,
                );
            }
        }

        foreach (array_reverse($operators) as $operator) {
            if (! $this->startsLine($tokens, $operator)) {
                $tokens->ensureWhitespaceAtIndex($operator - 1, 1, $lineEnding.$indent);
            }
        }
    }

    /**
     * Breaks one construct that starts on the line, when the line is too long or holds an
     * associative array with inline items around a multiline value. The outermost construct
     * breaks first, and of outermost siblings the last one, so a short call before a long one
     * keeps its arguments inline.
     *
     * @param Tokens<Token> $tokens
     */
    private function breakLine(Tokens $tokens, int $first, int $last, int $lineLength): bool
    {
        $long = $this->lineWidth($tokens, $first, $last) > $lineLength;
        $candidates = [];

        if ($long) {
            foreach ($this->chainsOnLine($tokens, $first, $last) as $chain) {
                if (count($chain['calls']) >= 3
                    && $this->isSingleLine($tokens, $chain['root'], $chain['end'])) {
                    $candidates[] = [
                        'chain' => $chain,
                        'end' => $chain['end'],
                        'kind' => 'chain',
                        'start' => $chain['start'],
                    ];
                }
            }
        }

        for ($index = $first; $index <= $last; ++$index) {
            $end = $this->listEnd($tokens, $index);

            // A line that fits only breaks lists that continue onto later lines with a line
            // comment, or associative arrays with a multiline value.
            if ($end === null
                || (! $long && ($end <= $last
                    || (! $this->hasLineComment($tokens, $index, $end)
                        && ! $this->hasInlineItemsAroundMultilineValue($tokens, $index, $end))))
                || ! $this->isBreakableList($tokens, $index, $end)
                || $this->testClosure($tokens, $index) !== null) {
                continue;
            }

            $candidates[] = ['chain' => null, 'end' => $end, 'kind' => 'list', 'start' => $index];
        }

        if ($long) {
            array_push(
                $candidates,
                ...$this->arrowFunctionsOnLine($tokens, $first, $last),
                ...$this->expressionsOnLine($tokens, $first, $last),
            );
        }

        // A test's arrow function stays on the test's line, like its title.
        for ($index = $first; $index <= $last; ++$index) {
            $closure = $tokens[$index]->equals('(') ? $this->testClosure($tokens, $index) : null;

            if ($closure !== null && $closure['arrow']) {
                $candidates = array_filter(
                    $candidates,
                    static fn (array $candidate): bool => $candidate['start'] < $closure['start']
                        || $candidate['start'] > $closure['end'],
                );
            }
        }

        $selected = null;

        foreach ($candidates as $candidate) {
            foreach ($candidates as $outer) {
                if ($outer !== $candidate
                    && $outer['start'] <= $candidate['start']
                    && $candidate['end'] <= $outer['end']) {
                    continue 2;
                }
            }

            if ($selected === null || $candidate['start'] > $selected['start']) {
                $selected = $candidate;
            }
        }

        if ($selected === null) {
            return false;
        }

        if ($selected['chain'] !== null) {
            $this->splitChain($tokens, $selected['chain']);

            return true;
        }

        if ($selected['kind'] === 'arrow') {
            $this->breakArrowFunction($tokens, $selected['start'], $selected['end'], $lineLength);

            return true;
        }

        if ($selected['kind'] === 'expression') {
            $this->breakExpression($tokens, $selected['start'], $selected['end']);

            return true;
        }

        $hugged = $this->huggedArray(
            $tokens,
            $first,
            $selected['start'],
            $selected['end'],
            $lineLength,
        );

        if ($hugged !== null) {
            $selected = [
                'chain' => null,
                'end' => $hugged['end'],
                'kind' => 'list',
                'start' => $hugged['start'],
            ];
        }

        TokenLayout::split(
            $tokens,
            $selected['start'],
            $selected['end'],
            $this->whitespacesConfig->getIndent(),
            $this->whitespacesConfig->getLineEnding(),
        );
        $this->joinBodyBrace($tokens, $selected['start']);

        return true;
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

    /**
     * @param Tokens<Token> $tokens
     *
     * @return list<array{
     *     breaks: list<int>,
     *     calls: list<array{end: int, open: int, operator: int}>,
     *     clean: bool,
     *     end: int,
     *     members: list<int>,
     *     root: int,
     *     start: int
     * }>
     */
    private function chains(Tokens $tokens): array
    {
        $chains = [];
        $claimed = [];

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

            $root = $tokens->getPrevMeaningfulToken($index);
            $chain = $this->withoutStaticTail($tokens, $chain);

            if ($chain === null || $chain['calls'] === [] || $root === null) {
                continue;
            }

            $breaks = [];

            foreach ($chain['members'] as $member) {
                if ($tokens[$member - 1]->isWhitespace()
                    && str_contains($tokens[$member - 1]->getContent(), "\n")) {
                    $breaks[] = $member - 1;
                }
            }

            $start = $this->rootStart($tokens, $root);
            $clean = true;

            for ($part = $start; $part <= $chain['end']; ++$part) {
                if ($tokens[$part]->isComment()) {
                    $clean = false;

                    break;
                }
            }

            $chains[] = [
                'breaks' => $breaks,
                'clean' => $clean,
                'root' => $root,
                'start' => $start,
                ...$chain,
            ];
        }

        return $chains;
    }

    /**
     * Finds the chains whose root is on the line, outside strings.
     *
     * @param Tokens<Token> $tokens
     *
     * @return list<array{
     *     calls: list<array{end: int, open: int, operator: int}>,
     *     end: int,
     *     members: list<int>,
     *     root: int,
     *     start: int
     * }>
     */
    private function chainsOnLine(Tokens $tokens, int $first, int $last): array
    {
        $chains = [];
        $claimed = [];
        $string = $tokens[$first]->isGivenKind(T_ENCAPSED_AND_WHITESPACE);

        for ($index = $first; $index <= $last; ++$index) {
            $token = $tokens[$index];

            if ($token->equalsAny(['"', '`'])) {
                $string = ! $string;

                continue;
            }

            if ($token->isGivenKind([T_END_HEREDOC, T_START_HEREDOC])) {
                $string = $token->isGivenKind(T_START_HEREDOC);

                continue;
            }

            if ($string || isset($claimed[$index]) || ! $this->isOperator($token)) {
                continue;
            }

            $chain = $this->chain($tokens, $index);

            foreach ($chain['members'] as $member) {
                $claimed[$member] = true;
            }

            $root = $tokens->getPrevMeaningfulToken($index);
            $chain = $this->withoutStaticTail($tokens, $chain);

            if ($chain === null || $chain['calls'] === [] || $root === null || $root < $first) {
                continue;
            }

            $chains[] = ['root' => $root, 'start' => $this->rootStart($tokens, $root), ...$chain];
        }

        return $chains;
    }

    /**
     * @param array{end: int, root: int, start: int} $parent
     * @param array{end: int, root: int, start: int} $chain
     */
    private function contains(array $parent, array $chain): bool
    {
        return $parent['root'] !== $chain['root']
            && $parent['start'] <= $chain['start']
            && $chain['end'] <= $parent['end'];
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function enclosingParenthesis(Tokens $tokens, int $index): null|int
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $block = Tokens::detectBlockType($tokens[$cursor]);

            if ($block === null) {
                continue;
            }

            if (! $block['isStart']) {
                $cursor = $tokens->findBlockStart($block['type'], $cursor);

                continue;
            }

            return $tokens[$cursor]->equals('(') ? $cursor : null;
        }

        return null;
    }

    /**
     * Finds the end of the expression that starts at $index: the last token before a comma,
     * semicolon, arrow, assignment, closing bracket or a colon that no ternary opened.
     *
     * @param Tokens<Token> $tokens
     */
    private function expressionEnd(Tokens $tokens, int $index): int
    {
        $end = $index;
        $ternaries = 0;
        $counter = $tokens->count();

        for ($cursor = $index; $cursor < $counter; ++$cursor) {
            $token = $tokens[$cursor];

            if ($token->isWhitespace() || $token->isComment()) {
                continue;
            }

            if ($token->isGivenKind(T_START_HEREDOC) || $token->equalsAny(['"', '`'])) {
                $end = $this->stringEnd($tokens, $cursor);
                $cursor = $end;

                continue;
            }

            if ($token->isGivenKind(T_FN)) {
                $open = $tokens->getNextTokenOfKind($cursor, ['(']);
                $arrow = $open === null ? null : $tokens->getNextTokenOfKind(
                    $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open),
                    [[T_DOUBLE_ARROW]],
                );

                if ($arrow !== null) {
                    $end = $arrow;
                    $cursor = $arrow;

                    continue;
                }
            }

            $block = Tokens::detectBlockType($token);

            if ($block !== null) {
                if (! $block['isStart']) {
                    break;
                }

                $end = $tokens->findBlockEnd($block['type'], $cursor);
                $cursor = $end;

                continue;
            }

            if ($token->equals('?')) {
                ++$ternaries;
            } elseif ($token->equals(':')) {
                if ($ternaries === 0) {
                    break;
                }

                --$ternaries;
            } elseif ($this->isExpressionDelimiter($token)) {
                break;
            }

            $end = $cursor;
        }

        return $end;
    }

    /**
     * Finds the expressions with an operator on the line that can break.
     *
     * @param Tokens<Token> $tokens
     *
     * @return list<array{chain: null, end: int, kind: 'expression', start: int}>
     */
    private function expressionsOnLine(Tokens $tokens, int $first, int $last): array
    {
        $candidates = [];
        $string = $tokens[$first]->isGivenKind(T_ENCAPSED_AND_WHITESPACE);

        for ($index = $first; $index <= $last; ++$index) {
            $token = $tokens[$index];

            if ($token->equalsAny(['"', '`'])) {
                $string = ! $string;

                continue;
            }

            if ($token->isGivenKind([T_END_HEREDOC, T_START_HEREDOC])) {
                $string = $token->isGivenKind(T_START_HEREDOC);

                continue;
            }

            if (
                $string
                || ! in_array($this->precedence($token), self::BREAKABLE_PRECEDENCES, true)
            ) {
                continue;
            }

            $start = $this->expressionStart($tokens, $index);
            $end = $this->expressionEnd($tokens, $start);

            if ($end < $index) {
                continue;
            }

            $expression = $this->expressionToBreak($tokens, $start, $end, $first, $last);

            if ($expression !== null && ! isset($candidates[$expression['start']])) {
                $candidates[$expression['start']] = [
                    'chain' => null,
                    'end' => $expression['end'],
                    'kind' => 'expression',
                    'start' => $expression['start'],
                ];
            }
        }

        return array_values($candidates);
    }

    /**
     * Finds the start of the expression around the operator at $index.
     *
     * @param Tokens<Token> $tokens
     */
    private function expressionStart(Tokens $tokens, int $index): int
    {
        $start = $index;

        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $token = $tokens[$cursor];

            if ($token->isWhitespace() || $token->isComment()) {
                continue;
            }

            if ($token->isGivenKind(T_END_HEREDOC)) {
                $start = (int) $tokens->getPrevTokenOfKind($cursor, [[T_START_HEREDOC]]);
                $cursor = (int) $tokens->getPrevTokenOfKind($cursor, [[T_START_HEREDOC]]);

                continue;
            }

            if ($token->equalsAny(['"', '`'])) {
                $start = (int) $tokens->getPrevTokenOfKind(
                    $cursor,
                    [$token->getContent()],
                );
                $cursor = (int) $tokens->getPrevTokenOfKind(
                    $cursor,
                    [$token->getContent()],
                );

                continue;
            }

            $block = Tokens::detectBlockType($token);

            // Braces that close a statement block end the search, unlike parentheses and brackets.
            if ($block !== null) {
                if ($block['isStart'] || $block['type'] === Tokens::BLOCK_TYPE_BRACE) {
                    break;
                }

                $start = $tokens->findBlockStart($block['type'], $cursor);
                $cursor = $start;

                continue;
            }

            if ($this->isExpressionDelimiter($token) || $token->isGivenKind([
                T_CASE,
                T_ECHO,
                T_INCLUDE,
                T_INCLUDE_ONCE,
                T_PRINT,
                T_REQUIRE,
                T_REQUIRE_ONCE,
                T_RETURN,
                T_THROW,
                T_YIELD,
                T_YIELD_FROM,
            ])) {
                break;
            }

            $start = $cursor;
        }

        return $start;
    }

    /**
     * Finds the part of the expression to break: the expression itself, or, when its lowest
     * operators already start lines, its operand on the line.
     *
     * @param Tokens<Token> $tokens
     *
     * @return null|array{end: int, start: int}
     */
    private function expressionToBreak(
        Tokens $tokens,
        int $start,
        int $end,
        int $first,
        int $last,
    ): null|array {
        while (($operators = $this->lowestOperators($tokens, $start, $end)) !== null) {
            foreach ($operators as $operator) {
                if (! $this->startsLine($tokens, $operator)) {
                    return ['end' => $end, 'start' => $start];
                }
            }

            $boundaries = [$start - 1, ...$operators, $end + 1];
            $operand = null;

            foreach (array_keys($operators) as $position) {
                $operandStart = $tokens->getNextMeaningfulToken($boundaries[$position + 1]);
                $operandEnd = $tokens->getPrevMeaningfulToken($boundaries[$position + 2]);

                if ($operandStart !== null && $operandEnd !== null
                    && $operandStart <= $last && $operandEnd >= $first) {
                    $operand = ['end' => $operandEnd, 'start' => $operandStart];
                }
            }

            if ($operand === null) {
                return null;
            }

            ['end' => $end, 'start' => $start] = $operand;
        }

        return null;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function hasInlineItemsAroundMultilineValue(Tokens $tokens, int $start, int $end): bool
    {
        if (! $tokens[$start]->isGivenKind(CT::T_ARRAY_BRACKET_OPEN)) {
            $previous = $tokens->getPrevMeaningfulToken($start);

            if ($previous === null || ! $tokens[$previous]->isGivenKind(T_ARRAY)) {
                return false;
            }
        }

        foreach (TokenLayout::topLevelTokens($tokens, $start, $end) as $item) {
            // Heredocs and multiline strings keep their layout.
            if (! $tokens[$item]->isWhitespace()
                && str_contains($tokens[$item]->getContent(), "\n")) {
                return false;
            }
        }

        return $this->isAssociative($tokens, $start, $end)
            && str_contains($tokens->generatePartialCode($start, $end), "\n");
    }

    /**
     * Whether the list has a line comment between its items, which puts it on several lines.
     *
     * @param Tokens<Token> $tokens
     */
    private function hasLineComment(Tokens $tokens, int $start, int $end): bool
    {
        return array_any(
            TokenLayout::topLevelTokens($tokens, $start, $end),
            fn (int $index): bool => $this->isLineComment($tokens[$index]),
        );
    }

    /**
     * Finds the array to break instead of the list around it: the only argument of a call, the
     * last associative argument of a call, or the only associative item of a list, when the line
     * fits up to the array's opening bracket.
     *
     * @param Tokens<Token> $tokens
     *
     * @return null|array{end: int, start: int}
     */
    private function huggedArray(
        Tokens $tokens,
        int $first,
        int $start,
        int $end,
        int $lineLength,
    ): null|array {
        $last = $tokens->getPrevMeaningfulToken($end);

        if ($last !== null && $tokens[$last]->equals(',')) {
            $last = $tokens->getPrevMeaningfulToken($last);
        }

        if ($last === null || ! $tokens[$last]->isGivenKind(CT::T_ARRAY_BRACKET_CLOSE)
            && (! $tokens[$last]->equals(')') || ! $this->isLongArray(
                $tokens,
                $tokens->findBlockStart(
                    Tokens::BLOCK_TYPE_PARENTHESIS,
                    $last,
                ),
            ))) {
            return null;
        }

        $open = $tokens[$last]->isGivenKind(CT::T_ARRAY_BRACKET_CLOSE)
            ? $tokens->findBlockStart(Tokens::BLOCK_TYPE_ARRAY_BRACKET, $last)
            : $tokens->findBlockStart(Tokens::BLOCK_TYPE_PARENTHESIS, $last);
        $itemStart = $tokens->getPrevMeaningfulToken($open);

        if ($itemStart !== null && $tokens[$itemStart]->isGivenKind(T_ARRAY)) {
            $itemStart = $tokens->getPrevMeaningfulToken($itemStart);
        }

        // Positional arrays stay compact unless they are a call's only argument.
        $sole = $itemStart === $start;
        $call = $tokens[$start]->equals('(') && ! $this->isParameterList($tokens, $start);

        if ($itemStart === null || ! $tokens[$itemStart]->equalsAny([
            ',',
            '(',
            [CT::T_ARRAY_BRACKET_OPEN],
        ])
            || (! $sole && ! $call)
            || ((! $sole || ! $call) && ! $this->isAssociative($tokens, $open, $last))
            || ! $this->isBreakableList($tokens, $open, $last)
            || $this->lineWidth($tokens, $first, $open) > $lineLength) {
            return null;
        }

        return ['end' => $last, 'start' => $open];
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function isAssociative(Tokens $tokens, int $start, int $end): bool
    {
        $arrowFunction = false;

        foreach (TokenLayout::topLevelTokens($tokens, $start, $end) as $item) {
            if ($tokens[$item]->isGivenKind(T_FN)) {
                $arrowFunction = true;
            } elseif ($tokens[$item]->isGivenKind(T_DOUBLE_ARROW)) {
                if (! $arrowFunction) {
                    return true;
                }

                $arrowFunction = false;
            }
        }

        return false;
    }

    /**
     * Whether the list has items to move onto their own lines.
     *
     * @param Tokens<Token> $tokens
     */
    private function isBreakableList(Tokens $tokens, int $start, int $end): bool
    {
        $inline = false;

        foreach ([$start, ...TokenLayout::topLevelTokens($tokens, $start, $end)] as $index) {
            if ($index !== $start && ! $tokens[$index]->equals(',')) {
                continue;
            }

            $next = $tokens->getNextMeaningfulToken($index);

            if ($next !== null && $next < $end
                && ! str_contains($tokens->generatePartialCode($index + 1, $next), "\n")) {
                $inline = true;
            }
        }

        return $inline;
    }

    private function isExpressionDelimiter(Token $token): bool
    {
        return $token->equalsAny([',', ';', '=', [T_CLOSE_TAG], [T_OPEN_TAG]])
            || $token->isGivenKind([
                T_AND_EQUAL,
                T_AS,
                T_COALESCE_EQUAL,
                T_CONCAT_EQUAL,
                T_DIV_EQUAL,
                T_DOUBLE_ARROW,
                T_MINUS_EQUAL,
                T_MOD_EQUAL,
                T_MUL_EQUAL,
                T_OR_EQUAL,
                T_PLUS_EQUAL,
                T_POW_EQUAL,
                T_SL_EQUAL,
                T_SR_EQUAL,
                T_XOR_EQUAL,
            ]);
    }

    /**
     * Whether the parenthesis holds a condition or groups an expression, rather than arguments
     * or parameters.
     *
     * @param Tokens<Token> $tokens
     */
    private function isGroupingParenthesis(Tokens $tokens, int $open): bool
    {
        if (! $tokens[$open]->equals('(')) {
            return false;
        }

        $previous = $tokens->getPrevMeaningfulToken($open);

        if ($previous === null) {
            return true;
        }

        if ($tokens[$previous]->isGivenKind([T_ELSEIF, T_IF, T_MATCH, T_SWITCH, T_WHILE])) {
            return true;
        }

        return $this->listEnd($tokens, $open) === null && ! $tokens[$previous]->isGivenKind([
            CT::T_USE_LAMBDA,
            T_CATCH,
            T_DECLARE,
            T_EMPTY,
            T_EVAL,
            T_EXIT,
            T_FN,
            T_FOR,
            T_FOREACH,
            T_ISSET,
            T_NEW,
            T_UNSET,
        ]);
    }

    private function isLineComment(Token $token): bool
    {
        return $token->isGivenKind(T_COMMENT)
            && (str_starts_with($token->getContent(), '//')
                || str_starts_with($token->getContent(), '#'));
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function isLongArray(Tokens $tokens, int $open): bool
    {
        $previous = $tokens->getPrevMeaningfulToken($open);

        return $previous !== null && $tokens[$previous]->isGivenKind(T_ARRAY);
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
    private function isParameterList(Tokens $tokens, int $open): bool
    {
        $previous = $tokens->getPrevMeaningfulToken($open);

        if ($previous === null) {
            return false;
        }

        if ($tokens[$previous]->isGivenKind([CT::T_USE_LAMBDA, T_FUNCTION])) {
            return true;
        }

        $function = $tokens->getPrevMeaningfulToken($previous);

        if ($function !== null && $tokens[$function]->isGivenKind(CT::T_RETURN_REF)) {
            $function = $tokens->getPrevMeaningfulToken($function);
        }

        return $tokens[$previous]->isGivenKind(T_STRING)
            && $function !== null
            && $tokens[$function]->isGivenKind(T_FUNCTION);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function isSingleLine(Tokens $tokens, int $start, int $end): bool
    {
        for ($part = $start; $part <= $end; ++$part) {
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
                $part = $tokens->findBlockEnd($arrayType, $arrayStart);

                continue;
            }

            if (str_contains($tokens[$part]->getContent(), "\n")) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function joinArgumentComments(
        Tokens $tokens,
        int $start,
        int $end,
        int $lineLength,
    ): void {
        foreach (TokenLayout::topLevelTokens($tokens, $start, $end) as $index) {
            $comment = $tokens[$index];

            if (! $comment->isGivenKind(T_COMMENT)
                || ! str_starts_with($comment->getContent(), '/*')
                || str_contains($comment->getContent(), "\n")) {
                continue;
            }

            $previous = $tokens->getPrevNonWhitespace($index);
            $next = $index + 1;

            if ($previous === null || ! $tokens[$previous]->equalsAny(['(', ','])
                || ! $tokens[$next]->isWhitespace()
                || ! str_contains($tokens[$next]->getContent(), "\n")
                || $tokens[$next + 1]->isComment()
                || $tokens[$next + 1]->equalsAny([',', ')'])) {
                continue;
            }

            $whitespace = $tokens[$next];
            $tokens[$next] = new Token([T_WHITESPACE, ' ']);
            $line = $index;

            while ($line < $tokens->count() - 1
                && ! str_contains($tokens[$line]->getContent(), "\n")) {
                ++$line;
            }

            if ($this->lineWidth($tokens, $index, $line) > $lineLength) {
                $tokens[$next] = $whitespace;
            }
        }
    }

    /**
     * Puts a function body's opening brace after the closing parenthesis of its multiline
     * parameters, as PSR-12 requires.
     *
     * @param Tokens<Token> $tokens
     */
    private function joinBodyBrace(Tokens $tokens, int $start): void
    {
        if (! $tokens[$start]->equals('(')) {
            return;
        }

        $end = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $start);
        $name = $tokens->getPrevMeaningfulToken($start);
        $function = $name === null ? null : $tokens->getPrevMeaningfulToken($name);

        if ($function !== null && $tokens[$function]->isGivenKind(CT::T_RETURN_REF)) {
            $function = $tokens->getPrevMeaningfulToken($function);
        }

        if ($name === null || $function === null || ! $tokens[$name]->isGivenKind(T_STRING)
            || ! $tokens[$function]->isGivenKind(T_FUNCTION)) {
            return;
        }

        $brace = $tokens->getNextTokenOfKind($end, ['{', ';']);

        if ($brace === null || ! $tokens[$brace]->equals('{')
            || ! $tokens[$brace - 1]->isWhitespace()
            || ! str_contains($tokens[$brace - 1]->getContent(), "\n")
            || $tokens[$tokens->getPrevNonWhitespace($brace) ?? $brace]->isComment()) {
            return;
        }

        $tokens[$brace - 1] = new Token([T_WHITESPACE, ' ']);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function joinChains(Tokens $tokens, int $lineLength): void
    {
        $chains = $this->chains($tokens);
        $breaks = [];

        foreach ($chains as $chain) {
            if (! $chain['clean'] || $chain['breaks'] === []) {
                continue;
            }

            $joinable = array_flip($chain['breaks']);

            foreach ($chains as $nested) {
                if ($nested['clean'] && $this->contains($chain, $nested)) {
                    $joinable += array_flip($nested['breaks']);
                }
            }

            // Arguments and other code that span lines keep the chain on separate lines.
            for ($index = $chain['root']; $index <= $chain['end']; ++$index) {
                if (! isset($joinable[$index])
                    && str_contains($tokens[$index]->getContent(), "\n")) {
                    continue 2;
                }
            }

            if ($this->joinedLineWidth($tokens, $chain, $chains) <= $lineLength) {
                array_push($breaks, ...$chain['breaks']);
            }
        }

        foreach ($breaks as $index) {
            $tokens->clearAt($index);
        }

        $tokens->clearEmptyTokens();
    }

    /**
     * Measures the chain's line as if its own, sibling and nested chains were inline, ignoring a
     * trailing line comment.
     *
     * @param Tokens<Token> $tokens
     * @param array{breaks: list<int>, clean: bool, end: int, root: int, start: int} $chain
     * @param list<array{breaks: list<int>, clean: bool, end: int, root: int, start: int}> $chains
     */
    private function joinedLineWidth(Tokens $tokens, array $chain, array $chains): int
    {
        $joined = [];

        foreach ($chains as $other) {
            if ($other['clean'] && ! $this->contains($other, $chain)) {
                $joined += array_flip($other['breaks']);
            }
        }

        $line = '';

        for ($index = $chain['root'] - 1; $index >= 0; --$index) {
            if (isset($joined[$index])) {
                continue;
            }

            $line = $tokens[$index]->getContent().$line;
            $newline = mb_strrpos($line, "\n");

            if ($newline !== false) {
                $line = mb_substr($line, $newline + 1);

                break;
            }
        }

        for ($index = $chain['root']; $index < $tokens->count(); ++$index) {
            if (isset($joined[$index])) {
                continue;
            }

            $content = $tokens[$index]->getContent();

            if ($index > $chain['end'] && $this->isLineComment($tokens[$index])) {
                break;
            }

            $newline = mb_strpos($content, "\n");
            $line .= $newline === false ? $content : mb_substr($content, 0, $newline);

            if ($newline !== false) {
                break;
            }
        }

        return mb_strlen(mb_rtrim($line, " \t\r"));
    }

    /**
     * Joins test calls whose title and closure were split onto their own lines.
     *
     * @param Tokens<Token> $tokens
     */
    private function joinTestCalls(Tokens $tokens): void
    {
        for ($open = 0; $open < $tokens->count(); ++$open) {
            $closure = $tokens[$open]->equals('(') ? $this->testClosure($tokens, $open) : null;

            if ($closure === null) {
                continue;
            }

            $title = $tokens->getNextMeaningfulToken($open);
            $close = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open);
            $gap = $closure['start'] - 1;

            // Comments between the arguments keep their lines.
            if ($title === null || $gap !== $title + 2
                || ! $tokens[$gap]->isWhitespace()
                || ! str_contains($tokens[$gap]->getContent(), "\n")) {
                continue;
            }

            for ($index = $open + 1; $index < $close; ++$index) {
                if (($index < $title || $index > $closure['end'])
                    && ! $tokens[$index]->isWhitespace() && ! $tokens[$index]->equals(',')) {
                    continue 2;
                }
            }

            TokenLayout::reindent(
                $tokens,
                $closure['start'],
                $closure['end'],
                TokenLayout::indentation($tokens, $closure['start']),
                $this->lineIndent($tokens, $open),
            );
            $tokens[$gap] = new Token([T_WHITESPACE, ' ']);

            for ($index = $open + 1; $index < $title; ++$index) {
                $tokens->clearAt($index);
            }

            for ($index = $closure['end'] + 1; $index < $close; ++$index) {
                $tokens->clearAt($index);
            }
        }

        $tokens->clearEmptyTokens();
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

            if (str_contains($content, "\n")) {
                break;
            }
        }

        $line = mb_substr($line, (int) mb_strrpos($line, "\n") + 1);

        return mb_substr($line, 0, strspn($line, " \t"));
    }

    /**
     * Measures the code on the line from $first to $last, without a trailing line comment.
     *
     * @param Tokens<Token> $tokens
     */
    private function lineWidth(Tokens $tokens, int $first, int $last): int
    {
        $line = '';

        for ($index = $first - 1; $index >= 0; --$index) {
            $content = $tokens[$index]->getContent();
            $newline = mb_strrpos($content, "\n");

            if ($newline !== false) {
                $line = mb_substr($content, $newline + 1).$line;

                break;
            }

            $line = $content.$line;
        }

        for ($index = $first; $index <= $last; ++$index) {
            if ($this->isLineComment($tokens[$index])) {
                break;
            }

            $content = $tokens[$index]->getContent();
            $newline = mb_strpos($content, "\n");
            $line .= $newline === false ? $content : mb_substr($content, 0, $newline);

            if ($newline !== false) {
                break;
            }
        }

        return mb_strlen(mb_rtrim($line, " \t\r"));
    }

    /**
     * Finds the end of an argument, parameter, array, closure use or destructuring list. Arrow
     * function parameters are left alone, since a long arrow function is long in its body.
     *
     * @param Tokens<Token> $tokens
     */
    private function listEnd(Tokens $tokens, int $index): null|int
    {
        $token = $tokens[$index];

        if ($token->isGivenKind([CT::T_ARRAY_BRACKET_OPEN, CT::T_DESTRUCTURING_BRACKET_OPEN])) {
            return $tokens->findBlockEnd($this->blockType($tokens, $index), $index);
        }

        if (! $token->equals('(')) {
            return null;
        }

        $previous = $tokens->getPrevMeaningfulToken($index);

        if ($previous === null || ! $tokens[$previous]->isGivenKind([
            CT::T_DYNAMIC_PROP_BRACE_CLOSE,
            CT::T_RETURN_REF,
            CT::T_USE_LAMBDA,
            T_ARRAY,
            T_CLASS,
            T_FUNCTION,
            T_LIST,
            T_STATIC,
            T_STRING,
            T_VARIABLE,
        ]) && ! $tokens[$previous]->equalsAny([')', ']'])) {
            return null;
        }

        return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $index);
    }

    /**
     * Finds the operators of the lowest precedence between $start and $end, outside nested
     * code, when they are operators that break lines.
     *
     * @param Tokens<Token> $tokens
     *
     * @return null|non-empty-list<int>
     */
    private function lowestOperators(Tokens $tokens, int $start, int $end): null|array
    {
        $operators = [];

        foreach (TokenLayout::topLevelTokens($tokens, $start - 1, $end + 1) as $index) {
            if ($index === $start) {
                continue;
            }

            $precedence = $this->precedence($tokens[$index]);

            if ($precedence !== null) {
                $operators[$index] = $precedence;
            }
        }

        if ($operators === []) {
            return null;
        }

        $lowest = min($operators);

        if (! in_array($lowest, self::BREAKABLE_PRECEDENCES, true)) {
            return null;
        }

        $group = [];

        foreach ($operators as $index => $precedence) {
            $previous = $tokens->getPrevMeaningfulToken($index);

            // The colon of a short ternary stays with its question mark.
            if ($precedence === $lowest && ($previous === null || ! $tokens[$index]->equals(':')
                || ! $tokens[$previous]->equals('?'))) {
                $group[] = $index;
            }
        }

        return $group === [] ? null : $group;
    }

    /**
     * Ranks the binary operators by PHP precedence, lowest first. Other operators bind tighter
     * than all of these.
     */
    private function precedence(Token $token): null|int
    {
        return match (true) {
            $token->isGivenKind(T_LOGICAL_OR) => 1,
            $token->isGivenKind(T_LOGICAL_XOR) => 2,
            $token->isGivenKind(T_LOGICAL_AND) => 3,
            $token->equalsAny(['?', ':']) => 4,
            $token->isGivenKind(T_COALESCE) => 5,
            $token->isGivenKind(T_BOOLEAN_OR) => 6,
            $token->isGivenKind(T_BOOLEAN_AND) => 7,
            $token->equals('|') => 8,
            $token->equals('^') => 9,
            $token->isGivenKind([
                T_IS_EQUAL,
                T_IS_IDENTICAL,
                T_IS_NOT_EQUAL,
                T_IS_NOT_IDENTICAL,
                T_SPACESHIP,
            ]) => 10,
            $token->isGivenKind([T_IS_GREATER_OR_EQUAL, T_IS_SMALLER_OR_EQUAL])
                || $token->equalsAny(['<', '>']) => 11,
            $token->equals('.') => 12,
            default => null,
        };
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function rootStart(Tokens $tokens, int $root): int
    {
        $start = $root;
        $block = Tokens::detectBlockType($tokens[$start]);

        while ($block !== null && ! $block['isStart']) {
            $start = $tokens->findBlockStart($block['type'], $start);
            $previous = $tokens->getPrevMeaningfulToken($start);

            if ($previous === null || ! $tokens[$previous]->isGivenKind([
                T_STRING,
                T_VARIABLE,
            ]) && ! $tokens[$previous]->equalsAny([')', ']'])) {
                break;
            }

            $start = $previous;
            $block = Tokens::detectBlockType($tokens[$start]);
        }

        return $start;
    }

    /**
     * @param Tokens<Token> $tokens
     * @param array{calls: list<array{end: int, open: int, operator: int}>, root: int} $chain
     */
    private function splitChain(Tokens $tokens, array $chain): void
    {
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

        $edits = [];
        $statementStart = $this->statementStart($tokens, $chain['root'], $forHeaders);

        if ($statementStart !== null) {
            $edits[$statementStart] = $this->whitespacesConfig->getLineEnding()
                .$this->lineIndent($tokens, $statementStart);
        }

        $originalIndent = $this->lineIndent($tokens, $chain['root']);
        $indent = $originalIndent.$this->whitespacesConfig->getIndent();

        foreach ($chain['calls'] as $offset => $call) {
            // An initial static call stays on the root line.
            if ($offset === 0 && $tokens[$call['operator']]->isGivenKind(T_DOUBLE_COLON)) {
                continue;
            }

            TokenLayout::reindent(
                $tokens,
                $call['open'] + 1,
                $call['end'] - 1,
                $originalIndent,
                $indent,
            );
            $edits[$call['operator']] = $this->whitespacesConfig->getLineEnding().$indent;
        }

        krsort($edits);

        foreach ($edits as $index => $whitespace) {
            $tokens->ensureWhitespaceAtIndex($index - 1, 1, $whitespace);
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function startsLine(Tokens $tokens, int $index): bool
    {
        return $tokens[$index - 1]->isWhitespace()
            && str_contains($tokens[$index - 1]->getContent(), "\n");
    }

    /**
     * @param Tokens<Token> $tokens
     * @param array<int, int> $forHeaders
     */
    private function statementStart(Tokens $tokens, int $root, array $forHeaders): null|int
    {
        foreach ($forHeaders as $open => $close) {
            if ($open < $root && $root < $close) {
                return null;
            }
        }

        for ($index = $root; $index >= 0; --$index) {
            if (str_contains($tokens[$index]->getContent(), "\n")) {
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

    /**
     * Finds the closure of a test call such as it('title', function () {}), whose arguments stay
     * on the call's line, like test runners print them.
     *
     * @param Tokens<Token> $tokens
     *
     * @return null|array{arrow: bool, end: int, start: int}
     */
    private function testClosure(Tokens $tokens, int $open): null|array
    {
        $name = $tokens->getPrevMeaningfulToken($open);
        $before = $name === null ? null : $tokens->getPrevMeaningfulToken($name);

        if ($name === null || ! $tokens[$name]->isGivenKind(T_STRING)
            || ! in_array(
                mb_strtolower($tokens[$name]->getContent()),
                ['describe', 'it', 'test'],
                true,
            )
            || ($before !== null && ($this->isOperator($tokens[$before])
                || $tokens[$before]->isGivenKind([T_CONST, T_FUNCTION, T_NEW])))) {
            return null;
        }

        $title = $tokens->getNextMeaningfulToken($open);
        $comma = $title === null ? null : $tokens->getNextMeaningfulToken($title);
        $start = $comma === null ? null : $tokens->getNextMeaningfulToken($comma);

        if ($start === null || ! $tokens[$title]->isGivenKind(T_CONSTANT_ENCAPSED_STRING)
            || ! $tokens[$comma]->equals(',')) {
            return null;
        }

        $function = $tokens[$start]->isGivenKind(T_STATIC)
            ? $tokens->getNextMeaningfulToken($start)
            : $start;
        $close = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open);
        $end = $tokens->getPrevMeaningfulToken($close);

        if ($end !== null && $tokens[$end]->equals(',')) {
            $end = $tokens->getPrevMeaningfulToken($end);
        }

        if ($function === null || $end === null
            || ! $tokens[$function]->isGivenKind([T_FN, T_FUNCTION])) {
            return null;
        }

        foreach (TokenLayout::topLevelTokens($tokens, $comma, $end + 1) as $index) {
            if ($tokens[$index]->equals(',')) {
                return null;
            }
        }

        return [
            'arrow' => $tokens[$function]->isGivenKind(T_FN),
            'end' => $end,
            'start' => $start,
        ];
    }

    /**
     * Ends the chain before a static access that ends it, such as ->first()::class, so the calls
     * before it can break. A static access in the middle of the chain conflicts with the native
     * :: whitespace, so that chain is left alone.
     *
     * @param array{calls: list<array{end: int, open: int, operator: int}>, end: int, members: list<int>} $chain
     * @param Tokens<Token> $tokens
     *
     * @return null|array{calls: list<array{end: int, open: int, operator: int}>, end: int, members: list<int>}
     */
    private function withoutStaticTail(Tokens $tokens, array $chain): null|array
    {
        $members = $chain['members'];

        foreach ($members as $position => $member) {
            if ($position === 0 || ! $tokens[$member]->isGivenKind(T_DOUBLE_COLON)) {
                continue;
            }

            if ($position !== count($members) - 1) {
                return null;
            }

            $end = $tokens->getPrevMeaningfulToken($member);

            if ($end === null) {
                return null;
            }

            return [
                'calls' => array_values(array_filter(
                    $chain['calls'],
                    static fn (array $call): bool => $call['operator'] !== $member,
                )),
                'end' => $end,
                'members' => array_slice($members, 0, $position),
            ];
        }

        return $chain;
    }
}
