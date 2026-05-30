<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteNocaseRtrimGlobAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $affinity = 'TEXT',
        string $collation = 'NOCASE',
        string $currentSource = 'main.app_settings@141',
        string $nextSource = 'main.app_settings@142',
        int $currentSchemaCookie = 141,
        int $nextSchemaCookie = 142,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite NOCASE/RTRIM GLOB current-source next142 collation must be BINARY, NOCASE, or RTRIM');
        }

        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $rangeUsable = $range !== null && $collation === 'BINARY';
        $current = self::scan($currentRows, $pattern, $affinity, $collation, $range, $rangeUsable);
        $next = self::scan($nextRows, $pattern, $affinity, $collation, $range, $rangeUsable);
        $changes = self::changes($current['trace'], $next['trace']);
        $currentCandidates = self::rowidsWhere($current['trace'], 'candidate');
        $nextCandidates = self::rowidsWhere($next['trace'], 'candidate');
        $currentMatches = self::rowidsWhere($current['trace'], 'matched');
        $nextMatches = self::rowidsWhere($next['trace'], 'matched');

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($range === null) {
            $reasons[] = 'no-prefix-range';
        } elseif (!$rangeUsable) {
            $reasons[] = 'glob-range-requires-binary-collation';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        foreach ([
            'affinity-value' => $changes['affinityValueRowids'],
            'storage-class' => $changes['storageRowids'],
            'collation-key' => $changes['collationKeyRowids'],
            'encoded-bytes' => $changes['bytesRowids'],
            'text-encoding' => $changes['encodingRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatches !== $nextMatches) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'operator' => 'GLOB',
            'pattern' => $pattern,
            'affinity' => strtoupper($affinity),
            'collation' => $collation,
            'range' => $range,
            'rangeUsable' => $rangeUsable,
            'residualScan' => !$rangeUsable,
            'fallbackReason' => $range === null ? 'no-prefix-range' : ($rangeUsable ? null : 'glob-range-requires-binary-collation'),
            'globResidualUsesBytewiseText' => true,
            'nocaseOnlyOrdersIndexKeys' => $collation === 'NOCASE',
            'rtrimOnlyTrimsIndexKeys' => $collation === 'RTRIM',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentOrderRowids' => self::rowids($current['trace']),
            'nextOrderRowids' => self::rowids($next['trace']),
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentRowids' => $currentMatches,
            'nextRowids' => $nextMatches,
            'currentResidualRejectedRowids' => array_values(array_diff($currentCandidates, $currentMatches)),
            'nextResidualRejectedRowids' => array_values(array_diff($nextCandidates, $nextMatches)),
            'retainedRowids' => array_values(array_intersect($currentMatches, $nextMatches)),
            'enteredRowids' => array_values(array_diff($nextMatches, $currentMatches)),
            'exitedRowids' => array_values(array_diff($currentMatches, $nextMatches)),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedAffinityValueRowids' => $changes['affinityValueRowids'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedCollationKeyRowids' => $changes['collationKeyRowids'],
            'changedBytesRowids' => $changes['bytesRowids'],
            'changedEncodingRowids' => $changes['encodingRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-affinity-comparison',
                'sqlite-glob-bytewise-residual',
                'sqlite-current-source-next142',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF text decoding, affinity coercion, BINARY/NOCASE/RTRIM collation keys, and bytewise GLOB residual matching',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, string $affinity, string $collation, ?array $range, bool $rangeUsable): array
    {
        $trace = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            $rowid = $row['option_id'];
            try {
                self::encodingName($row['text_encoding']);
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $value = self::applyAffinity($text, $affinity);
                $valueText = self::valueText($value);
                $key = self::collationKey($valueText, $collation);
                $candidate = $rangeUsable ? self::inRange($key, $range) : true;
                $matched = $candidate && SQLiteDatabase::globMatches($valueText, $pattern);
                $trace[] = [
                    'rowid' => $rowid,
                    'text' => $text,
                    'affinityValue' => $value,
                    'affinityText' => $valueText,
                    'storage' => SQLiteAffinityComparison::storageClass($value),
                    'collationKey' => $key,
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                    'candidate' => $candidate,
                    'matched' => $matched,
                    'rangeClass' => self::rangeClass($key, $range),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, static function (array $left, array $right) use ($collation): int {
            $comparison = SQLiteAffinityComparison::compare($left['affinityText'], $right['affinityText'], 'TEXT', 'TEXT', $collation);

            return $comparison !== null && $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });
        sort($malformed);
        ksort($errors);

        return ['trace' => $trace, 'malformedRowids' => $malformed, 'errors' => $errors];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite NOCASE/RTRIM GLOB current-source next142 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite NOCASE/RTRIM GLOB current-source next142 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite NOCASE/RTRIM GLOB current-source next142 rows require integer text_encoding');
        }
    }

    private static function applyAffinity(string $text, string $affinity): mixed
    {
        $normalized = strtoupper($affinity);
        if (in_array($normalized, ['INT', 'INTEGER', 'REAL', 'FLOAT', 'DOUBLE', 'NUM', 'NUMERIC', 'BOOLEAN', 'DATE', 'DATETIME'], true)) {
            $trimmed = trim($text);
            if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) !== 1) {
                return $text;
            }
            if (preg_match('/^[+-]?[0-9]+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }

            return (float) $trimmed;
        }

        $pair = SQLiteAffinityComparison::coercedPair($text, '', $affinity, 'TEXT');

        return $pair['left'];
    }

    private static function valueText(mixed $value): string
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            throw new \InvalidArgumentException('SQLite NOCASE/RTRIM GLOB current-source next142 affinity value must be scalar text');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_float($value) ? rtrim(rtrim(sprintf('%.15G', $value), '0'), '.') : (string) $value;
    }

    private static function collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'BINARY' => $text,
            'NOCASE' => self::asciiLower($text),
            'RTRIM' => rtrim($text, ' '),
            default => throw new \InvalidArgumentException('SQLite NOCASE/RTRIM GLOB current-source next142 collation must be BINARY, NOCASE, or RTRIM'),
        };
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function rangeClass(string $key, ?array $range): string
    {
        if ($range === null) {
            return 'residual-only';
        }
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return 'before-range';
        }
        if ($range['upperBound'] !== null && strcmp($key, $range['upperBound']) >= 0) {
            return 'after-range';
        }

        return 'in-range';
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{affinityValueRowids:list<int>,storageRowids:list<int>,collationKeyRowids:list<int>,bytesRowids:list<int>,encodingRowids:list<int>}
     */
    private static function changes(array $current, array $next): array
    {
        $currentByRowid = [];
        foreach ($current as $row) {
            $currentByRowid[$row['rowid']] = $row;
        }

        $value = [];
        $storage = [];
        $key = [];
        $bytes = [];
        $encoding = [];
        foreach ($next as $row) {
            $rowid = $row['rowid'];
            if (!isset($currentByRowid[$rowid])) {
                $value[] = $rowid;
                $storage[] = $rowid;
                $key[] = $rowid;
                $bytes[] = $rowid;
                $encoding[] = $rowid;
                continue;
            }
            if ($currentByRowid[$rowid]['affinityText'] !== $row['affinityText']) {
                $value[] = $rowid;
            }
            if ($currentByRowid[$rowid]['storage'] !== $row['storage']) {
                $storage[] = $rowid;
            }
            if ($currentByRowid[$rowid]['collationKey'] !== $row['collationKey']) {
                $key[] = $rowid;
            }
            if ($currentByRowid[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $bytes[] = $rowid;
            }
            if ($currentByRowid[$rowid]['encoding'] !== $row['encoding']) {
                $encoding[] = $rowid;
            }
        }
        foreach ($currentByRowid as $rowid => $_row) {
            if (!in_array($rowid, array_column($next, 'rowid'), true)) {
                $value[] = $rowid;
                $storage[] = $rowid;
                $key[] = $rowid;
                $bytes[] = $rowid;
                $encoding[] = $rowid;
            }
        }

        $value = self::uniqueSortedInts($value);
        $storage = self::uniqueSortedInts($storage);
        $key = self::uniqueSortedInts($key);
        $bytes = self::uniqueSortedInts($bytes);
        $encoding = self::uniqueSortedInts($encoding);

        return [
            'affinityValueRowids' => $value,
            'storageRowids' => $storage,
            'collationKeyRowids' => $key,
            'bytesRowids' => $bytes,
            'encodingRowids' => $encoding,
        ];
    }

    /** @param list<int> $values @return list<int> */
    private static function uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowidsWhere(array $rows, string $field): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if (($row[$field] ?? false) === true) {
                $rowids[] = $row['rowid'];
            }
        }

        return $rowids;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite NOCASE/RTRIM GLOB current-source next142 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
