<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEmbeddedNulPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@173',
        string $nextSource = 'main.wp_options@174',
        int $currentSchemaCookie = 173,
        int $nextSchemaCookie = 174,
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
            'nul-position' => $changes['nulPositionChangedRowids'],
            'cstring-prefix' => $changes['cstringPrefixChangedRowids'],
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
        if ($current['cstringFalseMatchRowids'] !== [] || $next['cstringFalseMatchRowids'] !== []) {
            $reasons[] = 'embedded-nul-full-text-recheck';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-nul-current-source-next174',
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
            'currentCstringFalseMatchRowids' => $current['cstringFalseMatchRowids'],
            'nextCstringFalseMatchRowids' => $next['cstringFalseMatchRowids'],
            'currentEmbeddedNulRowids' => $current['embeddedNulRowids'],
            'nextEmbeddedNulRowids' => $next['embeddedNulRowids'],
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
            'currentCstringPrefixes' => self::map($current['decoded'], 'cstringPrefix'),
            'nextCstringPrefixes' => self::map($next['decoded'], 'cstringPrefix'),
            'currentNulOffsets' => self::map($current['decoded'], 'nulOffset'),
            'nextNulOffsets' => self::map($next['decoded'], 'nulOffset'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'currentCstringResidualMatches' => self::map($current['candidates'], 'cstringResidualMatch'),
            'nextCstringResidualMatches' => self::map($next['candidates'], 'cstringResidualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedNulPositionRowids' => $changes['nulPositionChangedRowids'],
            'changedCstringPrefixRowids' => $changes['cstringPrefixChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'embeddedNulPreservesSuffixForLike' => true,
            'rtrimDoesNotTrimNul' => true,
            'nocaseFoldsAsciiAroundNulOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-embedded-nul-text-comparison',
                'sqlite-current-source-next174',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode and LIKE/NOCASE/RTRIM comparison while adding embedded-NUL full-text diagnostics for current-source cursor transitions',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,embeddedNulRowids:list<int>,cstringFalseMatchRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $embeddedNul = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $nulOffset = strpos($rtrim, "\0");
                if ($nulOffset !== false) {
                    $embeddedNul[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLower($rtrim),
                    'cstringPrefix' => $nulOffset === false ? $rtrim : substr($rtrim, 0, $nulOffset),
                    'nulOffset' => $nulOffset === false ? null : $nulOffset,
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($embeddedNul);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $cstringFalseMatches = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $entry['cstringResidualMatch'] = SQLiteDatabase::likeMatches($entry['cstringPrefix'], $pattern, $escape, false);
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
            if (!$entry['residualMatch'] && $entry['cstringResidualMatch']) {
                $cstringFalseMatches[] = $entry['rowid'];
            }
        }
        sort($cstringFalseMatches);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'embeddedNulRowids' => $embeddedNul,
            'cstringFalseMatchRowids' => $cstringFalseMatches,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM NUL next174 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM NUL next174 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM NUL next174 rows require integer text_encoding');
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
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,cstringPrefix:string,nulOffset:?int,bytesHex:string,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,cstringPrefix:string,nulOffset:?int,bytesHex:string,residualMatch?:bool}> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,nulPositionChangedRowids:list<int>,cstringPrefixChangedRowids:list<int>,bytesChangedRowids:list<int>,residualChangedRowids:list<int>}
     */
    private static function changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'nulPositionChangedRowids' => [],
            'cstringPrefixChangedRowids' => [],
            'bytesChangedRowids' => [],
            'residualChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $changes['textChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['rtrimText'] !== $row['rtrimText']) {
                $changes['rtrimChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['nocaseKey'] !== $row['nocaseKey']) {
                $changes['nocaseKeyChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['nulOffset'] !== $row['nulOffset']) {
                $changes['nulPositionChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['cstringPrefix'] !== $row['cstringPrefix']) {
                $changes['cstringPrefixChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $changes['bytesChangedRowids'][] = $rowid;
            }
            if (($current[$rowid]['residualMatch'] ?? null) !== ($row['residualMatch'] ?? null)) {
                $changes['residualChangedRowids'][] = $rowid;
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    /**
     * @param list<array{rowid:int,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,residualMatch?:bool}> $nextRows
     * @return list<int>
     */
    private static function residualChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = (bool) ($row['residualMatch'] ?? false);
        }

        $changed = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (array_key_exists($rowid, $current) && $current[$rowid] !== (bool) ($row['residualMatch'] ?? false)) {
                $changed[] = $rowid;
            }
        }
        sort($changed);

        return $changed;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
