<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext180Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameNonAsciiPrefixPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@179',
        string $nextSource = 'main.wp_options@180',
        int $currentSchemaCookie = 179,
        int $nextSchemaCookie = 180,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $usesFullScan = !$like['indexUsable'] && $like['rejectedReason'] === 'nocase_like_prefix_must_be_ascii_for_range';
        $current = self::scan($currentRows, $pattern, $escape, $like['range'], $usesFullScan);
        $next = self::scan($nextRows, $pattern, $escape, $like['range'], $usesFullScan);

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
        if ($usesFullScan) {
            $reasons[] = 'non-ascii-nocase-prefix-full-scan';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next180',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ?',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'likePlan' => $like,
            'prefix' => $like['prefix'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'rejectedReason' => $like['rejectedReason'],
            'indexUsable' => $like['indexUsable'],
            'usesFullScanFallback' => $usesFullScan,
            'range' => $like['range'],
            'currentDecodedRowids' => self::rowids($current['decoded']),
            'nextDecodedRowids' => self::rowids($next['decoded']),
            'currentCandidateRowids' => self::rowids($current['candidates']),
            'nextCandidateRowids' => self::rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentNonAsciiPrefixRowids' => $current['nonAsciiPrefixRowids'],
            'nextNonAsciiPrefixRowids' => $next['nonAsciiPrefixRowids'],
            'currentAsciiFoldedRowids' => $current['asciiFoldedRowids'],
            'nextAsciiFoldedRowids' => $next['asciiFoldedRowids'],
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
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-full-scan',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next180',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE matching, RTRIM expression keys, and current-source invalidation diagnostics',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,nonAsciiPrefixRowids:list<int>,asciiFoldedRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range, bool $usesFullScan): array
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
        $nonAsciiPrefix = [];
        $asciiFolded = [];
        foreach ($decoded as $entry) {
            if (!$usesFullScan && !self::inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $candidates[] = $entry;
            if (self::hasNonAscii($entry['rtrimText'])) {
                $nonAsciiPrefix[] = $entry['rowid'];
            }
            if ($entry['rtrimText'] !== $entry['nocaseKey']) {
                $asciiFolded[] = $entry['rowid'];
            }
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }
        sort($nonAsciiPrefix);
        sort($asciiFolded);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'nonAsciiPrefixRowids' => $nonAsciiPrefix,
            'asciiFoldedRowids' => $asciiFolded,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next180 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next180 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next180 rows require integer text_encoding');
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

    private static function hasNonAscii(string $value): bool
    {
        $length = strlen($value);
        for ($offset = 0; $offset < $length; $offset++) {
            if (ord($value[$offset]) > 0x7f) {
                return true;
            }
        }

        return false;
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
