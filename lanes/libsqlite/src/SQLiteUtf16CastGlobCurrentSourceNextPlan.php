<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16CastGlobCurrentSourceNextPlan
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
        string $currentSource = 'main.wp_options@134',
        string $nextSource = 'main.wp_options@135',
        int $currentSchemaCookie = 134,
        int $nextSchemaCookie = 135,
    ): array {
        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $currentTrace = self::traceRows($currentRows, $pattern, $range);
        $nextTrace = self::traceRows($nextRows, $pattern, $range);
        $currentByRowid = self::byRowid($currentTrace['rows']);
        $nextByRowid = self::byRowid($nextTrace['rows']);

        $currentCandidates = self::rowidsWhere($currentTrace['rows'], 'candidate');
        $nextCandidates = self::rowidsWhere($nextTrace['rows'], 'candidate');
        $currentMatched = self::rowidsWhere($currentTrace['rows'], 'matched');
        $nextMatched = self::rowidsWhere($nextTrace['rows'], 'matched');
        $changedCast = [];
        $changedBytes = [];
        $changedEncoding = [];
        $changedCandidate = [];
        $changedMatch = [];
        foreach (array_unique(array_merge(array_keys($currentByRowid), array_keys($nextByRowid))) as $rowid) {
            $current = $currentByRowid[$rowid] ?? null;
            $next = $nextByRowid[$rowid] ?? null;
            if ($current === null || $next === null || $current['castText'] !== $next['castText'] || $current['castStorage'] !== $next['castStorage']) {
                $changedCast[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['bytesHex'] !== $next['bytesHex']) {
                $changedBytes[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['encoding'] !== $next['encoding']) {
                $changedEncoding[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['candidate'] !== $next['candidate']) {
                $changedCandidate[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['matched'] !== $next['matched']) {
                $changedMatch[] = (int) $rowid;
            }
        }
        sort($changedCast);
        sort($changedBytes);
        sort($changedEncoding);
        sort($changedCandidate);
        sort($changedMatch);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($range === null) {
            $reasons[] = 'no-prefix-range';
        }
        if ($currentTrace['malformedRowids'] !== [] || $nextTrace['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($changedCast !== []) {
            $reasons[] = 'cast-result';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedCandidate !== []) {
            $reasons[] = 'candidate-rowset';
        }
        if ($changedMatch !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'operator' => 'GLOB',
            'collation' => 'BINARY',
            'castTarget' => 'TEXT',
            'pattern' => $pattern,
            'range' => $range,
            'indexUsable' => $range !== null,
            'residualScan' => true,
            'globDoesNotUseCollation' => true,
            'castDecodesUtf16BeforeGlob' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $currentTrace['rows'],
            'nextTrace' => $nextTrace['rows'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentResidualRejectedRowids' => array_values(array_diff($currentCandidates, $currentMatched)),
            'nextResidualRejectedRowids' => array_values(array_diff($nextCandidates, $nextMatched)),
            'currentRowids' => $currentMatched,
            'nextRowids' => $nextMatched,
            'retainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'changedCastRowids' => $changedCast,
            'changedBytesRowids' => $changedBytes,
            'changedEncodingRowids' => $changedEncoding,
            'changedCandidateRowids' => $changedCandidate,
            'changedMatchRowids' => $changedMatch,
            'currentMalformedRowids' => $currentTrace['malformedRowids'],
            'nextMalformedRowids' => $nextTrace['malformedRowids'],
            'currentErrors' => $currentTrace['errors'],
            'nextErrors' => $nextTrace['errors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-select-cast-expression',
                'sqlite-glob-prefix-range',
                'sqlite-current-source-nextoneThreeFive',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return array{rows:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function traceRows(array $rows, string $pattern, ?array $range): array
    {
        $trace = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $castText = SQLiteEncodingCollationSourceCursor::decodeText($row['option_value_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
                continue;
            }

            $candidate = $range !== null && strcmp($castText, $range['lowerInclusive']) >= 0
                && ($range['upperBound'] === null || strcmp($castText, $range['upperBound']) < 0);
            $matched = $candidate && SQLiteDatabase::globMatches($castText, $pattern);
            $trace[] = [
                'rowid' => $row['option_id'],
                'optionName' => $row['option_name'] ?? null,
                'originalStorage' => self::storageClass($row),
                'castStorage' => 'text',
                'encoding' => self::encodingName($row['text_encoding']),
                'bytesHex' => strtoupper(bin2hex($row['option_value_bytes'])),
                'castText' => $castText,
                'castTextHex' => strtoupper(bin2hex($castText)),
                'candidate' => $candidate,
                'matched' => $matched,
            ];
        }

        usort($trace, static fn (array $left, array $right): int => $left['rowid'] <=> $right['rowid']);
        sort($malformed);
        ksort($errors);

        return ['rows' => $trace, 'malformedRowids' => $malformed, 'errors' => $errors];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 CAST GLOB rows require integer option_id');
        }
        if (!array_key_exists('option_value_bytes', $row) || !is_string($row['option_value_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 CAST GLOB rows require option_value_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 CAST GLOB rows require integer text_encoding');
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function storageClass(array $row): string
    {
        $storage = $row['storage_class'] ?? 'text';
        if (!is_string($storage) || !in_array($storage, ['text', 'blob'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 CAST GLOB storage_class must be text or blob');
        }

        return $storage;
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

    /**
     * @param list<array<string,mixed>> $trace
     * @return array<int,array<string,mixed>>
     */
    private static function byRowid(array $trace): array
    {
        $indexed = [];
        foreach ($trace as $entry) {
            $indexed[$entry['rowid']] = $entry;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return list<int>
     */
    private static function rowidsWhere(array $trace, string $flag): array
    {
        return array_values(array_map(
            static fn (array $entry): int => $entry['rowid'],
            array_filter($trace, static fn (array $entry): bool => $entry[$flag] === true),
        ));
    }
}
