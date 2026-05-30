<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValuePlan(
        array $currentRows,
        array $nextRows,
        string $patternBytes,
        int|string $patternEncoding,
        string $operator = 'LIKE',
        ?string $escapeBytes = null,
        int|string|null $escapeEncoding = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options',
        string $nextSource = 'main.wp_options',
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 pattern LIKE/GLOB plan requires LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escapeBytes !== null) {
            throw new \InvalidArgumentException('SQLite GLOB pattern plan does not accept ESCAPE');
        }

        $pattern = self::decodeText($patternBytes, $patternEncoding, 'pattern');
        $escape = null;
        if ($escapeBytes !== null) {
            $escape = self::decodeText($escapeBytes, $escapeEncoding ?? $patternEncoding, 'escape');
            if (self::sqliteTextLength($escape) !== 1) {
                throw new \InvalidArgumentException('SQLite LIKE ESCAPE expression must be a single character');
            }
        }

        $plan = SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $operator,
            $escape,
            $caseSensitiveLike,
            $currentSource,
            $nextSource,
        );

        $plan['patternBytesHex'] = bin2hex($patternBytes);
        $plan['patternEncoding'] = self::encodingName(self::normalizeEncoding($patternEncoding));
        $plan['decodedPattern'] = $pattern;
        $plan['escapeBytesHex'] = $escapeBytes === null ? null : bin2hex($escapeBytes);
        $plan['escapeEncoding'] = $escapeBytes === null ? null : self::encodingName(self::normalizeEncoding($escapeEncoding ?? $patternEncoding));
        $plan['decodedEscape'] = $escape;
        $plan['dependencies'] = [
            'sqlite-utf16-pattern-decode',
            'sqlite-like-glob-affinity',
            'sqlite-current-source-nextoneOneFour',
        ];

        return $plan;
    }

    private static function decodeText(string $bytes, int|string $encoding, string $context): string
    {
        $text = SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::normalizeEncoding($encoding));
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 {$context} decoded to malformed UTF-8");
        }

        return $text;
    }

    private static function normalizeEncoding(int|string|null $encoding): int
    {
        if ($encoding === null) {
            throw new \InvalidArgumentException('SQLite UTF-16 pattern encoding is required');
        }
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite UTF-16 pattern encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE', 'UTF-16' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 pattern encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 pattern encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function sqliteTextLength(string $text): int
    {
        preg_match_all('/./us', $text, $matches);

        return count($matches[0]);
    }
}
