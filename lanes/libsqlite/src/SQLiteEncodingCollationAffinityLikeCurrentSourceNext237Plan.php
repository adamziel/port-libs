<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext237Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValueEscapePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_%!%%',
        ?string $escape = '!',
        string $collation = 'NOCASE',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@236',
        string $nextSource = 'main.wp_options@237',
        int $currentSchemaCookie = 236,
        int $nextSchemaCookie = 237,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE next237 collation must be BINARY, NOCASE, or RTRIM');
        }

        $like = SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike);
        $current = self::scan($currentRows, $pattern, $escape, $collation, $caseSensitiveLike, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $collation, $caseSensitiveLike, $like['range']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
        $changes = self::changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'storage-class' => $changes['storageRowids'],
            'affinity-text' => $changes['likeTextRowids'],
            'collation-key' => $changes['collationKeyRowids'],
            'range-membership' => $currentCandidates === $nextCandidates ? [] : self::uniqueSortedInts(array_merge($currentCandidates, $nextCandidates)),
            'residual-result' => $changes['residualRowids'],
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next237',
            'operator' => 'LIKE',
            'expression' => 'option_value LIKE ? ESCAPE ? COLLATE ' . $collation . ' /* text affinity before residual */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'prefixCharacters' => $like['prefixCharacters'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'rangeRejectedReason' => $like['rejectedReason'],
            'rangeLowerInclusive' => $like['range']['lowerInclusive'] ?? null,
            'rangeUpperBound' => $like['range']['upperBound'] ?? null,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedCollationKeyRowids' => $changes['collationKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'escapeTreatsUnderscoreAsLiteral' => true,
            'escapeTreatsPercentAsLiteralUntilTrailingWildcard' => true,
            'textAffinityBeforeLike' => true,
            'nullLikeResultIsUnknown' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-escape-prefix-range',
                'sqlite-text-affinity-like',
                'sqlite-like-nocase-collation',
                'sqlite-current-source-next237',
            ],
            'dependency_closure' => 'no new support component needed; reuses LIKE ESCAPE prefix planning, scalar text-affinity conversion, ASCII NOCASE collation keys, and current-source invalidation diagnostics',
            'non_overlap' => 'next237 covers escaped wildcard literals after text affinity under LIKE/NOCASE current-source scans; avoids accepted Unicode GLOB ranges, UTF-16 malformed record guards, UTF-16 NOCASE/RTRIM canonical-equivalent scans, blob LIKE/GLOB affinity next234, SQL expression ORDER BY, and JSON/WAL/B-tree clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, string $collation, bool $caseSensitiveLike, ?array $range): array
    {
        $trace = [];
        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $row) {
            self::assertRow($row);
            $rowid = $row['option_id'];
            try {
                $likeText = self::likeText($row['option_value']);
                $collationKey = $likeText === null ? null : self::collationKey($likeText, $collation);
                $inRange = $collationKey !== null && self::inRange($collationKey, $range);
                $residual = $likeText === null ? null : SQLiteDatabase::likeMatches($likeText, $pattern, $escape, $caseSensitiveLike);
                $entry = [
                    'rowid' => $rowid,
                    'optionName' => (string) ($row['option_name'] ?? ''),
                    'storage' => SQLiteAffinityComparison::storageClass($row['option_value']),
                    'likeText' => $likeText,
                    'likeTextHex' => $likeText === null ? null : strtoupper(bin2hex($likeText)),
                    'collationKey' => $collationKey,
                    'collationKeyHex' => $collationKey === null ? null : strtoupper(bin2hex($collationKey)),
                    'inRange' => $inRange,
                    'residualMatch' => $residual,
                    'matched' => $inRange && $residual === true,
                    'autoload' => $row['autoload'] ?? null,
                ];
                $trace[] = $entry;
                if ($inRange) {
                    $candidates[] = $entry;
                    if ($entry['matched']) {
                        $matched[] = $entry;
                    } else {
                        $falsePositive[] = $entry;
                    }
                }
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, self::sortTrace(...));
        usort($candidates, self::sortTrace(...));
        usort($matched, self::sortTrace(...));
        usort($falsePositive, self::sortTrace(...));
        sort($malformed);
        ksort($errors);

        return [
            'trace' => $trace,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE next237 rows require integer option_id');
        }
        if (!array_key_exists('option_value', $row)) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE next237 rows require option_value');
        }
    }

    private static function likeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE next237 rows require scalar option_value');
        }
        if (preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite encoding collation affinity LIKE next237 text value is malformed UTF-8');
        }

        return $value;
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $collationKey, ?array $range): bool
    {
        if ($range === null) {
            return false;
        }
        if (strcmp($collationKey, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($collationKey, $range['upperBound']) < 0;
    }

    private static function collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'BINARY' => $text,
            'NOCASE' => strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
            'RTRIM' => rtrim($text, ' '),
        };
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private static function sortTrace(array $left, array $right): int
    {
        $comparison = strcmp((string) ($left['collationKey'] ?? ''), (string) ($right['collationKey'] ?? ''));

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{storageRowids:list<int>,likeTextRowids:list<int>,collationKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function changes(array $current, array $next): array
    {
        $currentByRowid = [];
        foreach ($current as $row) {
            $currentByRowid[$row['rowid']] = $row;
        }

        $storage = [];
        $text = [];
        $key = [];
        $residual = [];
        foreach ($next as $row) {
            $rowid = $row['rowid'];
            if (!isset($currentByRowid[$rowid])) {
                $storage[] = $rowid;
                $text[] = $rowid;
                $key[] = $rowid;
                $residual[] = $rowid;
                continue;
            }
            $currentRow = $currentByRowid[$rowid];
            if ($currentRow['storage'] !== $row['storage']) {
                $storage[] = $rowid;
            }
            if ($currentRow['likeText'] !== $row['likeText']) {
                $text[] = $rowid;
            }
            if ($currentRow['collationKey'] !== $row['collationKey']) {
                $key[] = $rowid;
            }
            if ($currentRow['residualMatch'] !== $row['residualMatch']) {
                $residual[] = $rowid;
            }
        }
        $nextRowids = array_column($next, 'rowid');
        foreach ($currentByRowid as $rowid => $_row) {
            if (!in_array($rowid, $nextRowids, true)) {
                $storage[] = $rowid;
                $text[] = $rowid;
                $key[] = $rowid;
                $residual[] = $rowid;
            }
        }

        return [
            'storageRowids' => self::uniqueSortedInts($storage),
            'likeTextRowids' => self::uniqueSortedInts($text),
            'collationKeyRowids' => self::uniqueSortedInts($key),
            'residualRowids' => self::uniqueSortedInts($residual),
        ];
    }

    /** @param list<int> $values @return list<int> */
    private static function uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}
