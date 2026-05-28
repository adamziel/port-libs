<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext207Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameRtrimCollationRebindPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        bool $currentUsesRtrim = true,
        bool $nextUsesRtrim = false,
        string $currentSource = 'main.wp_options@206',
        string $nextSource = 'main.wp_options@207',
        int $currentSchemaCookie = 206,
        int $nextSchemaCookie = 207,
    ): array {
        $current = self::scan($currentRows, $pattern, $escape, $currentUsesRtrim);
        $next = self::scan($nextRows, $pattern, $escape, $nextUsesRtrim);
        $currentWithNextRtrim = self::scan($currentRows, $pattern, $escape, $nextUsesRtrim);
        $nextWithCurrentRtrim = self::scan($nextRows, $pattern, $escape, $currentUsesRtrim);

        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentMatchedWithNextRtrim = self::rowids($currentWithNextRtrim['matched']);
        $nextMatchedWithCurrentRtrim = self::rowids($nextWithCurrentRtrim['matched']);
        $rtrimResidualFlip = self::symmetricDifference($currentMatched, $currentMatchedWithNextRtrim);
        $nextRtrimResidualFlip = self::symmetricDifference($nextMatched, $nextMatchedWithCurrentRtrim);
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
        if ($currentUsesRtrim !== $nextUsesRtrim) {
            $reasons[] = 'rtrim-collation-rebound';
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
        if ($rtrimResidualFlip !== [] || $nextRtrimResidualFlip !== []) {
            $reasons[] = 'rtrim-residual-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next207',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* rtrim collation rebind */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentUsesRtrim' => $currentUsesRtrim,
            'nextUsesRtrim' => $nextUsesRtrim,
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
            'currentCandidateRowids' => self::rowids($current['candidates']),
            'nextCandidateRowids' => self::rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentMatchedWithNextRtrimRowids' => $currentMatchedWithNextRtrim,
            'nextMatchedWithCurrentRtrimRowids' => $nextMatchedWithCurrentRtrim,
            'rtrimResidualFlipRowids' => $rtrimResidualFlip,
            'nextRtrimResidualFlipRowids' => $nextRtrimResidualFlip,
            'matchedExitedRowids' => $matchedExited,
            'matchedEnteredRowids' => $matchedEntered,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentDecodedRowids' => self::rowids($current['decoded']),
            'nextDecodedRowids' => self::rowids($next['decoded']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTexts' => self::map($current['decoded'], 'text'),
            'nextTexts' => self::map($next['decoded'], 'text'),
            'currentProbeTexts' => self::map($current['decoded'], 'probeText'),
            'nextProbeTexts' => self::map($next['decoded'], 'probeText'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'rtrimChanged' => $currentUsesRtrim !== $nextUsesRtrim,
            'prefixChangedByRtrim' => ($currentLike['prefix'] ?? null) !== ($nextLike['prefix'] ?? null),
            'rangeChangedByRtrim' => ($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null),
            'residualChangedByRtrim' => $rtrimResidualFlip !== [] || $nextRtrimResidualFlip !== [],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustReprepareForRtrimRebind' => $currentUsesRtrim !== $nextUsesRtrim,
            'staleRangeCursorRisk' => $currentUsesRtrim !== $nextUsesRtrim && ($rtrimResidualFlip !== [] || $nextRtrimResidualFlip !== []),
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rtrimRebindCheckedBeforeRangeReuse' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next207',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE prefix planning, RTRIM expression keys, NOCASE residual matching, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'next207 adds rtrim/no-rtrim expression rebind fencing for UTF-16 NOCASE LIKE current-source cursors; avoids accepted next200 ESCAPE rebind, next206 integrated encoding batch, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, bool $usesRtrim): array
    {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $probe = $usesRtrim ? rtrim($text, ' ') : $text;
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'probeText' => $probe,
                    'nocaseKey' => self::asciiLower($probe),
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
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['probeText'], $pattern, $escape, false);
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
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next207 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next207 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next207 rows require integer text_encoding');
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

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
