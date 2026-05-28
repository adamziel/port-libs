<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext233Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameCanonicalUnicodePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_caf_',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@232',
        string $nextSource = 'main.wp_options@233',
        int $currentSchemaCookie = 232,
        int $nextSchemaCookie = 233,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::changes($current['decoded'], $next['decoded']);
        $changes['residualChangedRowids'] = self::residualChanges($current['candidates'], $next['candidates']);

        $currentCanonicalPeers = self::canonicalPeerRowsets($current['decoded']);
        $nextCanonicalPeers = self::canonicalPeerRowsets($next['decoded']);
        $currentCombiningMatched = self::intersectSorted(self::rowids($current['matched']), $current['combiningMarkRowids']);
        $nextCombiningMatched = self::intersectSorted(self::rowids($next['matched']), $next['combiningMarkRowids']);

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
            'unicode-codepoint-count' => $changes['codepointChangedRowids'],
            'utf16-code-units' => $changes['utf16CodeUnitChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['canonicalEquivalentRowids'] !== $next['canonicalEquivalentRowids']) {
            $reasons[] = 'canonical-equivalent-rowset';
        }
        if ($current['combiningMarkRowids'] !== $next['combiningMarkRowids']) {
            $reasons[] = 'combining-mark-rowset';
        }
        if ($current['singleWildcardFalsePositiveRowids'] !== [] || $next['singleWildcardFalsePositiveRowids'] !== []) {
            $reasons[] = 'single-wildcard-codepoint-boundary';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if (self::rowids($current['candidates']) !== self::rowids($next['candidates'])) {
            $reasons[] = 'candidate-rowset';
        }
        if (self::rowids($current['matched']) !== self::rowids($next['matched'])) {
            $reasons[] = 'matched-rowset';
        }
        if (self::rowsetMap($currentCanonicalPeers) !== self::rowsetMap($nextCanonicalPeers)) {
            $reasons[] = 'canonical-peer-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next233',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* no Unicode normalization */',
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
            'currentMatchedRowids' => self::rowids($current['matched']),
            'nextMatchedRowids' => self::rowids($next['matched']),
            'matchedRetainedRowids' => self::intersectSorted(self::rowids($current['matched']), self::rowids($next['matched'])),
            'matchedExitedRowids' => self::diffSorted(self::rowids($current['matched']), self::rowids($next['matched'])),
            'matchedEnteredRowids' => self::diffSorted(self::rowids($next['matched']), self::rowids($current['matched'])),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentCombiningMarkRowids' => $current['combiningMarkRowids'],
            'nextCombiningMarkRowids' => $next['combiningMarkRowids'],
            'currentPrecomposedAccentRowids' => $current['precomposedAccentRowids'],
            'nextPrecomposedAccentRowids' => $next['precomposedAccentRowids'],
            'currentCanonicalEquivalentRowids' => $current['canonicalEquivalentRowids'],
            'nextCanonicalEquivalentRowids' => $next['canonicalEquivalentRowids'],
            'currentCombiningMatchedRowids' => $currentCombiningMatched,
            'nextCombiningMatchedRowids' => $nextCombiningMatched,
            'currentSingleWildcardFalsePositiveRowids' => $current['singleWildcardFalsePositiveRowids'],
            'nextSingleWildcardFalsePositiveRowids' => $next['singleWildcardFalsePositiveRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'currentCodepointCounts' => self::map($current['decoded'], 'codepointCount'),
            'nextCodepointCounts' => self::map($next['decoded'], 'codepointCount'),
            'currentUtf16CodeUnitCounts' => self::map($current['decoded'], 'utf16CodeUnits'),
            'nextUtf16CodeUnitCounts' => self::map($next['decoded'], 'utf16CodeUnits'),
            'currentCanonicalKeys' => self::map($current['decoded'], 'canonicalKey'),
            'nextCanonicalKeys' => self::map($next['decoded'], 'canonicalKey'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'currentCanonicalPeerRowids' => $currentCanonicalPeers,
            'nextCanonicalPeerRowids' => $nextCanonicalPeers,
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedCodepointRowids' => $changes['codepointChangedRowids'],
            'changedUtf16CodeUnitRowids' => $changes['utf16CodeUnitChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'likeUnderscoreConsumesOneUnicodeCodepoint' => true,
            'combiningMarkIsSeparateLikeCharacter' => true,
            'unicodeNormalizationApplied' => false,
            'canonicalEquivalentTextMayCompareDistinct' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-unicode-codepoint-like',
                'sqlite-current-source-next233',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and Unicode codepoint splitting',
            'non_overlap' => 'next233 covers canonical-equivalent precomposed/decomposed Unicode text under UTF-16 NOCASE/RTRIM LIKE without normalization; avoids accepted Unicode GLOB ranges, UTF-16 malformed insert guards, non-ASCII prefix fallback, non-ASCII whitespace RTRIM, supplementary-plane wildcard, and storage/JSON/SQL planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,combiningMarkRowids:list<int>,precomposedAccentRowids:list<int>,canonicalEquivalentRowids:list<int>,singleWildcardFalsePositiveRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $combining = [];
        $precomposed = [];
        $canonicalEquivalent = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $canonicalKey = self::canonicalAccentKey($rtrim);
                $hasCombining = self::hasCombiningMark($rtrim);
                $hasPrecomposed = str_contains($rtrim, "\u{00e9}") || str_contains($rtrim, "\u{00c9}");
                if ($hasCombining) {
                    $combining[] = $row['option_id'];
                }
                if ($hasPrecomposed) {
                    $precomposed[] = $row['option_id'];
                }
                if ($hasCombining || $hasPrecomposed) {
                    $canonicalEquivalent[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                    'codepointCount' => self::codepointCount($rtrim),
                    'utf16CodeUnits' => self::utf16CodeUnits($rtrim),
                    'canonicalKey' => $canonicalKey,
                    'hasCombiningMark' => $hasCombining,
                    'hasPrecomposedAccent' => $hasPrecomposed,
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($combining);
        sort($precomposed);
        sort($canonicalEquivalent);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $singleWildcardFalsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $entry['singleWildcardFalsePositive'] = !$entry['residualMatch'] && $entry['hasCombiningMark'];
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
                if ($entry['singleWildcardFalsePositive']) {
                    $singleWildcardFalsePositive[] = $entry['rowid'];
                }
            }
        }
        sort($singleWildcardFalsePositive);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'combiningMarkRowids' => $combining,
            'precomposedAccentRowids' => $precomposed,
            'canonicalEquivalentRowids' => $canonicalEquivalent,
            'singleWildcardFalsePositiveRowids' => $singleWildcardFalsePositive,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next233 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next233 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next233 rows require integer text_encoding');
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

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return array<string,list<int>> */
    private static function changes(array $left, array $right): array
    {
        $leftById = self::byRowid($left);
        $rightById = self::byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'codepointChangedRowids' => [],
            'utf16CodeUnitChangedRowids' => [],
        ];
        foreach ($rowids as $rowid) {
            $leftRow = $leftById[$rowid] ?? null;
            $rightRow = $rightById[$rowid] ?? null;
            foreach ([
                'textChangedRowids' => 'text',
                'rtrimChangedRowids' => 'rtrimText',
                'nocaseKeyChangedRowids' => 'nocaseKey',
                'codepointChangedRowids' => 'codepointCount',
                'utf16CodeUnitChangedRowids' => 'utf16CodeUnits',
            ] as $bucket => $key) {
                if (($leftRow[$key] ?? null) !== ($rightRow[$key] ?? null)) {
                    $changes[$bucket][] = (int) $rowid;
                }
            }
        }

        return $changes;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function byRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        ksort($indexed);

        return $indexed;
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return list<int> */
    private static function residualChanges(array $left, array $right): array
    {
        $leftById = self::byRowid($left);
        $rightById = self::byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($leftById[$rowid]['residualMatch'] ?? null) !== ($rightById[$rowid]['residualMatch'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    private static function asciiLower(string $text): string
    {
        return strtolower($text);
    }

    private static function canonicalAccentKey(string $text): string
    {
        return str_replace(["\u{00c9}", "\u{00e9}", "E\u{0301}", "e\u{0301}"], ['e', 'e', 'e', 'e'], self::asciiLower($text));
    }

    private static function hasCombiningMark(string $text): bool
    {
        return preg_match('/\p{M}/u', $text) === 1;
    }

    private static function codepointCount(string $text): int
    {
        return count(self::characters($text));
    }

    private static function utf16CodeUnits(string $text): int
    {
        return intdiv(strlen(SQLiteEncodingCollationSourceCursor::encodeText($text, 'UTF-16LE')), 2);
    }

    /** @return list<string> */
    private static function characters(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @param list<array<string,mixed>> $rows @return array<string,list<int>> */
    private static function canonicalPeerRowsets(array $rows): array
    {
        $peers = [];
        foreach ($rows as $row) {
            $peers[$row['canonicalKey']] ??= [];
            $peers[$row['canonicalKey']][] = (int) $row['rowid'];
        }
        foreach ($peers as &$rowids) {
            sort($rowids);
        }
        unset($rowids);
        ksort($peers);

        return array_filter($peers, static fn (array $rowids): bool => count($rowids) > 1);
    }

    /** @param array<string,list<int>> $rowsets @return array<string,list<int>> */
    private static function rowsetMap(array $rowsets): array
    {
        foreach ($rowsets as &$rowids) {
            sort($rowids);
        }
        unset($rowids);
        ksort($rowsets);

        return $rowsets;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function intersectSorted(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function diffSorted(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }
}
