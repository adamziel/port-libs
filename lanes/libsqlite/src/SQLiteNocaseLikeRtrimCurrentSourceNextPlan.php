<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteNocaseLikeRtrimCurrentSourceNextPlan
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
        ?string $escape = null,
        string $currentSource = 'main.wp_options@145',
        string $nextSource = 'main.wp_options@146',
        int $currentSchemaCookie = 145,
        int $nextSchemaCookie = 146,
    ): array {
        $likePlan = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $range = $likePlan['range'];
        $rangeUsable = $range !== null;

        $current = self::scan($currentRows, $pattern, $escape, $range);
        $next = self::scan($nextRows, $pattern, $escape, $range);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
        $currentMatches = self::rowids($current['matched']);
        $nextMatches = self::rowids($next['matched']);
        $changes = self::changes($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$rangeUsable) {
            $reasons[] = $likePlan['rejectedReason'] ?? 'no-prefix-range';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        foreach ([
            'text-value' => $changes['textChangedRowids'],
            'rtrim-value' => $changes['rtrimChangedRowids'],
            'nocase-rtrim-key' => $changes['nocaseRtrimKeyChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
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
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'range' => $range,
            'likePlan' => $likePlan,
            'rangeUsable' => $rangeUsable,
            'indexKey' => 'ascii_lower(rtrim(option_name, space))',
            'residualUsesRtrimText' => true,
            'nocaseIsAsciiOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentOrderRowids' => self::rowids($current['decoded']),
            'nextOrderRowids' => self::rowids($next['decoded']),
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatches,
            'nextMatchedRowids' => $nextMatches,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatches, $nextMatches)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatches, $currentMatches)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatches, $nextMatches)),
            'currentDecoded' => $current['decoded'],
            'nextDecoded' => $next['decoded'],
            'currentTexts' => self::map($current['decoded'], 'text'),
            'nextTexts' => self::map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentKeys' => self::map($current['decoded'], 'nocaseRtrimKey'),
            'nextKeys' => self::map($next['decoded'], 'nocaseRtrimKey'),
            'currentEncodings' => self::map($current['decoded'], 'encoding'),
            'nextEncodings' => self::map($next['decoded'], 'encoding'),
            'currentBytesHex' => self::map($current['decoded'], 'bytesHex'),
            'nextBytesHex' => self::map($next['decoded'], 'bytesHex'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseRtrimKeyRowids' => $changes['nocaseRtrimKeyChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $rangeUsable,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-index',
                'sqlite-current-source-next146',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF text decoding, ASCII NOCASE LIKE range planning, RTRIM expression keys, and current-source invalidation metadata',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrimText = rtrim($text, ' ');
                $key = self::asciiLower($rtrimText);
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrimText,
                    'nocaseRtrimKey' => $key,
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                    'rangeClass' => self::rangeClass($key, $range),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, static fn (array $left, array $right): int => ($left['nocaseRtrimKey'] <=> $right['nocaseRtrimKey']) ?: ($left['rowid'] <=> $right['rowid']));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseRtrimKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite NOCASE LIKE RTRIM current-source next146 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite NOCASE LIKE RTRIM current-source next146 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite NOCASE LIKE RTRIM current-source next146 rows require integer text_encoding');
        }
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
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseRtrimKey:string,encoding:string,bytesHex:string,rangeClass:string}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseRtrimKey:string,encoding:string,bytesHex:string,rangeClass:string}> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseRtrimKeyChangedRowids:list<int>,bytesChangedRowids:list<int>,encodingChangedRowids:list<int>}
     */
    private static function changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $nextRowids = [];
        $text = [];
        $rtrim = [];
        $key = [];
        $bytes = [];
        $encoding = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            $nextRowids[] = $rowid;
            if (!isset($current[$rowid])) {
                $text[] = $rowid;
                $rtrim[] = $rowid;
                $key[] = $rowid;
                $bytes[] = $rowid;
                $encoding[] = $rowid;
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $text[] = $rowid;
            }
            if ($current[$rowid]['rtrimText'] !== $row['rtrimText']) {
                $rtrim[] = $rowid;
            }
            if ($current[$rowid]['nocaseRtrimKey'] !== $row['nocaseRtrimKey']) {
                $key[] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $bytes[] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $encoding[] = $rowid;
            }
        }
        foreach ($current as $rowid => $_row) {
            if (!in_array($rowid, $nextRowids, true)) {
                $text[] = $rowid;
                $rtrim[] = $rowid;
                $key[] = $rowid;
                $bytes[] = $rowid;
                $encoding[] = $rowid;
            }
        }

        return [
            'textChangedRowids' => self::uniqueSortedInts($text),
            'rtrimChangedRowids' => self::uniqueSortedInts($rtrim),
            'nocaseRtrimKeyChangedRowids' => self::uniqueSortedInts($key),
            'bytesChangedRowids' => self::uniqueSortedInts($bytes),
            'encodingChangedRowids' => self::uniqueSortedInts($encoding),
        ];
    }

    /** @param list<int> $values @return list<int> */
    private static function uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite NOCASE LIKE RTRIM current-source next146 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
