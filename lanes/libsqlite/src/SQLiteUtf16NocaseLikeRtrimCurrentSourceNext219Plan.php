<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext219Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameSupplementaryWildcardPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache_',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@218',
        string $nextSource = 'main.wp_options@219',
        int $currentSchemaCookie = 218,
        int $nextSchemaCookie = 219,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);

        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $changes = self::changes($current['decoded'], $next['decoded']);
        $changes['residualChangedRowids'] = self::residualChanges($current['candidates'], $next['candidates']);

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
            'supplementary-character' => $changes['supplementaryChangedRowids'],
            'utf16-code-units' => $changes['utf16CodeUnitChangedRowids'],
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
        if ($current['codeUnitWildcardTrapRowids'] !== [] || $next['codeUnitWildcardTrapRowids'] !== []) {
            $reasons[] = 'utf16-code-unit-wildcard-trap';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next219',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* supplementary wildcard */',
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
            'currentCodeUnitWildcardTrapRowids' => $current['codeUnitWildcardTrapRowids'],
            'nextCodeUnitWildcardTrapRowids' => $next['codeUnitWildcardTrapRowids'],
            'currentSupplementaryRowids' => $current['supplementaryRowids'],
            'nextSupplementaryRowids' => $next['supplementaryRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'currentCharacterCounts' => self::map($current['decoded'], 'characterCount'),
            'nextCharacterCounts' => self::map($next['decoded'], 'characterCount'),
            'currentUtf16CodeUnitCounts' => self::map($current['decoded'], 'utf16CodeUnits'),
            'nextUtf16CodeUnitCounts' => self::map($next['decoded'], 'utf16CodeUnits'),
            'currentSupplementaryCounts' => self::map($current['decoded'], 'supplementaryCount'),
            'nextSupplementaryCounts' => self::map($next['decoded'], 'supplementaryCount'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'currentCodeUnitTrapMatches' => self::map($current['candidates'], 'codeUnitTrapMatch'),
            'nextCodeUnitTrapMatches' => self::map($next['candidates'], 'codeUnitTrapMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedSupplementaryRowids' => $changes['supplementaryChangedRowids'],
            'changedUtf16CodeUnitRowids' => $changes['utf16CodeUnitChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'likeUnderscoreConsumesUnicodeCharacter' => true,
            'utf16SurrogatePairIsOneLikeCharacter' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-supplementary-plane-like-character',
                'sqlite-current-source-next219',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and binary-safe Unicode character splitting',
            'non_overlap' => 'next219 covers supplementary-plane UTF-16 decoded characters consumed by one LIKE underscore wildcard; avoids accepted embedded-NUL next210, Unicode ESCAPE next212/213, source refresh next211, pattern-space next217, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,supplementaryRowids:list<int>,codeUnitWildcardTrapRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $supplementary = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $supplementaryCount = self::supplementaryCount($rtrim);
                if ($supplementaryCount > 0) {
                    $supplementary[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                    'characterCount' => self::characterCount($rtrim),
                    'utf16CodeUnits' => self::utf16CodeUnits($rtrim),
                    'supplementaryCount' => $supplementaryCount,
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($supplementary);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $traps = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $entry['codeUnitTrapMatch'] = $entry['residualMatch'] && $entry['supplementaryCount'] > 0;
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
            if ($entry['residualMatch'] && $entry['codeUnitTrapMatch']) {
                $traps[] = $entry['rowid'];
            }
        }
        sort($traps);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'supplementaryRowids' => $supplementary,
            'codeUnitWildcardTrapRowids' => $traps,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next219 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next219 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next219 rows require integer text_encoding');
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

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function sortedDiff(array $left, array $right): array
    {
        $diff = array_values(array_diff($left, $right));
        sort($diff);

        return $diff;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function sortedIntersect(array $left, array $right): array
    {
        $intersect = array_values(array_intersect($left, $right));
        sort($intersect);

        return $intersect;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,supplementaryChangedRowids:list<int>,utf16CodeUnitChangedRowids:list<int>,residualChangedRowids:list<int>}
     */
    private static function changes(array $currentRows, array $nextRows): array
    {
        $current = self::byRowid($currentRows);
        $result = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'supplementaryChangedRowids' => [],
            'utf16CodeUnitChangedRowids' => [],
            'residualChangedRowids' => [],
        ];
        foreach (self::byRowid($nextRows) as $rowid => $entry) {
            if (!isset($current[$rowid])) {
                continue;
            }
            foreach ([
                'textChangedRowids' => 'text',
                'rtrimChangedRowids' => 'rtrimText',
                'nocaseKeyChangedRowids' => 'nocaseKey',
                'supplementaryChangedRowids' => 'supplementaryCount',
                'utf16CodeUnitChangedRowids' => 'utf16CodeUnits',
            ] as $target => $key) {
                if ($current[$rowid][$key] !== $entry[$key]) {
                    $result[$target][] = $rowid;
                }
            }
        }
        foreach ($result as $key => $rowids) {
            sort($rowids);
            $result[$key] = $rowids;
        }

        return $result;
    }

    /** @param list<array<string,mixed>> $currentRows @param list<array<string,mixed>> $nextRows @return list<int> */
    private static function residualChanges(array $currentRows, array $nextRows): array
    {
        $current = self::byRowid($currentRows);
        $rowids = [];
        foreach (self::byRowid($nextRows) as $rowid => $entry) {
            if (isset($current[$rowid]) && ($current[$rowid]['residualMatch'] ?? null) !== ($entry['residualMatch'] ?? null)) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function byRowid(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row;
        }

        return $mapped;
    }

    private static function characterCount(string $value): int
    {
        return count(self::characters($value));
    }

    private static function utf16CodeUnits(string $value): int
    {
        $bytes = SQLiteEncodingCollationSourceCursor::encodeText($value, 2);

        return intdiv(strlen($bytes), 2);
    }

    private static function supplementaryCount(string $value): int
    {
        $count = 0;
        foreach (self::characters($value) as $character) {
            if (self::utf16CodeUnits($character) === 2) {
                $count++;
            }
        }

        return $count;
    }

    /** @return list<string> */
    private static function characters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
