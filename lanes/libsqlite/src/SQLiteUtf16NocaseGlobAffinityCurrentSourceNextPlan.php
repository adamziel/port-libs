<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyGlobPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $currentSource = 'main.app_settings@current',
        string $nextSource = 'main.app_settings@next',
        string $currentDatabaseEncoding = 'UTF-16LE',
        string $nextDatabaseEncoding = 'UTF-16LE',
    ): array {
        $range = self::nocaseGlobRange($pattern);
        $currentEncoding = self::normalizeUtf16Encoding($currentDatabaseEncoding);
        $nextEncoding = self::normalizeUtf16Encoding($nextDatabaseEncoding);
        $current = self::scanSource($currentRows, $pattern, $range);
        $next = self::scanSource($nextRows, $pattern, $range);
        $currentCandidateRowids = self::rowids($current['candidates']);
        $nextCandidateRowids = self::rowids($next['candidates']);
        $currentRowids = self::rowids($current['matches']);
        $nextRowids = self::rowids($next['matches']);
        $changes = self::retainedChanges($current['candidates'], $next['candidates']);
        $rangeBytesChanged = self::rangeBytes($range, $currentEncoding) !== self::rangeBytes($range, $nextEncoding);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($current['errors'] !== $next['errors']) {
            $reasons[] = 'malformed-text';
        }
        if ($range === null) {
            $reasons[] = 'no-prefix-range';
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
        if ($changes['storageChangedRowids'] !== []) {
            $reasons[] = 'storage-class';
        }
        if ($changes['encodingChangedRowids'] !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changes['bytesChangedRowids'] !== []) {
            $reasons[] = 'encoded-bytes';
        }

        return [
            'operator' => 'GLOB',
            'collation' => 'NOCASE',
            'globResidualCaseSensitive' => true,
            'affinity' => 'TEXT',
            'pattern' => $pattern,
            'prefix' => $range['prefix'] ?? null,
            'prefixFolded' => $range['foldedPrefix'] ?? null,
            'range' => $range === null ? null : [
                'lowerInclusive' => $range['lowerInclusive'],
                'upperBound' => $range['upperBound'],
            ],
            'indexUsable' => $range !== null,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentDatabaseEncoding' => $currentEncoding,
            'nextDatabaseEncoding' => $nextEncoding,
            'currentRangeBytesHex' => self::rangeBytes($range, $currentEncoding),
            'nextRangeBytesHex' => self::rangeBytes($range, $nextEncoding),
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
            'currentKeys' => self::keyMap($current['valid']),
            'nextKeys' => self::keyMap($next['valid']),
            'currentText' => self::textMap($current['valid']),
            'nextText' => self::textMap($next['valid']),
            'currentBytesHex' => self::bytesMap($current['valid']),
            'nextBytesHex' => self::bytesMap($next['valid']),
            'currentEncodings' => self::encodingMap($current['valid']),
            'nextEncodings' => self::encodingMap($next['valid']),
            'currentStorage' => self::storageMap($current['valid']),
            'nextStorage' => self::storageMap($next['valid']),
            'retainedTextChangedRowids' => $changes['textChangedRowids'],
            'retainedStorageChangedRowids' => $changes['storageChangedRowids'],
            'retainedEncodingChangedRowids' => $changes['encodingChangedRowids'],
            'retainedBytesChangedRowids' => $changes['bytesChangedRowids'],
            'currentPlanSteps' => self::planSteps($current['candidates'], $pattern, $range),
            'nextPlanSteps' => self::planSteps($next['candidates'], $pattern, $range),
            'cursorReusable' => $reasons === [] && $range !== null,
            'cursorInvalidated' => $reasons !== [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-glob-prefix-range',
                'sqlite-nocase-collation-source-cursor',
                'sqlite-glob-case-sensitive-residual',
                'sqlite-current-source-nextoneFourEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decoding, TEXT affinity casting, NOCASE source cursor keys, and case-sensitive GLOB residual matching',
        ];
    }

    /**
     * @param null|array<string,mixed> $range
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

    /** @return null|array{prefix:string,foldedPrefix:string,lowerInclusive:string,upperBound:?string} */
    private static function nocaseGlobRange(string $pattern): ?array
    {
        $binary = SQLiteDatabase::globPrefixRangeBounds($pattern);
        if ($binary === null) {
            return null;
        }

        $prefix = $binary['lowerInclusive'];
        $folded = self::asciiLower($prefix);
        $foldedRange = SQLiteDatabase::globPrefixRangeBounds($folded . '*');

        return [
            'prefix' => $prefix,
            'foldedPrefix' => $folded,
            'lowerInclusive' => $folded,
            'upperBound' => $foldedRange['upperBound'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param null|array<string,mixed> $range
     * @return array{valid:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matches:list<array<string,mixed>>,errors:array<int,string>}
     */
    private static function scanSource(array $rows, string $pattern, ?array $range): array
    {
        $valid = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $errors[$row['setting_id']] = $exception->getMessage();
                continue;
            }

            $valid[] = [
                'rowid' => $row['setting_id'],
                'text' => $text,
                'key' => self::asciiLower($text),
                'bytes' => $row['key_name_bytes'],
                'encoding' => $row['text_encoding'] === 2 ? 'UTF-16LE' : 'UTF-16BE',
                'storage' => self::storageClass($row),
            ];
        }

        usort($valid, static fn (array $left, array $right): int => $left['key'] === $right['key']
            ? $left['rowid'] <=> $right['rowid']
            : strcmp($left['key'], $right['key']));

        $candidates = [];
        $matches = [];
        foreach ($valid as $row) {
            if (!self::inRange($row['key'], $range)) {
                continue;
            }
            $candidates[] = $row;
            if (SQLiteDatabase::globMatches($row['text'], $pattern)) {
                $matches[] = $row;
            }
        }

        ksort($errors);

        return ['valid' => $valid, 'candidates' => $candidates, 'matches' => $matches, 'errors' => $errors];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE GLOB affinity current-source nextOneFourEight rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE GLOB affinity current-source nextOneFourEight rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !in_array($row['text_encoding'], [2, 3], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE GLOB affinity current-source nextOneFourEight rows require UTF-16 text_encoding');
        }
    }

    /** @param array<string,mixed> $row */
    private static function storageClass(array $row): string
    {
        $storage = $row['storage_class'] ?? 'text';
        if (!is_string($storage) || !in_array($storage, ['text', 'blob'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE GLOB affinity current-source nextOneFourEight storage_class must be text or blob');
        }

        return $storage;
    }

    /** @param null|array<string,mixed> $range */
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
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{textChangedRowids:list<int>,storageChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>}
     */
    private static function retainedChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $text = [];
        $storage = [];
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
            if ($current[$rowid]['storage'] !== $row['storage']) {
                $storage[] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $encoding[] = $rowid;
            }
            if ($current[$rowid]['bytes'] !== $row['bytes']) {
                $bytes[] = $rowid;
            }
        }

        sort($text);
        sort($storage);
        sort($encoding);
        sort($bytes);

        return ['textChangedRowids' => $text, 'storageChangedRowids' => $storage, 'encodingChangedRowids' => $encoding, 'bytesChangedRowids' => $bytes];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param null|array<string,mixed> $range
     * @return list<array<string,mixed>>
     */
    private static function planSteps(array $rows, string $pattern, ?array $range): array
    {
        $steps = [];
        foreach ($rows as $position => $row) {
            $next = $rows[$position + 1] ?? null;
            $steps[] = [
                'position' => $position,
                'rowid' => $row['rowid'],
                'text' => $row['text'],
                'key' => $row['key'],
                'storage' => $row['storage'],
                'encoding' => $row['encoding'],
                'bytesHex' => bin2hex($row['bytes']),
                'inRange' => self::inRange($row['key'], $range),
                'residualMatch' => SQLiteDatabase::globMatches($row['text'], $pattern),
                'nextRowid' => $next['rowid'] ?? null,
                'nextResidualMatch' => $next === null ? null : SQLiteDatabase::globMatches($next['text'], $pattern),
            ];
        }

        return $steps;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function keyMap(array $rows): array
    {
        return self::fieldMap($rows, 'key');
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function textMap(array $rows): array
    {
        return self::fieldMap($rows, 'text');
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function bytesMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = bin2hex($row['bytes']);
        }

        return $map;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function encodingMap(array $rows): array
    {
        return self::fieldMap($rows, 'encoding');
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function storageMap(array $rows): array
    {
        return self::fieldMap($rows, 'storage');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,string>
     */
    private static function fieldMap(array $rows, string $field): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = $row[$field];
        }

        return $map;
    }

    private static function normalizeUtf16Encoding(string $encoding): string
    {
        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-16LE', 'UTF16LE' => 'UTF-16LE',
            'UTF-16BE', 'UTF16BE' => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE GLOB affinity current-source nextOneFourEight requires UTF-16LE or UTF-16BE database encoding'),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
