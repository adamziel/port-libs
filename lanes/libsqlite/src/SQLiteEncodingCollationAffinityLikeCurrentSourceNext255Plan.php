<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext255Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressGlobClassFallbackPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $currentSource = 'main.wp_options@254',
        string $nextSource = 'main.wp_options@255',
        int $currentSchemaCookie = 254,
        int $nextSchemaCookie = 255,
    ): array {
        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $current = self::scanRows($currentRows, $pattern);
        $next = self::scanRows($nextRows, $pattern);
        $currentRowids = self::rowids($current);
        $nextRowids = self::rowids($next);
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $changedText = [];
        $changedBytes = [];
        $changedEncoding = [];
        $changedStorage = [];
        $changedResidual = [];

        foreach (array_values(array_intersect(array_keys($currentByRowid), array_keys($nextByRowid))) as $rowid) {
            if ($currentByRowid[$rowid]['text'] !== $nextByRowid[$rowid]['text']) {
                $changedText[] = $rowid;
            }
            if ($currentByRowid[$rowid]['bytesHex'] !== $nextByRowid[$rowid]['bytesHex']) {
                $changedBytes[] = $rowid;
            }
            if ($currentByRowid[$rowid]['textEncoding'] !== $nextByRowid[$rowid]['textEncoding']) {
                $changedEncoding[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storageClass'] !== $nextByRowid[$rowid]['storageClass']) {
                $changedStorage[] = $rowid;
            }
            if ($currentByRowid[$rowid]['residualMatch'] !== $nextByRowid[$rowid]['residualMatch']) {
                $changedResidual[] = $rowid;
            }
        }

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($range === null) {
            $reasons[] = 'glob-class-full-scan';
        }
        foreach ([
            'text-value' => $changedText,
            'text-bytes' => $changedBytes,
            'text-encoding' => $changedEncoding,
            'storage-class' => $changedStorage,
            'residual-truth' => $changedResidual,
            'matched-rowset' => ($entered !== [] || $exited !== []) ? array_values(array_unique(array_merge($entered, $exited))) : [],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next255',
            'operator' => 'GLOB',
            'expression' => 'option_name GLOB ? /* bracket class has no prefix range */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'range' => $range,
            'rangeUsable' => $range !== null,
            'fullScanResidualRequired' => $range === null,
            'globCharacterClassPattern' => str_starts_with($pattern, '[') || str_starts_with($pattern, '[^'),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => $retained,
            'enteredRowids' => $entered,
            'exitedRowids' => $exited,
            'changedTextRowids' => $changedText,
            'changedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'changedStorageClassRowids' => $changedStorage,
            'changedResidualTruthRowids' => $changedResidual,
            'currentText' => self::fieldByRowid($currentByRowid, 'text'),
            'nextText' => self::fieldByRowid($nextByRowid, 'text'),
            'currentBytesHex' => self::fieldByRowid($currentByRowid, 'bytesHex'),
            'nextBytesHex' => self::fieldByRowid($nextByRowid, 'bytesHex'),
            'currentTextEncodings' => self::fieldByRowid($currentByRowid, 'textEncoding'),
            'nextTextEncodings' => self::fieldByRowid($nextByRowid, 'textEncoding'),
            'currentStorageClasses' => self::fieldByRowid($currentByRowid, 'storageClass'),
            'nextStorageClasses' => self::fieldByRowid($nextByRowid, 'storageClass'),
            'currentOptionValues' => self::fieldByRowid($currentByRowid, 'optionValue'),
            'nextOptionValues' => self::fieldByRowid($nextByRowid, 'optionValue'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-glob-character-class',
                'sqlite-mixed-utf-source-decoder',
                'sqlite-text-affinity',
                'sqlite-current-source-next255',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-8/UTF-16 decode, scalar text-affinity coercion, and GLOB bracket-class residual matching',
            'non_overlap' => 'next255 covers GLOB bracket-class residual fallback when no fixed prefix range exists; avoids next252 numeric prefix cursor, next251 prepared LIKE pattern affinity, next250 RTRIM LIKE residual peers, accepted Unicode GLOB prefix ranges, malformed UTF guards, JSON, WAL, VFS, B-tree, and SQL planner clusters',
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function scanRows(array $rows, string $pattern): array
    {
        $matched = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row) && !array_key_exists('option_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite GLOB class next255 rows require option_name or option_name_bytes');
            }
            $coerced = self::coerceText($row);
            if ($coerced === null) {
                continue;
            }
            $residual = SQLiteDatabase::globMatches($coerced['text'], $pattern);
            if (!$residual) {
                continue;
            }
            $matched[] = [
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'text' => $coerced['text'],
                'bytesHex' => bin2hex($coerced['bytes']),
                'textEncoding' => $coerced['textEncoding'],
                'storageClass' => $coerced['storageClass'],
                'optionValue' => $row['option_value'] ?? null,
                'residualMatch' => true,
            ];
        }

        usort($matched, static fn (array $left, array $right): int => strcmp($left['text'], $right['text']) ?: $left['rowid'] <=> $right['rowid']);

        return $matched;
    }

    /** @param array<string,mixed> $row @return array{text:string,bytes:string,textEncoding:string,storageClass:string}|null */
    private static function coerceText(array $row): ?array
    {
        if (array_key_exists('option_name_bytes', $row)) {
            if (!is_string($row['option_name_bytes']) || !isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite GLOB class next255 byte rows require option_name_bytes and integer text_encoding');
            }
            $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);

            return [
                'text' => $text,
                'bytes' => $row['option_name_bytes'],
                'textEncoding' => self::encodingName($row['text_encoding']),
                'storageClass' => 'text',
            ];
        }

        $value = $row['option_name'];
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_int($value) || is_bool($value)) {
            $text = (string) (int) $value;
            return ['text' => $text, 'bytes' => $text, 'textEncoding' => 'UTF-8', 'storageClass' => 'integer'];
        }
        if (is_float($value)) {
            $text = self::formatReal($value);
            return ['text' => $text, 'bytes' => $text, 'textEncoding' => 'UTF-8', 'storageClass' => 'real'];
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite GLOB class next255 string option_name must be well-formed UTF-8');
            }

            return ['text' => $value, 'bytes' => $value, 'textEncoding' => 'UTF-8', 'storageClass' => 'text'];
        }

        throw new \InvalidArgumentException('SQLite GLOB class next255 option_name must be scalar text-affinity input');
    }

    private static function formatReal(float $value): string
    {
        $formatted = sprintf('%.15G', $value);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite GLOB class next255 text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }
}
