<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16GlobRangeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{
     *   pattern:string,
     *   collation:string,
     *   currentSource:string,
     *   nextSource:string,
     *   currentSchemaCookie:int,
     *   nextSchemaCookie:int,
     *   currentEncoding:string,
     *   nextEncoding:string,
     *   sourceChanged:bool,
     *   cursorReusable:bool,
     *   reprepareReasons:list<string>,
     *   current:array<string,mixed>,
     *   next:array<string,mixed>,
     *   retainedRowids:list<int>,
     *   exitedRowids:list<int>,
     *   enteredRowids:list<int>,
     *   changedBytesRowids:list<int>,
     *   dependencies:list<string>
     * }
     */
    public static function optionRowNameGlobRange(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $collation = 'BINARY',
        string $currentEncoding = 'UTF-16LE',
        string $nextEncoding = 'UTF-16LE',
        string $currentSource = 'main.wp_options@current',
        string $nextSource = 'main.wp_options@next',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
    ): array {
        $collation = strtoupper($collation);
        $currentEncoding = strtoupper($currentEncoding);
        $nextEncoding = strtoupper($nextEncoding);

        $currentCursor = new SQLiteUtf16LikeGlobCurrentNextCursor(
            self::entries($currentRows, 'current'),
            $pattern,
            'GLOB',
            $currentEncoding,
            $collation,
        );
        $nextCursor = new SQLiteUtf16LikeGlobCurrentNextCursor(
            self::entries($nextRows, 'next'),
            $pattern,
            'GLOB',
            $nextEncoding,
            $collation,
        );

        $currentRowsMatched = $currentCursor->matchedRows();
        $nextRowsMatched = $nextCursor->matchedRows();
        $currentRowids = self::rowids($currentRowsMatched);
        $nextRowids = self::rowids($nextRowsMatched);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $changedBytes = self::changedBytes($currentRowsMatched, $nextRowsMatched);
        $currentSummary = self::sourceSummary($currentCursor, $currentRowsMatched, $currentEncoding);
        $nextSummary = self::sourceSummary($nextCursor, $nextRowsMatched, $nextEncoding);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentEncoding !== $nextEncoding) {
            $reasons[] = 'text-encoding';
        }
        if ($currentSummary['rangeBytesHex'] !== $nextSummary['rangeBytesHex']) {
            $reasons[] = 'range-bytes';
        }
        if ($exited !== [] || $entered !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'key-bytes';
        }

        return [
            'pattern' => $pattern,
            'collation' => $collation,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentEncoding' => $currentEncoding,
            'nextEncoding' => $nextEncoding,
            'sourceChanged' => $currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie,
            'cursorReusable' => $reasons === [],
            'reprepareReasons' => $reasons,
            'current' => $currentSummary,
            'next' => $nextSummary,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedBytesRowids' => $changedBytes,
            'dependencies' => [
                'sqlite-utf16-like-glob-current-next-cursor',
                'sqlite-glob-prefix-range-bounds',
                'sqlite-current-source-next-range-reprepare',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{keyBytes:string,rowid:int,payload:array<string,mixed>}>
     */
    private static function entries(array $rows, string $label): array
    {
        $entries = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException("SQLite UTF-16 GLOB {$label} source requires integer option_id");
            }
            if (!array_key_exists('option_name_utf16', $row) || !is_string($row['option_name_utf16'])) {
                throw new \InvalidArgumentException("SQLite UTF-16 GLOB {$label} source requires option_name_utf16 bytes");
            }
            $entries[] = [
                'keyBytes' => $row['option_name_utf16'],
                'rowid' => $row['option_id'],
                'payload' => $row,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{rowid:int}> $rows
     * @return list<int>
     */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array{rowid:int,keyBytesHex:string}> $currentRows
     * @param list<array{rowid:int,keyBytesHex:string}> $nextRows
     * @return list<int>
     */
    private static function changedBytes(array $currentRows, array $nextRows): array
    {
        $current = self::bytesByRowid($currentRows);
        $next = self::bytesByRowid($nextRows);
        $changed = [];
        foreach (array_intersect(array_keys($current), array_keys($next)) as $rowid) {
            if ($current[$rowid] !== $next[$rowid]) {
                $changed[] = (int) $rowid;
            }
        }
        sort($changed);

        return $changed;
    }

    /**
     * @param list<array{rowid:int,keyBytesHex:string}> $rows
     * @return array<int,string>
     */
    private static function bytesByRowid(array $rows): array
    {
        $bytes = [];
        foreach ($rows as $row) {
            $bytes[$row['rowid']] = $row['keyBytesHex'];
        }

        return $bytes;
    }

    /**
     * @param list<array{rowid:int,keyText:string,keyBytesHex:string,payload:array<string,mixed>,position:int}> $matchedRows
     * @return array<string,mixed>
     */
    private static function sourceSummary(SQLiteUtf16LikeGlobCurrentNextCursor $cursor, array $matchedRows, string $encoding): array
    {
        $plan = $cursor->currentNextPlan();
        $range = $plan['range'];

        return [
            'rowids' => self::rowids($matchedRows),
            'matchedCount' => count($matchedRows),
            'firstRowid' => $matchedRows[0]['rowid'] ?? null,
            'lastRowid' => $matchedRows === [] ? null : $matchedRows[array_key_last($matchedRows)]['rowid'],
            'firstText' => $matchedRows[0]['keyText'] ?? null,
            'lastText' => $matchedRows === [] ? null : $matchedRows[array_key_last($matchedRows)]['keyText'],
            'range' => $range,
            'rangeBytesHex' => self::rangeBytesHex($range, $encoding),
            'cursor' => $plan,
            'bytesHexByRowid' => self::bytesByRowid($matchedRows),
        ];
    }

    /**
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return null|array{lowerInclusive:string,upperBound:?string}
     */
    private static function rangeBytesHex(?array $range, string $encoding): ?array
    {
        if ($range === null) {
            return null;
        }

        return [
            'lowerInclusive' => bin2hex(SQLiteUtf16LikeGlobCurrentNextCursor::encodeUtf16($range['lowerInclusive'], $encoding)),
            'upperBound' => $range['upperBound'] === null ? null : bin2hex(SQLiteUtf16LikeGlobCurrentNextCursor::encodeUtf16($range['upperBound'], $encoding)),
        ];
    }
}
