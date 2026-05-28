<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext230Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameLineBreakBoundaryPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin_cache',
        ?string $escape = null,
        string $currentSource = 'main.wp_options@229',
        string $nextSource = 'main.wp_options@230',
        int $currentSchemaCookie = 229,
        int $nextSchemaCookie = 230,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::changes($current['decoded'], $next['decoded']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'line-break-suffix' => $changes['lineBreakClassChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
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
        if (
            ($current['lineBreakSuffixRowids'] !== []
                || $next['lineBreakSuffixRowids'] !== []
                || $current['formFeedSuffixRowids'] !== []
                || $next['formFeedSuffixRowids'] !== [])
            && (
                $changes['lineBreakClassChangedRowids'] !== []
                || $currentMatched !== $nextMatched
            )
        ) {
            $reasons[] = 'non-space-rtrim-line-boundary';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next230',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* line-break RTRIM boundary */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'rangeLowerInclusive' => $like['range']['lowerInclusive'] ?? null,
            'rangeUpperBound' => $like['range']['upperBound'] ?? null,
            'indexUsable' => $like['indexUsable'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::sortedDiff($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::sortedDiff($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentAsciiSpaceSuffixRowids' => $current['asciiSpaceSuffixRowids'],
            'nextAsciiSpaceSuffixRowids' => $next['asciiSpaceSuffixRowids'],
            'currentLineBreakSuffixRowids' => $current['lineBreakSuffixRowids'],
            'nextLineBreakSuffixRowids' => $next['lineBreakSuffixRowids'],
            'currentFormFeedSuffixRowids' => $current['formFeedSuffixRowids'],
            'nextFormFeedSuffixRowids' => $next['formFeedSuffixRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentDecodedTexts' => self::map($current['decoded'], 'text'),
            'nextDecodedTexts' => self::map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'currentSuffixClasses' => self::map($current['decoded'], 'suffixClass'),
            'nextSuffixClasses' => self::map($next['decoded'], 'suffixClass'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedLineBreakClassRowids' => $changes['lineBreakClassChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'asciiSpaceSuffixMatchesAfterRtrim' => true,
            'lineBreakSuffixDoesNotRtrim' => true,
            'formFeedSuffixDoesNotRtrim' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-line-break-rtrim-boundary',
                'sqlite-current-source-next230',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and binary-safe residual matching',
            'non_overlap' => 'next230 covers CR/LF/form-feed suffixes that remain significant after RTRIM for UTF-16 NOCASE LIKE current-source cursors; avoids accepted next227 tab/NBSP boundary, next226 combining-mark normalization, next225 source-byte fencing, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,asciiSpaceSuffixRowids:list<int>,lineBreakSuffixRowids:list<int>,formFeedSuffixRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $asciiSpace = [];
        $lineBreaks = [];
        $formFeeds = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $suffixClass = self::suffixClass($text);
                if ($suffixClass === 'ascii-space') {
                    $asciiSpace[] = $row['option_id'];
                } elseif ($suffixClass === 'line-break') {
                    $lineBreaks[] = $row['option_id'];
                } elseif ($suffixClass === 'form-feed') {
                    $formFeeds[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                    'suffixClass' => $suffixClass,
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($asciiSpace);
        sort($lineBreaks);
        sort($formFeeds);
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
        $residualByRowid = [];
        foreach ($candidates as $entry) {
            $residualByRowid[$entry['rowid']] = $entry['residualMatch'];
        }
        foreach ($decoded as &$entry) {
            if (array_key_exists($entry['rowid'], $residualByRowid)) {
                $entry['residualMatch'] = $residualByRowid[$entry['rowid']];
            }
        }
        unset($entry);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'asciiSpaceSuffixRowids' => $asciiSpace,
            'lineBreakSuffixRowids' => $lineBreaks,
            'formFeedSuffixRowids' => $formFeeds,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next230 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next230 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next230 rows require integer text_encoding');
        }
    }

    private static function suffixClass(string $text): string
    {
        if (str_ends_with($text, ' ')) {
            return 'ascii-space';
        }
        if (str_ends_with($text, "\n") || str_ends_with($text, "\r")) {
            return 'line-break';
        }
        if (str_ends_with($text, "\f")) {
            return 'form-feed';
        }

        return 'none';
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
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,suffixClass:string,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,suffixClass:string,residualMatch?:bool}> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,lineBreakClassChangedRowids:list<int>,residualChangedRowids:list<int>}
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
            'lineBreakClassChangedRowids' => [],
            'residualChangedRowids' => [],
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
            if ($current[$rowid]['suffixClass'] !== $row['suffixClass']) {
                $changes['lineBreakClassChangedRowids'][] = $rowid;
            }
            if (($current[$rowid]['residualMatch'] ?? null) !== ($row['residualMatch'] ?? null)) {
                $changes['residualChangedRowids'][] = $rowid;
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function sortedIntersect(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function sortedDiff(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
