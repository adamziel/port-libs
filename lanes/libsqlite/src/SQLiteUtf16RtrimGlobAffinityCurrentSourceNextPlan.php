<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameValuePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        float|int|string $minimumNumeric,
        float|int|string $maximumNumeric,
        string $currentSource = 'main.wp_options@current',
        string $nextSource = 'main.wp_options@next',
        int $currentSchemaCookie = 1,
        int $nextSchemaCookie = 1,
        int $currentCollationVersion = 1,
        int $nextCollationVersion = 1,
    ): array {
        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $minimum = self::coerceNumericBound($minimumNumeric, 'minimum');
        $maximum = self::coerceNumericBound($maximumNumeric, 'maximum');
        if ($minimum > $maximum) {
            throw new \InvalidArgumentException('SQLite UTF-16 RTRIM GLOB affinity current-source nextOneFourFive numeric range is reversed');
        }

        $current = self::sourceRows($currentRows, $pattern, $range, $minimum, $maximum);
        $next = self::sourceRows($nextRows, $pattern, $range, $minimum, $maximum);

        $currentCandidateRowids = self::rowids($current['candidates']);
        $nextCandidateRowids = self::rowids($next['candidates']);
        $currentMatchedRowids = self::rowids($current['matched']);
        $nextMatchedRowids = self::rowids($next['matched']);
        $currentAffinityRowids = self::rowids($current['affinityMatched']);
        $nextAffinityRowids = self::rowids($next['affinityMatched']);
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
        if ($currentAffinityRowids !== $nextAffinityRowids) {
            $reasons[] = 'affinity-rowset';
        }
        if ($changes['comparisonKeyChangedRowids'] !== []) {
            $reasons[] = 'comparison-key';
        }
        if ($changes['nameEncodingChangedRowids'] !== []) {
            $reasons[] = 'name-encoding';
        }
        if ($changes['nameBytesChangedRowids'] !== []) {
            $reasons[] = 'name-bytes';
        }
        if ($changes['valueChangedRowids'] !== []) {
            $reasons[] = 'affinity-value';
        }
        if ($changes['valueEncodingChangedRowids'] !== []) {
            $reasons[] = 'value-encoding';
        }
        if ($changes['valueBytesChangedRowids'] !== []) {
            $reasons[] = 'value-bytes';
        }

        return [
            'operator' => 'GLOB',
            'expression' => 'rtrim(option_name) COLLATE NOCASE GLOB ? AND option_value BETWEEN ? AND ?',
            'pattern' => $pattern,
            'nameCollation' => 'RTRIM+NOCASE',
            'residualCollation' => 'BINARY',
            'valueAffinity' => 'NUMERIC',
            'numericRange' => ['minimum' => $minimum, 'maximum' => $maximum],
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
            'currentAffinityMatchedRowids' => $currentAffinityRowids,
            'nextAffinityMatchedRowids' => $nextAffinityRowids,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'currentAffinityRejectedRowids' => self::rowids($current['affinityRejected']),
            'nextAffinityRejectedRowids' => self::rowids($next['affinityRejected']),
            'retainedAffinityMatchedRowids' => array_values(array_intersect($currentAffinityRowids, $nextAffinityRowids)),
            'enteredAffinityMatchedRowids' => array_values(array_diff($nextAffinityRowids, $currentAffinityRowids)),
            'exitedAffinityMatchedRowids' => array_values(array_diff($currentAffinityRowids, $nextAffinityRowids)),
            'currentComparisonKeys' => self::map($current['valid'], 'comparisonKey'),
            'nextComparisonKeys' => self::map($next['valid'], 'comparisonKey'),
            'currentNameTexts' => self::map($current['valid'], 'nameText'),
            'nextNameTexts' => self::map($next['valid'], 'nameText'),
            'currentValueTexts' => self::map($current['valid'], 'valueText'),
            'nextValueTexts' => self::map($next['valid'], 'valueText'),
            'currentNumericValues' => self::map($current['valid'], 'numericValue'),
            'nextNumericValues' => self::map($next['valid'], 'numericValue'),
            'currentNameEncodings' => self::map($current['valid'], 'nameEncoding'),
            'nextNameEncodings' => self::map($next['valid'], 'nameEncoding'),
            'currentValueEncodings' => self::map($current['valid'], 'valueEncoding'),
            'nextValueEncodings' => self::map($next['valid'], 'valueEncoding'),
            'currentNameBytesHex' => self::map($current['valid'], 'nameBytesHex'),
            'nextNameBytesHex' => self::map($next['valid'], 'nameBytesHex'),
            'currentValueBytesHex' => self::map($current['valid'], 'valueBytesHex'),
            'nextValueBytesHex' => self::map($next['valid'], 'valueBytesHex'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'currentAffinityMatches' => self::map($current['matched'], 'affinityMatch'),
            'nextAffinityMatches' => self::map($next['matched'], 'affinityMatch'),
            'currentMalformedRowids' => array_keys($current['errors']),
            'nextMalformedRowids' => array_keys($next['errors']),
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'retainedComparisonKeyChangedRowids' => $changes['comparisonKeyChangedRowids'],
            'retainedNameEncodingChangedRowids' => $changes['nameEncodingChangedRowids'],
            'retainedNameBytesChangedRowids' => $changes['nameBytesChangedRowids'],
            'retainedValueChangedRowids' => $changes['valueChangedRowids'],
            'retainedValueEncodingChangedRowids' => $changes['valueEncodingChangedRowids'],
            'retainedValueBytesChangedRowids' => $changes['valueBytesChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $range !== null,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-rtrim-expression-index',
                'sqlite-nocase-collation',
                'sqlite-glob-binary-residual',
                'sqlite-numeric-affinity',
                'sqlite-encoding-source-cursor',
                'sqlite-current-source-nextoneFourFive',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array<string,mixed>
     */
    private static function sourceRows(array $rows, string $pattern, ?array $range, float $minimum, float $maximum): array
    {
        $valid = [];
        $errors = [];
        foreach ($rows as $row) {
            self::validateRow($row);
            try {
                $nameText = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['name_text_encoding']);
                $valueText = SQLiteEncodingCollationSourceCursor::decodeText($row['option_value_bytes'], $row['value_text_encoding']);
                $numericValue = self::numericAffinity($valueText);
                $valid[] = [
                    'rowid' => $row['option_id'],
                    'nameText' => $nameText,
                    'valueText' => $valueText,
                    'numericValue' => $numericValue,
                    'comparisonKey' => self::comparisonKey($nameText),
                    'nameEncoding' => self::encodingName($row['name_text_encoding']),
                    'valueEncoding' => self::encodingName($row['value_text_encoding']),
                    'nameBytesHex' => bin2hex($row['option_name_bytes']),
                    'valueBytesHex' => bin2hex($row['option_value_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($valid, self::sortRows(...));

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $affinityMatched = [];
        $affinityRejected = [];
        foreach ($valid as $entry) {
            if (!self::inRange($entry['comparisonKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::globMatches($entry['nameText'], $pattern);
            $entry['affinityMatch'] = $entry['numericValue'] !== null && $entry['numericValue'] >= $minimum && $entry['numericValue'] <= $maximum;
            $candidates[] = $entry;
            if (!$entry['residualMatch']) {
                $falsePositive[] = $entry;
                continue;
            }
            $matched[] = $entry;
            if ($entry['affinityMatch']) {
                $affinityMatched[] = $entry;
            } else {
                $affinityRejected[] = $entry;
            }
        }

        return [
            'valid' => $valid,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'affinityMatched' => $affinityMatched,
            'affinityRejected' => $affinityRejected,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function validateRow(array $row): void
    {
        foreach (['option_id', 'name_text_encoding', 'value_text_encoding'] as $key) {
            if (!isset($row[$key]) || !is_int($row[$key])) {
                throw new \InvalidArgumentException("SQLite UTF-16 RTRIM GLOB affinity current-source nextOneFourFive rows require integer {$key}");
            }
        }
        foreach (['option_name_bytes', 'option_value_bytes'] as $key) {
            if (!array_key_exists($key, $row) || !is_string($row[$key])) {
                throw new \InvalidArgumentException("SQLite UTF-16 RTRIM GLOB affinity current-source nextOneFourFive rows require {$key}");
            }
        }
    }

    private static function inRange(string $comparisonKey, ?array $range): bool
    {
        if ($range === null) {
            return false;
        }

        $lower = self::comparisonKey($range['lowerInclusive']);
        $upper = $range['upperBound'] === null ? null : self::comparisonKey($range['upperBound']);
        return strcmp($comparisonKey, $lower) >= 0 && ($upper === null || strcmp($comparisonKey, $upper) < 0);
    }

    private static function comparisonKey(string $text): string
    {
        return self::asciiLower(rtrim($text, ' '));
    }

    private static function numericAffinity(string $text): float|int|null
    {
        $trimmed = trim($text);
        if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $trimmed, $match)) {
            return null;
        }

        $number = $match[0];
        $float = (float) $number;
        if (strpbrk($number, '.eE') === false && $float >= PHP_INT_MIN && $float <= PHP_INT_MAX) {
            return (int) $number;
        }

        return $float;
    }

    private static function coerceNumericBound(float|int|string $value, string $name): float
    {
        $coerced = is_string($value) ? self::numericAffinity($value) : $value;
        if ($coerced === null) {
            throw new \InvalidArgumentException("SQLite UTF-16 RTRIM GLOB affinity current-source nextOneFourFive {$name} bound must be numeric");
        }

        return (float) $coerced;
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
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,list<int>>
     */
    private static function retainedCandidateChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'comparisonKeyChangedRowids' => [],
            'nameEncodingChangedRowids' => [],
            'nameBytesChangedRowids' => [],
            'valueChangedRowids' => [],
            'valueEncodingChangedRowids' => [],
            'valueBytesChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            foreach ([
                'comparisonKey' => 'comparisonKeyChangedRowids',
                'nameEncoding' => 'nameEncodingChangedRowids',
                'nameBytesHex' => 'nameBytesChangedRowids',
                'numericValue' => 'valueChangedRowids',
                'valueEncoding' => 'valueEncodingChangedRowids',
                'valueBytesHex' => 'valueBytesChangedRowids',
            ] as $field => $changeKey) {
                if ($current[$rowid][$field] !== $row[$field]) {
                    $changes[$changeKey][] = $rowid;
                }
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 RTRIM GLOB affinity current-source nextOneFourFive rows require UTF-8, UTF-16LE, or UTF-16BE encoding'),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
