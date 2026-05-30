<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValuePlan(
        array $currentRows,
        array $nextRows,
        string $column,
        string $pattern,
        string $operator = 'LIKE',
        string $affinity = 'TEXT',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = true,
        string $currentSource = 'main.app_settings',
        string $nextSource = 'main.app_settings',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite LIKE/GLOB affinity range operator must be LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escape !== null) {
            throw new \InvalidArgumentException('SQLite GLOB affinity range does not accept ESCAPE');
        }

        $range = self::rangeForPattern($pattern, $operator, $collation, $escape, $caseSensitiveLike);
        $current = self::matchedRows($currentRows, $column, $pattern, $operator, $affinity, $collation, $escape, $caseSensitiveLike, $range);
        $next = self::matchedRows($nextRows, $column, $pattern, $operator, $affinity, $collation, $escape, $caseSensitiveLike, $range);
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $changedText = [];
        $changedStorage = [];
        $changedRangeClass = [];
        $changedBytes = [];
        foreach ($retained as $rowid) {
            $before = $currentByRowid[$rowid];
            $after = $nextByRowid[$rowid];
            if ($before['text'] !== $after['text']) {
                $changedText[] = $rowid;
            }
            if ($before['storage'] !== $after['storage']) {
                $changedStorage[] = $rowid;
            }
            if ($before['rangeClass'] !== $after['rangeClass']) {
                $changedRangeClass[] = $rowid;
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
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }
        if ($changedText !== []) {
            $reasons[] = 'text-affinity';
        }
        if ($changedRangeClass !== []) {
            $reasons[] = 'range-class';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'operator' => $operator,
            'pattern' => $pattern,
            'column' => $column,
            'affinity' => strtoupper($affinity),
            'collation' => strtoupper($collation),
            'escape' => $escape,
            'caseSensitiveLike' => $caseSensitiveLike,
            'range' => $range,
            'rangeUsable' => $range !== null,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedTextRowids' => $changedText,
            'changedStorageRowids' => $changedStorage,
            'changedRangeClassRowids' => $changedRangeClass,
            'changedBytesRowids' => $changedBytes,
            'currentRows' => $current,
            'nextRows' => $next,
            'currentTexts' => self::fieldByRowid($currentByRowid, 'text'),
            'nextTexts' => self::fieldByRowid($nextByRowid, 'text'),
            'currentRangeClasses' => self::fieldByRowid($currentByRowid, 'rangeClass'),
            'nextRangeClasses' => self::fieldByRowid($nextByRowid, 'rangeClass'),
            'currentBytesHex' => self::fieldByRowid($currentByRowid, 'bytesHex'),
            'nextBytesHex' => self::fieldByRowid($nextByRowid, 'bytesHex'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-glob-affinity-range',
                'sqlite-current-source-next104',
            ],
        ];
    }

    /**
     * @return ?array{lowerInclusive:string,upperBound:?string}
     */
    private static function rangeForPattern(string $pattern, string $operator, string $collation, ?string $escape, bool $caseSensitiveLike): ?array
    {
        return $operator === 'LIKE'
            ? SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike)['range']
            : SQLiteDatabase::globPrefixRangeBounds($pattern);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(
        array $rows,
        string $column,
        string $pattern,
        string $operator,
        string $affinity,
        string $collation,
        ?string $escape,
        bool $caseSensitiveLike,
        ?array $range,
    ): array {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite LIKE/GLOB affinity range row is missing {$column}");
            }
            $text = self::coerceText($row[$column], $affinity);
            if ($text === null) {
                continue;
            }
            $residual = $operator === 'LIKE'
                ? SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)
                : SQLiteDatabase::globMatches($text, $pattern);
            if (!$residual) {
                continue;
            }

            $matched[] = [
                'rowid' => is_int($row['setting_id'] ?? null) ? $row['setting_id'] : $index + 1,
                'text' => $text,
                'storage' => SQLiteAffinityComparison::storageClass($row[$column]),
                'rangeClass' => self::rangeClass($text, $range, $collation),
                'bytesHex' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($text, 'UTF-16LE')),
                'payload' => $row,
            ];
        }

        usort($matched, static function (array $left, array $right) use ($collation): int {
            $comparison = SQLiteAffinityComparison::compare($left['text'], $right['text'], 'TEXT', 'TEXT', $collation);
            if ($comparison !== null && $comparison !== 0) {
                return $comparison;
            }

            return $left['rowid'] <=> $right['rowid'];
        });

        return $matched;
    }

    private static function coerceText(mixed $value, string $affinity): ?string
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        $affinity = strtoupper($affinity);
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite LIKE/GLOB affinity range requires well-formed UTF-8 text');
            }

            return $value;
        }
        if (!in_array($affinity, ['TEXT', 'NUMERIC', 'INTEGER', 'REAL', 'NONE'], true)) {
            SQLiteAffinityComparison::coercedPair(0, 0, $affinity, $affinity);
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

        throw new \InvalidArgumentException('SQLite LIKE/GLOB affinity range requires scalar setting values');
    }

    /**
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     */
    private static function rangeClass(string $text, ?array $range, string $collation): string
    {
        if ($range === null) {
            return 'residual-only';
        }
        $lower = SQLiteAffinityComparison::compare($text, $range['lowerInclusive'], 'TEXT', 'TEXT', $collation);
        $upper = $range['upperBound'] === null ? -1 : SQLiteAffinityComparison::compare($text, $range['upperBound'], 'TEXT', 'TEXT', $collation);
        if ($lower !== null && $lower < 0) {
            return 'before-range';
        }
        if ($upper !== null && $upper >= 0) {
            return 'after-range';
        }

        return 'in-range';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
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
}
