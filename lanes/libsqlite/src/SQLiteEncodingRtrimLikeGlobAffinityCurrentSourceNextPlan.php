<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingRtrimLikeGlobAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValuePlan(
        array $currentRows,
        array $nextRows,
        string $valueColumn,
        string $patternColumn,
        string $operator = 'LIKE',
        ?string $escapeColumn = null,
        string $currentSource = 'main.wp_options@143',
        string $nextSource = 'main.wp_options@144',
        int $currentSchemaCookie = 143,
        int $nextSchemaCookie = 144,
        int|string $currentEncoding = 'UTF-16LE',
        int|string $nextEncoding = 'UTF-16BE',
        bool $caseSensitiveLike = true,
    ): array {
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite RTRIM affinity current-source next144 operator must be LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escapeColumn !== null) {
            throw new \InvalidArgumentException('SQLite RTRIM affinity GLOB current-source next144 does not accept ESCAPE');
        }

        $currentEncodingName = self::encodingName($currentEncoding);
        $nextEncodingName = self::encodingName($nextEncoding);
        $current = self::scan($currentRows, $valueColumn, $patternColumn, $operator, $escapeColumn, $currentEncodingName, $caseSensitiveLike);
        $next = self::scan($nextRows, $valueColumn, $patternColumn, $operator, $escapeColumn, $nextEncodingName, $caseSensitiveLike);

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
        if ($currentEncodingName !== $nextEncodingName) {
            $reasons[] = 'scan-encoding';
        }
        foreach ([
            'value-affinity' => $changes['valueChangedRowids'],
            'pattern-affinity' => $changes['patternChangedRowids'],
            'escape-affinity' => $changes['escapeChangedRowids'],
            'rtrim-key' => $changes['rtrimKeyChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'residual-result' => $changes['matchChangedRowids'],
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
            'status' => 'encoding-rtrim-like-glob-affinity-current-source-next144',
            'operator' => $operator,
            'valueColumn' => $valueColumn,
            'patternColumn' => $patternColumn,
            'escapeColumn' => $escapeColumn,
            'collation' => 'RTRIM',
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentEncoding' => $currentEncodingName,
            'nextEncoding' => $nextEncodingName,
            'currentOrderRowids' => self::rowids($current['decoded']),
            'nextOrderRowids' => self::rowids($next['decoded']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedMatchedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentFalseRowids' => self::rowids($current['false']),
            'nextFalseRowids' => self::rowids($next['false']),
            'currentNullRowids' => $current['nullRowids'],
            'nextNullRowids' => $next['nullRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentValues' => self::map($current['decoded'], 'valueText'),
            'nextValues' => self::map($next['decoded'], 'valueText'),
            'currentPatterns' => self::map($current['decoded'], 'patternText'),
            'nextPatterns' => self::map($next['decoded'], 'patternText'),
            'currentEscapes' => self::map($current['decoded'], 'escapeText'),
            'nextEscapes' => self::map($next['decoded'], 'escapeText'),
            'currentRtrimKeys' => self::map($current['decoded'], 'rtrimKey'),
            'nextRtrimKeys' => self::map($next['decoded'], 'rtrimKey'),
            'currentValueStorage' => self::map($current['decoded'], 'valueStorage'),
            'nextValueStorage' => self::map($next['decoded'], 'valueStorage'),
            'currentPatternStorage' => self::map($current['decoded'], 'patternStorage'),
            'nextPatternStorage' => self::map($next['decoded'], 'patternStorage'),
            'currentValueBytesHex' => self::map($current['decoded'], 'valueBytesHex'),
            'nextValueBytesHex' => self::map($next['decoded'], 'valueBytesHex'),
            'currentPatternBytesHex' => self::map($current['decoded'], 'patternBytesHex'),
            'nextPatternBytesHex' => self::map($next['decoded'], 'patternBytesHex'),
            'currentResidualMatches' => self::map($current['decoded'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['decoded'], 'residualMatch'),
            'changedValueRowids' => $changes['valueChangedRowids'],
            'changedPatternRowids' => $changes['patternChangedRowids'],
            'changedEscapeRowids' => $changes['escapeChangedRowids'],
            'changedRtrimKeyRowids' => $changes['rtrimKeyChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['matchChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-text-affinity',
                'sqlite-rtrim-collation',
                'sqlite-like-glob-dynamic-pattern',
                'sqlite-current-source-next144',
            ],
            'dependency_closure' => 'no new support component needed; reuses native text affinity, RTRIM collation, LIKE/GLOB residual matching, and UTF encoding helpers',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decoded:list<array<string,mixed>>,matched:list<array<string,mixed>>,false:list<array<string,mixed>>,nullRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $valueColumn, string $patternColumn, string $operator, ?string $escapeColumn, string $encoding, bool $caseSensitiveLike): array
    {
        $decoded = [];
        $nulls = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $rowid = self::rowid($row, $index);
            if (!array_key_exists($valueColumn, $row)) {
                throw new \InvalidArgumentException("SQLite RTRIM affinity current-source next144 row is missing {$valueColumn}");
            }
            if (!array_key_exists($patternColumn, $row)) {
                throw new \InvalidArgumentException("SQLite RTRIM affinity current-source next144 row is missing {$patternColumn}");
            }
            if ($escapeColumn !== null && !array_key_exists($escapeColumn, $row)) {
                throw new \InvalidArgumentException("SQLite RTRIM affinity current-source next144 row is missing {$escapeColumn}");
            }

            try {
                $value = self::textAffinity($row[$valueColumn], 'value');
                $pattern = self::textAffinity($row[$patternColumn], 'pattern');
                $escape = $escapeColumn === null ? null : self::textAffinity($row[$escapeColumn], 'escape');
                if ($value === null || $pattern === null || ($escapeColumn !== null && $escape === null)) {
                    $nulls[] = $rowid;
                    continue;
                }
                if ($escape !== null && self::textLength($escape) !== 1) {
                    throw new \InvalidArgumentException('SQLite RTRIM affinity current-source next144 LIKE ESCAPE must be a single character after text affinity');
                }

                $matches = $operator === 'LIKE'
                    ? SQLiteDatabase::likeMatches($value, $pattern, $escape, $caseSensitiveLike)
                    : SQLiteDatabase::globMatches($value, $pattern);
                $decoded[] = [
                    'rowid' => $rowid,
                    'valueText' => $value,
                    'patternText' => $pattern,
                    'escapeText' => $escape,
                    'rtrimKey' => rtrim($value, ' '),
                    'valueStorage' => SQLiteAffinityComparison::storageClass($row[$valueColumn]),
                    'patternStorage' => SQLiteAffinityComparison::storageClass($row[$patternColumn]),
                    'escapeStorage' => $escapeColumn === null ? null : SQLiteAffinityComparison::storageClass($row[$escapeColumn]),
                    'valueBytesHex' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding)),
                    'patternBytesHex' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($pattern, $encoding)),
                    'escapeBytesHex' => $escape === null ? null : bin2hex(SQLiteEncodingCollationSourceCursor::encodeText($escape, $encoding)),
                    'residualMatch' => $matches,
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($nulls);
        sort($malformed);
        ksort($errors);

        return [
            'decoded' => $decoded,
            'matched' => array_values(array_filter($decoded, static fn (array $row): bool => $row['residualMatch'] === true)),
            'false' => array_values(array_filter($decoded, static fn (array $row): bool => $row['residualMatch'] === false)),
            'nullRowids' => $nulls,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, int $index): int
    {
        if (!array_key_exists('option_id', $row)) {
            return $index + 1;
        }
        if (!is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite RTRIM affinity current-source next144 option_id must be an integer');
        }

        return $row['option_id'];
    }

    private static function textAffinity(mixed $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof SQLiteBlobValue) {
            $value = $value->bytes;
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException("SQLite RTRIM affinity current-source next144 {$label} requires well-formed UTF-8 before encoding");
            }

            return $value;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        throw new \InvalidArgumentException("SQLite RTRIM affinity current-source next144 {$label} must be scalar text-affinity input");
    }

    private static function textLength(string $text): int
    {
        if (preg_match_all('/./us', $text, $matches) === false) {
            throw new \InvalidArgumentException('SQLite RTRIM affinity current-source next144 text length requires well-formed UTF-8');
        }

        return count($matches[0]);
    }

    /** @param array{rtrimKey:string,rowid:int} $left @param array{rtrimKey:string,rowid:int} $right */
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
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{valueChangedRowids:list<int>,patternChangedRowids:list<int>,escapeChangedRowids:list<int>,rtrimKeyChangedRowids:list<int>,bytesChangedRowids:list<int>,matchChangedRowids:list<int>}
     */
    private static function changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'valueChangedRowids' => [],
            'patternChangedRowids' => [],
            'escapeChangedRowids' => [],
            'rtrimKeyChangedRowids' => [],
            'bytesChangedRowids' => [],
            'matchChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['valueText'] !== $row['valueText']) {
                $changes['valueChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['patternText'] !== $row['patternText']) {
                $changes['patternChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['escapeText'] !== $row['escapeText']) {
                $changes['escapeChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['rtrimKey'] !== $row['rtrimKey']) {
                $changes['rtrimKeyChangedRowids'][] = $rowid;
            }
            if (
                $current[$rowid]['valueBytesHex'] !== $row['valueBytesHex']
                || $current[$rowid]['patternBytesHex'] !== $row['patternBytesHex']
                || $current[$rowid]['escapeBytesHex'] !== $row['escapeBytesHex']
            ) {
                $changes['bytesChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['residualMatch'] !== $row['residualMatch']) {
                $changes['matchChangedRowids'][] = $rowid;
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    private static function encodingName(int|string $encoding): string
    {
        if (is_int($encoding)) {
            return match ($encoding) {
                1 => 'UTF-8',
                2 => 'UTF-16LE',
                3 => 'UTF-16BE',
                default => throw new \InvalidArgumentException('SQLite RTRIM affinity current-source next144 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
            };
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 'UTF-8',
            'UTF-16LE', 'UTF16LE' => 'UTF-16LE',
            'UTF-16BE', 'UTF16BE' => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite RTRIM affinity current-source next144 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
