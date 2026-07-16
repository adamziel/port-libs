<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteLikeCollationPlan
{
    /**
     * @return array{
     *   pattern:string,
     *   escape:?string,
     *   collation:string,
     *   caseSensitiveLike:bool,
     *   prefix:string,
     *   prefixCharacters:int,
     *   prefixIsAscii:bool,
     *   hasWildcard:bool,
     *   matcherCaseSensitive:bool,
     *   indexUsable:bool,
     *   range:?array{lowerInclusive:string,upperBound:?string},
     *   rejectedReason:?string,
     *   dependencies:list<string>
     * }
     */
    public static function plan(string $pattern, string $collation = 'BINARY', ?string $escape = null, bool $caseSensitiveLike = false): array
    {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite LIKE collation: {$collation}");
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $reason = null;
        $range = null;
        if ($patternPlan['prefix'] === '') {
            $reason = 'no_fixed_prefix';
        } elseif ($caseSensitiveLike && $collation !== 'BINARY') {
            $reason = 'case_sensitive_like_requires_binary_index';
        } elseif (!$caseSensitiveLike && $collation !== 'NOCASE') {
            $reason = 'default_like_requires_nocase_index';
        } elseif (!$caseSensitiveLike && !$patternPlan['prefixIsAscii']) {
            $reason = 'nocase_like_prefix_must_be_ascii_for_range';
        } else {
            $range = $caseSensitiveLike ? $patternPlan['binaryRange'] : $patternPlan['noCaseRange'];
        }

        return [
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitiveLike,
            'prefix' => $patternPlan['prefix'],
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'matcherCaseSensitive' => $caseSensitiveLike,
            'indexUsable' => $range !== null,
            'range' => $range,
            'rejectedReason' => $reason,
            'dependencies' => ['sqlite-like-collation-prefix-range'],
        ];
    }

    /**
     * @param iterable<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function filterRows(iterable $rows, string $column, string $pattern, string $collation = 'BINARY', ?string $escape = null, bool $caseSensitiveLike = false): array
    {
        self::plan($pattern, $collation, $escape, $caseSensitiveLike);
        $matched = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite LIKE collation filter row is missing column {$column}");
            }
            $value = $row[$column];
            if ($value === null) {
                continue;
            }
            if (!is_string($value)) {
                throw new \InvalidArgumentException('SQLite LIKE collation filter expects text values');
            }
            if (SQLiteDatabase::likeMatches($value, $pattern, $escape, $caseSensitiveLike)) {
                $matched[] = $row;
            }
        }

        return $matched;
    }
}
