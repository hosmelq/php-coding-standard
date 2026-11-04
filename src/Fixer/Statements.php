<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @internal
 */
final class Statements
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

    private const array CONTROL_KEYWORDS = [
        T_DECLARE,
        T_ELSEIF,
        T_FOR,
        T_FOREACH,
        T_IF,
        T_SWITCH,
        T_WHILE,
    ];

    /**
     * @param Tokens<Token> $tokens
     *
     * @return array<int, int> Assignment start indices mapped to their semicolons.
     */
    public static function assignments(Tokens $tokens): array
    {
        $assignments = [];

        foreach (self::find($tokens) as $start => $statement) {
            if ($statement['subject'] === 'assignment') {
                $assignments[$start] = $statement['end'];
            }
        }

        return $assignments;
    }

    /**
     * @param Tokens<Token> $tokens
     *
     * @return array<int, array{end: int, subject: string}> Subject statements keyed by their starts.
     */
    public static function find(Tokens $tokens): array
    {
        $caseColons = self::caseColons($tokens);
        $bodies = self::bodyContexts($tokens);
        $contexts = [];
        $context = true;
        $statements = [];
        $forEnd = -1;

        foreach ($tokens as $index => $token) {
            if ($index <= $forEnd) {
                continue;
            }

            if (isset($contexts[$index])) {
                $context = $contexts[$index];

                unset($contexts[$index]);
            }

            if ($token->isGivenKind(T_FOR)) {
                $open = $tokens->getNextMeaningfulToken($index);

                if ($open !== null && $tokens[$open]->equals('(')) {
                    // Exclude even executable bodies nested inside a for header.
                    $forEnd = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open);
                }

                continue;
            }

            $block = Tokens::detectBlockType($token);

            if ($block !== null) {
                if ($block['isStart']) {
                    $contexts[$tokens->findBlockEnd($block['type'], $index)] = $context;
                    $context = $bodies[$index] ?? ($token->equals('{') && $context);
                }

                continue;
            }

            if (! $context || ! self::startsStatement($tokens, $index, $caseColons)) {
                continue;
            }

            $subject = self::subject($tokens, $index);

            if ($subject === null) {
                continue;
            }

            $end = self::statementEnd($tokens, $index);

            if ($end !== null) {
                $statements[$index] = ['end' => $end, 'subject' => $subject];
            }
        }

        return $statements;
    }

    /**
     * Class and match bodies contain declarations or expressions; function bodies contain statements.
     *
     * @param Tokens<Token> $tokens
     *
     * @return array<int, bool>
     */
    private static function bodyContexts(Tokens $tokens): array
    {
        $bodies = [];

        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(CT::T_PROPERTY_HOOK_BRACE_OPEN)) {
                $bodies += self::hookBodies($tokens, $index);

                continue;
            }

            if (! $token->isClassy() && ! $token->isGivenKind([T_FUNCTION, T_MATCH])) {
                continue;
            }

            $previous = $tokens->getPrevMeaningfulToken($index);

            if ($previous !== null && $tokens[$previous]->isGivenKind(T_DOUBLE_COLON)) {
                continue;
            }

            for ($cursor = $index + 1; $cursor < $tokens->count(); ++$cursor) {
                if ($tokens[$cursor]->equals(';')) {
                    break;
                }

                if ($tokens[$cursor]->equals('{')) {
                    $bodies[$cursor] = $token->isGivenKind(T_FUNCTION);

                    break;
                }

                $block = Tokens::detectBlockType($tokens[$cursor]);

                if ($block !== null && $block['isStart']) {
                    $cursor = $tokens->findBlockEnd($block['type'], $cursor);
                }
            }
        }

        return $bodies;
    }

    /**
     * @param Tokens<Token> $tokens
     *
     * @return array<int, true> Indices of the colons that end `case` expressions.
     */
    private static function caseColons(Tokens $tokens): array
    {
        $colons = [];

        foreach ($tokens as $case => $token) {
            if (! $token->isGivenKind(T_CASE)) {
                continue;
            }

            $ternaries = 0;

            for ($index = $case + 1; $index < $tokens->count(); ++$index) {
                $current = $tokens[$index];
                $block = Tokens::detectBlockType($current);

                if ($block !== null) {
                    if (! $block['isStart']) {
                        break;
                    }

                    $index = $tokens->findBlockEnd($block['type'], $index);

                    continue;
                }

                if ($current->equals('?')) {
                    ++$ternaries;

                    continue;
                }

                if ($current->equals(':')) {
                    if ($ternaries === 0) {
                        $colons[$index] = true;

                        break;
                    }

                    --$ternaries;

                    continue;
                }

                if ($current->equalsAny([';', [T_CLOSE_TAG]])) {
                    break;
                }
            }
        }

        return $colons;
    }

    /**
     * Classify statements such as `$$name = 1;` and `${'name'}->run();`.
     *
     * @param Tokens<Token> $tokens
     */
    private static function dynamicVariableSubject(Tokens $tokens, int $start): null|string
    {
        $index = self::variableEnd($tokens, $start);

        if ($index === null) {
            return null;
        }

        $name = $tokens->generatePartialCode($start, $index);
        $next = $tokens->getNextMeaningfulToken(self::targetEnd($tokens, $index));

        return $next !== null && $tokens[$next]->equalsAny(self::ASSIGNMENT_OPERATORS)
            ? 'assignment'
            : 'variable:'.$name;
    }

    /**
     * @param Tokens<Token> $tokens
     *
     * @return array<int, true> Indices of the property hook bodies' opening braces.
     */
    private static function hookBodies(Tokens $tokens, int $open): array
    {
        $bodies = [];
        $close = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PROPERTY_HOOK, $open);

        for ($index = $open + 1; $index < $close; ++$index) {
            $block = Tokens::detectBlockType($tokens[$index]);

            if ($block === null || ! $block['isStart']) {
                continue;
            }

            if ($tokens[$index]->equals('{')) {
                $bodies[$index] = true;
            }

            $index = $tokens->findBlockEnd($block['type'], $index);
        }

        return $bodies;
    }

    /**
     * @param Tokens<Token> $tokens
     * @param array<int, true> $caseColons
     */
    private static function opensStatements(Tokens $tokens, int $colon, array $caseColons): bool
    {
        if (isset($caseColons[$colon])) {
            return true;
        }

        $previous = $tokens->getPrevMeaningfulToken($colon);

        if ($previous === null) {
            return false;
        }

        if ($tokens[$previous]->isGivenKind([T_DEFAULT, T_ELSE])) {
            return true;
        }

        if (! $tokens[$previous]->equals(')')) {
            return false;
        }

        $open = $tokens->findBlockStart(Tokens::BLOCK_TYPE_PARENTHESIS, $previous);
        $keyword = $tokens->getPrevMeaningfulToken($open);

        return $keyword !== null && $tokens[$keyword]->isGivenKind(self::CONTROL_KEYWORDS);
    }

    /**
     * @param Tokens<Token> $tokens
     * @param array<int, true> $caseColons
     */
    private static function startsStatement(Tokens $tokens, int $index, array $caseColons): bool
    {
        $previous = $tokens->getPrevMeaningfulToken($index);

        return $previous !== null
            && (
                $tokens[$previous]->equalsAny([';', '{', '}'])
                || $tokens[$previous]->isGivenKind(T_OPEN_TAG)
                || (
                    $tokens[$previous]->equals(':')
                    && self::opensStatements($tokens, $previous, $caseColons)
                )
            );
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private static function statementEnd(Tokens $tokens, int $start): null|int
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

    /**
     * @param Tokens<Token> $tokens
     */
    private static function subject(Tokens $tokens, int $start): null|string
    {
        if ($tokens[$start]->isGivenKind(T_VARIABLE)) {
            $next = $tokens->getNextMeaningfulToken(self::targetEnd($tokens, $start));

            return $next !== null && $tokens[$next]->equalsAny(self::ASSIGNMENT_OPERATORS)
                ? 'assignment'
                : 'variable:'.$tokens[$start]->getContent();
        }

        if ($tokens[$start]->equals('$')) {
            return self::dynamicVariableSubject($tokens, $start);
        }

        if ($tokens[$start]->isGivenKind(T_NEW)) {
            return 'new';
        }

        if (! $tokens[$start]->isGivenKind([
            CT::T_NAMESPACE_OPERATOR,
            T_NS_SEPARATOR,
            T_STATIC,
            T_STRING,
        ])) {
            return null;
        }

        $next = $tokens->getNextMeaningfulToken($start);

        if (
            $tokens[$start]->isGivenKind(CT::T_NAMESPACE_OPERATOR)
            && ($next === null || ! $tokens[$next]->isGivenKind(T_NS_SEPARATOR))
        ) {
            return null;
        }

        while ($next !== null && $tokens[$next]->isGivenKind([T_STRING, T_NS_SEPARATOR])) {
            $next = $tokens->getNextMeaningfulToken($next);
        }

        if ($next !== null && $tokens[$next]->isGivenKind(T_DOUBLE_COLON)) {
            $property = $tokens->getNextMeaningfulToken($next);
            $property = $property === null ? null : self::variableEnd($tokens, $property);

            if ($property !== null) {
                $operator = $tokens->getNextMeaningfulToken(self::targetEnd($tokens, $property));

                if (
                    $operator !== null
                    && $tokens[$operator]->equalsAny(self::ASSIGNMENT_OPERATORS)
                ) {
                    return 'assignment';
                }
            }

            return 'static';
        }

        return $next !== null && $tokens[$next]->equals('(')
            && ! $tokens[$start]->isGivenKind(T_STATIC) ? 'function' : null;
    }

    /**
     * Skip the calls, array offsets, and properties of the assigned variable.
     *
     * @param Tokens<Token> $tokens
     */
    private static function targetEnd(Tokens $tokens, int $variable): int
    {
        $index = $variable;

        while (($next = $tokens->getNextMeaningfulToken($index)) !== null) {
            $block = Tokens::detectBlockType($tokens[$next]);

            if ($block !== null && $block['isStart'] && in_array($block['type'], [
                Tokens::BLOCK_TYPE_INDEX_BRACE,
                Tokens::BLOCK_TYPE_INDEX_BRACKET,
                Tokens::BLOCK_TYPE_PARENTHESIS,
            ], true)) {
                $index = $tokens->findBlockEnd($block['type'], $next);

                continue;
            }

            $name = $tokens[$next]->isGivenKind([T_DOUBLE_COLON, T_OBJECT_OPERATOR])
                ? $tokens->getNextMeaningfulToken($next)
                : null;

            if ($name !== null && $tokens[$name]->isGivenKind(CT::T_DYNAMIC_PROP_BRACE_OPEN)) {
                $index = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_DYNAMIC_PROP_BRACE, $name);

                continue;
            }

            if ($name !== null && $tokens[$name]->isGivenKind(T_STRING)) {
                $index = $name;

                continue;
            }

            $name = $name === null ? null : self::variableEnd($tokens, $name);

            if ($name === null) {
                break;
            }

            $index = $name;
        }

        return $index;
    }

    /**
     * Find the end of a variable name such as `$name`, `$$name`, or `${'name'}`.
     *
     * @param Tokens<Token> $tokens
     */
    private static function variableEnd(Tokens $tokens, int $index): null|int
    {
        while ($tokens[$index]->equals('$')) {
            $index = $tokens->getNextMeaningfulToken($index);

            if ($index === null) {
                return null;
            }
        }

        if ($tokens[$index]->isGivenKind(CT::T_DYNAMIC_VAR_BRACE_OPEN)) {
            return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_DYNAMIC_VAR_BRACE, $index);
        }

        return $tokens[$index]->isGivenKind(T_VARIABLE) ? $index : null;
    }
}
