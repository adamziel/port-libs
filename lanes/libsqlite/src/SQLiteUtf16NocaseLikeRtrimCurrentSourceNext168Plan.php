<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext168Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameCaseSensitiveLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@167',
        string $nextSource = 'main.wp_options@168',
        int $currentSchemaCookie = 167,
        int $nextSchemaCookie = 168,
        bool $currentCaseSensitiveLike = false,
        bool $nextCaseSensitiveLike = true,
    ): array {
        $currentLike = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, $currentCaseSensitiveLike);
        $nextLike = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, $nextCaseSensitiveLike);
        $current = self::scan($currentRows, $pattern, $escape, $currentLike['range'], $currentCaseSensitiveLike);
        $next = self::scan($nextRows, $pattern, $escape, $currentLike['range'], $nextCaseSensitiveLike);
        $nextFull = self::scan($nextRows, $pattern, $escape, null, $nextCaseSensitiveLike, true);

        $currentCandidateRowids = self::rowids($current['candidates']);
        $nextCandidateRowids = self::rowids($next['candidates']);
        $currentMatchedRowids = self::rowids($current['matched']);
        $nextMatchedRowids = self::rowids($next['matched']);
        $nextFullMatchedRowids = self::rowids($nextFull['matched']);
        $changes = self::changes($current['decoded'], $next['decoded']);
        $changes['matchChangedRowids'] = self::residualChanges($current['candidates'], $next['candidates']);
        $retainedCandidateRowids = array_values(array_intersect($currentCandidateRowids, $nextCandidateRowids));
        $retainedMatchedRowids = array_values(array_intersect($currentMatchedRowids, $nextMatchedRowids));

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentCaseSensitiveLike !== $nextCaseSensitiveLike) {
            $reasons[] = 'case-sensitive-like';
        }
        if (!$currentLike['indexUsable']) {
            $reasons[] = 'current-no-nocase-prefix-range';
        }
        if (!$nextLike['indexUsable']) {
            $reasons[] = 'next-nocase-index-unusable';
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
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($nextCandidateRowids !== $nextFullMatchedRowids) {
            $reasons[] = 'case-sensitive-fullscan-required';
        }

        $caseSensitiveDrops = array_values(array_diff($currentMatchedRowids, $nextMatchedRowids));
        $caseSensitiveKeeps = array_values(array_intersect($currentMatchedRowids, $nextMatchedRowids));
        $caseSensitiveEnters = array_values(array_diff($nextMatchedRowids, $currentMatchedRowids));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next168',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCaseSensitiveLike' => $currentCaseSensitiveLike,
            'nextCaseSensitiveLike' => $nextCaseSensitiveLike,
            'currentLikePlan' => $currentLike,
            'nextLikePlan' => $nextLike,
            'prefix' => $currentLike['prefix'],
            'currentRange' => $currentLike['range'],
            'nextRange' => $nextLike['range'],
            'currentIndexUsable' => $currentLike['indexUsable'],
            'nextIndexUsable' => $nextLike['indexUsable'],
            'nextRejectedReason' => $nextLike['rejectedReason'],
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowidsUsingCurrentNocaseRange' => $nextCandidateRowids,
            'retainedCandidateRowids' => $retainedCandidateRowids,
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'nextFullScanMatchedRowids' => $nextFullMatchedRowids,
            'retainedMatchedRowids' => $retainedMatchedRowids,
            'caseSensitiveDroppedRowids' => $caseSensitiveDrops,
            'caseSensitiveKeptRowids' => $caseSensitiveKeeps,
            'caseSensitiveEnteredRowids' => $caseSensitiveEnters,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'caseSensitiveRangeFalsePositiveRowids' => array_values(array_diff($nextCandidateRowids, $nextMatchedRowids)),
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
            'currentNocaseRangeCanSeedRecheck' => $currentLike['indexUsable'],
            'nextRequiresBinaryLikeScan' => !$nextLike['indexUsable'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'caseSensitiveLikeHonorsAsciiCase' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-case-sensitive-like',
                'sqlite-current-source-next168',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, NOCASE LIKE prefix planning, and adds case-sensitive LIKE residual recheck diagnostics for current-source transitions',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range, bool $caseSensitiveLike, bool $fullScan = false): array
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
            if (!$fullScan && !self::inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, $caseSensitiveLike);
            if (!$fullScan) {
                $candidates[] = $entry;
            }
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } elseif (!$fullScan) {
                $falsePositive[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'candidates' => $fullScan ? $matched : $candidates,
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
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next168 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next168 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next168 rows require integer text_encoding');
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

    /**
     * @param list<array{rowid:int,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,residualMatch?:bool}> $nextRows
     * @return list<int>
     */
    private static function residualChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = (bool) ($row['residualMatch'] ?? false);
        }

        $changed = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (array_key_exists($rowid, $current) && $current[$rowid] !== (bool) ($row['residualMatch'] ?? false)) {
                $changed[] = $rowid;
            }
        }
        sort($changed);

        return $changed;
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
