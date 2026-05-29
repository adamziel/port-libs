<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext201Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameNullPatternRebindPlan(
        array $currentRows,
        array $nextRows,
        ?string $currentPattern = 'plugin!_cache%',
        ?string $nextPattern = null,
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@200',
        string $nextSource = 'main.wp_options@201',
        int $currentSchemaCookie = 200,
        int $nextSchemaCookie = 201,
    ): array {
        $current = self::scan($currentRows, $currentPattern, $escape);
        $next = self::scan($nextRows, $nextPattern, $escape);
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
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'pattern-rebound';
        }
        if ($currentPattern !== $nextPattern && ($currentPattern === null || $nextPattern === null)) {
            $reasons[] = 'null-like-pattern';
        }
        if (($current['likePlan']['range'] ?? null) !== ($next['likePlan']['range'] ?? null)) {
            $reasons[] = 'like-range';
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
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next201',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* NULL pattern rebind */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPatternIsSqlNull' => $currentPattern === null,
            'nextPatternIsSqlNull' => $nextPattern === null,
            'currentLikeResultIsNull' => $currentPattern === null,
            'nextLikeResultIsNull' => $nextPattern === null,
            'currentPrefix' => $current['likePlan']['prefix'],
            'nextPrefix' => $next['likePlan']['prefix'],
            'currentRangeLowerInclusive' => $current['likePlan']['range']['lowerInclusive'] ?? null,
            'currentRangeUpperBound' => $current['likePlan']['range']['upperBound'] ?? null,
            'nextRangeLowerInclusive' => $next['likePlan']['range']['lowerInclusive'] ?? null,
            'nextRangeUpperBound' => $next['likePlan']['range']['upperBound'] ?? null,
            'currentIndexUsable' => $current['likePlan']['indexUsable'],
            'nextIndexUsable' => $next['likePlan']['indexUsable'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'candidateExitedRowids' => array_values(array_diff($currentCandidates, $nextCandidates)),
            'candidateEnteredRowids' => array_values(array_diff($nextCandidates, $currentCandidates)),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedExitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'matchedEnteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentExcludedDecodedRowids' => array_values(array_diff(self::rowids($current['decoded']), $currentCandidates)),
            'nextExcludedDecodedRowids' => array_values(array_diff(self::rowids($next['decoded']), $nextCandidates)),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'currentMatchedTexts' => self::selectMap(self::map($current['decoded'], 'rtrimText'), $currentMatched),
            'nextMatchedTexts' => self::selectMap(self::map($next['decoded'], 'rtrimText'), $nextMatched),
            'mustReprepareForNullPattern' => $currentPattern !== $nextPattern && ($currentPattern === null || $nextPattern === null),
            'nullPatternDisablesPrefixRange' => $currentPattern === null || $nextPattern === null,
            'nullPatternMatchesNoRows' => $currentPattern === null || $nextPattern === null,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleRangeCursorRisk' => $nextPattern === null && $currentCandidates !== [],
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-null-pattern-rebind',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next201',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE NULL-result semantics, RTRIM keys, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'next201 adds prepared LIKE pattern SQL NULL rebind fencing for UTF-16 RTRIM/NOCASE current-source cursors; avoids accepted escape rebind next200, escaped wildcard next194, prepared byte rebind next191, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, ?string $pattern, ?string $escape): array
    {
        $like = $pattern === null
            ? [
                'prefix' => null,
                'range' => null,
                'indexUsable' => false,
                'rejectedReason' => 'null_like_pattern',
            ]
            : SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
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
            if ($pattern === null || !self::inRange($entry['nocaseKey'], $like['range'])) {
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
            'likePlan' => $like,
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
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next201 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next201 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next201 rows require integer text_encoding');
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

    /** @param array<int,string> $values @param list<int> $rowids @return array<int,string> */
    private static function selectMap(array $values, array $rowids): array
    {
        $selected = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $values)) {
                $selected[$rowid] = $values[$rowid];
            }
        }

        return $selected;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
