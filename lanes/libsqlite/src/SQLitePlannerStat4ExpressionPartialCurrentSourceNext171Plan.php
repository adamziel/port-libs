<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext171Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $whereTerms, array $neededColumns): array
    {
        self::validateTerms($whereTerms);
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next171 needed columns cannot be empty');
        }

        $prepared = self::sourcePlan($preparedSource, $whereTerms, $neededColumns);
        $current = self::sourcePlan($currentSource, $whereTerms, $neededColumns);
        $preparedSignature = self::signature($preparedSource);
        $currentSignature = self::signature($currentSource);
        $stale = $preparedSignature !== $currentSignature
            || self::intValue($preparedSource, 'schemaCookie') !== self::intValue($currentSource, 'schemaCookie')
            || self::intValue($preparedSource, 'stat4Generation') !== self::intValue($currentSource, 'stat4Generation');
        $selected = $stale ? $current : $prepared;
        $source = $stale ? $currentSource : $preparedSource;
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImplied'] ?? false) === true
            && ($selected['unsampledEqualityKey'] ?? null) !== null
            && ($selected['matchedRows'] ?? []) !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next171-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::intValue($preparedSource, 'schemaCookie') !== self::intValue($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::intValue($preparedSource, 'stat4Generation') !== self::intValue($currentSource, 'stat4Generation'),
            'sourceSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedPlan' => self::summary($preparedSource, $prepared, $preparedSignature),
            'currentPlan' => self::summary($currentSource, $current, $currentSignature),
            'selectedPlan' => self::selectedSummary($selected, $ready),
            'matchedRows' => $selected['matchedRows'] ?? [],
            'matchedRowids' => array_column($selected['matchedRows'] ?? [], 'rowid'),
            'matchedExpressionKeys' => array_column($selected['matchedRows'] ?? [], 'expressionKey'),
            'unsampledEqualityKey' => $selected['unsampledEqualityKey'] ?? null,
            'stat4Bracket' => $selected['stat4Bracket'] ?? [],
            'stat4BracketChanged' => self::signature($prepared['stat4Bracket'] ?? []) !== self::signature($current['stat4Bracket'] ?? []),
            'stalePreparedRowidsBlockedByBracket' => array_values(array_diff(
                array_column($prepared['matchedRows'] ?? [], 'rowid'),
                array_column($current['matchedRows'] ?? [], 'rowid'),
            )),
            'currentSourceRowidsAdmittedByBracket' => array_values(array_diff(
                array_column($current['matchedRows'] ?? [], 'rowid'),
                array_column($prepared['matchedRows'] ?? [], 'rowid'),
            )),
            'cursorProgram' => self::cursorProgram($selected, $ready),
            'stat4Fence' => [
                'schemaCookie' => self::intValue($source, 'schemaCookie'),
                'stat4Generation' => self::intValue($source, 'stat4Generation'),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'expressionSignature' => self::normalizeExpression((string) ($selected['expression'] ?? '')),
                'partialPredicateSignature' => self::signature($selected['partialPredicateTerms'] ?? []),
                'stat4SampleSignature' => self::signature($selected['stat4Samples'] ?? []),
                'stat4BracketSignature' => self::signature($selected['stat4Bracket'] ?? []),
                'rowStreamSignature' => self::signature(array_column($selected['matchedRows'] ?? [], 'rowid')),
            ],
            'tableLookupRequired' => !($selected['covering'] ?? false),
            'residualPredicateRequired' => true,
            'detail' => (($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT171 UNSAMPLED EQUALITY BRACKET ' . (string) ($selected['name'] ?? 'NO INDEX')),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next171'],
            'dependency_closure' => 'no new support component needed; next171 reuses lane-local expression evaluation, partial predicate implication, STAT4 sample fences, and current-source row diagnostics',
            'non_overlap' => 'avoids accepted next154 exact STAT4 equality/IN/BETWEEN row streams, next167 post-ANALYZE sample-window fences, next164 range proof, expression ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters; this slice admits an unsampled equality key using the current STAT4 bracket for a partial expression index',
        ];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $terms, array $neededColumns): array
    {
        $best = null;
        foreach (self::listValue($source['indexes'] ?? []) as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next171 indexes must be arrays');
            }
            $expression = self::stringValue($index, 'expression');
            $key = self::equalityKey($terms, $expression);
            $stat4 = self::stat4Samples(self::listValue($index['stat4Samples'] ?? []));
            $exactSample = $key === null ? null : self::exactSample($stat4, $key, (string) ($index['collation'] ?? 'BINARY'));
            $bracket = $key === null || $exactSample !== null ? [] : self::bracket($stat4, $key, (string) ($index['collation'] ?? 'BINARY'));
            $partial = self::listValue($index['partialPredicateTerms'] ?? []);
            $partialImplied = self::partialPredicateImplied($partial, $terms);
            $matchedRows = $key === null || $bracket === [] || !$partialImplied
                ? []
                : self::matchedRows($source, $terms, $expression, (string) ($index['collation'] ?? 'BINARY'));
            $covering = self::covers($index['coveringColumns'] ?? [], $neededColumns);
            $estimated = max(1, min(
                max(1, (int) ($bracket['right']['nlt'] ?? $bracket['left']['nlt'] ?? 1)),
                max(1, count($matchedRows)),
            ));
            $plan = [
                'usable' => $key !== null && $exactSample === null && $bracket !== [] && $partialImplied && $matchedRows !== [],
                'name' => self::stringValue($index, 'name'),
                'rootPage' => self::intValue($index, 'rootPage'),
                'expression' => $expression,
                'expressionColumn' => (string) ($index['expressionColumn'] ?? ''),
                'collation' => strtoupper((string) ($index['collation'] ?? 'BINARY')),
                'coveringColumns' => self::stringList($index['coveringColumns'] ?? []),
                'covering' => $covering,
                'partialPredicateTerms' => $partial,
                'partialPredicateImplied' => $partialImplied,
                'stat4Samples' => $stat4,
                'stat4Used' => $stat4 !== [],
                'exactStat4SamplePresent' => $exactSample !== null,
                'unsampledEqualityKey' => $exactSample === null ? $key : null,
                'stat4Bracket' => $bracket,
                'matchedRows' => $matchedRows,
                'estimatedRows' => $estimated,
                'estimatedCost' => $estimated + ($covering ? 0 : 12) + ($bracket === [] ? 40 : 0),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name') . ' USING STAT4 EXPRESSION PARTIAL UNSAMPLED EQUALITY BRACKET',
            ];
            if ($best === null || [
                $plan['usable'] ? 0 : 1,
                $plan['estimatedCost'],
                $plan['name'],
            ] < [
                $best['usable'] ? 0 : 1,
                $best['estimatedCost'],
                $best['name'],
            ]) {
                $best = $plan;
            }
        }

        return $best ?? ['usable' => false, 'matchedRows' => [], 'stat4Used' => false, 'partialPredicateImplied' => false];
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function equalityKey(array $terms, string $expression): mixed
    {
        $normalized = self::normalizeExpression($expression);
        foreach ($terms as $term) {
            if (self::normalizeExpression((string) ($term['left']['expression'] ?? '')) !== $normalized) {
                continue;
            }
            if (strtoupper((string) ($term['operator'] ?? '')) === '=') {
                return self::literal($term['right'] ?? null);
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return array<string,mixed>|null
     */
    private static function exactSample(array $samples, mixed $key, string $collation): ?array
    {
        foreach ($samples as $sample) {
            if (self::compare($sample['key'], $key, $collation) === 0) {
                return $sample;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return array{left:array<string,mixed>|null,right:array<string,mixed>|null,key:mixed,kind:string}|array{}
     */
    private static function bracket(array $samples, mixed $key, string $collation): array
    {
        $left = null;
        $right = null;
        foreach ($samples as $sample) {
            $comparison = self::compare($sample['key'], $key, $collation);
            if ($comparison < 0) {
                $left = $sample;
                continue;
            }
            if ($comparison > 0) {
                $right = $sample;
                break;
            }
        }
        if ($left === null && $right === null) {
            return [];
        }

        return [
            'kind' => $left === null ? 'before-first' : ($right === null ? 'after-last' : 'between-samples'),
            'key' => $key,
            'left' => $left,
            'right' => $right,
        ];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $source, array $terms, string $expression, string $collation): array
    {
        $rows = [];
        foreach (self::listValue($source['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next171 rows must be arrays');
            }
            $rowid = $row['rowid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next171 rowid must be a non-negative integer');
            }
            if (!self::rowSatisfiesTerms($row, $terms, $expression, $collation)) {
                continue;
            }
            $rows[] = [
                'rowid' => $rowid,
                'expressionKey' => self::expressionValue($row, $expression),
                'payload' => $row,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => self::compare($a['expressionKey'] ?? null, $b['expressionKey'] ?? null, $collation)
            ?: ((int) ($a['rowid'] ?? 0) <=> (int) ($b['rowid'] ?? 0)));

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfiesTerms(array $row, array $terms, string $expression, string $collation): bool
    {
        foreach ($terms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                return false;
            }
            $value = array_key_exists('expression', $left)
                ? self::expressionValue($row, (string) $left['expression'])
                : ($row[(string) ($left['column'] ?? '')] ?? null);
            if ($operator === '=' && self::compare($value, self::literal($term['right'] ?? null), $collation) !== 0) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
            if ($operator === 'IS NULL' && $value !== null) {
                return false;
            }
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $comparison = self::compare($value, self::literal($term['right'] ?? null), $collation);
                if (($operator === '>' && $comparison <= 0)
                    || ($operator === '>=' && $comparison < 0)
                    || ($operator === '<' && $comparison >= 0)
                    || ($operator === '<=' && $comparison > 0)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $partialTerms
     * @param list<array<string,mixed>> $terms
     */
    private static function partialPredicateImplied(array $partialTerms, array $terms): bool
    {
        foreach ($partialTerms as $partial) {
            if (!is_array($partial)) {
                return false;
            }
            $found = false;
            foreach ($terms as $term) {
                if (self::termImplies($term, $partial)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $term */
    private static function termImplies(array $term, array $partial): bool
    {
        if (self::leftKey($term['left'] ?? null) !== self::leftKey($partial['left'] ?? null)) {
            return false;
        }
        $operator = strtoupper((string) ($term['operator'] ?? ''));
        $partialOperator = strtoupper((string) ($partial['operator'] ?? ''));
        if ($partialOperator === 'IS NOT NULL') {
            return $operator !== 'IS NULL';
        }
        if ($operator === $partialOperator && self::literal($term['right'] ?? null) === self::literal($partial['right'] ?? null)) {
            return true;
        }
        if (in_array($operator, ['=', '>', '>=', '<', '<='], true) && in_array($partialOperator, ['>', '>=', '<', '<='], true)) {
            $comparison = self::compare(self::literal($term['right'] ?? null), self::literal($partial['right'] ?? null), 'BINARY');
            return match ($partialOperator) {
                '>' => in_array($operator, ['=', '>', '>='], true) && $comparison > 0,
                '>=' => in_array($operator, ['=', '>', '>='], true) && $comparison >= 0,
                '<' => in_array($operator, ['=', '<', '<='], true) && $comparison < 0,
                '<=' => in_array($operator, ['=', '<', '<='], true) && $comparison <= 0,
                default => false,
            };
        }

        return false;
    }

    /** @param mixed $left */
    private static function leftKey(mixed $left): string
    {
        if (!is_array($left)) {
            return '';
        }
        if (isset($left['expression'])) {
            return 'expr:' . self::normalizeExpression((string) $left['expression']);
        }

        return 'col:' . strtolower((string) ($left['column'] ?? ''));
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return list<array<string,mixed>>
     */
    private static function stat4Samples(array $samples): array
    {
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next171 STAT4 samples need key and rowid');
            }
            $out[] = [
                'key' => $sample['sample'][0],
                'rowid' => self::intValue(['rowid' => $sample['sample'][1]], 'rowid'),
                'neq' => self::firstStatInt($sample['neq'] ?? 1),
                'nlt' => self::firstStatInt($sample['nlt'] ?? 0),
                'ndlt' => self::firstStatInt($sample['ndlt'] ?? 0),
            ];
        }
        usort($out, static fn (array $a, array $b): int => self::compare($a['key'], $b['key'], 'BINARY') ?: ($a['rowid'] <=> $b['rowid']));

        return $out;
    }

    private static function firstStatInt(mixed $value): int
    {
        $first = is_string($value) ? strtok($value, ' ') : $value;
        if (!is_numeric($first)) {
            throw new \InvalidArgumentException('SQLite next171 STAT4 counters must be numeric');
        }

        return max(0, (int) $first);
    }

    /** @param array<string,mixed> $selected */
    private static function selectedSummary(array $selected, bool $ready): array
    {
        return [
            'next171Ready' => $ready,
            'name' => $selected['name'] ?? null,
            'rootPage' => $selected['rootPage'] ?? null,
            'expression' => $selected['expression'] ?? null,
            'expressionColumn' => $selected['expressionColumn'] ?? null,
            'collation' => $selected['collation'] ?? null,
            'covering' => $selected['covering'] ?? false,
            'partialPredicateImplied' => $selected['partialPredicateImplied'] ?? false,
            'stat4Used' => $selected['stat4Used'] ?? false,
            'exactStat4SamplePresent' => $selected['exactStat4SamplePresent'] ?? false,
            'unsampledEqualityKey' => $selected['unsampledEqualityKey'] ?? null,
            'stat4Bracket' => $selected['stat4Bracket'] ?? [],
            'matchedRowids' => array_column($selected['matchedRows'] ?? [], 'rowid'),
            'matchedExpressionKeys' => array_column($selected['matchedRows'] ?? [], 'expressionKey'),
            'estimatedRows' => $selected['estimatedRows'] ?? null,
            'estimatedCost' => $selected['estimatedCost'] ?? null,
            'detail' => $selected['detail'] ?? null,
        ];
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $plan */
    private static function summary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => $source['name'] ?? null,
            'schemaCookie' => self::intValue($source, 'schemaCookie'),
            'stat4Generation' => self::intValue($source, 'stat4Generation'),
            'sourceSignature' => $signature,
            'usable' => $plan['usable'] ?? false,
            'rootPage' => $plan['rootPage'] ?? null,
            'unsampledEqualityKey' => $plan['unsampledEqualityKey'] ?? null,
            'matchedRowids' => array_column($plan['matchedRows'] ?? [], 'rowid'),
            'stat4Bracket' => $plan['stat4Bracket'] ?? [],
        ];
    }

    /** @param array<string,mixed> $selected @return list<array<string,mixed>> */
    private static function cursorProgram(array $selected, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'FallbackFullScan', 'reason' => 'unsampled STAT4 equality bracket not usable']];
        }
        $bracket = $selected['stat4Bracket'] ?? [];

        return [
            ['opcode' => 'OpenRead', 'rootPage' => $selected['rootPage'] ?? null, 'index' => $selected['name'] ?? null],
            ['opcode' => 'FenceStat4Bracket', 'signature' => self::signature($bracket)],
            ['opcode' => 'SeekBracketLower', 'key' => $bracket['left']['key'] ?? null],
            ['opcode' => 'IdxBracketUpper', 'key' => $bracket['right']['key'] ?? null],
            ['opcode' => 'ProbeUnsampledEquality', 'key' => $selected['unsampledEqualityKey'] ?? null],
            ['opcode' => (($selected['covering'] ?? false) ? 'ColumnFromIndex' : 'DeferredSeek')],
            ['opcode' => 'ResultRow', 'rowids' => array_column($selected['matchedRows'] ?? [], 'rowid')],
            ['opcode' => 'Next'],
        ];
    }

    /**
     * @param mixed $columns
     * @param list<string> $needed
     */
    private static function covers(mixed $columns, array $needed): bool
    {
        $available = array_map('strtolower', self::stringList($columns));

        return array_diff(array_map('strtolower', $needed), $available) === [];
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    /** @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next171 value must be a list');
        }

        return $value;
    }

    /** @param list<array<string,mixed>> $terms */
    private static function validateTerms(array $terms): void
    {
        if ($terms === []) {
            throw new \InvalidArgumentException('SQLite next171 where terms cannot be empty');
        }
        foreach ($terms as $term) {
            if (!is_array($term['left'] ?? null) || !isset($term['operator'])) {
                throw new \InvalidArgumentException('SQLite next171 where terms need left and operator');
            }
        }
    }

    /** @param array<string,mixed> $array */
    private static function stringValue(array $array, string $key): string
    {
        $value = $array[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite next171 {$key} must be a non-empty string");
        }

        return $value;
    }

    /** @param array<string,mixed> $array */
    private static function intValue(array $array, string $key): int
    {
        $value = $array[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite next171 {$key} must be a non-negative integer");
        }

        return $value;
    }

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    /** @param array<string,mixed> $row */
    private static function expressionValue(array $row, string $expression): mixed
    {
        $normalized = self::normalizeExpression($expression);
        if ($normalized === 'lower(option_name)') {
            $value = $row['option_name'] ?? null;

            return $value === null ? null : strtolower((string) $value);
        }
        if ($normalized === 'substr(option_name,1,12)') {
            $value = $row['option_name'] ?? null;

            return $value === null ? null : substr((string) $value, 0, 12);
        }

        return $row[$expression] ?? null;
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null && $right === null) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        $a = (string) $left;
        $b = (string) $right;
        if (strtoupper($collation) === 'NOCASE') {
            $a = strtolower($a);
            $b = strtolower($b);
        }

        return $a <=> $b;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
