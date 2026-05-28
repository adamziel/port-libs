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
    public static function wordpressOptionValuePlan(
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

        $current = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::wordpressOptionValueScan(
            $currentRows,
            $column,
            $pattern,
            $operator,
            $collation,
            $escape,
            $caseSensitiveLike,
            $currentEncoding,
        );
        $next = SQLiteUtf16LikeGlobAffinityCurrentSourceCursor::wordpressOptionValueScan(
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
