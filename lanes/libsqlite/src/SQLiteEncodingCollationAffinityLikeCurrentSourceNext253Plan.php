<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext253Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressAutoloadValuePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'yes%',
        ?string $escape = null,
        string $currentSource = 'main.wp_options@252',
        string $nextSource = 'main.wp_options@253',
        int $currentSchemaCookie = 252,
        int $nextSchemaCookie = 253,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::changes($current['decoded'], $next['decoded']);
        $residualChanged = self::residualChanges($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'text-affinity' => $changes['textAffinityChangedRowids'],
            'storage-class' => $changes['storageChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'encoding' => $changes['encodingChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'residual-result' => $residualChanged,
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if (self::rowids($current['matched']) !== self::rowids($next['matched'])) {
            $reasons[] = 'matched-rowset';
        }
        if (self::rowids($current['candidate']) !== self::rowids($next['candidate'])) {
            $reasons[] = 'candidate-rowset';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next253',
            'operator' => 'LIKE',
            'expression' => 'option_value COLLATE NOCASE LIKE ? /* TEXT affinity cursor */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'affinity' => 'TEXT',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'rejectedReason' => $like['rejectedReason'],
            'currentCandidateRowids' => self::rowids($current['candidate']),
            'nextCandidateRowids' => self::rowids($next['candidate']),
            'currentMatchedRowids' => self::rowids($current['matched']),
            'nextMatchedRowids' => self::rowids($next['matched']),
            'matchedRetainedRowids' => self::intersectSorted(self::rowids($current['matched']), self::rowids($next['matched'])),
            'matchedExitedRowids' => self::diffSorted(self::rowids($current['matched']), self::rowids($next['matched'])),
            'matchedEnteredRowids' => self::diffSorted(self::rowids($next['matched']), self::rowids($current['matched'])),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentStorageClasses' => self::map($current['decoded'], 'storageClass'),
            'nextStorageClasses' => self::map($next['decoded'], 'storageClass'),
            'currentTextValues' => self::map($current['decoded'], 'textValue'),
            'nextTextValues' => self::map($next['decoded'], 'textValue'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'currentEncodingNames' => self::map($current['decoded'], 'encodingName'),
            'nextEncodingNames' => self::map($next['decoded'], 'encodingName'),
            'currentByteHex' => self::map($current['decoded'], 'byteHex'),
            'nextByteHex' => self::map($next['decoded'], 'byteHex'),
            'currentResidualMatches' => self::map($current['decoded'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['decoded'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedTextAffinityRowids' => $changes['textAffinityChangedRowids'],
            'changedStorageRowids' => $changes['storageChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedResidualRowids' => $residualChanged,
            'textAffinityAppliedBeforeLike' => true,
            'blobValuesDoNotMatchTextLike' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-text-affinity',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-next253',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, TEXT affinity coercion, ASCII NOCASE LIKE matching, and current-source rowset diagnostics',
            'non_overlap' => 'next253 covers option_value TEXT-affinity LIKE over mixed UTF-8/UTF-16/scalar storage; avoids accepted option_name UTF-16 RTRIM/NOCASE LIKE current-source, Unicode GLOB, malformed insert guards, VFS/WAL/B-tree/JSON/planner clusters, and suite next253 evidence',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decoded:list<array<string,mixed>>,candidate:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $value = self::textAffinityValue($row);
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'storageClass' => self::storageClass($row),
                    'textValue' => $value,
                    'nocaseKey' => strtolower($value),
                    'encodingName' => self::encodingName($row['value_encoding'] ?? null),
                    'byteHex' => isset($row['option_value_bytes']) && is_string($row['option_value_bytes']) ? bin2hex($row['option_value_bytes']) : null,
                    'residualMatch' => SQLiteDatabase::likeMatches($value, $pattern, $escape, false),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, static fn (array $left, array $right): int => strcmp($left['nocaseKey'], $right['nocaseKey']) ?: $left['rowid'] <=> $right['rowid']);
        sort($malformed);
        ksort($errors);

        $candidate = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $range)) {
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
            'decoded' => $decoded,
            'candidate' => $candidate,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
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

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE next253 rows require integer option_id');
        }
        if (!array_key_exists('storage', $row) || !is_string($row['storage'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE next253 rows require storage');
        }
    }

    /** @param array<string,mixed> $row */
    private static function textAffinityValue(array $row): string
    {
        return match (strtolower($row['storage'])) {
            'text' => self::decodeTextRow($row),
            'integer', 'real' => (string) $row['option_value'],
            'null' => '',
            'blob' => throw new \InvalidArgumentException('SQLite TEXT affinity LIKE does not coerce BLOB option_value bytes'),
            default => throw new \InvalidArgumentException('SQLite encoding affinity LIKE next253 unsupported storage class'),
        };
    }

    /** @param array<string,mixed> $row */
    private static function decodeTextRow(array $row): string
    {
        if (!array_key_exists('option_value_bytes', $row) || !is_string($row['option_value_bytes'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE next253 text rows require option_value_bytes');
        }
        if (!array_key_exists('value_encoding', $row) || !is_int($row['value_encoding'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE next253 text rows require integer value_encoding');
        }

        return SQLiteEncodingCollationSourceCursor::decodeText($row['option_value_bytes'], $row['value_encoding']);
    }

    /** @param array<string,mixed> $row */
    private static function storageClass(array $row): string
    {
        return strtolower($row['storage']);
    }

    private static function encodingName(mixed $encoding): ?string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            null => null,
            default => 'unknown',
        };
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return array<string,list<int>> */
    private static function changes(array $left, array $right): array
    {
        $leftById = self::byRowid($left);
        $rightById = self::byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changes = [
            'textChangedRowids' => [],
            'textAffinityChangedRowids' => [],
            'storageChangedRowids' => [],
            'bytesChangedRowids' => [],
            'encodingChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
        ];
        foreach ($rowids as $rowid) {
            $leftRow = $leftById[$rowid] ?? null;
            $rightRow = $rightById[$rowid] ?? null;
            foreach ([
                'textChangedRowids' => 'textValue',
                'textAffinityChangedRowids' => 'textValue',
                'storageChangedRowids' => 'storageClass',
                'bytesChangedRowids' => 'byteHex',
                'encodingChangedRowids' => 'encodingName',
                'nocaseKeyChangedRowids' => 'nocaseKey',
            ] as $bucket => $key) {
                if (($leftRow[$key] ?? null) !== ($rightRow[$key] ?? null)) {
                    $changes[$bucket][] = (int) $rowid;
                }
            }
        }

        return $changes;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function byRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }
        ksort($indexed);

        return $indexed;
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return list<int> */
    private static function residualChanges(array $left, array $right): array
    {
        $leftById = self::byRowid($left);
        $rightById = self::byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($leftById[$rowid]['residualMatch'] ?? null) !== ($rightById[$rowid]['residualMatch'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function intersectSorted(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function diffSorted(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }
}
