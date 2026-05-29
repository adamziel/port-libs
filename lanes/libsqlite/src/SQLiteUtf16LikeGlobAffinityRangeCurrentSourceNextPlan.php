<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValuePlan(
        array $currentRows,
        array $nextRows,
        string $column,
        string $patternBytes,
        int|string $patternEncoding,
        string $operator = 'LIKE',
        string $affinity = 'TEXT',
        string $collation = 'BINARY',
        ?string $escapeBytes = null,
        int|string|null $escapeEncoding = null,
        bool $caseSensitiveLike = true,
        string $currentSource = 'main.wp_options',
        string $nextSource = 'main.wp_options',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity range plan requires LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escapeBytes !== null) {
            throw new \InvalidArgumentException('SQLite UTF-16 GLOB affinity range plan does not accept ESCAPE');
        }

        $patternEncodingId = self::normalizeEncoding($patternEncoding);
        $pattern = self::decodeText($patternBytes, $patternEncodingId, 'pattern');
        $escape = null;
        $escapeEncodingId = null;
        if ($escapeBytes !== null) {
            $escapeEncodingId = self::normalizeEncoding($escapeEncoding ?? $patternEncodingId);
            $escape = self::decodeText($escapeBytes, $escapeEncodingId, 'escape');
            if (self::sqliteTextLength($escape) !== 1) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE ESCAPE expression must decode to one character');
            }
        }

        $plan = SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan::wordpressOptionValuePlan(
            $currentRows,
            $nextRows,
            $column,
            $pattern,
            $operator,
            $affinity,
            $collation,
            $escape,
            $caseSensitiveLike,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $plan['decodedPattern'] = $pattern;
        $plan['patternEncoding'] = self::encodingName($patternEncodingId);
        $plan['patternBytesHex'] = bin2hex($patternBytes);
        $plan['patternUtf16LeHex'] = bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($pattern, 'UTF-16LE'));
        $plan['patternUtf16BeHex'] = bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($pattern, 'UTF-16BE'));
        $plan['decodedEscape'] = $escape;
        $plan['escapeEncoding'] = $escapeEncodingId === null ? null : self::encodingName($escapeEncodingId);
        $plan['escapeBytesHex'] = $escapeBytes === null ? null : bin2hex($escapeBytes);
        $plan['rangeUtf16LeHex'] = self::rangeBytes($plan['range'], 'UTF-16LE');
        $plan['rangeUtf16BeHex'] = self::rangeBytes($plan['range'], 'UTF-16BE');
        $plan['patternSource'] = 'decoded-utf16-pattern-bytes';
        $plan['dependencies'] = [
            'sqlite-utf16-like-glob-pattern-decode',
            'sqlite-like-glob-affinity-range',
            'sqlite-current-source-nextoneTwoFour',
        ];

        return $plan;
    }

    private static function decodeText(string $bytes, int $encoding, string $context): string
    {
        $text = SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding);
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 LIKE/GLOB {$context} decoded to malformed UTF-8");
        }

        return $text;
    }

    private static function normalizeEncoding(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity range encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE', 'UTF-16' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity range encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity range encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function sqliteTextLength(string $text): int
    {
        preg_match_all('/./us', $text, $matches);

        return count($matches[0]);
    }

    /**
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return ?array{lowerInclusive:string,upperBound:?string}
     */
    private static function rangeBytes(?array $range, string $encoding): ?array
    {
        if ($range === null) {
            return null;
        }

        return [
            'lowerInclusive' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($range['lowerInclusive'], $encoding)),
            'upperBound' => $range['upperBound'] === null ? null : bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($range['upperBound'], $encoding)),
        ];
    }
}
