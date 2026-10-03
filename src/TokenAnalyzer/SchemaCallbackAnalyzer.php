<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\TokenAnalyzer;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @internal
 */
final class SchemaCallbackAnalyzer
{
    /**
     * @param Tokens<Token> $tokens
     *
     * @return list<array{end: int, migration: bool, start: int}>
     */
    public function callbackScopes(Tokens $tokens): array
    {
        $scopes = [];

        foreach ($tokens as $index => $token) {
            if (! $token->isGivenKind([T_FN, T_FUNCTION])) {
                continue;
            }

            $open = $tokens->getNextTokenOfKind($index, ['(']);

            if ($open === null) {
                continue;
            }

            $close = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $open);

            if ($token->isGivenKind(T_FN)) {
                $body = $tokens->getNextTokenOfKind($close, [[T_DOUBLE_ARROW]]);

                if ($body === null) {
                    continue;
                }

                $end = $this->expressionEnd($tokens, $body + 1);
            } else {
                $body = $tokens->getNextTokenOfKind($close, ['{', ';']);

                if ($body === null || ! $tokens[$body]->equals('{')) {
                    continue;
                }

                $end = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_BRACE, $body);
            }

            $variable = $tokens->getNextTokenOfKind($open, [[T_VARIABLE]]);
            $type = '';

            if ($variable !== null && $variable < $close) {
                for ($part = $open + 1; $part < $variable; ++$part) {
                    if (! $tokens[$part]->isWhitespace() && ! $tokens[$part]->isComment()) {
                        $type .= $tokens[$part]->getContent();
                    }
                }
            }

            $migration = $this->isBlueprint($type)
                && $variable !== null
                && $tokens[$variable]->equals([T_VARIABLE, '$table'])
                && $this->isSchemaCallback($tokens, $index);

            $scopes[] = [
                'end' => $end,
                'migration' => $migration,
                'start' => $body,
            ];
        }

        return $scopes;
    }

    /**
     * @param Tokens<Token> $tokens
     * @param list<array{end: int, migration: bool, start: int}> $scopes
     */
    public function isMigration(Tokens $tokens, int $root, array $scopes): bool
    {
        if (! $tokens[$root]->equals([T_VARIABLE, '$table'])) {
            return false;
        }

        $previous = $tokens->getPrevMeaningfulToken($root);

        if ($previous !== null && $tokens[$previous]->isGivenKind(
            [T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR, T_OBJECT_OPERATOR],
        )) {
            return false;
        }

        $migration = false;

        foreach ($scopes as $scope) {
            if ($scope['start'] < $root && $root < $scope['end']) {
                $migration = $scope['migration'];
            }
        }

        return $migration;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function expressionEnd(Tokens $tokens, int $index): int
    {
        $counter = count($tokens);

        for ($cursor = $index; $cursor < $counter; ++$cursor) {
            $block = Tokens::detectBlockType($tokens[$cursor]);

            if ($block !== null && $block['isStart']) {
                $cursor = $tokens->findBlockEnd($block['type'], $cursor);

                continue;
            }

            if ($block !== null || $tokens[$cursor]->equalsAny([',', ';'])) {
                return $cursor;
            }
        }

        return count($tokens) - 1;
    }

    private function isBlueprint(string $type): bool
    {
        return in_array(mb_strtolower($type), [
            '\\illuminate\\database\\schema\\blueprint',
            'blueprint',
            'illuminate\\database\\schema\\blueprint',
        ], true);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function isSchemaCallback(Tokens $tokens, int $index): bool
    {
        $previous = $tokens->getPrevMeaningfulToken($index);

        if ($previous !== null && $tokens[$previous]->isGivenKind(T_STATIC)) {
            $previous = $tokens->getPrevMeaningfulToken($previous);
        }

        if ($previous === null || ! $tokens[$previous]->equals(',')) {
            return false;
        }

        // Walk over the first argument, including nested calls, to the Schema call's opening.
        for ($cursor = $previous - 1; $cursor >= 0; --$cursor) {
            $block = Tokens::detectBlockType($tokens[$cursor]);

            if ($block !== null && ! $block['isStart']) {
                $cursor = $tokens->findBlockStart($block['type'], $cursor);

                continue;
            }

            if (! $tokens[$cursor]->equals('(')) {
                continue;
            }

            $method = $tokens->getPrevMeaningfulToken($cursor);
            $operator = $method === null ? null : $tokens->getPrevMeaningfulToken($method);
            $class = $operator === null ? null : $tokens->getPrevMeaningfulToken($operator);
            $name = '';

            if ($class !== null) {
                for ($part = $class; $part >= 0; --$part) {
                    if (! $tokens[$part]->isGivenKind([T_NS_SEPARATOR, T_STRING])) {
                        break;
                    }

                    $name = $tokens[$part]->getContent().$name;
                }
            }

            return $method !== null
                && in_array(
                    mb_strtolower($tokens[$method]->getContent()),
                    ['create', 'table'],
                    true,
                )
                && $operator !== null
                && $tokens[$operator]->isGivenKind(T_DOUBLE_COLON)
                && $class !== null
                && in_array(mb_strtolower($name), [
                    '\\illuminate\\support\\facades\\schema',
                    'illuminate\\support\\facades\\schema',
                    'schema',
                ], true);
        }

        return false;
    }
}
