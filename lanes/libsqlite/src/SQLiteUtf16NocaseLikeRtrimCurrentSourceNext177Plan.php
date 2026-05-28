<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext177Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameUnicodeWildcardPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@176',
        string $nextSource = 'main.wp_options@177',
        int $currentSchemaCookie = 176,
        int $nextSchemaCookie = 177,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);

        $currentCandidateRowids = self::rowids($current['candidates']);
        $nextCandidateRowids = self::rowids($next['candidates']);
        $currentMatchedRowids = self::rowids($current['matched']);
        $nextMatchedRowids = self::rowids($next['matched']);
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
            'character-count' => $changes['characterCountChangedRowids'],
            'utf16-code-unit-count' => $changes['utf16CodeUnitCountChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
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
        if ($current['byteWildcardMismatchRowids'] !== [] || $next['byteWildcardMismatchRowids'] !== []) {
            $reasons[] = 'unicode-wildcard-recheck';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next177',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ?',
            'pattern' => $pattern,
            'escape' => $escape,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'likePlan' => $like,
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentUnicodeWildcardRowids' => $current['unicodeWildcardRowids'],
            'nextUnicodeWildcardRowids' => $next['unicodeWildcardRowids'],
            'currentByteWildcardMismatchRowids' => $current['byteWildcardMismatchRowids'],
            'nextByteWildcardMismatchRowids' => $next['byteWildcardMismatchRowids'],
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
            'currentCharacterCounts' => self::map($current['decoded'], 'characterCount'),
            'nextCharacterCounts' => self::map($next['decoded'], 'characterCount'),
            'currentUtf16CodeUnitCounts' => self::map($current['decoded'], 'utf16CodeUnitCount'),
            'nextUtf16CodeUnitCounts' => self::map($next['decoded'], 'utf16CodeUnitCount'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'currentByteWildcardMatches' => self::map($current['candidates'], 'byteWildcardMatch'),
            'nextByteWildcardMatches' => self::map($next['candidates'], 'byteWildcardMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedCharacterCountRowids' => $changes['characterCountChangedRowids'],
            'changedUtf16CodeUnitCountRowids' => $changes['utf16CodeUnitCountChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'likeUnderscoreConsumesOneDecodedCharacter' => true,
            'utf16SurrogatePairIsOneLikeCharacter' => true,
            'byteLengthCannotDriveLikeUnderscore' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-unicode-character-wildcard',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-next177',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE character matching, NOCASE/RTRIM prefix planning, and adds Unicode wildcard recheck diagnostics for current-source cursor transitions',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,unicodeWildcardRowids:list<int>,byteWildcardMismatchRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $unicodeWildcard = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $characterCount = self::characterCount($rtrim);
                $utf16CodeUnitCount = strlen(SQLiteEncodingCollationSourceCursor::encodeText($rtrim, $row['text_encoding'])) / 2;
                if ($characterCount !== $utf16CodeUnitCount) {
                    $unicodeWildcard[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                    'characterCount' => $characterCount,
                    'utf16CodeUnitCount' => (int) $utf16CodeUnitCount,
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($unicodeWildcard);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $byteWildcardMismatch = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $entry['byteWildcardMatch'] = self::byteWildcardLikeMatches($entry['rtrimText'], $pattern, $escape);
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
            if ($entry['residualMatch'] !== $entry['byteWildcardMatch']) {
                $byteWildcardMismatch[] = $entry['rowid'];
            }
        }
        sort($byteWildcardMismatch);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'unicodeWildcardRowids' => $unicodeWildcard,
            'byteWildcardMismatchRowids' => $byteWildcardMismatch,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next177 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next177 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next177 rows require integer text_encoding');
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
            'characterCountChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'characterCount'),
            'utf16CodeUnitCountChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'utf16CodeUnitCount'),
            'bytesChangedRowids' => self::changed($currentByRowid, $nextByRowid, 'bytesHex'),
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

    /** @param array<int,array<string,mixed>> $current @param array<int,array<string,mixed>> $next @return list<int> */
    private static function changed(array $current, array $next, string $key): array
    {
        $rowids = array_values(array_intersect(array_keys($current), array_keys($next)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid][$key] ?? null) !== ($next[$rowid][$key] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<int> */
    private static function residualChanges(array $current, array $next): array
    {
        $currentByRowid = self::byRowid($current);
        $nextByRowid = self::byRowid($next);
        $rowids = array_values(array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($currentByRowid[$rowid]['residualMatch'] ?? null) !== ($nextByRowid[$rowid]['residualMatch'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    private static function characterCount(string $value): int
    {
        return count(self::characters($value));
    }

    /** @return list<string> */
    private static function characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next177 decoded text must be valid UTF-8');
        }

        return $characters;
    }

    private static function byteWildcardLikeMatches(string $value, string $pattern, ?string $escape): bool
    {
        if ($escape !== null && strlen($escape) !== 1) {
            return SQLiteDatabase::likeMatches($value, $pattern, $escape, false);
        }

        $regex = '';
        $length = strlen($pattern);
        for ($offset = 0; $offset < $length; $offset++) {
            $character = $pattern[$offset];
            if ($escape !== null && $character === $escape) {
                $offset++;
                if ($offset >= $length) {
                    return false;
                }
                $regex .= preg_quote($pattern[$offset], '/');
                continue;
            }
            if ($character === '%') {
                $regex .= '.*';
                continue;
            }
            if ($character === '_') {
                $regex .= '.';
                continue;
            }
            $regex .= preg_quote($character, '/');
        }

        return preg_match('/^' . $regex . '$/s', self::asciiLower($value)) === 1;
    }

    private static function asciiLower(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            if ($ord >= 0x41 && $ord <= 0x5a) {
                $bytes[$i] = chr($ord + 0x20);
            }
        }

        return $bytes;
    }
}
