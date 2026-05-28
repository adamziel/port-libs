<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext158Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNamePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@157',
        string $nextSource = 'main.wp_options@158',
        int $currentSchemaCookie = 157,
        int $nextSchemaCookie = 158,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $changes = self::changes($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$like['indexUsable']) {
            $reasons[] = 'no-nocase-prefix-range';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'residual-result' => $changes['matchChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next158',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'prefix' => $like['prefix'],
            'prefixCharacters' => $like['prefixCharacters'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'rejectedReason' => $like['rejectedReason'],
            'range' => $like['range'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentOrderRowids' => self::rowids($current['decoded']),
            'nextOrderRowids' => self::rowids($next['decoded']),
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTexts' => self::map($current['decoded'], 'text'),
            'nextTexts' => self::map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'currentEncodings' => self::map($current['decoded'], 'encoding'),
            'nextEncodings' => self::map($next['decoded'], 'encoding'),
            'currentBytesHex' => self::map($current['decoded'], 'bytesHex'),
            'nextBytesHex' => self::map($next['decoded'], 'bytesHex'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['matchChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-next158',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE prefix planning, and current-source invalidation metadata',
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
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $range)) {
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
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next158 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next158 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next158 rows require integer text_encoding');
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

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
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

    /**
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,matchChangedRowids:list<int>}
     */
    private static function changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'encodingChangedRowids' => [],
            'bytesChangedRowids' => [],
            'matchChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $changes['textChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['rtrimText'] !== $row['rtrimText']) {
                $changes['rtrimChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['nocaseKey'] !== $row['nocaseKey']) {
                $changes['nocaseKeyChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $changes['encodingChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $changes['bytesChangedRowids'][] = $rowid;
            }
            if (($current[$rowid]['residualMatch'] ?? null) !== ($row['residualMatch'] ?? null)) {
                $changes['matchChangedRowids'][] = $rowid;
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
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

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
