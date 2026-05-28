<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressBinaryCollationDefaultLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'Plugin%',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@258',
        string $nextSource = 'main.wp_options@259',
        int $currentSchemaCookie = 258,
        int $nextSchemaCookie = 259,
    ): array {
        if ($escape !== null && self::sqliteTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite BINARY LIKE next259 ESCAPE must be one SQLite character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $patternPlan['binaryRange'];
        $binaryRangeUsable = $caseSensitiveLike && $range['lowerInclusive'] !== '';
        $current = self::scan($currentRows, $pattern, $escape, $caseSensitiveLike, $binaryRangeUsable ? $range : null);
        $next = self::scan($nextRows, $pattern, $escape, $caseSensitiveLike, $binaryRangeUsable ? $range : null);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $changes = self::changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$binaryRangeUsable) {
            $reasons[] = 'binary-prefix-range-unsafe';
        }
        foreach ([
            'text-value' => $changes['textRowids'],
            'text-bytes' => $changes['bytesRowids'],
            'text-encoding' => $changes['encodingRowids'],
            'storage-class' => $changes['storageRowids'],
            'binary-key' => $changes['binaryKeyRowids'],
            'residual-result' => $changes['residualRowids'],
            'candidate-rowset' => self::rowids($current['candidate']) === self::rowids($next['candidate']) ? [] : self::uniqueSortedInts(array_merge(self::rowids($current['candidate']), self::rowids($next['candidate']))),
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next259',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE BINARY LIKE ? /* default LIKE ignores BINARY collation for ASCII folding */',
            'pattern' => $pattern,
            'patternHex' => strtoupper(bin2hex($pattern)),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : strtoupper(bin2hex($escape)),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => 'BINARY',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => strtoupper(bin2hex($patternPlan['prefix'])),
            'binaryRange' => $range,
            'binaryRangeUsable' => $binaryRangeUsable,
            'fullScanResidualRequired' => !$binaryRangeUsable,
            'defaultLikeIgnoresBinaryCollationForAsciiFold' => !$caseSensitiveLike,
            'caseSensitiveLikeRestoresBinaryRangeSafety' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCandidateRowids' => self::rowids($current['candidate']),
            'nextCandidateRowids' => self::rowids($next['candidate']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => self::intersectSorted($currentMatched, $nextMatched),
            'enteredRowids' => self::diffSorted($nextMatched, $currentMatched),
            'exitedRowids' => self::diffSorted($currentMatched, $nextMatched),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'changedTextRowids' => $changes['textRowids'],
            'changedBytesRowids' => $changes['bytesRowids'],
            'changedEncodingRowids' => $changes['encodingRowids'],
            'changedStorageClassRowids' => $changes['storageRowids'],
            'changedBinaryKeyRowids' => $changes['binaryKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentText' => self::fieldByRowid($current['trace'], 'text'),
            'nextText' => self::fieldByRowid($next['trace'], 'text'),
            'currentTextHex' => self::fieldByRowid($current['trace'], 'textHex'),
            'nextTextHex' => self::fieldByRowid($next['trace'], 'textHex'),
            'currentBinaryKeys' => self::fieldByRowid($current['trace'], 'binaryKey'),
            'nextBinaryKeys' => self::fieldByRowid($next['trace'], 'binaryKey'),
            'currentEncodings' => self::fieldByRowid($current['trace'], 'encoding'),
            'nextEncodings' => self::fieldByRowid($next['trace'], 'encoding'),
            'currentStorage' => self::fieldByRowid($current['trace'], 'storage'),
            'nextStorage' => self::fieldByRowid($next['trace'], 'storage'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-default-ascii-fold',
                'sqlite-binary-collation-key',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-current-source-next259',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE matching, BINARY collation byte keys, mixed UTF decoding, scalar text-affinity, and current-source diagnostics',
            'non_overlap' => 'next259 covers default LIKE ASCII folding over a BINARY-collated option_name expression where a BINARY prefix cursor is unsafe until case_sensitive_like is enabled; avoids accepted next255 GLOB bracket-class fallback, next256 dynamic pattern affinity, Unicode GLOB ranges, UTF-16 malformed guards, JSON/VFS/WAL/B-tree/SQL planner clusters, and suite evidence slices',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidate:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, ?array $range): array
    {
        $trace = [];
        $unknown = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row) && !array_key_exists('option_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite BINARY LIKE next259 rows require option_name or option_name_bytes');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            try {
                $coerced = self::coerceText($row);
                if ($coerced === null) {
                    $unknown[] = $rowid;
                    continue;
                }
                if (preg_match('//u', $coerced['text']) !== 1) {
                    throw new \InvalidArgumentException('SQLite BINARY LIKE next259 option_name text is malformed UTF-8');
                }
                $trace[] = [
                    'rowid' => $rowid,
                    'text' => $coerced['text'],
                    'textHex' => strtoupper(bin2hex($coerced['text'])),
                    'binaryKey' => $coerced['text'],
                    'encoding' => $coerced['encoding'],
                    'storage' => $coerced['storage'],
                    'residualMatch' => SQLiteDatabase::likeMatches($coerced['text'], $pattern, $escape, $caseSensitiveLike),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, static fn (array $left, array $right): int => strcmp($left['binaryKey'], $right['binaryKey']) ?: $left['rowid'] <=> $right['rowid']);
        sort($unknown);
        sort($malformed);
        ksort($errors);

        $candidate = [];
        $matched = [];
        $falsePositive = [];
        foreach ($trace as $entry) {
            if (!self::inRange($entry['binaryKey'], $range)) {
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
        if ($range === null) {
            return true;
        }
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array<string,mixed> $row @return ?array{text:string,encoding:string,storage:string} */
    private static function coerceText(array $row): ?array
    {
        if (array_key_exists('option_name_bytes', $row)) {
            if (!is_string($row['option_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite BINARY LIKE next259 byte rows require option_name_bytes and integer text_encoding');
            }

            return [
                'text' => SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']),
                'encoding' => self::encodingName($row['text_encoding']),
                'storage' => 'text',
            ];
        }

        $value = $row['option_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value) || is_bool($value)) {
            return ['text' => (string) (int) $value, 'encoding' => 'UTF-8', 'storage' => 'integer'];
        }
        if (is_float($value)) {
            $text = sprintf('%.15G', $value);
            if (str_contains($text, '.')) {
                $text = rtrim(rtrim($text, '0'), '.');
            }

            return ['text' => $text === '-0' ? '0' : $text, 'encoding' => 'UTF-8', 'storage' => 'real'];
        }
        if (is_string($value)) {
            return ['text' => $value, 'encoding' => 'UTF-8', 'storage' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite BINARY LIKE next259 option_name must be scalar text-affinity input');
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite BINARY LIKE next259 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function sqliteTextLength(string $text): int
    {
        preg_match_all('/./us', $text, $matches);

        return count($matches[0]);
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{textRowids:list<int>,bytesRowids:list<int>,encodingRowids:list<int>,storageRowids:list<int>,binaryKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function changes(array $current, array $next): array
    {
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $rowids = array_values(array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)));
        sort($rowids);
        $changes = [
            'textRowids' => [],
            'bytesRowids' => [],
            'encodingRowids' => [],
            'storageRowids' => [],
            'binaryKeyRowids' => [],
            'residualRowids' => [],
        ];

        foreach ($rowids as $rowid) {
            foreach ([
                'text' => 'textRowids',
                'textHex' => 'bytesRowids',
                'encoding' => 'encodingRowids',
                'storage' => 'storageRowids',
                'binaryKey' => 'binaryKeyRowids',
                'residualMatch' => 'residualRowids',
            ] as $field => $bucket) {
                if ($currentByRowid[$rowid][$field] !== $nextByRowid[$rowid][$field]) {
                    $changes[$bucket][] = $rowid;
                }
            }
        }

        return $changes;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function intersectSorted(array $left, array $right): array
    {
        $result = array_values(array_intersect($left, $right));
        sort($result);

        return $result;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function diffSorted(array $left, array $right): array
    {
        $result = array_values(array_diff($left, $right));
        sort($result);

        return $result;
    }

    /** @param list<int> $values @return list<int> */
    private static function uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = $row;
        }
        ksort($byRowid);

        return $byRowid;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[$row['rowid']] = $row[$field];
        }
        ksort($values);

        return $values;
    }
}
