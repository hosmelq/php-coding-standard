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
     * @param Tokens<Token> $tokens
     */
    public static function isLongSingleLine(
        Tokens $tokens,
        int $start,
        int $end,
        int $lineLength,
    ): bool {
        for ($index = $start; $index <= $end; ++$index) {
            if (strpbrk($tokens[$index]->getContent(), "\r\n") !== false) {
                return false;
            }
        }

        $line = '';

        for ($index = $start - 1; $index >= 0; --$index) {
            $line = $tokens[$index]->getContent().$line;

            $newline = mb_strrpos($line, "\n");

            if ($newline !== false) {
                $line = mb_substr($line, $newline + 1);

                break;
            }
        }

        for ($index = $start; $index < $tokens->count(); ++$index) {
            $token = $tokens[$index];
            $content = $token->getContent();

            if ($token->isGivenKind(T_COMMENT)
                && (str_starts_with($content, '//') || str_starts_with($content, '#'))) {
                break;
            }

            $newline = mb_strpos($content, "\n");
            $line .= $newline === false ? $content : mb_substr($content, 0, $newline);

            if ($newline !== false) {
                break;
            }
        }

        return mb_strlen(mb_rtrim($line, " \t\r")) > $lineLength;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public static function split(
        Tokens $tokens,
        int $start,
        int $end,
        string $indentation,
        string $indent,
        string $lineEnding,
    ): void {
        $separators = [];

        foreach (self::topLevelTokens($tokens, $start, $end) as $index) {
            if ($tokens[$index]->equals(',')) {
                $separators[] = $index;
            }
        }

        $last = $tokens->getPrevMeaningfulToken($end);
        $hasTrailingComma = $last !== null && $tokens[$last]->equals(',');
        $result = [$tokens[$start]];
        $itemStart = $start + 1;

        foreach ([...$separators, $end] as $separator) {
            $itemEnd = $separator - 1;

            while ($itemStart <= $itemEnd && $tokens[$itemStart]->isWhitespace()) {
                ++$itemStart;
            }

            while ($itemEnd >= $itemStart && $tokens[$itemEnd]->isWhitespace()) {
                --$itemEnd;
            }

            if ($itemStart <= $itemEnd) {
                $result[] = new Token([T_WHITESPACE, $lineEnding.$indentation.$indent]);

                $previousIndentation = self::indentation($tokens, $itemStart);

                for ($index = $itemStart; $index <= $itemEnd; ++$index) {
                    $token = $tokens[$index];
                    $content = $token->getContent();
                    $newline = strrpos($content, "\n");

                    if ($token->isWhitespace() && $newline !== false) {
                        $suffix = substr($content, $newline + 1);

                        if (str_starts_with($suffix, $previousIndentation)) {
                            $token = new Token([
                                T_WHITESPACE,
                                substr($content, 0, $newline + 1).$indentation.$indent
                                    .substr($suffix, strlen($previousIndentation)),
                            ]);
                        }
                    }

                    $result[] = $token;

                    if (! $hasTrailingComma && $separator === $end && $index === $last) {
                        $result[] = new Token(',');
                    }
                }
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
}
