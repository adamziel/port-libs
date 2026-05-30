<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationIndexLikeGlobCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyIndexPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'NOCASE',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings',
        string $nextSource = 'main.app_settings',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
        int $currentCollationVersion = 1,
        int $nextCollationVersion = 1,
    ): array {
        $operator = strtoupper($operator);
        $collation = strtoupper($collation);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite encoding index current-source operator must be LIKE or GLOB');
        }

        $currentMatches = SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan(
            $currentRows,
            $pattern,
            $operator,
            $collation,
            $escape,
            $caseSensitiveLike,
        );
        $nextMatches = SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan(
            $nextRows,
            $pattern,
            $operator,
            $collation,
            $escape,
            $caseSensitiveLike,
        );

        $rangePlan = self::rangePlan($pattern, $operator, $collation, $escape, $caseSensitiveLike);
        $currentByRowid = self::sourceRowsByRowid($currentRows);
        $nextByRowid = self::sourceRowsByRowid($nextRows);
        $currentRowids = self::rowids($currentMatches);
        $nextRowids = self::rowids($nextMatches);
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
            'operator' => $operator,
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitiveLike,
            'indexUsable' => $rangePlan['indexUsable'],
            'rejectedReason' => $rangePlan['rejectedReason'],
            'range' => $rangePlan['range'],
            'prefix' => $rangePlan['prefix'],
            'prefixCharacters' => $rangePlan['prefixCharacters'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCollationVersion' => $currentCollationVersion,
            'nextCollationVersion' => $nextCollationVersion,
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
            'currentEncodings' => self::encodingMap($currentMatches),
            'nextEncodings' => self::encodingMap($nextMatches),
            'currentBytesHex' => self::bytesMap($currentMatches),
            'nextBytesHex' => self::bytesMap($nextMatches),
            'dependencies' => [
                'sqlite-like-glob-collation',
                'sqlite-encoding-source-cursor',
                'sqlite-index-current-source-next89',
            ],
        ];
    }

    /**
     * @return array{indexUsable:bool,rejectedReason:?string,range:?array{lowerInclusive:string,upperBound:?string},prefix:string,prefixCharacters:int}
     */
    private static function rangePlan(string $pattern, string $operator, string $collation, ?string $escape, bool $caseSensitiveLike): array
    {
        if ($operator === 'LIKE') {
            $plan = SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike);

            return [
                'indexUsable' => $plan['indexUsable'],
                'rejectedReason' => $plan['rejectedReason'],
                'range' => $plan['range'],
                'prefix' => $plan['prefix'],
                'prefixCharacters' => $plan['prefixCharacters'],
            ];
        }

        if ($escape !== null) {
            throw new \InvalidArgumentException('SQLite GLOB index current-source plan does not accept ESCAPE');
        }
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite GLOB current-source collation: {$collation}");
        }
        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $prefix = $range['lowerInclusive'] ?? '';

        return [
            'indexUsable' => $range !== null,
            'rejectedReason' => $range === null ? 'no_fixed_prefix' : null,
            'range' => $range,
            'prefix' => $prefix,
            'prefixCharacters' => self::sqliteTextLength($prefix),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{option_name_bytes:string,text_encoding:int}>
     */
    private static function sourceRowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite encoding index current-source rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite encoding index current-source rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite encoding index current-source rows require integer text_encoding');
            }
            $indexed[$row['option_id']] = [
                'option_name_bytes' => $row['option_name_bytes'],
                'text_encoding' => $row['text_encoding'],
            ];
        }

        return $indexed;
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

    private static function sqliteTextLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return preg_match_all('/./us', $value, $matches);
    }
}
