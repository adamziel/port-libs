<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16CollationAffinitySourceSwitchPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{
     *   probe:mixed,
     *   leftAffinity:string,
     *   rightAffinity:string,
     *   collation:string,
     *   currentSource:string,
     *   nextSource:string,
     *   sourceChanged:bool,
     *   cursorInvalidated:bool,
     *   invalidationReasons:list<string>,
     *   currentRowids:list<int>,
     *   nextRowids:list<int>,
     *   retainedRowids:list<int>,
     *   exitedRowids:list<int>,
     *   enteredRowids:list<int>,
     *   changedEncodingRowids:list<int>,
     *   changedBytesRowids:list<int>,
     *   changedDecodedValueRowids:list<int>,
     *   changedCoercedStorageRowids:list<int>,
     *   changedComparisonRowids:list<int>,
     *   currentEncodings:array<int,string>,
     *   nextEncodings:array<int,string>,
     *   currentBytesHex:array<int,string>,
     *   nextBytesHex:array<int,string>,
     *   currentComparisons:array<int,int|null>,
     *   nextComparisons:array<int,int|null>,
     *   dependencies:list<string>
     * }
     */
    public static function settingRowValueSourceSwitch(
        array $currentRows,
        array $nextRows,
        mixed $probe,
        string $leftAffinity = 'TEXT',
        string $rightAffinity = 'TEXT',
        string $collation = 'BINARY',
        string $currentSource = 'current',
        string $nextSource = 'next',
    ): array {
        $currentMatches = SQLiteUtf16CollationAffinityCursor::settingRowValueSeek(
            $currentRows,
            $probe,
            $leftAffinity,
            $rightAffinity,
            $collation,
        );
        $nextMatches = SQLiteUtf16CollationAffinityCursor::settingRowValueSeek(
            $nextRows,
            $probe,
            $leftAffinity,
            $rightAffinity,
            $collation,
        );

        $currentByRowid = self::normalizedRows($currentRows, $leftAffinity, $rightAffinity, $collation, $probe);
        $nextByRowid = self::normalizedRows($nextRows, $leftAffinity, $rightAffinity, $collation, $probe);
        $currentRowids = self::rowids($currentMatches);
        $nextRowids = self::rowids($nextMatches);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $changedEncodings = [];
        $changedBytes = [];
        $changedDecoded = [];
        $changedStorage = [];
        $changedComparisons = [];

        $matchedRowids = array_unique(array_merge($currentRowids, $nextRowids));
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if (!in_array($rowid, $matchedRowids, true)) {
                continue;
            }
            $current = $currentByRowid[$rowid];
            $next = $nextByRowid[$rowid];
            if ($current['encoding'] !== $next['encoding']) {
                $changedEncodings[] = $rowid;
            }
            if ($current['bytesHex'] !== $next['bytesHex']) {
                $changedBytes[] = $rowid;
            }
            if ($current['value'] !== $next['value']) {
                $changedDecoded[] = $rowid;
            }
            if ($current['coercedStorage'] !== $next['coercedStorage']) {
                $changedStorage[] = $rowid;
            }
            if ($current['comparisonToProbe'] !== $next['comparisonToProbe']) {
                $changedComparisons[] = $rowid;
            }
        }

        sort($changedEncodings);
        sort($changedBytes);
        sort($changedDecoded);
        sort($changedStorage);
        sort($changedComparisons);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($changedEncodings !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'value-bytes';
        }
        if ($changedDecoded !== []) {
            $reasons[] = 'decoded-value';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'coerced-storage';
        }
        if ($changedComparisons !== []) {
            $reasons[] = 'comparison-to-probe';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'probe' => $probe,
            'leftAffinity' => strtoupper($leftAffinity),
            'rightAffinity' => strtoupper($rightAffinity),
            'collation' => strtoupper($collation),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'sourceChanged' => $currentSource !== $nextSource,
            'cursorInvalidated' => $reasons !== [],
            'invalidationReasons' => $reasons,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedEncodingRowids' => $changedEncodings,
            'changedBytesRowids' => $changedBytes,
            'changedDecodedValueRowids' => $changedDecoded,
            'changedCoercedStorageRowids' => $changedStorage,
            'changedComparisonRowids' => $changedComparisons,
            'currentEncodings' => self::fieldMap($currentByRowid, 'encoding'),
            'nextEncodings' => self::fieldMap($nextByRowid, 'encoding'),
            'currentBytesHex' => self::fieldMap($currentByRowid, 'bytesHex'),
            'nextBytesHex' => self::fieldMap($nextByRowid, 'bytesHex'),
            'currentComparisons' => self::fieldMap($currentByRowid, 'comparisonToProbe'),
            'nextComparisons' => self::fieldMap($nextByRowid, 'comparisonToProbe'),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-affinity-comparison',
                'sqlite-current-next-source-invalidation',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{value:mixed,encoding:string,bytesHex:string,coercedStorage:string,comparisonToProbe:int|null}>
     */
    private static function normalizedRows(array $rows, string $leftAffinity, string $rightAffinity, string $collation, mixed $probe): array
    {
        $remaining = SQLiteUtf16CollationAffinityCursor::settingRowValueSeek($rows, $probe, $leftAffinity, $rightAffinity, $collation);
        $byRowid = [];
        foreach ($remaining as $row) {
            $byRowid[$row['rowid']] = [
                'value' => $row['value'],
                'encoding' => $row['encoding'] ?? 'native',
                'bytesHex' => $row['valueBytesHex'] ?? '',
                'coercedStorage' => SQLiteAffinityComparison::storageClass(self::applyAffinity($row['value'], $leftAffinity)),
                'comparisonToProbe' => $row['comparisonToProbe'],
            ];
        }

        return $byRowid;
    }

    /**
     * @param list<array{rowid:int}> $rows
     * @return list<int>
     */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    private static function applyAffinity(mixed $value, string $affinity): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        $normalized = strtoupper($affinity);
        if (in_array($normalized, ['INT', 'INTEGER', 'REAL', 'FLOAT', 'DOUBLE', 'NUM', 'NUMERIC', 'BOOLEAN', 'DATE', 'DATETIME'], true)) {
            $text = $value instanceof SQLiteBlobValue ? $value->bytes : $value;
            $trimmed = trim($text);
            if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) !== 1) {
                return $value;
            }
            if (preg_match('/^[+-]?[0-9]+$/', $trimmed) === 1 && self::integerLiteralFitsInt64($trimmed)) {
                return (int) $trimmed;
            }
            $real = (float) $trimmed;

            return is_finite($real) && floor($real) === $real && preg_match('/[.eE]/', $trimmed) === 1 && self::integerLiteralFitsInt64(sprintf('%.0F', $real)) ? (int) $real : $real;
        }
        if (in_array($normalized, ['CHAR', 'CLOB', 'VARCHAR', 'TEXT'], true)) {
            if ($value instanceof SQLiteBlobValue || is_string($value)) {
                return $value;
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return (string) $value;
        }
        if (in_array($normalized, ['BLOB', 'NONE', ''], true)) {
            return $value;
        }

        throw new \InvalidArgumentException("SQLite comparison affinity {$affinity} is not supported");
    }

    private static function integerLiteralFitsInt64(string $literal): bool
    {
        $literal = trim($literal);
        $negative = str_starts_with($literal, '-');
        $digits = ltrim($literal, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return true;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        $length = strlen($digits);
        $limitLength = strlen($limit);
        if ($length !== $limitLength) {
            return $length < $limitLength;
        }

        return strcmp($digits, $limit) <= 0;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,mixed>
     */
    private static function fieldMap(array $rows, string $field): array
    {
        $map = [];
        foreach ($rows as $rowid => $row) {
            $map[$rowid] = $row[$field];
        }

        return $map;
    }
}
