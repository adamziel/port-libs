<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeCurrentSourceNext141Plan
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
                'sqlite-current-source-next141',
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
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE current-source next141 rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE current-source next141 rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !in_array($row['text_encoding'], [2, 3], true)) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE current-source next141 rows require UTF-16 text_encoding');
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
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE current-source next141 requires UTF-16LE or UTF-16BE database encoding'),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
