<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext257Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameNumericAffinityLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = '2024%',
        ?string $escape = null,
        string $currentSource = 'main.wp_options@256',
        string $nextSource = 'main.wp_options@257',
        int $currentSchemaCookie = 256,
        int $nextSchemaCookie = 257,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::changes($current['decoded'], $next['decoded']);
        $residualChanges = self::residualChanges($current['decoded'], $next['decoded']);
        $currentCandidates = self::rowids($current['candidate']);
        $nextCandidates = self::rowids($next['candidate']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'name-affinity' => $changes['textChangedRowids'],
            'storage-class' => $changes['storageChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'encoding' => $changes['encodingChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'residual-result' => $residualChanges,
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next257',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE NOCASE LIKE ? /* NUMERIC storage coerced through TEXT affinity */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'affinity' => 'TEXT-for-LIKE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'rejectedReason' => $like['rejectedReason'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::intersectSorted($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::diffSorted($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::diffSorted($nextMatched, $currentMatched),
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
            'changedStorageRowids' => $changes['storageChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedResidualRowids' => $residualChanges,
            'numericStorageCoercedBeforeLike' => true,
            'blobAndNullRemainOutsideLikeCursor' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-nocase-prefix-range',
                'sqlite-text-affinity',
                'sqlite-utf16-decode',
                'sqlite-current-source-next257',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE prefix planning, TEXT affinity coercion for numeric storage, UTF-16 decode, and current-source rowset invalidation diagnostics',
            'non_overlap' => 'next257 covers option_name numeric-storage TEXT coercion entering/leaving a NOCASE LIKE cursor; avoids accepted next253 option_value TEXT-affinity LIKE, next245 dangling ESCAPE, Unicode GLOB ranges, UTF-16 malformed insert guards, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
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
                $text = self::textAffinityName($row);
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'storageClass' => strtolower($row['storage']),
                    'textValue' => $text,
                    'nocaseKey' => self::asciiLower($text),
                    'encodingName' => self::encodingName($row['name_encoding'] ?? null),
                    'byteHex' => isset($row['option_name_bytes']) && is_string($row['option_name_bytes']) ? bin2hex($row['option_name_bytes']) : null,
                    'residualMatch' => SQLiteDatabase::likeMatches($text, $pattern, $escape, false),
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

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE next257 rows require integer option_id');
        }
        if (!array_key_exists('storage', $row) || !is_string($row['storage'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE next257 rows require storage');
        }
    }

    /** @param array<string,mixed> $row */
    private static function textAffinityName(array $row): string
    {
        return match (strtolower($row['storage'])) {
            'text' => self::decodeTextName($row),
            'integer', 'real' => (string) $row['option_name'],
            'blob' => throw new \InvalidArgumentException('SQLite TEXT affinity LIKE does not coerce BLOB option_name bytes'),
            'null' => throw new \InvalidArgumentException('SQLite LIKE over NULL option_name remains unknown'),
            default => throw new \InvalidArgumentException('SQLite encoding affinity LIKE next257 unsupported storage class'),
        };
    }

    /** @param array<string,mixed> $row */
    private static function decodeTextName(array $row): string
    {
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE next257 text rows require option_name_bytes');
        }
        if (!array_key_exists('name_encoding', $row) || !is_int($row['name_encoding'])) {
            throw new \InvalidArgumentException('SQLite encoding affinity LIKE next257 text rows require integer name_encoding');
        }

        return SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['name_encoding']);
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
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

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
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

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return array<string,list<int>> */
    private static function changes(array $left, array $right): array
    {
        $leftById = self::byRowid($left);
        $rightById = self::byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changes = [
            'textChangedRowids' => [],
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
