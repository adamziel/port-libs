<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRtrimNocaseGlobCurrentSourceNext136Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameExpressionPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $currentSource = 'main.wp_options@current',
        string $nextSource = 'main.wp_options@next',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
        int $currentCollationVersion = 1,
        int $nextCollationVersion = 1,
    ): array {
        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $current = self::sourceRows($currentRows, $pattern, $range);
        $next = self::sourceRows($nextRows, $pattern, $range);

        $currentCandidateRowids = self::rowids($current['candidates']);
        $nextCandidateRowids = self::rowids($next['candidates']);
        $currentMatchedRowids = self::rowids($current['matched']);
        $nextMatchedRowids = self::rowids($next['matched']);
        $retainedMatchedRowids = array_values(array_intersect($currentMatchedRowids, $nextMatchedRowids));
        $enteredMatchedRowids = array_values(array_diff($nextMatchedRowids, $currentMatchedRowids));
        $exitedMatchedRowids = array_values(array_diff($currentMatchedRowids, $nextMatchedRowids));
        $changes = self::retainedCandidateChanges($current['candidates'], $next['candidates']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentCollationVersion !== $nextCollationVersion) {
            $reasons[] = 'collation-version';
        }
        if ($current['errors'] !== $next['errors']) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($changes['comparisonKeyChangedRowids'] !== []) {
            $reasons[] = 'comparison-key';
        }
        if ($changes['encodingChangedRowids'] !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changes['bytesChangedRowids'] !== []) {
            $reasons[] = 'key-bytes';
        }

        return [
            'operator' => 'GLOB',
            'expression' => 'rtrim(option_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'collation' => 'NOCASE',
            'rangeCollation' => 'RTRIM+NOCASE',
            'residualCollation' => 'BINARY',
            'globResidualCaseSensitive' => true,
            'range' => $range,
            'indexUsable' => $range !== null,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCollationVersion' => $currentCollationVersion,
            'nextCollationVersion' => $nextCollationVersion,
            'currentOrderRowids' => self::rowids($current['valid']),
            'nextOrderRowids' => self::rowids($next['valid']),
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'retainedMatchedRowids' => $retainedMatchedRowids,
            'enteredMatchedRowids' => $enteredMatchedRowids,
            'exitedMatchedRowids' => $exitedMatchedRowids,
            'currentComparisonKeys' => self::map($current['valid'], 'comparisonKey'),
            'nextComparisonKeys' => self::map($next['valid'], 'comparisonKey'),
            'currentTexts' => self::map($current['valid'], 'text'),
            'nextTexts' => self::map($next['valid'], 'text'),
            'currentEncodings' => self::map($current['valid'], 'encoding'),
            'nextEncodings' => self::map($next['valid'], 'encoding'),
            'currentBytesHex' => self::map($current['valid'], 'bytesHex'),
            'nextBytesHex' => self::map($next['valid'], 'bytesHex'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'currentMalformedRowids' => array_keys($current['errors']),
            'nextMalformedRowids' => array_keys($next['errors']),
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'repairedMalformedRowids' => array_values(array_diff(array_keys($current['errors']), array_keys($next['errors']))),
            'newlyMalformedRowids' => array_values(array_diff(array_keys($next['errors']), array_keys($current['errors']))),
            'retainedComparisonKeyChangedRowids' => $changes['comparisonKeyChangedRowids'],
            'retainedEncodingChangedRowids' => $changes['encodingChangedRowids'],
            'retainedBytesChangedRowids' => $changes['bytesChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $range !== null,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-rtrim-expression-index',
                'sqlite-nocase-collation',
                'sqlite-glob-binary-residual',
                'sqlite-encoding-source-cursor',
                'sqlite-current-source-next136',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{
     *   valid:list<array{rowid:int,text:string,comparisonKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>}>,
     *   candidates:list<array{rowid:int,text:string,comparisonKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>,residualMatch:bool}>,
     *   matched:list<array{rowid:int,text:string,comparisonKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>,residualMatch:bool}>,
     *   falsePositive:list<array{rowid:int,text:string,comparisonKey:string,encoding:string,bytesHex:string,payload:array<string,mixed>,residualMatch:bool}>,
     *   errors:array<int,string>
     * }
     */
    private static function sourceRows(array $rows, string $pattern, ?array $range): array
    {
        $valid = [];
        $errors = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source next136 rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source next136 rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source next136 rows require integer text_encoding');
            }

            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $valid[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'comparisonKey' => self::comparisonKey($text),
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                    'payload' => $row,
                ];
            } catch (\InvalidArgumentException $exception) {
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($valid, self::sortRows(...));

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($valid as $entry) {
            if (!self::inRange($entry['comparisonKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::globMatches($entry['text'], $pattern);
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }

        return [
            'valid' => $valid,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'errors' => $errors,
        ];
    }

    private static function inRange(string $comparisonKey, ?array $range): bool
    {
        if ($range === null) {
            return false;
        }

        $lower = self::comparisonKey($range['lowerInclusive']);
        $upper = $range['upperBound'] === null ? null : self::comparisonKey($range['upperBound']);
        if (strcmp($comparisonKey, $lower) < 0) {
            return false;
        }
        if ($upper !== null && strcmp($comparisonKey, $upper) >= 0) {
            return false;
        }

        return true;
    }

    private static function comparisonKey(string $text): string
    {
        return self::asciiLower(rtrim($text, ' '));
    }

    /**
     * @param array{rowid:int,comparisonKey:string} $left
     * @param array{rowid:int,comparisonKey:string} $right
     */
    private static function sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['comparisonKey'], $right['comparisonKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /**
     * @param list<array{rowid:int}> $rows
     * @return list<int>
     */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,mixed>
     */
    private static function map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array{rowid:int,comparisonKey:string,encoding:string,bytesHex:string}> $currentRows
     * @param list<array{rowid:int,comparisonKey:string,encoding:string,bytesHex:string}> $nextRows
     * @return array{comparisonKeyChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>}
     */
    private static function retainedCandidateChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $comparisonKeyChanged = [];
        $encodingChanged = [];
        $bytesChanged = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['comparisonKey'] !== $row['comparisonKey']) {
                $comparisonKeyChanged[] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $encodingChanged[] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $bytesChanged[] = $rowid;
            }
        }

        sort($comparisonKeyChanged);
        sort($encodingChanged);
        sort($bytesChanged);

        return [
            'comparisonKeyChangedRowids' => $comparisonKeyChanged,
            'encodingChangedRowids' => $encodingChanged,
            'bytesChangedRowids' => $bytesChanged,
        ];
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source next136 rows require UTF-8, UTF-16LE, or UTF-16BE encoding'),
        };
    }

    private static function asciiLower(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $ord = ord($bytes[$offset]);
            if ($ord >= 0x41 && $ord <= 0x5a) {
                $bytes[$offset] = chr($ord + 0x20);
            }
        }

        return $bytes;
    }
}
