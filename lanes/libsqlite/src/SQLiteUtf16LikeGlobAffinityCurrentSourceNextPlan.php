<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValuePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $operator = 'LIKE',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.app_settings',
        string $nextSource = 'main.app_settings',
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity plan requires LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escape !== null) {
            throw new \InvalidArgumentException('SQLite GLOB affinity plan does not accept ESCAPE');
        }

        $current = self::scanRows($currentRows, $pattern, $operator, $escape, $caseSensitiveLike);
        $next = self::scanRows($nextRows, $pattern, $operator, $escape, $caseSensitiveLike);
        $currentRowids = self::matchedRowids($current);
        $nextRowids = self::matchedRowids($next);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $changedEncoding = [];
        $changedBytes = [];
        $changedStorage = [];

        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if (!in_array($rowid, array_unique(array_merge($currentRowids, $nextRowids)), true)) {
                continue;
            }
            if ($currentByRowid[$rowid]['textEncoding'] !== $nextByRowid[$rowid]['textEncoding']) {
                $changedEncoding[] = $rowid;
            }
            if ($currentByRowid[$rowid]['bytesHex'] !== $nextByRowid[$rowid]['bytesHex']) {
                $changedBytes[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storage'] !== $nextByRowid[$rowid]['storage']) {
                $changedStorage[] = $rowid;
            }
        }
        sort($changedEncoding);
        sort($changedBytes);
        sort($changedStorage);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'value-bytes';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'operator' => $operator,
            'pattern' => $pattern,
            'escape' => $escape,
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedEncodingRowids' => $changedEncoding,
            'changedBytesRowids' => $changedBytes,
            'changedStorageRowids' => $changedStorage,
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentMatchedRows' => $current['matchedRows'],
            'nextMatchedRows' => $next['matchedRows'],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-glob-affinity',
                'sqlite-current-source-nextnineTwo',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{matchedRows:list<array<string,mixed>>, malformedRowids:list<int>}
     */
    private static function scanRows(array $rows, string $pattern, string $operator, ?string $escape, bool $caseSensitiveLike): array
    {
        $matched = [];
        $malformed = [];
        foreach ($rows as $row) {
            if (!isset($row['setting_id']) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity rows require integer setting_id');
            }
            if (!array_key_exists('key_value_bytes', $row) && !array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity rows require key_value or key_value_bytes');
            }
            try {
                $decoded = self::decodeKeyValue($row);
            } catch (\InvalidArgumentException) {
                $malformed[] = $row['setting_id'];
                continue;
            }

            $text = self::valueForLikeGlob($decoded['value']);
            $matches = $operator === 'LIKE'
                ? SQLiteDatabase::likeMatches($text, $pattern, $escape, $caseSensitiveLike)
                : SQLiteDatabase::globMatches($text, $pattern);
            if (!$matches) {
                continue;
            }

            $matched[] = [
                'rowid' => $row['setting_id'],
                'value' => $decoded['value'],
                'textValue' => $text,
                'storage' => SQLiteAffinityComparison::storageClass($decoded['value']),
                'textEncoding' => $decoded['textEncodingName'],
                'bytesHex' => $decoded['bytesHex'],
                'payload' => $row,
            ];
        }

        return ['matchedRows' => $matched, 'malformedRowids' => $malformed];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{value:mixed,textEncodingName:?string,bytesHex:?string}
     */
    private static function decodeKeyValue(array $row): array
    {
        if (array_key_exists('key_value_bytes', $row)) {
            if (!is_string($row['key_value_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity rows require string key_value_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity rows require integer text_encoding');
            }
            return [
                'value' => SQLiteEncodingCollationSourceCursor::decodeText($row['key_value_bytes'], $row['text_encoding']),
                'textEncodingName' => self::encodingName($row['text_encoding']),
                'bytesHex' => bin2hex($row['key_value_bytes']),
            ];
        }

        if (!array_key_exists('key_value', $row)) {
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity rows require key_value or key_value_bytes');
        }

        return [
            'value' => $row['key_value'],
            'textEncodingName' => null,
            'bytesHex' => is_string($row['key_value']) ? bin2hex($row['key_value']) : null,
        ];
    }

    private static function valueForLikeGlob(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if ($value === null) {
            return '';
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
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity text is malformed UTF-8');
            }
            return $value;
        }

        throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity rows require scalar setting values');
    }

    /**
     * @param array{matchedRows:list<array<string,mixed>>} $scan
     * @return list<int>
     */
    private static function matchedRowids(array $scan): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $scan['matchedRows']));
    }

    /**
     * @param array{matchedRows:list<array<string,mixed>>} $scan
     * @return array<int,array{textEncoding:?string,bytesHex:?string,storage:string}>
     */
    private static function rowsByRowid(array $scan): array
    {
        $byRowid = [];
        foreach ($scan['matchedRows'] as $row) {
            $byRowid[$row['rowid']] = [
                'textEncoding' => $row['textEncoding'],
                'bytesHex' => $row['bytesHex'],
                'storage' => $row['storage'],
            ];
        }

        return $byRowid;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
