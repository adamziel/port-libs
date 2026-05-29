<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NoCaseLikeRtrimCurrentSourceNextBasicImpl
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
        ?string $escape = '\\',
        string $currentSource = 'main.wp_options@155',
        string $nextSource = 'main.wp_options@156',
        int $currentSchemaCookie = 155,
        int $nextSchemaCookie = 156,
    ): array {
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $patternPlan['prefix'] === '' ? null : $patternPlan['binaryRange'];
        $danglingEscape = self::hasDanglingEscape($pattern, $escape);

        $current = self::scan($currentRows, $pattern, $escape, $range, $danglingEscape);
        $next = self::scan($nextRows, $pattern, $escape, $range, $danglingEscape);
        $currentCandidateRowids = self::rowids($current['candidates']);
        $nextCandidateRowids = self::rowids($next['candidates']);
        $currentMatchedRowids = self::rowids($current['matched']);
        $nextMatchedRowids = self::rowids($next['matched']);
        $changes = self::changes($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($range === null) {
            $reasons[] = 'no-rtrim-prefix-range';
        }
        if ($danglingEscape) {
            $reasons[] = 'dangling-escape';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        foreach ([
            'text-value' => $changes['changedTextRowids'],
            'rtrim-key' => $changes['changedRtrimKeyRowids'],
            'nocase-key' => $changes['changedNoCaseKeyRowids'],
            'text-encoding' => $changes['changedEncodingRowids'],
            'encoded-bytes' => $changes['changedBytesRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next156',
            'operator' => 'LIKE',
            'indexCollation' => 'RTRIM',
            'residualCollation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'pattern' => $pattern,
            'escape' => $escape,
            'prefix' => $patternPlan['prefix'],
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'hasDanglingEscape' => $danglingEscape,
            'rtrimRange' => $range,
            'indexUsable' => $range !== null && !$danglingEscape,
            'residualUsesUntrimmedText' => true,
            'residualUsesAsciiNoCase' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentOrderRowids' => self::rowids($current['decoded']),
            'nextOrderRowids' => self::rowids($next['decoded']),
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatchedRowids, $nextMatchedRowids)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatchedRowids, $currentMatchedRowids)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatchedRowids, $nextMatchedRowids)),
            'currentTexts' => self::map($current['decoded'], 'text'),
            'nextTexts' => self::map($next['decoded'], 'text'),
            'currentRtrimKeys' => self::map($current['decoded'], 'rtrimKey'),
            'nextRtrimKeys' => self::map($next['decoded'], 'rtrimKey'),
            'currentNoCaseKeys' => self::map($current['decoded'], 'noCaseKey'),
            'nextNoCaseKeys' => self::map($next['decoded'], 'noCaseKey'),
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
            'changedTextRowids' => $changes['changedTextRowids'],
            'changedRtrimKeyRowids' => $changes['changedRtrimKeyRowids'],
            'changedNoCaseKeyRowids' => $changes['changedNoCaseKeyRowids'],
            'changedEncodingRowids' => $changes['changedEncodingRowids'],
            'changedBytesRowids' => $changes['changedBytesRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $range !== null && !$danglingEscape,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-text-decode',
                'sqlite-rtrim-collation-range',
                'sqlite-like-nocase-residual',
                'sqlite-current-source-next156',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM range keys, and ASCII NOCASE LIKE residual matching',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range, bool $danglingEscape): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimKey' => rtrim($text, ' '),
                    'noCaseKey' => self::asciiLower($text),
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, static fn (array $left, array $right): int => strcmp($left['rtrimKey'], $right['rtrimKey']) ?: $left['rowid'] <=> $right['rowid']);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::inRtrimRange(self::asciiLower($entry['rtrimKey']), $range)) {
                continue;
            }
            $entry['residualMatch'] = !$danglingEscape && SQLiteDatabase::likeMatches($entry['text'], $pattern, $escape, false);
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
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next156 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next156 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next156 rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRtrimRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, rtrim($range['lowerInclusive'], ' ')) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, rtrim($range['upperBound'], ' ')) < 0;
    }

    private static function hasDanglingEscape(string $pattern, ?string $escape): bool
    {
        if ($escape === null) {
            return false;
        }
        $characters = self::characters($pattern);
        $escapeCharacters = self::characters($escape);
        if (count($escapeCharacters) !== 1 || $characters === []) {
            return false;
        }
        $escaped = false;
        foreach ($characters as $character) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($character === $escapeCharacters[0]) {
                $escaped = true;
            }
        }

        return $escaped;
    }

    /** @return list<string> */
    private static function characters(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source next156 pattern text must be well-formed UTF-8');
        }

        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
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
     * @param list<array{rowid:int,text:string,rtrimKey:string,noCaseKey:string,encoding:string,bytesHex:string}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimKey:string,noCaseKey:string,encoding:string,bytesHex:string}> $nextRows
     * @return array{changedTextRowids:list<int>,changedRtrimKeyRowids:list<int>,changedNoCaseKeyRowids:list<int>,changedEncodingRowids:list<int>,changedBytesRowids:list<int>}
     */
    private static function changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changed = [
            'changedTextRowids' => [],
            'changedRtrimKeyRowids' => [],
            'changedNoCaseKeyRowids' => [],
            'changedEncodingRowids' => [],
            'changedBytesRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $changed['changedTextRowids'][] = $rowid;
            }
            if ($current[$rowid]['rtrimKey'] !== $row['rtrimKey']) {
                $changed['changedRtrimKeyRowids'][] = $rowid;
            }
            if ($current[$rowid]['noCaseKey'] !== $row['noCaseKey']) {
                $changed['changedNoCaseKeyRowids'][] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $changed['changedEncodingRowids'][] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $changed['changedBytesRowids'][] = $rowid;
            }
        }
        foreach ($changed as $key => $rowids) {
            sort($rowids);
            $changed[$key] = $rowids;
        }

        return $changed;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => 'unknown-' . $encoding,
        };
    }
}

