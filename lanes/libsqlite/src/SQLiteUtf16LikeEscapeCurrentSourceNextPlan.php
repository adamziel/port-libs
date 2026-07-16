<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16LikeEscapeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyLikeEscape(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = '\\',
        string $collation = 'BINARY',
        string $currentSource = 'main.app_settings@142',
        string $nextSource = 'main.app_settings@143',
        int $currentSchemaCookie = 142,
        int $nextSchemaCookie = 143,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("SQLite UTF-16 LIKE ESCAPE current-source next143 unsupported collation: {$collation}");
        }

        $caseSensitive = $collation === 'BINARY' || $collation === 'RTRIM';
        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $patternPlan['prefix'] === '' ? null : ($collation === 'NOCASE' ? $patternPlan['noCaseRange'] : $patternPlan['binaryRange']);
        $hasDanglingEscape = self::hasDanglingEscape($pattern, $escape);

        $current = self::scan($currentRows, $pattern, $escape, $collation, $caseSensitive, $range, $hasDanglingEscape);
        $next = self::scan($nextRows, $pattern, $escape, $collation, $caseSensitive, $range, $hasDanglingEscape);
        $changes = self::changes($current['decoded'], $next['decoded']);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($range === null) {
            $reasons[] = 'no-prefix-range';
        }
        if ($hasDanglingEscape) {
            $reasons[] = 'dangling-escape';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        foreach ([
            'text-value' => $changes['textChangedRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'collation-key' => $changes['collationKeyChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'operator' => 'LIKE',
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitive,
            'pattern' => $pattern,
            'escape' => $escape,
            'prefix' => $patternPlan['prefix'],
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'hasDanglingEscape' => $hasDanglingEscape,
            'range' => $range,
            'indexUsable' => $range !== null && !$hasDanglingEscape,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentOrderRowids' => self::rowids($current['decoded']),
            'nextOrderRowids' => self::rowids($next['decoded']),
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentTexts' => self::map($current['decoded'], 'text'),
            'nextTexts' => self::map($next['decoded'], 'text'),
            'currentCollationKeys' => self::map($current['decoded'], 'collationKey'),
            'nextCollationKeys' => self::map($next['decoded'], 'collationKey'),
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
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedCollationKeyRowids' => $changes['collationKeyChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $range !== null && !$hasDanglingEscape,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-text-decode',
                'sqlite-like-escape-prefix',
                'sqlite-collation-range',
                'sqlite-current-source-next143',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE ESCAPE prefix planning, residual LIKE matching, and collation keys',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(
        array $rows,
        string $pattern,
        ?string $escape,
        string $collation,
        bool $caseSensitive,
        ?array $range,
        bool $hasDanglingEscape,
    ): array {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'collationKey' => self::collationKey($text, $collation),
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, static fn (array $left, array $right): int => $left['collationKey'] <=> $right['collationKey'] ?: $left['rowid'] <=> $right['rowid']);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['collationKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = !$hasDanglingEscape && SQLiteDatabase::likeMatches($entry['text'], $pattern, $escape, $caseSensitive);
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
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE ESCAPE current-source next143 rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE ESCAPE current-source next143 rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE ESCAPE current-source next143 rows require integer text_encoding');
        }
    }

    private static function collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'NOCASE' => strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
            'RTRIM' => rtrim($text, ' '),
            default => $text,
        };
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
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
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE ESCAPE current-source next143 pattern text must be well-formed UTF-8');
        }

        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
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
     * @param list<array{rowid:int,text:string,collationKey:string,encoding:string,bytesHex:string}> $currentRows
     * @param list<array{rowid:int,text:string,collationKey:string,encoding:string,bytesHex:string}> $nextRows
     * @return array{textChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,collationKeyChangedRowids:list<int>}
     */
    private static function changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $text = [];
        $encoding = [];
        $bytes = [];
        $keys = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $text[] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $encoding[] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $bytes[] = $rowid;
            }
            if ($current[$rowid]['collationKey'] !== $row['collationKey']) {
                $keys[] = $rowid;
            }
        }
        sort($text);
        sort($encoding);
        sort($bytes);
        sort($keys);

        return [
            'textChangedRowids' => $text,
            'encodingChangedRowids' => $encoding,
            'bytesChangedRowids' => $bytes,
            'collationKeyChangedRowids' => $keys,
        ];
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
