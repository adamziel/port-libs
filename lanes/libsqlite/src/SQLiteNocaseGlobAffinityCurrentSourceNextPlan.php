<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteNocaseGlobAffinityCurrentSourceNextPlan
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
        string $currentSource = 'main.app_settings@138',
        string $nextSource = 'main.app_settings@139',
        int $currentSchemaCookie = 138,
        int $nextSchemaCookie = 139,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("SQLite NOCASE GLOB current-source collation {$collation} is not supported");
        }

        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $rangeUsable = $range !== null && $collation === 'BINARY';
        $currentTrace = self::traceRows($currentRows, $pattern, $affinity, $collation, $range, $rangeUsable);
        $nextTrace = self::traceRows($nextRows, $pattern, $affinity, $collation, $range, $rangeUsable);
        $currentByRowid = self::byRowid($currentTrace);
        $nextByRowid = self::byRowid($nextTrace);
        $currentMatches = self::rowidsWhere($currentTrace, 'matched');
        $nextMatches = self::rowidsWhere($nextTrace, 'matched');
        $currentCandidates = self::rowidsWhere($currentTrace, 'candidate');
        $nextCandidates = self::rowidsWhere($nextTrace, 'candidate');

        $changedText = [];
        $changedStorage = [];
        $changedBytes = [];
        $changedCandidate = [];
        $changedMatch = [];
        foreach (array_unique(array_merge(array_keys($currentByRowid), array_keys($nextByRowid))) as $rowid) {
            $current = $currentByRowid[$rowid] ?? null;
            $next = $nextByRowid[$rowid] ?? null;
            if ($current === null || $next === null || $current['text'] !== $next['text']) {
                $changedText[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['storage'] !== $next['storage']) {
                $changedStorage[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['bytesHex'] !== $next['bytesHex']) {
                $changedBytes[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['candidate'] !== $next['candidate']) {
                $changedCandidate[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['matched'] !== $next['matched']) {
                $changedMatch[] = (int) $rowid;
            }
        }
        sort($changedText);
        sort($changedStorage);
        sort($changedBytes);
        sort($changedCandidate);
        sort($changedMatch);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($range !== null && !$rangeUsable) {
            $reasons[] = 'glob-range-requires-binary-collation';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }
        if ($changedText !== []) {
            $reasons[] = 'text-affinity';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($changedCandidate !== []) {
            $reasons[] = 'candidate-rowset';
        }
        if ($changedMatch !== []) {
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
            'fallbackReason' => $range === null ? 'no-fixed-prefix' : ($rangeUsable ? null : 'glob-range-requires-binary-collation'),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $currentTrace,
            'nextTrace' => $nextTrace,
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentRowids' => $currentMatches,
            'nextRowids' => $nextMatches,
            'retainedRowids' => array_values(array_intersect($currentMatches, $nextMatches)),
            'enteredRowids' => array_values(array_diff($nextMatches, $currentMatches)),
            'exitedRowids' => array_values(array_diff($currentMatches, $nextMatches)),
            'currentResidualRejectedRowids' => array_values(array_diff($currentCandidates, $currentMatches)),
            'nextResidualRejectedRowids' => array_values(array_diff($nextCandidates, $nextMatches)),
            'changedTextRowids' => $changedText,
            'changedStorageRowids' => $changedStorage,
            'changedBytesRowids' => $changedBytes,
            'changedCandidateRowids' => $changedCandidate,
            'changedMatchRowids' => $changedMatch,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-glob-bytewise-residual',
                'sqlite-nocase-index-range-rejection',
                'sqlite-affinity-text-coercion',
                'sqlite-current-source-next139',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return list<array<string,mixed>>
     */
    private static function traceRows(array $rows, string $pattern, string $affinity, string $collation, ?array $range, bool $rangeUsable): array
    {
        $trace = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !array_key_exists('option_name', $row)) {
                throw new \InvalidArgumentException('SQLite NOCASE GLOB current-source row is missing option_name');
            }
            $rowid = $row['option_id'] ?? ($index + 1);
            if (!is_int($rowid)) {
                throw new \InvalidArgumentException('SQLite NOCASE GLOB current-source option_id must be an integer when present');
            }
            $text = self::coerceText($row['option_name'], $affinity);
            $candidate = $rangeUsable
                ? self::inRange($text, $range, $collation)
                : true;
            $matched = $candidate && SQLiteDatabase::globMatches($text, $pattern);
            $trace[] = [
                'rowid' => $rowid,
                'text' => $text,
                'storage' => SQLiteAffinityComparison::storageClass($row['option_name']),
                'bytesHex' => strtoupper(bin2hex($text)),
                'candidate' => $candidate,
                'matched' => $matched,
                'rangeClass' => self::rangeClass($text, $range, $collation),
                'payload' => $row,
            ];
        }

        usort($trace, static function (array $left, array $right) use ($collation): int {
            $comparison = SQLiteAffinityComparison::compare($left['text'], $right['text'], 'TEXT', 'TEXT', $collation);

            return $comparison !== null && $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return $trace;
    }

    private static function coerceText(mixed $value, string $affinity): string
    {
        if ($value instanceof SQLiteBlobValue || $value === null) {
            throw new \InvalidArgumentException('SQLite NOCASE GLOB current-source requires non-null text-affinity values');
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite NOCASE GLOB current-source requires well-formed UTF-8 text');
            }

            return $value;
        }
        SQLiteAffinityComparison::coercedPair($value, '', $affinity, 'TEXT');
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
        }

        throw new \InvalidArgumentException('SQLite NOCASE GLOB current-source requires scalar option_name values');
    }

    /**
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     */
    private static function inRange(string $text, ?array $range, string $collation): bool
    {
        if ($range === null) {
            return false;
        }

        return self::rangeClass($text, $range, $collation) === 'in-range';
    }

    /**
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     */
    private static function rangeClass(string $text, ?array $range, string $collation): string
    {
        if ($range === null) {
            return 'residual-only';
        }
        $lower = SQLiteAffinityComparison::compare($text, $range['lowerInclusive'], 'TEXT', 'TEXT', $collation);
        $upper = $range['upperBound'] === null ? -1 : SQLiteAffinityComparison::compare($text, $range['upperBound'], 'TEXT', 'TEXT', $collation);
        if ($lower !== null && $lower < 0) {
            return 'before-range';
        }
        if ($upper !== null && $upper >= 0) {
            return 'after-range';
        }

        return 'in-range';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function byRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = $row;
        }

        return $byRowid;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
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
}
