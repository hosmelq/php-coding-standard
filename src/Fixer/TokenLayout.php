<?php

declare(strict_types=1);

namespace HosmelQ\PhpCodingStandard\Fixer;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @internal
 */
final class TokenLayout
{
    /**
     * @param Tokens<Token> $tokens
     */
    public static function indentation(Tokens $tokens, int $index): string
    {
        $prefix = '';

        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $prefix = $tokens[$cursor]->getContent().$prefix;
            $newline = mb_strrpos($prefix, "\n");

            if ($newline !== false) {
                $prefix = mb_substr($prefix, $newline + 1);

                break;
            }
        }

        return mb_substr($prefix, 0, strspn($prefix, " \t"));
    }

    /**
     * Replaces the $from indentation with $to on every line that starts inside the range,
     * including heredoc bodies and closing markers, so their values do not change.
     *
     * @param Tokens<Token> $tokens
     */
    public static function reindent(
        Tokens $tokens,
        int $start,
        int $end,
        string $from,
        string $to,
    ): void {
        $heredoc = false;

        for ($index = $start; $index <= $end; ++$index) {
            $token = $tokens[$index];

            if ($token->isGivenKind(T_START_HEREDOC)) {
                $heredoc = true;

                continue;
            }

            if ($token->isGivenKind(T_END_HEREDOC)) {
                $heredoc = false;

                if (str_starts_with($token->getContent(), $from)) {
                    $tokens[$index] = new Token([
                        T_END_HEREDOC,
                        $to.mb_substr($token->getContent(), mb_strlen($from)),
                    ]);
                }

                continue;
            }

            if ($token->isWhitespace()) {
                $content = $token->getContent();
                $newline = mb_strrpos($content, "\n");

                if ($newline === false) {
                    continue;
                }

                $suffix = mb_substr($content, $newline + 1);

                if (str_starts_with($suffix, $from)) {
                    $tokens[$index] = new Token([
                        T_WHITESPACE,
                        mb_substr($content, 0, $newline + 1).$to
                            .mb_substr($suffix, mb_strlen($from)),
                    ]);
                }

                continue;
            }

            if (! $heredoc || ! $token->isGivenKind(T_ENCAPSED_AND_WHITESPACE)) {
                continue;
            }

            $previous = $tokens[$index - 1];
            $lines = explode("\n", $token->getContent());

            foreach ($lines as $line => $text) {
                $lineStart = $line > 0 || $previous->isGivenKind(T_START_HEREDOC)
                    || str_ends_with($previous->getContent(), "\n");

                if ($lineStart && $text !== '' && str_starts_with($text, $from)) {
                    $lines[$line] = $to.mb_substr($text, mb_strlen($from));
                }
            }

            $tokens[$index] = new Token([T_ENCAPSED_AND_WHITESPACE, implode("\n", $lines)]);
        }
    }

    /**
     * Puts each item of the list between $start and $end on its own line, with a trailing comma.
     *
     * @param Tokens<Token> $tokens
     */
    public static function split(
        Tokens $tokens,
        int $start,
        int $end,
        string $indent,
        string $lineEnding,
    ): void {
        $indentation = self::indentation($tokens, $start);
        $separators = [];

        foreach (self::topLevelTokens($tokens, $start, $end) as $index) {
            if ($tokens[$index]->equals(',')) {
                $separators[] = $index;
            }
        }

        $trailing = [];

        foreach ([$start, ...$separators] as $position => $boundary) {
            $trailing[$boundary] = self::trailingComment(
                $tokens,
                $boundary,
                [...$separators, $end][$position],
            );
        }

        $itemStart = ($trailing[$start] ?? $start) + 1;

        // Indent nested lines first, while every item still has its original line.
        foreach ([...$separators, $end] as $separator) {
            $first = $itemStart;

            while ($first < $separator && $tokens[$first]->isWhitespace()) {
                ++$first;
            }

            if ($first < $separator) {
                self::reindent(
                    $tokens,
                    $first,
                    $separator - 1,
                    self::indentation($tokens, $first),
                    $indentation.$indent,
                );
            }

            $itemStart = ($trailing[$separator] ?? $separator) + 1;
        }

        $last = $tokens->getPrevMeaningfulToken($end);
        $hasTrailingComma = $last !== null && $tokens[$last]->equals(',');
        $result = [$tokens[$start]];
        $itemStart = $start + 1;

        foreach ([...$separators, $end] as $separator) {
            $boundary = $itemStart - 1;

            // A line comment after the opening token or a comma stays at the end of that line.
            if (($trailing[$boundary] ?? null) !== null) {
                $result[] = new Token([T_WHITESPACE, ' ']);
                $result[] = $tokens[$trailing[$boundary]];
                $itemStart = $trailing[$boundary] + 1;
            }

            $itemEnd = $separator - 1;

            while ($itemStart <= $itemEnd && $tokens[$itemStart]->isWhitespace()) {
                ++$itemStart;
            }

            while ($itemEnd >= $itemStart && $tokens[$itemEnd]->isWhitespace()) {
                --$itemEnd;
            }

            if ($itemStart <= $itemEnd) {
                $result[] = new Token([T_WHITESPACE, $lineEnding.$indentation.$indent]);

                for ($index = $itemStart; $index <= $itemEnd; ++$index) {
                    $result[] = $tokens[$index];

                    if (! $hasTrailingComma && $separator === $end && $index === $last) {
                        $result[] = new Token(',');
                    }
                }
            } elseif ($separator !== $end) {
                $result[] = new Token([T_WHITESPACE, $lineEnding.$indentation.$indent]);
            }

            if ($separator !== $end) {
                $result[] = $tokens[$separator];
            }

            $itemStart = $separator + 1;
        }

        $result[] = new Token([T_WHITESPACE, $lineEnding.$indentation]);
        $result[] = $tokens[$end];
        $tokens->overrideRange($start, $end, $result);
    }

    /**
     * @param Tokens<Token> $tokens
     *
     * @return list<int>
     */
    public static function topLevelTokens(Tokens $tokens, int $start, int $end): array
    {
        $indices = [];

        for ($index = $start + 1; $index < $end; ++$index) {
            $indices[] = $index;
            $block = Tokens::detectBlockType($tokens[$index]);

            if ($block !== null && $block['isStart']) {
                $index = $tokens->findBlockEnd($block['type'], $index);
            }
        }

        return $indices;
    }

    /**
     * Finds a line comment that follows $index on the same line, before $end.
     *
     * @param Tokens<Token> $tokens
     */
    private static function trailingComment(Tokens $tokens, int $index, int $end): null|int
    {
        $next = $index + 1;

        if ($next < $end && $tokens[$next]->isWhitespace()
            && ! str_contains($tokens[$next]->getContent(), "\n")) {
            ++$next;
        }

        if ($next >= $end || ! $tokens[$next]->isGivenKind(T_COMMENT)
            || str_starts_with($tokens[$next]->getContent(), '/*')) {
            return null;
        }

        return $next;
    }
}
