<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingNumericAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValueComparisonPlan(
        array $currentRows,
        array $nextRows,
        string $column,
        mixed $probe,
        string $operator = '=',
        string $columnAffinity = 'NUMERIC',
        string $probeAffinity = 'NONE',
        string $collation = 'BINARY',
        int|string $currentEncoding = 'UTF-16LE',
        int|string $nextEncoding = 'UTF-16LE',
        string $currentSource = 'main.wp_options',
        string $nextSource = 'main.wp_options',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
    ): array {
        $operator = self::normalizeOperator($operator);
        $collation = self::normalizeCollation($collation);
        $currentEncodingName = self::encodingName($currentEncoding);
        $nextEncodingName = self::encodingName($nextEncoding);
        $current = self::matchingRows($currentRows, $column, $probe, $operator, $columnAffinity, $probeAffinity, $collation, $currentEncodingName);
        $next = self::matchingRows($nextRows, $column, $probe, $operator, $columnAffinity, $probeAffinity, $collation, $nextEncodingName);

        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $changedValue = [];
        $changedCoerced = [];
        $changedStorage = [];
        $changedCoercedStorage = [];
        $changedEncoding = [];
        $changedBytes = [];
        foreach ($retained as $rowid) {
            $before = $currentByRowid[$rowid];
            $after = $nextByRowid[$rowid];
            if ($before['value'] !== $after['value']) {
                $changedValue[] = $rowid;
            }
            if ($before['coercedValue'] !== $after['coercedValue']) {
                $changedCoerced[] = $rowid;
            }
            if ($before['storage'] !== $after['storage']) {
                $changedStorage[] = $rowid;
            }
            if ($before['coercedStorage'] !== $after['coercedStorage']) {
                $changedCoercedStorage[] = $rowid;
            }
            if ($before['textEncoding'] !== $after['textEncoding']) {
                $changedEncoding[] = $rowid;
            }
            if ($before['bytesHex'] !== $after['bytesHex']) {
                $changedBytes[] = $rowid;
            }
        }

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentEncodingName !== $nextEncodingName) {
            $reasons[] = 'scan-encoding';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }
        if ($changedCoercedStorage !== []) {
            $reasons[] = 'numeric-affinity-storage';
        }
        if ($changedValue !== []) {
            $reasons[] = 'raw-value';
        }
        if ($changedCoerced !== []) {
            $reasons[] = 'numeric-affinity-value';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }

        [, $coercedProbe] = self::coercedComparisonValues(0, $probe, $columnAffinity, $probeAffinity);

        return [
            'operator' => $operator,
            'column' => $column,
            'columnAffinity' => strtoupper($columnAffinity),
            'probeAffinity' => strtoupper($probeAffinity),
            'probe' => $probe,
            'probeStorage' => SQLiteAffinityComparison::storageClass($probe),
            'probeCoercedValue' => $coercedProbe,
            'probeCoercedStorage' => SQLiteAffinityComparison::storageClass($coercedProbe),
            'collation' => $collation,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentEncoding' => $currentEncodingName,
            'nextEncoding' => $nextEncodingName,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedValueRowids' => $changedValue,
            'changedCoercedValueRowids' => $changedCoerced,
            'changedStorageRowids' => $changedStorage,
            'changedCoercedStorageRowids' => $changedCoercedStorage,
            'changedEncodingRowids' => $changedEncoding,
            'changedBytesRowids' => $changedBytes,
            'currentValues' => self::fieldByRowid($currentByRowid, 'value'),
            'nextValues' => self::fieldByRowid($nextByRowid, 'value'),
            'currentCoercedValues' => self::fieldByRowid($currentByRowid, 'coercedValue'),
            'nextCoercedValues' => self::fieldByRowid($nextByRowid, 'coercedValue'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storage'),
            'currentCoercedStorage' => self::fieldByRowid($currentByRowid, 'coercedStorage'),
            'nextCoercedStorage' => self::fieldByRowid($nextByRowid, 'coercedStorage'),
            'currentBytesHex' => self::fieldByRowid($currentByRowid, 'bytesHex'),
            'nextBytesHex' => self::fieldByRowid($nextByRowid, 'bytesHex'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-numeric-affinity',
                'sqlite-collation-comparison',
                'sqlite-current-source-next107',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,value:mixed,coercedValue:mixed,storage:string,coercedStorage:string,bytesHex:?string,textEncoding:string,payload:array<string,mixed>}>
     */
    private static function matchingRows(
        array $rows,
        string $column,
        mixed $probe,
        string $operator,
        string $columnAffinity,
        string $probeAffinity,
        string $collation,
        string $encoding,
    ): array {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite numeric affinity row is missing {$column}");
            }
            $value = $row[$column];
            [$left, $right] = self::coercedComparisonValues($value, $probe, $columnAffinity, $probeAffinity);
            $comparison = self::compareValues($left, $right, $collation);
            if ($comparison === null || !self::operatorMatches($comparison, $operator)) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'value' => $value,
                'coercedValue' => $left,
                'storage' => SQLiteAffinityComparison::storageClass($value),
                'coercedStorage' => SQLiteAffinityComparison::storageClass($left),
                'bytesHex' => is_string($value) ? bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding)) : null,
                'textEncoding' => $encoding,
                'payload' => $row,
            ];
        }

        usort($matched, static function (array $left, array $right) use ($collation): int {
            $comparison = self::compareValues($left['coercedValue'], $right['coercedValue'], $collation);
            if ($comparison === null) {
                $comparison = 0;
            }

            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return $matched;
    }

    private static function normalizeOperator(string $operator): string
    {
        return match (strtoupper(trim($operator))) {
            '=', '==', 'IS' => '=',
            '!=', '<>', 'IS NOT' => '!=',
            '<', '<=', '>', '>=' => $operator,
            default => throw new \InvalidArgumentException("SQLite numeric affinity comparison operator {$operator} is not supported"),
        };
    }

    private static function normalizeCollation(string $collation): string
    {
        return match (strtoupper($collation)) {
            'BINARY' => 'BINARY',
            'NOCASE' => 'NOCASE',
            'RTRIM' => 'RTRIM',
            default => throw new \InvalidArgumentException("SQLite numeric affinity comparison collation {$collation} is not supported"),
        };
    }

    /**
     * @return array{0:mixed,1:mixed}
     */
    private static function coercedComparisonValues(mixed $left, mixed $right, string $leftAffinity, string $rightAffinity): array
    {
        $leftAffinity = self::normalizeAffinity($leftAffinity);
        $rightAffinity = self::normalizeAffinity($rightAffinity);

        if (self::isNumericAffinity($leftAffinity)) {
            $left = self::applyNumericAffinity($left);
            if (in_array($rightAffinity, ['NONE', 'TEXT', 'BLOB'], true)) {
                $right = self::applyNumericAffinity($right);
            }
        }
        if (self::isNumericAffinity($rightAffinity)) {
            $right = self::applyNumericAffinity($right);
            if (in_array($leftAffinity, ['NONE', 'TEXT', 'BLOB'], true)) {
                $left = self::applyNumericAffinity($left);
            }
        }
        if ($leftAffinity === 'TEXT' && $rightAffinity === 'NONE') {
            $right = self::applyTextAffinity($right);
        } elseif ($rightAffinity === 'TEXT' && $leftAffinity === 'NONE') {
            $left = self::applyTextAffinity($left);
        }

        return [$left, $right];
    }

    private static function compareValues(mixed $left, mixed $right, string $collation): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }
        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($leftRank === 1) {
            return ((float) $left) <=> ((float) $right);
        }
        $leftText = $left instanceof SQLiteBlobValue ? $left->bytes : (string) $left;
        $rightText = $right instanceof SQLiteBlobValue ? $right->bytes : (string) $right;
        if ($leftRank === 2) {
            return match ($collation) {
                'BINARY' => strcmp($leftText, $rightText),
                'NOCASE' => strcmp(strtolower($leftText), strtolower($rightText)),
                'RTRIM' => strcmp(rtrim($leftText, ' '), rtrim($rightText, ' ')),
            };
        }

        return strcmp($leftText, $rightText);
    }

    private static function normalizeAffinity(string $affinity): string
    {
        return match (strtoupper($affinity)) {
            'INT', 'INTEGER' => 'INTEGER',
            'REAL', 'FLOAT', 'DOUBLE' => 'REAL',
            'NUM', 'NUMERIC', 'BOOLEAN', 'DATE', 'DATETIME' => 'NUMERIC',
            'CHAR', 'CLOB', 'VARCHAR', 'TEXT' => 'TEXT',
            'BLOB', 'NONE', '' => 'NONE',
            default => throw new \InvalidArgumentException("SQLite numeric affinity comparison affinity {$affinity} is not supported"),
        };
    }

    private static function isNumericAffinity(string $affinity): bool
    {
        return in_array($affinity, ['INTEGER', 'REAL', 'NUMERIC'], true);
    }

    private static function applyNumericAffinity(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if ($value instanceof SQLiteBlobValue) {
            return $value;
        }

        $trimmed = trim($value);
        if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) !== 1) {
            return $value;
        }
        if (preg_match('/^[+-]?[0-9]+$/', $trimmed) === 1) {
            return (int) $trimmed;
        }

        $real = (float) $trimmed;

        return is_finite($real) && floor($real) === $real ? (int) $real : $real;
    }

    private static function applyTextAffinity(mixed $value): mixed
    {
        if ($value === null || is_string($value) || $value instanceof SQLiteBlobValue) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function sortRank(mixed $value): int
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }
        if ($value instanceof SQLiteBlobValue) {
            return 3;
        }

        throw new \InvalidArgumentException('SQLite numeric affinity comparison values must be scalar, BLOB, or NULL');
    }

    private static function operatorMatches(int $comparison, string $operator): bool
    {
        return match ($operator) {
            '=' => $comparison === 0,
            '!=' => $comparison !== 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
        };
    }

    /**
     * @param list<array{rowid:int,value:mixed,coercedValue:mixed,storage:string,coercedStorage:string,bytesHex:?string,textEncoding:string,payload:array<string,mixed>}> $rows
     * @return array<int,array{rowid:int,value:mixed,coercedValue:mixed,storage:string,coercedStorage:string,bytesHex:?string,textEncoding:string,payload:array<string,mixed>}>
     */
    private static function rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,mixed>
     */
    private static function fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function encodingName(int|string $encoding): string
    {
        if (is_int($encoding)) {
            return match ($encoding) {
                1 => 'UTF-8',
                2 => 'UTF-16LE',
                3 => 'UTF-16BE',
                default => throw new \InvalidArgumentException('SQLite numeric affinity current/next encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
            };
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 'UTF-8',
            'UTF-16LE', 'UTF16LE' => 'UTF-16LE',
            'UTF-16BE', 'UTF16BE' => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite numeric affinity current/next encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
