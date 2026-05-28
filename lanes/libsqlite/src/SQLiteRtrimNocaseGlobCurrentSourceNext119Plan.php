<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRtrimNocaseGlobCurrentSourceNext119Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $collation,
        string $currentSource = 'main.wp_options@current',
        string $nextSource = 'main.wp_options@next',
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source plan requires BINARY, NOCASE, or RTRIM collation');
        }

        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $current = self::sourceRows($currentRows, $pattern, $collation, $range);
        $next = self::sourceRows($nextRows, $pattern, $collation, $range);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);

        return [
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'pattern' => $pattern,
            'collation' => $collation,
            'range' => $range,
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFalsePositiveRowids' => array_values(array_diff($currentCandidates, $currentMatched)),
            'nextFalsePositiveRowids' => array_values(array_diff($nextCandidates, $nextMatched)),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentOrderRowids' => self::rowids($current['ordered']),
            'nextOrderRowids' => self::rowids($next['ordered']),
            'currentComparisonKeys' => self::mapByRowid($current['ordered'], 'comparisonKey'),
            'nextComparisonKeys' => self::mapByRowid($next['ordered'], 'comparisonKey'),
            'currentResidualMatches' => self::mapByRowid($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::mapByRowid($next['candidates'], 'residualMatch'),
            'currentMalformedRowids' => $current['malformed'],
            'nextMalformedRowids' => $next['malformed'],
            'repairedMalformedRowids' => array_values(array_diff($current['malformed'], $next['malformed'])),
            'newlyMalformedRowids' => array_values(array_diff($next['malformed'], $current['malformed'])),
            'cursorInvalidated' => $currentSource !== $nextSource
                || $currentCandidates !== $nextCandidates
                || $currentMatched !== $nextMatched
                || $current['malformed'] !== $next['malformed'],
            'invalidationReasons' => self::invalidationReasons(
                $currentSource,
                $nextSource,
                $currentCandidates,
                $nextCandidates,
                $currentMatched,
                $nextMatched,
                $current['malformed'],
                $next['malformed'],
            ),
            'dependencies' => ['sqlite-glob-prefix-range', 'sqlite-' . strtolower($collation) . '-collation', 'sqlite-glob-binary-residual'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{ordered:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,malformed:list<int>}
     */
    private static function sourceRows(array $rows, string $pattern, string $collation, ?array $range): array
    {
        $ordered = [];
        $malformed = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source rows require integer option_id');
            }
            if (!array_key_exists('option_name', $row) || !is_string($row['option_name'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source rows require text option_name');
            }

            $key = self::comparisonKey($row['option_name'], $collation);
            $malformedUtf8 = preg_match('//u', $row['option_name']) !== 1;
            if ($malformedUtf8) {
                $malformed[] = $row['option_id'];
            }
            $ordered[] = [
                'rowid' => $row['option_id'],
                'text' => $row['option_name'],
                'comparisonKey' => $key,
                'payload' => $row,
                'malformedUtf8' => $malformedUtf8,
            ];
        }

        usort($ordered, static function (array $left, array $right): int {
            $comparison = strcmp($left['comparisonKey'], $right['comparisonKey']);
            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        $candidates = [];
        $matched = [];
        foreach ($ordered as $entry) {
            $inRange = $range !== null
                && strcmp($entry['comparisonKey'], self::comparisonKey($range['lowerInclusive'], $collation)) >= 0
                && ($range['upperBound'] === null || strcmp($entry['comparisonKey'], self::comparisonKey($range['upperBound'], $collation)) < 0);
            $residual = SQLiteDatabase::globMatches($entry['text'], $pattern);
            if ($inRange) {
                $entry['residualMatch'] = $residual;
                $candidates[] = $entry;
            }
            if ($inRange && $residual) {
                $matched[] = $entry;
            }
        }

        sort($malformed);

        return ['ordered' => $ordered, 'candidates' => $candidates, 'matched' => $matched, 'malformed' => $malformed];
    }

    private static function comparisonKey(string $text, string $collation): string
    {
        return match ($collation) {
            'BINARY' => $text,
            'NOCASE' => self::asciiLower($text),
            'RTRIM' => rtrim($text, ' '),
            default => throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source plan requires BINARY, NOCASE, or RTRIM collation'),
        };
    }

    /**
     * @param list<array{rowid:int}> $rows
     * @return list<int>
     */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,mixed>
     */
    private static function mapByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[$row['rowid']] = $row[$field];
        }

        return $values;
    }

    /**
     * @param list<int> $currentCandidates
     * @param list<int> $nextCandidates
     * @param list<int> $currentMatched
     * @param list<int> $nextMatched
     * @param list<int> $currentMalformed
     * @param list<int> $nextMalformed
     * @return list<string>
     */
    private static function invalidationReasons(
        string $currentSource,
        string $nextSource,
        array $currentCandidates,
        array $nextCandidates,
        array $currentMatched,
        array $nextMatched,
        array $currentMalformed,
        array $nextMalformed,
    ): array {
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($currentMalformed !== $nextMalformed) {
            $reasons[] = 'malformed-text';
        }

        return $reasons;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
