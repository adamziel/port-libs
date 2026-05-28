<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext256Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressPatternAffinityPlan(
        array $currentRows,
        array $nextRows,
        mixed $currentPattern,
        mixed $nextPattern,
        ?string $escape = null,
        string $collation = 'NOCASE',
        string $currentSource = 'main.wp_options@255',
        string $nextSource = 'main.wp_options@256',
        int $currentSchemaCookie = 255,
        int $nextSchemaCookie = 256,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite pattern-affinity LIKE next256 collation must be BINARY, NOCASE, or RTRIM');
        }
        if ($escape !== null && self::sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite pattern-affinity LIKE next256 ESCAPE must be one SQLite character');
        }

        $currentPatternPlan = self::patternPlan($currentPattern, $escape, $collation, 'current');
        $nextPatternPlan = self::patternPlan($nextPattern, $escape, $collation, 'next');
        $current = self::scan($currentRows, $currentPatternPlan['patternText'], $escape, $currentPatternPlan['range'], $collation);
        $next = self::scan($nextRows, $nextPatternPlan['patternText'], $escape, $nextPatternPlan['range'], $collation);
        $changes = self::changes($current['trace'], $next['trace']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentPatternPlan['storage'] !== $nextPatternPlan['storage']) {
            $reasons[] = 'pattern-storage';
        }
        if ($currentPatternPlan['patternText'] !== $nextPatternPlan['patternText']) {
            $reasons[] = 'pattern-text';
        }
        if ($currentPatternPlan['patternKey'] !== $nextPatternPlan['patternKey']) {
            $reasons[] = 'pattern-collation-key';
        }
        if ($currentPatternPlan['error'] !== null || $nextPatternPlan['error'] !== null) {
            $reasons[] = 'pattern-malformed';
        }
        foreach ([
            'storage-class' => $changes['storageRowids'],
            'like-text' => $changes['likeTextRowids'],
            'collation-key' => $changes['collationKeyRowids'],
            'candidate-rowset' => self::rowids($current['candidate']) === self::rowids($next['candidate']) ? [] : self::uniqueSortedInts(array_merge(self::rowids($current['candidate']), self::rowids($next['candidate']))),
            'residual-result' => $changes['residualRowids'],
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['unknownRowids'] !== [] || $next['unknownRowids'] !== []) {
            $reasons[] = 'unknown-like';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next256',
            'operator' => 'LIKE',
            'expression' => 'option_value COLLATE ' . $collation . ' LIKE dynamic_pattern /* pattern TEXT affinity current-source fence */',
            'escape' => $escape,
            'collation' => $collation,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPattern' => $currentPatternPlan,
            'nextPattern' => $nextPatternPlan,
            'currentCandidateRowids' => self::rowids($current['candidate']),
            'nextCandidateRowids' => self::rowids($next['candidate']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => self::intersectSorted($currentMatched, $nextMatched),
            'enteredRowids' => self::diffSorted($nextMatched, $currentMatched),
            'exitedRowids' => self::diffSorted($currentMatched, $nextMatched),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedCollationKeyRowids' => $changes['collationKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'patternUsesTextAffinity' => true,
            'nullPatternMakesLikeUnknown' => true,
            'blobPatternIsRejected' => true,
            'blobValuesDoNotMatchTextLike' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-pattern-text-affinity',
                'sqlite-like-prefix-range',
                'sqlite-nocase-rtrim-collation',
                'sqlite-current-source-next256',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE matching, SQLite scalar text-affinity conversion, ASCII NOCASE/RTRIM collation keys, and current-source diagnostics',
            'non_overlap' => 'next256 covers the LIKE pattern operand TEXT-affinity current-source fence; avoids next253 fixed-pattern value affinity, next246 dynamic ESCAPE affinity, UTF-16 NOCASE/RTRIM cursor work, Unicode GLOB ranges, JSON/VFS/WAL/B-tree/planner clusters, and suite evidence slices',
        ];
    }

    /** @return array<string,mixed> */
    private static function patternPlan(mixed $pattern, ?string $escape, string $collation, string $label): array
    {
        $storage = SQLiteAffinityComparison::storageClass($pattern);
        if ($pattern === null) {
            return self::emptyPatternPlan($storage, null, null);
        }
        if ($pattern instanceof SQLiteBlobValue) {
            return self::emptyPatternPlan($storage, null, "SQLite pattern-affinity LIKE next256 {$label} pattern is BLOB, not text");
        }

        $text = self::textAffinity($pattern, "SQLite pattern-affinity LIKE next256 {$label} pattern");
        if (preg_match('//u', $text) !== 1) {
            return self::emptyPatternPlan($storage, $text, "SQLite pattern-affinity LIKE next256 {$label} pattern text is malformed UTF-8");
        }

        $like = SQLiteLikeCollationPlan::plan($text, $collation, $escape, false);

        return [
            'storage' => $storage,
            'patternText' => $text,
            'patternHex' => strtoupper(bin2hex($text)),
            'patternKey' => self::collationKey($text, $collation),
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'rejectedReason' => $like['rejectedReason'],
            'error' => null,
            'unknown' => false,
        ];
    }

    /** @return array<string,mixed> */
    private static function emptyPatternPlan(string $storage, ?string $text, ?string $error): array
    {
        return [
            'storage' => $storage,
            'patternText' => $text,
            'patternHex' => $text === null ? null : strtoupper(bin2hex($text)),
            'patternKey' => null,
            'prefix' => '',
            'range' => null,
            'indexUsable' => false,
            'rejectedReason' => $error === null ? 'pattern_is_null' : 'pattern_is_not_text',
            'error' => $error,
            'unknown' => true,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidate:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, ?string $pattern, ?string $escape, ?array $range, string $collation): array
    {
        $trace = [];
        $unknown = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite pattern-affinity LIKE next256 row requires option_value');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            if ($pattern === null) {
                $unknown[] = $rowid;
                continue;
            }
            try {
                $likeText = self::textAffinity($row['option_value'], 'SQLite pattern-affinity LIKE next256 option_value');
                if (preg_match('//u', $likeText) !== 1) {
                    throw new \InvalidArgumentException('SQLite pattern-affinity LIKE next256 option_value text is malformed UTF-8');
                }
                $entry = [
                    'rowid' => $rowid,
                    'optionName' => (string) ($row['option_name'] ?? ''),
                    'storage' => SQLiteAffinityComparison::storageClass($row['option_value']),
                    'likeText' => $likeText,
                    'likeTextHex' => strtoupper(bin2hex($likeText)),
                    'collationKey' => self::collationKey($likeText, $collation),
                    'residualMatch' => SQLiteDatabase::likeMatches($likeText, $pattern, $escape, false),
                ];
                $trace[] = $entry;
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, self::sortTrace(...));
        sort($unknown);
        sort($malformed);
        ksort($errors);

        $candidate = [];
        $matched = [];
        $falsePositive = [];
        foreach ($trace as $entry) {
            if (!self::inRange($entry['collationKey'], $range)) {
                continue;
            }
            $candidate[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }

        return [
            'trace' => $trace,
            'candidate' => $candidate,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    private static function textAffinity(mixed $value, string $label): string
    {
        if ($value instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException($label . ' is BLOB, not text');
        }
        if ($value === null) {
            throw new \InvalidArgumentException($label . ' is NULL');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $text = sprintf('%.15g', $value);
            return str_contains($text, '.') || stripos($text, 'e') !== false ? $text : $text . '.0';
        }
        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException($label . ' must be scalar text-affinity input');
    }

    private static function collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'NOCASE' => strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
            'RTRIM' => rtrim($text, ' '),
            default => $text,
        };
    }

    private static function sortTrace(array $left, array $right): int
    {
        return strcmp($left['collationKey'], $right['collationKey']) ?: $left['rowid'] <=> $right['rowid'];
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
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $rowids = self::uniqueSortedInts(array_merge(array_keys($currentByRowid), array_keys($nextByRowid)));
        $storage = [];
        $text = [];
        $key = [];
        $residual = [];
        foreach ($rowids as $rowid) {
            $left = $currentByRowid[$rowid] ?? null;
            $right = $nextByRowid[$rowid] ?? null;
            if ($left === null || $right === null) {
                $storage[] = $rowid;
                $text[] = $rowid;
                $key[] = $rowid;
                $residual[] = $rowid;
                continue;
            }
            if ($left['storage'] !== $right['storage']) {
                $storage[] = $rowid;
            }
            if ($left['likeText'] !== $right['likeText']) {
                $text[] = $rowid;
            }
            if ($left['collationKey'] !== $right['collationKey']) {
                $key[] = $rowid;
            }
            if ($left['residualMatch'] !== $right['residualMatch']) {
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

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param list<int> $values @return list<int> */
    private static function uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function intersectSorted(array $left, array $right): array
    {
        return self::uniqueSortedInts(array_values(array_intersect($left, $right)));
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function diffSorted(array $left, array $right): array
    {
        return self::uniqueSortedInts(array_values(array_diff($left, $right)));
    }

    private static function sqliteTextLength(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        if (preg_match_all('/./us', $text, $matches) === false || implode('', $matches[0]) !== $text) {
            return strlen($text);
        }

        return count($matches[0]);
    }
}
