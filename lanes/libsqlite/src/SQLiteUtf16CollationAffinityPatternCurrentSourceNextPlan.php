<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValuePlan(
        array $currentRows,
        array $nextRows,
        string $patternBytes,
        int|string $patternEncoding,
        string $operator = 'LIKE',
        string $collation = 'NOCASE',
        ?string $escapeBytes = null,
        int|string|null $escapeEncoding = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options',
        string $nextSource = 'main.wp_options',
        int|string $currentRangeEncoding = 'UTF-16LE',
        int|string $nextRangeEncoding = 'UTF-16LE',
    ): array {
        $operator = strtoupper($operator);
        $collation = strtoupper($collation);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 collation affinity pattern plan requires LIKE or GLOB');
        }
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite UTF-16 pattern collation: {$collation}");
        }
        if ($operator === 'GLOB' && $escapeBytes !== null) {
            throw new \InvalidArgumentException('SQLite GLOB collation pattern plan does not accept ESCAPE');
        }

        $pattern = self::decodeText($patternBytes, $patternEncoding, 'pattern');
        $escape = null;
        if ($escapeBytes !== null) {
            $escape = self::decodeText($escapeBytes, $escapeEncoding ?? $patternEncoding, 'escape');
            if (self::sqliteTextLength($escape) !== 1) {
                throw new \InvalidArgumentException('SQLite LIKE ESCAPE expression must be a single character');
            }
        }

        $base = SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::wordpressOptionValuePlan(
            $currentRows,
            $nextRows,
            $patternBytes,
            $patternEncoding,
            $operator,
            $escapeBytes,
            $escapeEncoding,
            $caseSensitiveLike,
            $currentSource,
            $nextSource,
        );

        $currentEncoding = self::encodingName(self::normalizeEncoding($currentRangeEncoding));
        $nextEncoding = self::encodingName(self::normalizeEncoding($nextRangeEncoding));
        $rangePlan = self::rangePlan($pattern, $operator, $collation, $escape, $caseSensitiveLike);
        $currentRangeBytes = self::rangeBytes($rangePlan['range'], $currentEncoding);
        $nextRangeBytes = self::rangeBytes($rangePlan['range'], $nextEncoding);

        $reasons = $base['invalidationReasons'];
        if ($currentEncoding !== $nextEncoding) {
            $reasons[] = 'range-encoding';
        }
        if ($currentRangeBytes !== $nextRangeBytes) {
            $reasons[] = 'range-bytes';
        }
        $reasons = array_values(array_unique($reasons));

        return array_replace($base, [
            'collation' => $collation,
            'rangePlan' => $rangePlan,
            'currentRangeEncoding' => $currentEncoding,
            'nextRangeEncoding' => $nextEncoding,
            'currentRangeBytesHex' => $currentRangeBytes,
            'nextRangeBytesHex' => $nextRangeBytes,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-pattern-decode',
                'sqlite-like-glob-affinity',
                'sqlite-collation-range-current-source-nextoneOneEight',
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function rangePlan(
        string $pattern,
        string $operator,
        string $collation,
        ?string $escape,
        bool $caseSensitiveLike,
    ): array {
        if ($operator === 'LIKE') {
            $plan = SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike);

            return [
                'operator' => 'LIKE',
                'collation' => $collation,
                'caseSensitiveLike' => $caseSensitiveLike,
                'indexUsable' => $plan['indexUsable'],
                'range' => $plan['range'],
                'prefix' => $plan['prefix'],
                'prefixIsAscii' => $plan['prefixIsAscii'],
                'rejectedReason' => $plan['rejectedReason'],
            ];
        }

        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $indexUsable = $range !== null && $collation === 'BINARY';

        return [
            'operator' => 'GLOB',
            'collation' => $collation,
            'caseSensitiveLike' => null,
            'indexUsable' => $indexUsable,
            'range' => $indexUsable ? $range : null,
            'prefix' => $range === null ? '' : $range['lowerInclusive'],
            'prefixIsAscii' => $range !== null && preg_match('/^[\x00-\x7F]*$/', $range['lowerInclusive']) === 1,
            'rejectedReason' => $indexUsable ? null : ($range === null ? 'no_fixed_prefix' : 'glob_requires_binary_index'),
        ];
    }

    /**
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{lowerInclusive:?string,upperBound:?string}
     */
    private static function rangeBytes(?array $range, string $encoding): array
    {
        if ($range === null) {
            return ['lowerInclusive' => null, 'upperBound' => null];
        }

        return [
            'lowerInclusive' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($range['lowerInclusive'], $encoding)),
            'upperBound' => $range['upperBound'] === null ? null : bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($range['upperBound'], $encoding)),
        ];
    }

    private static function decodeText(string $bytes, int|string $encoding, string $context): string
    {
        $text = SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::normalizeEncoding($encoding));
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 collation {$context} decoded to malformed UTF-8");
        }

        return $text;
    }

    private static function normalizeEncoding(int|string|null $encoding): int
    {
        if ($encoding === null) {
            throw new \InvalidArgumentException('SQLite UTF-16 collation pattern encoding is required');
        }
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite UTF-16 collation pattern encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE', 'UTF-16' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 collation pattern encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 collation pattern encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function sqliteTextLength(string $text): int
    {
        preg_match_all('/./us', $text, $matches);

        return count($matches[0]);
    }
}
