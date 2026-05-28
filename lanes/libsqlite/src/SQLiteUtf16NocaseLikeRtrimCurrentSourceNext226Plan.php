<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext226Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameCombiningMarkPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin_caf_',
        ?string $escape = null,
        string $currentSource = 'main.wp_options@225',
        string $nextSource = 'main.wp_options@226',
        int $currentSchemaCookie = 225,
        int $nextSchemaCookie = 226,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);

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
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'unicode-normalization-form' => $changes['normalizationChangedRowids'],
            'combining-mark-count' => $changes['combiningMarkChangedRowids'],
            'like-character-count' => $changes['characterCountChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if (self::rowids($current['candidates']) !== self::rowids($next['candidates'])) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($current['normalizationTrapRowids'] !== [] || $next['normalizationTrapRowids'] !== []) {
            $reasons[] = 'unicode-normalization-not-applied';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next226',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* combining mark normalization boundary */',
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
            'currentCandidateRowids' => self::rowids($current['candidates']),
            'nextCandidateRowids' => self::rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::sortedDiff($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::sortedDiff($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentCombiningMarkRowids' => $current['combiningMarkRowids'],
            'nextCombiningMarkRowids' => $next['combiningMarkRowids'],
            'currentNormalizationTrapRowids' => $current['normalizationTrapRowids'],
            'nextNormalizationTrapRowids' => $next['normalizationTrapRowids'],
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
            'currentCombiningMarkCounts' => self::map($current['decoded'], 'combiningMarkCount'),
            'nextCombiningMarkCounts' => self::map($next['decoded'], 'combiningMarkCount'),
            'currentNormalizationForms' => self::map($current['decoded'], 'normalizationForm'),
            'nextNormalizationForms' => self::map($next['decoded'], 'normalizationForm'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedNormalizationRowids' => $changes['normalizationChangedRowids'],
            'changedCombiningMarkRowids' => $changes['combiningMarkChangedRowids'],
            'changedCharacterCountRowids' => $changes['characterCountChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'likeUnderscoreConsumesUnicodeCodepoint' => true,
            'combiningMarkRemainsSeparateLikeCharacter' => true,
            'unicodeNormalizationIsNotApplied' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-combining-mark-like-character',
                'sqlite-current-source-next226',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and binary-safe Unicode code point splitting',
            'non_overlap' => 'next226 covers composed versus decomposed Unicode combining-mark LIKE residual behavior without normalization; avoids accepted next219 supplementary-plane wildcard, next209 ASCII-space RTRIM, Unicode GLOB ranges, escape rebind, and malformed UTF-16 insert guard clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,combiningMarkRowids:list<int>,normalizationTrapRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $combining = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $combiningCount = self::combiningMarkCount($rtrim);
                if ($combiningCount > 0) {
                    $combining[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                    'characterCount' => count(self::characters($rtrim)),
                    'combiningMarkCount' => $combiningCount,
                    'normalizationForm' => self::normalizationForm($rtrim),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($combining);
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
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
            if (!$entry['residualMatch'] && $entry['combiningMarkCount'] > 0) {
                $traps[] = $entry['rowid'];
            }
        }
        sort($traps);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'combiningMarkRowids' => $combining,
            'normalizationTrapRowids' => $traps,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next226 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next226 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next226 rows require integer text_encoding');
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
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array<string,list<int>>
     */
    private static function changes(array $current, array $next): array
    {
        $currentByRowid = self::byRowid($current);
        $nextByRowid = self::byRowid($next);

        return [
            'textChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'text'),
            'rtrimChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'rtrimText'),
            'nocaseKeyChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'nocaseKey'),
            'normalizationChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'normalizationForm'),
            'combiningMarkChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'combiningMarkCount'),
            'characterCountChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'characterCount'),
            'residualChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'residualMatch'),
        ];
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
     * @param array<int,array<string,mixed>> $current
     * @param array<int,array<string,mixed>> $next
     * @return list<int>
     */
    private static function changed(array $current, array $next, string $field): array
    {
        $rowids = array_values(array_unique(array_merge(array_keys($current), array_keys($next))));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid][$field] ?? null) !== ($next[$rowid][$field] ?? null)) {
                $changed[] = $rowid;
            }
        }

        return $changed;
    }

    /** @return list<int> */
    private static function sortedIntersect(array $left, array $right): array
    {
        $result = array_values(array_intersect($left, $right));
        sort($result);

        return $result;
    }

    /** @return list<int> */
    private static function sortedDiff(array $left, array $right): array
    {
        $result = array_values(array_diff($left, $right));
        sort($result);

        return $result;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @return list<string> */
    private static function characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next226 text is not valid UTF-8 after decode');
        }

        return $chars;
    }

    private static function combiningMarkCount(string $value): int
    {
        $count = 0;
        foreach (self::characters($value) as $char) {
            if (preg_match('/^\p{M}$/u', $char) === 1) {
                $count++;
            }
        }

        return $count;
    }

    private static function normalizationForm(string $value): string
    {
        if (str_contains($value, "e\xcc\x81")) {
            return 'decomposed-combining-acute';
        }
        if (str_contains($value, "\xc3\xa9")) {
            return 'composed-latin-small-e-acute';
        }
        if (self::combiningMarkCount($value) > 0) {
            return 'decomposed-combining-mark';
        }

        return 'plain';
    }
}
