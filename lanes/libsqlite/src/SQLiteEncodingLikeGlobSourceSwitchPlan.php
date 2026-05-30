<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingLikeGlobSourceSwitchPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{
     *   pattern:string,
     *   operator:string,
     *   collation:string,
     *   caseSensitiveLike:bool,
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
     *   currentEncodings:array<int,string>,
     *   nextEncodings:array<int,string>,
     *   currentBytesHex:array<int,string>,
     *   nextBytesHex:array<int,string>,
     *   dependencies:list<string>
     * }
     */
    public static function optionRowNameSourceSwitch(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'current',
        string $nextSource = 'next',
    ): array {
        $currentMatches = SQLiteEncodingCollationSourceCursor::optionRowNameScan(
            $currentRows,
            $pattern,
            $operator,
            $collation,
            $escape,
            $caseSensitiveLike,
        );
        $nextMatches = SQLiteEncodingCollationSourceCursor::optionRowNameScan(
            $nextRows,
            $pattern,
            $operator,
            $collation,
            $escape,
            $caseSensitiveLike,
        );

        $currentByRowid = self::byRowid($currentRows);
        $nextByRowid = self::byRowid($nextRows);
        $currentRowids = self::rowids($currentMatches);
        $nextRowids = self::rowids($nextMatches);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $changedEncodings = [];
        $changedBytes = [];

        $matchedRowids = array_unique(array_merge($currentRowids, $nextRowids));
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if (!in_array($rowid, $matchedRowids, true)) {
                continue;
            }
            $current = $currentByRowid[$rowid];
            $next = $nextByRowid[$rowid];
            if ($current['text_encoding'] !== $next['text_encoding']) {
                $changedEncodings[] = $rowid;
            }
            if ($current['option_name_bytes'] !== $next['option_name_bytes']) {
                $changedBytes[] = $rowid;
            }
        }

        sort($changedEncodings);
        sort($changedBytes);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($changedEncodings !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'key-bytes';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'pattern' => $pattern,
            'operator' => strtoupper($operator),
            'collation' => strtoupper($collation),
            'caseSensitiveLike' => $caseSensitiveLike,
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
            'currentEncodings' => self::encodingMap($currentMatches),
            'nextEncodings' => self::encodingMap($nextMatches),
            'currentBytesHex' => self::bytesMap($currentMatches),
            'nextBytesHex' => self::bytesMap($nextMatches),
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-like-glob-collation',
                'sqlite-current-next-source-invalidation',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{option_name_bytes:string,text_encoding:int}>
     */
    private static function byRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite encoding source switch requires integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite encoding source switch requires option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite encoding source switch requires integer text_encoding');
            }
            $byRowid[$row['option_id']] = [
                'option_name_bytes' => $row['option_name_bytes'],
                'text_encoding' => $row['text_encoding'],
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

    /**
     * @param list<array{rowid:int,textEncoding:string}> $rows
     * @return array<int,string>
     */
    private static function encodingMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = $row['textEncoding'];
        }

        return $map;
    }

    /**
     * @param list<array{rowid:int,keyBytesHex:string}> $rows
     * @return array<int,string>
     */
    private static function bytesMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = $row['keyBytesHex'];
        }

        return $map;
    }
}
