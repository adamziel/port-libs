<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeCurrentSourceNext126Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options',
        string $nextSource = 'main.wp_options',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
        int $currentCollationVersion = 1,
        int $nextCollationVersion = 1,
        string $currentDatabaseEncoding = 'UTF-16LE',
        string $nextDatabaseEncoding = 'UTF-16LE',
    ): array {
        $currentEncoding = self::normalizeUtf16Encoding($currentDatabaseEncoding);
        $nextEncoding = self::normalizeUtf16Encoding($nextDatabaseEncoding);
        $rangePlan = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, $caseSensitiveLike);

        $currentScan = SQLiteEncodingCollationSourceCursor::wordpressOptionNameScan(
            self::assertUtf16Rows($currentRows, 'current'),
            $pattern,
            'LIKE',
            'NOCASE',
            $escape,
            $caseSensitiveLike,
        );
        $nextScan = SQLiteEncodingCollationSourceCursor::wordpressOptionNameScan(
            self::assertUtf16Rows($nextRows, 'next'),
            $pattern,
            'LIKE',
            'NOCASE',
            $escape,
            $caseSensitiveLike,
        );

        $currentByRowid = self::sourceRowsByRowid($currentRows, 'current');
        $nextByRowid = self::sourceRowsByRowid($nextRows, 'next');
        $currentRowids = self::rowids($currentScan);
        $nextRowids = self::rowids($nextScan);
        $retainedRowids = array_values(array_intersect($currentRowids, $nextRowids));
        $exitedRowids = array_values(array_diff($currentRowids, $nextRowids));
        $enteredRowids = array_values(array_diff($nextRowids, $currentRowids));

        $changedEncodingRowids = [];
        $changedBytesRowids = [];
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if (!in_array($rowid, array_unique(array_merge($currentRowids, $nextRowids)), true)) {
                continue;
            }
            if ($currentByRowid[$rowid]['text_encoding'] !== $nextByRowid[$rowid]['text_encoding']) {
                $changedEncodingRowids[] = $rowid;
            }
            if ($currentByRowid[$rowid]['option_name_bytes'] !== $nextByRowid[$rowid]['option_name_bytes']) {
                $changedBytesRowids[] = $rowid;
            }
        }
        sort($changedEncodingRowids);
        sort($changedBytesRowids);

        $rangeBytesChanged = self::rangeBytes($rangePlan['range'], $currentEncoding) !== self::rangeBytes($rangePlan['range'], $nextEncoding);
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentCollationVersion !== $nextCollationVersion) {
            $reasons[] = 'collation-version';
        }
        if ($currentEncoding !== $nextEncoding || $rangeBytesChanged) {
            $reasons[] = 'range-bytes';
        }
        if ($changedEncodingRowids !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedBytesRowids !== []) {
            $reasons[] = 'key-bytes';
        }
        if ($enteredRowids !== [] || $exitedRowids !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'operator' => 'LIKE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => $caseSensitiveLike,
            'indexUsable' => $rangePlan['indexUsable'],
            'rejectedReason' => $rangePlan['rejectedReason'],
            'range' => $rangePlan['range'],
            'prefix' => $rangePlan['prefix'],
            'prefixCharacters' => $rangePlan['prefixCharacters'],
            'prefixIsAscii' => $rangePlan['prefixIsAscii'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCollationVersion' => $currentCollationVersion,
            'nextCollationVersion' => $nextCollationVersion,
            'currentDatabaseEncoding' => $currentEncoding,
            'nextDatabaseEncoding' => $nextEncoding,
            'currentRangeBytesHex' => self::rangeBytes($rangePlan['range'], $currentEncoding),
            'nextRangeBytesHex' => self::rangeBytes($rangePlan['range'], $nextEncoding),
            'rangeBytesChanged' => $rangeBytesChanged,
            'cursorReusable' => $reasons === [] && $rangePlan['indexUsable'],
            'cursorInvalidated' => $reasons !== [],
            'invalidationReasons' => $reasons,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retainedRowids,
            'exitedRowids' => $exitedRowids,
            'enteredRowids' => $enteredRowids,
            'changedEncodingRowids' => $changedEncodingRowids,
            'changedBytesRowids' => $changedBytesRowids,
            'currentKeys' => self::keyMap($currentScan),
            'nextKeys' => self::keyMap($nextScan),
            'currentEncodings' => self::encodingMap($currentScan),
            'nextEncodings' => self::encodingMap($nextScan),
            'currentBytesHex' => self::bytesMap($currentScan),
            'nextBytesHex' => self::bytesMap($nextScan),
            'currentFirstRowid' => $currentRowids[0] ?? null,
            'nextFirstRowid' => $nextRowids[0] ?? null,
            'currentLastRowid' => $currentRowids === [] ? null : $currentRowids[array_key_last($currentRowids)],
            'nextLastRowid' => $nextRowids === [] ? null : $nextRowids[array_key_last($nextRowids)],
            'dependencies' => [
                'sqlite-like-collation-prefix-range',
                'sqlite-encoding-source-cursor',
                'sqlite-utf16-nocase-current-source-next126',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function assertUtf16Rows(array $rows, string $side): array
    {
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE {$side} rows require integer option_id");
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE {$side} rows require option_name_bytes");
            }
            if (!isset($row['text_encoding']) || !in_array($row['text_encoding'], [2, 3], true)) {
                throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE {$side} rows require UTF-16 text_encoding");
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{option_name_bytes:string,text_encoding:int}>
     */
    private static function sourceRowsByRowid(array $rows, string $side): array
    {
        self::assertUtf16Rows($rows, $side);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['option_id']] = [
                'option_name_bytes' => $row['option_name_bytes'],
                'text_encoding' => $row['text_encoding'],
            ];
        }

        return $indexed;
    }

    /**
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return array{lowerInclusive:?string,upperBound:?string}
     */
    private static function rangeBytes(?array $range, string $encoding): array
    {
        if ($range === null) {
            return ['lowerInclusive' => null, 'upperBound' => null];
        }

        return [
            'lowerInclusive' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($range['lowerInclusive'], $encoding)),
            'upperBound' => $range['upperBound'] === null ? null : bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($range['upperBound'], $encoding)),
        ];
    }

    private static function normalizeUtf16Encoding(string $encoding): string
    {
        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-16LE', 'UTF16LE' => 'UTF-16LE',
            'UTF-16BE', 'UTF16BE' => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE plan requires UTF-16LE or UTF-16BE database encoding'),
        };
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
     * @param list<array{rowid:int,key:string}> $rows
     * @return array<int,string>
     */
    private static function keyMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = $row['key'];
        }

        return $map;
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
