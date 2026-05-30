<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function optionRowNamePlan(
        array $currentRows,
        array $nextRows,
        string $operator,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.wp_options@139',
        string $nextSource = 'main.wp_options@140',
        int $currentSchemaCookie = 139,
        int $nextSchemaCookie = 140,
    ): array {
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE/GLOB current-source next140 operator must be LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escape !== null) {
            throw new \InvalidArgumentException('SQLite GLOB does not accept an ESCAPE expression');
        }

        $range = $operator === 'LIKE'
            ? SQLiteDatabase::likePatternPlan($pattern, $escape)['binaryRange']
            : SQLiteDatabase::globPrefixRangeBounds($pattern);
        if ($range !== null && $range['lowerInclusive'] === '') {
            $range = null;
        }

        $current = self::scan($currentRows, $operator, $pattern, $escape, $range);
        $next = self::scan($nextRows, $operator, $pattern, $escape, $range);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
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
        if ($range === null) {
            $reasons[] = 'no-prefix-range';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        foreach ([
            'text-value' => $changes['textChangedRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'rtrim-key' => $changes['rtrimKeyChangedRowids'],
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
            'operator' => $operator,
            'expression' => 'rtrim(option_name)',
            'collation' => 'RTRIM',
            'pattern' => $pattern,
            'escape' => $escape,
            'range' => $range,
            'indexUsable' => $range !== null,
            'residualUsesUntrimmedText' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
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
            'currentRtrimKeys' => self::map($current['decoded'], 'rtrimKey'),
            'nextRtrimKeys' => self::map($next['decoded'], 'rtrimKey'),
            'currentTexts' => self::map($current['decoded'], 'text'),
            'nextTexts' => self::map($next['decoded'], 'text'),
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
            'changedRtrimKeyRowids' => $changes['rtrimKeyChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $range !== null,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-rtrim-expression-index',
                'sqlite-like-glob-binary-residual',
                'sqlite-current-source-next140',
            ],
            'dependency_closure' => 'no new support component needed; reuses native text encoding decode, LIKE/GLOB range planning, RTRIM keys, and current-source invalidation metadata',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $operator, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $errors = [];
        $malformed = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimKey' => rtrim($text, ' '),
                    'encoding' => self::encodingName($row['text_encoding']),
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
            if (!self::inRange($entry['rtrimKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = $operator === 'LIKE'
                ? SQLiteDatabase::likeMatches($entry['text'], $pattern, $escape, true)
                : SQLiteDatabase::globMatches($entry['text'], $pattern);
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
            throw new \InvalidArgumentException('SQLite RTRIM LIKE/GLOB current-source next140 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE/GLOB current-source next140 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite RTRIM LIKE/GLOB current-source next140 rows require integer text_encoding');
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

    /**
     * @param array{rtrimKey:string,rowid:int} $left
     * @param array{rtrimKey:string,rowid:int} $right
     */
    private static function sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['rtrimKey'], $right['rtrimKey']);

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
     * @param list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimKey:string,encoding:string,bytesHex:string}> $nextRows
     * @return array{textChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,rtrimKeyChangedRowids:list<int>}
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
        $rtrim = [];
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
            if ($current[$rowid]['rtrimKey'] !== $row['rtrimKey']) {
                $rtrim[] = $rowid;
            }
        }
        sort($text);
        sort($encoding);
        sort($bytes);
        sort($rtrim);

        return [
            'textChangedRowids' => $text,
            'encodingChangedRowids' => $encoding,
            'bytesChangedRowids' => $bytes,
            'rtrimKeyChangedRowids' => $rtrim,
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