final class SQLiteUtf16NoCaseLikeRtrimCurrentSourceNextNormalizedPatternImpl
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameNormalizedPatternPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        ?string $currentEscapeBytes = null,
        int $currentEscapeEncoding = 1,
        ?string $nextEscapeBytes = null,
        int $nextEscapeEncoding = 1,
        string $currentSource = 'main.wp_options@161',
        string $nextSource = 'main.wp_options@162',
        int $currentSchemaCookie = 161,
        int $nextSchemaCookie = 162,
    ): array {
        $base = SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNext160Plan::wordpressOptionNamePatternPlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $byteReasons = [];
        $semanticReasons = [];
        foreach ($base['invalidationReasons'] as $reason) {
            if (in_array($reason, ['pattern-encoding', 'pattern-bytes', 'escape-bytes'], true)) {
                $byteReasons[] = $reason;
                continue;
            }
            $semanticReasons[] = $reason;
        }

        $sameDecodedPattern = $base['currentPattern'] === $base['nextPattern'];
        $sameDecodedEscape = $base['currentEscape'] === $base['nextEscape'];
        $byteOnlyReprepare = $byteReasons !== [] && $semanticReasons === [] && $sameDecodedPattern && $sameDecodedEscape;

        if ($byteOnlyReprepare) {
            $semanticReasons = [];
        } else {
            if (!$sameDecodedPattern && !in_array('pattern-text', $semanticReasons, true)) {
                $semanticReasons[] = 'pattern-text';
            }
            if (!$sameDecodedEscape && !in_array('escape-text', $semanticReasons, true)) {
                $semanticReasons[] = 'escape-text';
            }
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next162',
            'operator' => 'LIKE',
            'indexCollation' => 'RTRIM',
            'residualCollation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'normalizesPreparedPatternBytes' => true,
            'rawPatternByteChangeIsSemantic' => false,
            'rawEscapeByteChangeIsSemantic' => false,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentPattern' => $base['currentPattern'],
            'nextPattern' => $base['nextPattern'],
            'sameDecodedPattern' => $sameDecodedPattern,
            'currentPatternEncoding' => $base['currentPatternEncoding'],
            'nextPatternEncoding' => $base['nextPatternEncoding'],
            'currentPatternBytesHex' => $base['currentPatternBytesHex'],
            'nextPatternBytesHex' => $base['nextPatternBytesHex'],
            'currentEscape' => $base['currentEscape'],
            'nextEscape' => $base['nextEscape'],
            'sameDecodedEscape' => $sameDecodedEscape,
            'currentEscapeBytesHex' => $base['currentEscapeBytesHex'],
            'nextEscapeBytesHex' => $base['nextEscapeBytesHex'],
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRtrimRange' => $base['currentRtrimRange'],
            'nextRtrimRange' => $base['nextRtrimRange'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'retainedMatchedRowids' => $base['retainedMatchedRowids'],
            'enteredMatchedRowids' => $base['enteredMatchedRowids'],
            'exitedMatchedRowids' => $base['exitedMatchedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'byteReprepareReasons' => $byteReasons,
            'semanticInvalidationReasons' => $semanticReasons,
            'byteOnlyReprepare' => $byteOnlyReprepare,
            'cursorInvalidated' => $semanticReasons !== [],
            'cursorReusable' => $semanticReasons === [] && $base['currentIndexUsable'] && $base['nextIndexUsable'],
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'dependencies' => [
                'sqlite-utf16-pattern-normalization',
                'sqlite-rtrim-collation-range',
                'sqlite-like-nocase-residual',
                'sqlite-current-source-next162',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 pattern decode, RTRIM range keys, ASCII NOCASE LIKE residual matching, and current-source diagnostics',
        ];
    }
}
