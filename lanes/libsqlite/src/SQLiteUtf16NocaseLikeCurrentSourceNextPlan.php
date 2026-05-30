<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeCurrentSourceNextPlan
{
    public static function keyValueRowKeyLikePlan(mixed ...$args): array
    {
        return SQLiteUtf16NocaseLikeRangeBytesPlan::keyValueRowKeyLikePlan(...$args);
    }

    public static function keyValueRowKeyResidualPlan(mixed ...$args): array
    {
        return SQLiteUtf16NocaseLikeResidualPlan::keyValueRowKeyLikePlan(...$args);
    }

}

final class SQLiteUtf16NocaseLikeRangeBytesPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyLikePlan(
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

        $currentScan = SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan(
            self::assertUtf16Rows($currentRows, 'current'),
            $pattern,
            'LIKE',
            'NOCASE',
            $escape,
            $caseSensitiveLike,
        );
        $nextScan = SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan(
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
                'sqlite-utf16-nocase-current-source-nextoneTwoSix',
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

final class SQLiteUtf16NocaseLikeResidualPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@current',
        string $nextSource = 'main.wp_options@next',
        string $currentDatabaseEncoding = 'UTF-16LE',
        string $nextDatabaseEncoding = 'UTF-16LE',
    ): array {
        $rangePlan = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $currentEncoding = self::normalizeUtf16Encoding($currentDatabaseEncoding);
        $nextEncoding = self::normalizeUtf16Encoding($nextDatabaseEncoding);
        $current = self::scanSource($currentRows, $rangePlan, $pattern, $escape);
        $next = self::scanSource($nextRows, $rangePlan, $pattern, $escape);
        $currentRowids = self::rowids($current['matches']);
        $nextRowids = self::rowids($next['matches']);
        $currentCandidateRowids = self::rowids($current['candidates']);
        $nextCandidateRowids = self::rowids($next['candidates']);
        $changes = self::retainedChanges($current['candidates'], $next['candidates']);
        $rangeBytesChanged = self::rangeBytes($rangePlan['range'], $currentEncoding) !== self::rangeBytes($rangePlan['range'], $nextEncoding);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($current['errors'] !== $next['errors']) {
            $reasons[] = 'malformed-text';
        }
        if ($rangeBytesChanged) {
            $reasons[] = 'range-bytes';
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentRowids !== $nextRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($changes['textChangedRowids'] !== []) {
            $reasons[] = 'text-value';
        }
        if ($changes['encodingChangedRowids'] !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changes['bytesChangedRowids'] !== []) {
            $reasons[] = 'encoded-bytes';
        }

        return [
            'operator' => 'LIKE',
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'pattern' => $pattern,
            'escape' => $escape,
            'prefix' => $rangePlan['prefix'],
            'prefixCharacters' => $rangePlan['prefixCharacters'],
            'prefixIsAscii' => $rangePlan['prefixIsAscii'],
            'indexUsable' => $rangePlan['indexUsable'],
            'rejectedReason' => $rangePlan['rejectedReason'],
            'range' => $rangePlan['range'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentDatabaseEncoding' => $currentEncoding,
            'nextDatabaseEncoding' => $nextEncoding,
            'currentRangeBytesHex' => self::rangeBytes($rangePlan['range'], $currentEncoding),
            'nextRangeBytesHex' => self::rangeBytes($rangePlan['range'], $nextEncoding),
            'rangeBytesChanged' => $rangeBytesChanged,
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => array_values(array_intersect($currentRowids, $nextRowids)),
            'enteredRowids' => array_values(array_diff($nextRowids, $currentRowids)),
            'exitedRowids' => array_values(array_diff($currentRowids, $nextRowids)),
            'currentResidualRejectedRowids' => array_values(array_diff($currentCandidateRowids, $currentRowids)),
            'nextResidualRejectedRowids' => array_values(array_diff($nextCandidateRowids, $nextRowids)),
            'currentMalformedRowids' => array_keys($current['errors']),
            'nextMalformedRowids' => array_keys($next['errors']),
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'repairedRowids' => array_values(array_diff(array_keys($current['errors']), array_keys($next['errors']))),
            'newlyMalformedRowids' => array_values(array_diff(array_keys($next['errors']), array_keys($current['errors']))),
            'currentKeys' => self::keyMap($current['valid']),
            'nextKeys' => self::keyMap($next['valid']),
            'currentBytesHex' => self::bytesMap($current['valid']),
            'nextBytesHex' => self::bytesMap($next['valid']),
            'currentEncodings' => self::encodingMap($current['valid']),
            'nextEncodings' => self::encodingMap($next['valid']),
            'retainedTextChangedRowids' => $changes['textChangedRowids'],
            'retainedEncodingChangedRowids' => $changes['encodingChangedRowids'],
            'retainedBytesChangedRowids' => $changes['bytesChangedRowids'],
            'currentPlanSteps' => self::planSteps($current['candidates'], $pattern, $escape, $rangePlan['range']),
            'nextPlanSteps' => self::planSteps($next['candidates'], $pattern, $escape, $rangePlan['range']),
            'cursorReusable' => $reasons === [] && $rangePlan['indexUsable'],
            'cursorInvalidated' => $reasons !== [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-range',
                'sqlite-like-residual-byte-preserving',
                'sqlite-current-source-nextoneFourOne',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $rangePlan
     * @return array{valid:list<array{rowid:int,text:string,key:string,bytes:string,encoding:string}>,candidates:list<array{rowid:int,text:string,key:string,bytes:string,encoding:string}>,matches:list<array{rowid:int,text:string,key:string,bytes:string,encoding:string}>,errors:array<int,string>}
     */
    private static function scanSource(array $rows, array $rangePlan, string $pattern, ?string $escape): array
    {
        $valid = [];
        $errors = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE residual rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE residual rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !in_array($row['text_encoding'], [2, 3], true)) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE residual rows require UTF-16 text_encoding');
            }

            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $errors[$row['option_id']] = $exception->getMessage();
                continue;
            }

            $valid[] = [
                'rowid' => $row['option_id'],
                'text' => $text,
                'key' => self::asciiLower($text),
                'bytes' => $row['option_name_bytes'],
                'encoding' => $row['text_encoding'] === 2 ? 'UTF-16LE' : 'UTF-16BE',
            ];
        }

        usort($valid, static fn (array $left, array $right): int => $left['key'] === $right['key']
            ? $left['rowid'] <=> $right['rowid']
            : strcmp($left['key'], $right['key']));

        $candidates = [];
        $matches = [];
        foreach ($valid as $row) {
            if (!self::inRange($row['key'], $rangePlan['range'])) {
                continue;
            }
            $candidates[] = $row;
            if (SQLiteDatabase::likeMatches($row['text'], $pattern, $escape, false)) {
                $matches[] = $row;
            }
        }

        return ['valid' => $valid, 'candidates' => $candidates, 'matches' => $matches, 'errors' => $errors];
    }

    /** @param null|array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, ?array $range): bool
    {
        if ($range === null) {
            return false;
        }
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /**
     * @param list<array{rowid:int,text:string,bytes:string,encoding:string}> $currentRows
     * @param list<array{rowid:int,text:string,bytes:string,encoding:string}> $nextRows
     * @return array{textChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>}
     */
    private static function retainedChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $text = [];
        $encoding = [];
        $bytes = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $text[] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $encoding[] = $rowid;
            }
            if ($current[$rowid]['bytes'] !== $row['bytes']) {
                $bytes[] = $rowid;
            }
        }

        sort($text);
        sort($encoding);
        sort($bytes);

        return ['textChangedRowids' => $text, 'encodingChangedRowids' => $encoding, 'bytesChangedRowids' => $bytes];
    }

    /**
     * @param list<array{rowid:int,text:string,key:string,bytes:string,encoding:string}> $rows
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return list<array<string,mixed>>
     */
    private static function planSteps(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $steps = [];
        foreach ($rows as $position => $row) {
            $next = $rows[$position + 1] ?? null;
            $steps[] = [
                'position' => $position,
                'rowid' => $row['rowid'],
                'key' => $row['key'],
                'encoding' => $row['encoding'],
                'bytesHex' => bin2hex($row['bytes']),
                'inRange' => self::inRange($row['key'], $range),
                'residualMatch' => SQLiteDatabase::likeMatches($row['text'], $pattern, $escape, false),
                'nextRowid' => $next['rowid'] ?? null,
                'nextResidualMatch' => $next === null ? null : SQLiteDatabase::likeMatches($next['text'], $pattern, $escape, false),
            ];
        }

        return $steps;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param list<array{rowid:int,key:string}> $rows @return array<int,string> */
    private static function keyMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = $row['key'];
        }

        return $map;
    }

    /** @param list<array{rowid:int,bytes:string}> $rows @return array<int,string> */
    private static function bytesMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = bin2hex($row['bytes']);
        }

        return $map;
    }

    /** @param list<array{rowid:int,encoding:string}> $rows @return array<int,string> */
    private static function encodingMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = $row['encoding'];
        }

        return $map;
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
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE residual plan requires UTF-16LE or UTF-16BE database encoding'),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
