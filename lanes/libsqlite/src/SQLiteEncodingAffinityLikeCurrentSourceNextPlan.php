<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingAffinityLikeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function optionRowValuePlan(
        array $currentRows,
        array $nextRows,
        string $column,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = true,
        int|string $currentEncoding = 'UTF-16LE',
        int|string $nextEncoding = 'UTF-16LE',
        string $currentSource = 'main.wp_options',
        string $nextSource = 'main.wp_options',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite encoding affinity current/next operator must be LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escape !== null) {
            throw new \InvalidArgumentException('SQLite GLOB current/next scan does not accept ESCAPE');
        }

        $range = $operator === 'LIKE'
            ? SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike)['range']
            : SQLiteDatabase::globPrefixRangeBounds($pattern);

        $current = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::optionRowValueScan(
            $currentRows,
            $column,
            $pattern,
            $operator,
            $collation,
            $escape,
            $caseSensitiveLike,
            $currentEncoding,
        );
        $next = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::optionRowValueScan(
            $nextRows,
            $column,
            $pattern,
            $operator,
            $collation,
            $escape,
            $caseSensitiveLike,
            $nextEncoding,
        );

        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $changedText = [];
        $changedStorage = [];
        $changedEncoding = [];
        $changedBytes = [];
        foreach ($retained as $rowid) {
            $before = $currentByRowid[$rowid];
            $after = $nextByRowid[$rowid];
            if ($before['text'] !== $after['text']) {
                $changedText[] = $rowid;
            }
            if ($before['originalStorage'] !== $after['originalStorage']) {
                $changedStorage[] = $rowid;
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
        if ($currentEncoding !== $nextEncoding) {
            $reasons[] = 'scan-encoding';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }
        if ($changedText !== []) {
            $reasons[] = 'text-affinity';
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

        return [
            'operator' => $operator,
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => strtoupper($collation),
            'caseSensitiveLike' => $caseSensitiveLike,
            'column' => $column,
            'range' => $range,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentEncoding' => self::encodingName($currentEncoding),
            'nextEncoding' => self::encodingName($nextEncoding),
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedTextRowids' => $changedText,
            'changedStorageRowids' => $changedStorage,
            'changedEncodingRowids' => $changedEncoding,
            'changedBytesRowids' => $changedBytes,
            'currentTexts' => self::fieldByRowid($currentByRowid, 'text'),
            'nextTexts' => self::fieldByRowid($nextByRowid, 'text'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'originalStorage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'originalStorage'),
            'currentBytesHex' => self::fieldByRowid($currentByRowid, 'bytesHex'),
            'nextBytesHex' => self::fieldByRowid($nextByRowid, 'bytesHex'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => ['sqlite-text-affinity', 'sqlite-like-glob-current-source-next94'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function optionRowValueDynamicPatternPlan(
        array $currentRows,
        array $nextRows,
        string $valueColumn,
        string $patternColumn,
        ?string $escapeColumn = null,
        bool $caseSensitiveLike = false,
        int|string $currentEncoding = 'UTF-16LE',
        int|string $nextEncoding = 'UTF-16LE',
        string $currentSource = 'main.wp_options',
        string $nextSource = 'main.wp_options',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
    ): array {
        return self::optionRowValueDynamicLikeGlobPlan(
            $currentRows,
            $nextRows,
            $valueColumn,
            $patternColumn,
            'LIKE',
            $escapeColumn,
            $caseSensitiveLike,
            $currentEncoding,
            $nextEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function optionRowValueDynamicLikeGlobPlan(
        array $currentRows,
        array $nextRows,
        string $valueColumn,
        string $patternColumn,
        string $operator = 'LIKE',
        ?string $escapeColumn = null,
        bool $caseSensitiveLike = false,
        int|string $currentEncoding = 'UTF-16LE',
        int|string $nextEncoding = 'UTF-16LE',
        string $currentSource = 'main.wp_options',
        string $nextSource = 'main.wp_options',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite dynamic LIKE/GLOB current/next operator must be LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escapeColumn !== null) {
            throw new \InvalidArgumentException('SQLite dynamic GLOB current/next scan does not accept ESCAPE');
        }

        $currentEncodingName = self::encodingName($currentEncoding);
        $nextEncodingName = self::encodingName($nextEncoding);
        $current = self::dynamicPatternRows($currentRows, $valueColumn, $patternColumn, $operator, $escapeColumn, $caseSensitiveLike, $currentEncodingName);
        $next = self::dynamicPatternRows($nextRows, $valueColumn, $patternColumn, $operator, $escapeColumn, $caseSensitiveLike, $nextEncodingName);

        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $currentRowids = array_column($current, 'rowid');
        $nextRowids = array_column($next, 'rowid');
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $changedValue = [];
        $changedPattern = [];
        $changedEscape = [];
        $changedStorage = [];
        $changedEncoding = [];
        $changedBytes = [];
        foreach ($retained as $rowid) {
            $before = $currentByRowid[$rowid];
            $after = $nextByRowid[$rowid];
            if ($before['text'] !== $after['text']) {
                $changedValue[] = $rowid;
            }
            if ($before['patternText'] !== $after['patternText']) {
                $changedPattern[] = $rowid;
            }
            if ($before['escapeText'] !== $after['escapeText']) {
                $changedEscape[] = $rowid;
            }
            if ($before['originalStorage'] !== $after['originalStorage'] || $before['patternStorage'] !== $after['patternStorage']) {
                $changedStorage[] = $rowid;
            }
            if ($before['textEncoding'] !== $after['textEncoding']) {
                $changedEncoding[] = $rowid;
            }
            if (
                $before['bytesHex'] !== $after['bytesHex']
                || $before['patternBytesHex'] !== $after['patternBytesHex']
                || $before['escapeBytesHex'] !== $after['escapeBytesHex']
            ) {
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
        if ($changedValue !== []) {
            $reasons[] = 'text-affinity';
        }
        if ($changedPattern !== []) {
            $reasons[] = 'pattern-affinity';
        }
        if ($changedEscape !== []) {
            $reasons[] = 'escape-affinity';
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

        return [
            'operator' => $operator,
            'valueColumn' => $valueColumn,
            'patternColumn' => $patternColumn,
            'escapeColumn' => $escapeColumn,
            'caseSensitiveLike' => $caseSensitiveLike,
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
            'changedPatternRowids' => $changedPattern,
            'changedEscapeRowids' => $changedEscape,
            'changedStorageRowids' => $changedStorage,
            'changedEncodingRowids' => $changedEncoding,
            'changedBytesRowids' => $changedBytes,
            'currentTexts' => self::fieldByRowid($currentByRowid, 'text'),
            'nextTexts' => self::fieldByRowid($nextByRowid, 'text'),
            'currentPatterns' => self::fieldByRowid($currentByRowid, 'patternText'),
            'nextPatterns' => self::fieldByRowid($nextByRowid, 'patternText'),
            'currentEscapes' => self::fieldByRowid($currentByRowid, 'escapeText'),
            'nextEscapes' => self::fieldByRowid($nextByRowid, 'escapeText'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'originalStorage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'originalStorage'),
            'currentPatternStorage' => self::fieldByRowid($currentByRowid, 'patternStorage'),
            'nextPatternStorage' => self::fieldByRowid($nextByRowid, 'patternStorage'),
            'currentBytesHex' => self::fieldByRowid($currentByRowid, 'bytesHex'),
            'nextBytesHex' => self::fieldByRowid($nextByRowid, 'bytesHex'),
            'currentPatternBytesHex' => self::fieldByRowid($currentByRowid, 'patternBytesHex'),
            'nextPatternBytesHex' => self::fieldByRowid($nextByRowid, 'patternBytesHex'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-text-affinity',
                $operator === 'LIKE'
                    ? 'sqlite-like-dynamic-pattern-current-source-next99'
                    : 'sqlite-glob-dynamic-pattern-current-source-next105',
            ],
        ];
    }

    /**
     * @param list<array{rowid:int,text:string,originalStorage:string,bytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int}> $rows
     * @return array<int,array{rowid:int,text:string,originalStorage:string,bytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int}>
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,text:string,patternText:string,escapeText:?string,originalStorage:string,patternStorage:string,bytesHex:string,patternBytesHex:string,escapeBytesHex:?string,textEncoding:string,payload:array<string,mixed>}>
     */
    private static function dynamicPatternRows(
        array $rows,
        string $valueColumn,
        string $patternColumn,
        string $operator,
        ?string $escapeColumn,
        bool $caseSensitiveLike,
        string $encoding,
    ): array {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists($valueColumn, $row)) {
                throw new \InvalidArgumentException("SQLite dynamic LIKE/GLOB row is missing {$valueColumn}");
            }
            if (!array_key_exists($patternColumn, $row)) {
                throw new \InvalidArgumentException("SQLite dynamic LIKE/GLOB row is missing {$patternColumn}");
            }
            $text = self::coerceTextLikeGlobOperand($row[$valueColumn], 'value');
            $pattern = self::coerceTextLikeGlobOperand($row[$patternColumn], 'pattern');
            $escape = null;
            if ($escapeColumn !== null) {
                if (!array_key_exists($escapeColumn, $row)) {
                    throw new \InvalidArgumentException("SQLite dynamic LIKE/GLOB row is missing {$escapeColumn}");
                }
                $escape = self::coerceTextLikeGlobOperand($row[$escapeColumn], 'escape');
                if ($escape !== null && self::sqliteTextLength($escape) !== 1) {
                    throw new \InvalidArgumentException('SQLite dynamic LIKE ESCAPE expression must be a single character after text affinity');
                }
            }
            if ($text === null || $pattern === null) {
                continue;
            }
            $matches = $operator === 'LIKE'
                ? SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)
                : SQLiteDatabase::globMatches($text, $pattern);
            if (!$matches) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'text' => $text,
                'patternText' => $pattern,
                'escapeText' => $escape,
                'originalStorage' => SQLiteAffinityComparison::storageClass($row[$valueColumn]),
                'patternStorage' => SQLiteAffinityComparison::storageClass($row[$patternColumn]),
                'bytesHex' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding)),
                'patternBytesHex' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($pattern, $encoding)),
                'escapeBytesHex' => $escape === null ? null : bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($escape, $encoding)),
                'textEncoding' => $encoding,
                'payload' => $row,
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['text'], $right['text']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    private static function coerceTextLikeGlobOperand(mixed $value, string $label): ?string
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException("SQLite dynamic LIKE/GLOB {$label} requires well-formed UTF-8 before encoding");
            }

            return $value;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        throw new \InvalidArgumentException("SQLite dynamic LIKE/GLOB {$label} must be scalar text-affinity input");
    }

    private static function sqliteTextLength(string $value): int
    {
        if ($value === '') {
            return 0;
        }
        if (preg_match_all('/./us', $value, $matches) === false) {
            throw new \InvalidArgumentException('SQLite dynamic LIKE text length requires well-formed UTF-8');
        }

        return count($matches[0]);
    }

    private static function encodingName(int|string $encoding): string
    {
        if (is_int($encoding)) {
            return match ($encoding) {
                1 => 'UTF-8',
                2 => 'UTF-16LE',
                3 => 'UTF-16BE',
                default => throw new \InvalidArgumentException('SQLite current/next affinity encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
            };
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 'UTF-8',
            'UTF-16LE', 'UTF16LE' => 'UTF-16LE',
            'UTF-16BE', 'UTF16BE' => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite current/next affinity encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
