<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRtrimNocaseGlobCurrentSourceNextPlan
{
    public static function keyValueRowKeyPlan(mixed ...$args): array
    {
        return SQLiteRtrimNocaseGlobCurrentSourceNextPlainImpl::keyValueRowKeyPlan(...$args);
    }

    public static function keyValueRowKeyExpressionPlan(mixed ...$args): array
    {
        return SQLiteRtrimNocaseGlobCurrentSourceNextExpressionImpl::keyValueRowKeyExpressionPlan(...$args);
    }

}

final class SQLiteRtrimNocaseGlobCurrentSourceNextPlainImpl
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $collation,
        string $currentSource = 'main.app_settings@current',
        string $nextSource = 'main.app_settings@next',
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source plan requires BINARY, NOCASE, or RTRIM collation');
        }

        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $current = self::sourceRows($currentRows, $pattern, $collation, $range);
        $next = self::sourceRows($nextRows, $pattern, $collation, $range);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);

        return [
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'pattern' => $pattern,
            'collation' => $collation,
            'range' => $range,
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFalsePositiveRowids' => array_values(array_diff($currentCandidates, $currentMatched)),
            'nextFalsePositiveRowids' => array_values(array_diff($nextCandidates, $nextMatched)),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentOrderRowids' => self::rowids($current['ordered']),
            'nextOrderRowids' => self::rowids($next['ordered']),
            'currentComparisonKeys' => self::mapByRowid($current['ordered'], 'comparisonKey'),
            'nextComparisonKeys' => self::mapByRowid($next['ordered'], 'comparisonKey'),
            'currentResidualMatches' => self::mapByRowid($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::mapByRowid($next['candidates'], 'residualMatch'),
            'currentMalformedRowids' => $current['malformed'],
            'nextMalformedRowids' => $next['malformed'],
            'repairedMalformedRowids' => array_values(array_diff($current['malformed'], $next['malformed'])),
            'newlyMalformedRowids' => array_values(array_diff($next['malformed'], $current['malformed'])),
            'cursorInvalidated' => $currentSource !== $nextSource
                || $currentCandidates !== $nextCandidates
                || $currentMatched !== $nextMatched
                || $current['malformed'] !== $next['malformed'],
            'invalidationReasons' => self::invalidationReasons(
                $currentSource,
                $nextSource,
                $currentCandidates,
                $nextCandidates,
                $currentMatched,
                $nextMatched,
                $current['malformed'],
                $next['malformed'],
            ),
            'dependencies' => ['sqlite-glob-prefix-range', 'sqlite-' . strtolower($collation) . '-collation', 'sqlite-glob-binary-residual'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{ordered:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,malformed:list<int>}
     */
    private static function sourceRows(array $rows, string $pattern, string $collation, ?array $range): array
    {
        $ordered = [];
        $malformed = [];
        foreach ($rows as $row) {
            if (!isset($row['setting_id']) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source rows require integer setting_id');
            }
            if (!array_key_exists('key_name', $row) || !is_string($row['key_name'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source rows require text key_name');
            }

            $key = self::comparisonKey($row['key_name'], $collation);
            $malformedUtf8 = preg_match('//u', $row['key_name']) !== 1;
            if ($malformedUtf8) {
                $malformed[] = $row['setting_id'];
            }
            $ordered[] = [
                'rowid' => $row['setting_id'],
                'text' => $row['key_name'],
                'comparisonKey' => $key,
                'payload' => $row,
                'malformedUtf8' => $malformedUtf8,
            ];
        }

        usort($ordered, static function (array $left, array $right): int {
            $comparison = strcmp($left['comparisonKey'], $right['comparisonKey']);
            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        $candidates = [];
        $matched = [];
        foreach ($ordered as $entry) {
            $inRange = $range !== null
                && strcmp($entry['comparisonKey'], self::comparisonKey($range['lowerInclusive'], $collation)) >= 0
                && ($range['upperBound'] === null || strcmp($entry['comparisonKey'], self::comparisonKey($range['upperBound'], $collation)) < 0);
            $residual = SQLiteDatabase::globMatches($entry['text'], $pattern);
            if ($inRange) {
                $entry['residualMatch'] = $residual;
                $candidates[] = $entry;
            }
            if ($inRange && $residual) {
                $matched[] = $entry;
            }
        }

        sort($malformed);

        return ['ordered' => $ordered, 'candidates' => $candidates, 'matched' => $matched, 'malformed' => $malformed];
    }

    private static function comparisonKey(string $text, string $collation): string
    {
        return match ($collation) {
            'BINARY' => $text,
            'NOCASE' => self::asciiLower($text),
            'RTRIM' => rtrim($text, ' '),
            default => throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source plan requires BINARY, NOCASE, or RTRIM collation'),
        };
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
    private static function mapByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[$row['rowid']] = $row[$field];
        }

        return $values;
    }

    /**
     * @param list<int> $currentCandidates
     * @param list<int> $nextCandidates
     * @param list<int> $currentMatched
     * @param list<int> $nextMatched
     * @param list<int> $currentMalformed
     * @param list<int> $nextMalformed
     * @return list<string>
     */
    private static function invalidationReasons(
        string $currentSource,
        string $nextSource,
        array $currentCandidates,
        array $nextCandidates,
        array $currentMatched,
        array $nextMatched,
        array $currentMalformed,
        array $nextMalformed,
    ): array {
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($currentMalformed !== $nextMalformed) {
            $reasons[] = 'malformed-text';
        }

        return $reasons;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}

final class SQLiteRtrimNocaseGlobCurrentSourceNextExpressionImpl
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyExpressionPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $currentSource = 'main.app_settings@current',
        string $nextSource = 'main.app_settings@next',
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
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
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
                'sqlite-current-source-nextoneThreeSix',
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
            if (!isset($row['setting_id']) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source nextOneThreeSix rows require integer setting_id');
            }
            if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source nextOneThreeSix rows require key_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source nextOneThreeSix rows require integer text_encoding');
            }

            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $valid[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'comparisonKey' => self::comparisonKey($text),
                    'encoding' => self::encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                    'payload' => $row,
                ];
            } catch (\InvalidArgumentException $exception) {
                $errors[$row['setting_id']] = $exception->getMessage();
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
            default => throw new \InvalidArgumentException('SQLite RTRIM NOCASE GLOB current-source nextOneThreeSix rows require UTF-8, UTF-16LE, or UTF-16BE encoding'),
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
