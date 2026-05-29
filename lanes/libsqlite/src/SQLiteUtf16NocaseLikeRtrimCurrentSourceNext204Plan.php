<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext204Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameNonAsciiPrefixPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_é%',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@203',
        string $nextSource = 'main.wp_options@204',
        int $currentSchemaCookie = 203,
        int $nextSchemaCookie = 204,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        if ($like['rejectedReason'] !== 'nocase_like_prefix_must_be_ascii_for_range') {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next204 expects a non-ASCII NOCASE LIKE prefix');
        }

        $current = self::scan($currentRows, $pattern, $escape);
        $next = self::scan($nextRows, $pattern, $escape);
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
        $reasons[] = 'non-ascii-nocase-prefix-full-scan';
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
            'status' => 'utf16-nocase-like-rtrim-current-source-next204',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* non-ASCII prefix full scan */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'likePlan' => $like,
            'prefix' => $like['prefix'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'rangeLowerInclusive' => null,
            'rangeUpperBound' => null,
            'indexUsable' => false,
            'usesPrefixRangeCursor' => false,
            'usesFullScanFallback' => true,
            'rejectedReason' => $like['rejectedReason'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentDecodedRowids' => self::rowids($current['decoded']),
            'nextDecodedRowids' => self::rowids($next['decoded']),
            'currentCandidateRowids' => self::rowids($current['decoded']),
            'nextCandidateRowids' => self::rowids($next['decoded']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFullScanRejectedRowids' => self::rowids($current['rejected']),
            'nextFullScanRejectedRowids' => self::rowids($next['rejected']),
            'matchedRetainedRowids' => self::retained($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::exited($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::entered($currentMatched, $nextMatched),
            'currentTexts' => self::map($current['decoded'], 'text'),
            'nextTexts' => self::map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'currentMatchedTexts' => self::map($current['matched'], 'rtrimText'),
            'nextMatchedTexts' => self::map($next['matched'], 'rtrimText'),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'cursorInvalidated' => true,
            'cursorReusable' => false,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'likeResidualAppliesAfterRtrim' => true,
            'nonAsciiPrefixRequiresFullScan' => true,
            'asciiNocaseOnlyKeepsAccentCaseDistinct' => true,
            'malformedRowsDoNotAbortFullScan' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-non-ascii-prefix-full-scan',
                'sqlite-rtrim-residual-match',
                'sqlite-current-source-next204',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII-only NOCASE LIKE residual matching, RTRIM keys, and current-source diagnostics',
            'non_overlap' => 'next204 covers non-ASCII fixed-prefix NOCASE LIKE fallback over UTF-16 RTRIM rows; avoids next203 no-fixed-prefix fallback, next202 source-row patterns, next200 ESCAPE rebinds, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decoded:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape): array
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

        $matched = [];
        $rejected = [];
        foreach ($decoded as $entry) {
            if (SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false)) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'matched' => $matched,
            'rejected' => $rejected,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next204 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next204 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next204 rows require integer text_encoding');
        }
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

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function retained(array $current, array $next): array
    {
        return array_values(array_intersect($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function exited(array $current, array $next): array
    {
        return array_values(array_diff($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function entered(array $current, array $next): array
    {
        return array_values(array_diff($next, $current));
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
