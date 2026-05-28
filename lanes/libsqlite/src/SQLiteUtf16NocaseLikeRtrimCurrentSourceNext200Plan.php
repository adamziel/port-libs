<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEscapeRebindPlan(
        array $currentRows,
        array $nextRows,
        string $currentPattern = 'plugin!_%',
        ?string $currentEscape = '!',
        string $nextPattern = 'plugin!_%',
        ?string $nextEscape = '~',
        string $currentSource = 'main.wp_options@199',
        string $nextSource = 'main.wp_options@200',
        int $currentSchemaCookie = 199,
        int $nextSchemaCookie = 200,
    ): array {
        $current = self::scan($currentRows, $currentPattern, $currentEscape);
        $next = self::scan($nextRows, $nextPattern, $nextEscape);
        $nextWithCurrentEscape = self::scan($nextRows, $currentPattern, $currentEscape);
        $currentWithNextEscape = self::scan($currentRows, $nextPattern, $nextEscape);

        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $nextMatchedWithCurrentEscape = self::rowids($nextWithCurrentEscape['matched']);
        $currentMatchedWithNextEscape = self::rowids($currentWithNextEscape['matched']);
        $escapeResidualFlip = self::symmetricDifference($nextMatchedWithCurrentEscape, $nextMatched);
        $currentEscapeResidualFlip = self::symmetricDifference($currentMatched, $currentMatchedWithNextEscape);
        $matchedExited = array_values(array_diff($currentMatched, $nextMatched));
        $matchedEntered = array_values(array_diff($nextMatched, $currentMatched));
        sort($matchedExited);
        sort($matchedEntered);

        $currentLike = $current['likePlan'];
        $nextLike = $next['likePlan'];
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'pattern';
        }
        if ($currentEscape !== $nextEscape) {
            $reasons[] = 'escape-rebound';
        }
        if (($currentLike['prefix'] ?? null) !== ($nextLike['prefix'] ?? null)) {
            $reasons[] = 'like-prefix';
        }
        if (($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null)) {
            $reasons[] = 'like-range';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($escapeResidualFlip !== [] || $currentEscapeResidualFlip !== []) {
            $reasons[] = 'escape-residual-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next200',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escape rebind */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPrefix' => $currentLike['prefix'],
            'nextPrefix' => $nextLike['prefix'],
            'currentRangeLowerInclusive' => $currentLike['range']['lowerInclusive'] ?? null,
            'currentRangeUpperBound' => $currentLike['range']['upperBound'] ?? null,
            'nextRangeLowerInclusive' => $nextLike['range']['lowerInclusive'] ?? null,
            'nextRangeUpperBound' => $nextLike['range']['upperBound'] ?? null,
            'currentIndexUsable' => $currentLike['indexUsable'],
            'nextIndexUsable' => $nextLike['indexUsable'],
            'currentCandidateRowids' => self::rowids($current['candidates']),
            'nextCandidateRowids' => self::rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'nextMatchedWithCurrentEscapeRowids' => $nextMatchedWithCurrentEscape,
            'currentMatchedWithNextEscapeRowids' => $currentMatchedWithNextEscape,
            'escapeResidualFlipRowids' => $escapeResidualFlip,
            'currentEscapeResidualFlipRowids' => $currentEscapeResidualFlip,
            'matchedExitedRowids' => $matchedExited,
            'matchedEnteredRowids' => $matchedEntered,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentExcludedDecodedRowids' => array_values(array_diff(self::rowids($current['decoded']), self::rowids($current['candidates']))),
            'nextExcludedDecodedRowids' => array_values(array_diff(self::rowids($next['decoded']), self::rowids($next['candidates']))),
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
            'escapeChanged' => $currentEscape !== $nextEscape,
            'prefixChangedByEscape' => ($currentLike['prefix'] ?? null) !== ($nextLike['prefix'] ?? null),
            'rangeChangedByEscape' => ($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null),
            'residualChangedByEscape' => $escapeResidualFlip !== [] || $currentEscapeResidualFlip !== [],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustReprepareForEscapeRebind' => $currentEscape !== $nextEscape,
            'staleRangeCursorRisk' => $currentEscape !== $nextEscape && (($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null) || $escapeResidualFlip !== []),
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'escapeRebindCheckedBeforeRangeReuse' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next200',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE ESCAPE prefix planning, RTRIM keys, NOCASE residual matching, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'next200 adds ESCAPE rebind fencing for UTF-16 RTRIM/NOCASE LIKE current-source cursors; avoids accepted escaped literal wildcard next194, deleted-token/rowid replay, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape): array
    {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
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
            if (!self::inRange($entry['nocaseKey'], $like['range'])) {
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
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next200 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next200 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next200 rows require integer text_encoding');
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

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function symmetricDifference(array $left, array $right): array
    {
        $diff = array_values(array_unique(array_merge(array_diff($left, $right), array_diff($right, $left))));
        sort($diff);

        return $diff;
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
