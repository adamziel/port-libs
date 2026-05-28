<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext211Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameSourceRefreshPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@210',
        string $nextSource = 'main.wp_options@211',
        int $currentSchemaCookie = 210,
        int $nextSchemaCookie = 211,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $like, $pattern, $escape);
        $next = self::scan($nextRows, $like, $pattern, $escape);

        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
        $currentFalsePositive = self::rowids($current['falsePositive']);
        $nextFalsePositive = self::rowids($next['falsePositive']);
        $byteOrderOnlyRowids = self::byteOrderOnlyRowids($current['decoded'], $next['decoded']);
        $textChangedRowids = self::changedRowids($current['decoded'], $next['decoded'], 'rtrimText');
        $encodingChangedRowids = self::changedRowids($current['decoded'], $next['decoded'], 'encodingName');
        $matchedExited = self::sortedDiff($currentMatched, $nextMatched);
        $matchedEntered = self::sortedDiff($nextMatched, $currentMatched);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($currentFalsePositive !== $nextFalsePositive) {
            $reasons[] = 'range-false-positive-rowset';
        }
        if ($textChangedRowids !== []) {
            $reasons[] = 'decoded-rtrim-text';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($byteOrderOnlyRowids !== [] && $reasons === []) {
            $reasons[] = 'byte-order-only-refresh';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next211',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* current-source refresh */',
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
            'candidateRetainedRowids' => self::sortedIntersect($currentCandidates, $nextCandidates),
            'candidateExitedRowids' => self::sortedDiff($currentCandidates, $nextCandidates),
            'candidateEnteredRowids' => self::sortedDiff($nextCandidates, $currentCandidates),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => $matchedExited,
            'matchedEnteredRowids' => $matchedEntered,
            'currentFalsePositiveRowids' => $currentFalsePositive,
            'nextFalsePositiveRowids' => $nextFalsePositive,
            'currentExcludedDecodedRowids' => self::sortedDiff(self::rowids($current['decoded']), $currentCandidates),
            'nextExcludedDecodedRowids' => self::sortedDiff(self::rowids($next['decoded']), $nextCandidates),
            'currentRtrimTexts' => self::mapByRowid($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::mapByRowid($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::mapByRowid($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::mapByRowid($next['decoded'], 'nocaseKey'),
            'currentEncodings' => self::mapByRowid($current['decoded'], 'encodingName'),
            'nextEncodings' => self::mapByRowid($next['decoded'], 'encodingName'),
            'byteOrderOnlyRowids' => $byteOrderOnlyRowids,
            'encodingChangedRowids' => $encodingChangedRowids,
            'decodedRtrimTextChangedRowids' => $textChangedRowids,
            'currentMatchedTexts' => self::selectMap(self::mapByRowid($current['decoded'], 'rtrimText'), $currentMatched),
            'nextMatchedTexts' => self::selectMap(self::mapByRowid($next['decoded'], 'rtrimText'), $nextMatched),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'cursorInvalidated' => array_diff($reasons, ['byte-order-only-refresh']) !== [],
            'cursorReusable' => array_diff($reasons, ['byte-order-only-refresh']) === [],
            'byteOrderOnlyRefreshReusable' => $byteOrderOnlyRowids !== [] && array_diff($reasons, ['byte-order-only-refresh']) === [],
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'residualCheckedAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-next211',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, NOCASE LIKE prefix ranges, RTRIM expression keys, and current-source cursor diagnostics',
            'non_overlap' => 'next211 audits source-refresh rowset changes after UTF-16 decode, RTRIM keys, NOCASE range matching, and LIKE residuals; it avoids accepted BOM normalization, ESCAPE rebind, Unicode GLOB, malformed insert guard, and next209 coverage',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $like
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, array $like, string $pattern, ?string $escape): array
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
                    'encodingName' => self::encodingName($row['text_encoding']),
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
            if (!self::inRange($entry['nocaseKey'], $like['range'] ?? null)) {
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
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next211 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next211 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next211 rows require integer text_encoding');
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

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function mapByRowid(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        ksort($mapped);

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

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<int> */
    private static function byteOrderOnlyRowids(array $current, array $next): array
    {
        $currentByRowid = self::byRowid($current);
        $rowids = [];
        foreach (self::byRowid($next) as $rowid => $entry) {
            if (!isset($currentByRowid[$rowid])) {
                continue;
            }
            if ($currentByRowid[$rowid]['rtrimText'] === $entry['rtrimText']
                && $currentByRowid[$rowid]['encodingName'] !== $entry['encodingName']) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<int> */
    private static function changedRowids(array $current, array $next, string $key): array
    {
        $currentByRowid = self::byRowid($current);
        $rowids = [];
        foreach (self::byRowid($next) as $rowid => $entry) {
            if (isset($currentByRowid[$rowid]) && $currentByRowid[$rowid][$key] !== $entry[$key]) {
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

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next211 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
