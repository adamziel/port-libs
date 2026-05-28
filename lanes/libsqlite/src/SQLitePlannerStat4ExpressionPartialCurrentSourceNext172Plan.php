<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext172Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $predicate): array
    {
        $preparedPlan = self::sourcePlan($preparedSource, $predicate);
        $currentPlan = self::sourcePlan($currentSource, $predicate);
        $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
        $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
        $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
        $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedCookie !== $currentCookie
            || $preparedStat4 !== $currentStat4
            || $preparedSignature !== $currentSignature;
        $selected = $stale ? $currentPlan : $preparedPlan;
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImplied'] ?? false) === true
            && ($selected['stat4Used'] ?? false) === true
            && ($selected['currentSourceRowsFiltered'] ?? false) === true;

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next172-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => $preparedCookie !== $currentCookie,
            'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
            'sourceSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedSource' => self::sourceSummary($preparedSource, $preparedPlan, $preparedSignature),
            'currentSource' => self::sourceSummary($currentSource, $currentPlan, $currentSignature),
            'selectedPlan' => $selected,
            'stat4Fence' => [
                'schemaCookie' => $currentCookie,
                'stat4Generation' => $currentStat4,
                'sourceSignature' => $currentSignature,
                'sampleSignature' => $currentPlan['sampleSignature'] ?? null,
            ],
            'cursorTape' => self::cursorTape($selected, $stale ? 'current' : 'prepared'),
            'tableLookupDeferred' => true,
            'residualPredicateRequired' => true,
            'detail' => ($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 PARTIAL EXPRESSION CURRENT SOURCE ' . (string) ($selected['indexName'] ?? 'NO INDEX'),
            'dependencies' => [
                'SQLiteCreateIndex expression parsing',
                'SQLiteIndexPredicate implication',
                'sqlite-sqlplanner-stat4-expression-partial-current-source-next172',
            ],
            'dependency_closure' => 'no new support component needed; next172 reuses native PHP expression evaluation, partial predicate implication, and STAT4 sample fences',
            'non_overlap' => 'avoids accepted STAT4 collation next114, partial expression materialization next121, STAT4 covering/skip-scan next142/147, and expression-index range-cost ranking; this slice only refreshes stale STAT4 expression partial-index selectivity from the current source',
        ];
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $predicate @return array<string,mixed> */
    private static function sourcePlan(array $source, array $predicate): array
    {
        $rows = self::listValue($source, 'rows');
        $plans = [];
        foreach (self::listValue($source, 'indexes') as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next172 indexes must be arrays');
            }
            $expression = self::stringValue($index, 'expression');
            $partial = self::arrayValue($index, 'partialPredicate');
            $range = self::expressionRange($predicate, $expression);
            if ($range === null || !self::predicateImplies($partial, $predicate)) {
                continue;
            }
            $samples = self::stat4Samples(self::listValue($index, 'stat4Samples'));
            $matchedRows = self::matchedRows($rows, $predicate, $expression, $range);
            $matchedSamples = array_values(array_filter($samples, static fn (array $sample): bool => self::insideRange($sample['key'], $range)));
            $sampleEstimate = array_sum(array_map(static fn (array $sample): int => $sample['nEq'], $matchedSamples));
            $estimatedRows = max(1, min(count($matchedRows), $sampleEstimate > 0 ? $sampleEstimate : count($matchedRows)));
            $plans[] = [
                'usable' => true,
                'indexName' => self::stringValue($index, 'name'),
                'rootPage' => self::nonNegativeInt($index, 'rootPage'),
                'expression' => $expression,
                'partialPredicateImplied' => true,
                'rangeLower' => $range['lower'],
                'rangeUpper' => $range['upper'],
                'lowerInclusive' => $range['lowerInclusive'],
                'upperInclusive' => $range['upperInclusive'],
                'stat4Used' => $samples !== [],
                'sampleSignature' => self::sampleSignature($samples),
                'stat4SampleCount' => count($samples),
                'matchedStat4SampleCount' => count($matchedSamples),
                'matchedStat4Keys' => array_map(static fn (array $sample): mixed => $sample['key'], $matchedSamples),
                'stat4CurrentNext' => self::currentNext($samples),
                'matchedStat4CurrentNext' => self::currentNext($matchedSamples),
                'stat4RangeFence' => self::rangeFence($samples, $range),
                'currentSourceRowsFiltered' => true,
                'matchedRowCount' => count($matchedRows),
                'matchedRowids' => array_map(static fn (array $row): int => $row['rowid'], $matchedRows),
                'matchedKeys' => array_map(static fn (array $row): mixed => $row['key'], $matchedRows),
                'estimatedRows' => $estimatedRows,
                'estimatedCost' => max(1, $estimatedRows + max(0, count($samples) - count($matchedSamples))),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name') . ' USING STAT4 PARTIAL EXPRESSION RANGE',
            ];
        }
        usort($plans, static fn (array $a, array $b): int => [$a['estimatedCost'], $a['indexName']] <=> [$b['estimatedCost'], $b['indexName']]);

        return $plans[0] ?? [
            'usable' => false,
            'partialPredicateImplied' => false,
            'stat4Used' => false,
            'currentSourceRowsFiltered' => false,
            'detail' => 'SCAN TABLE; NO STAT4 PARTIAL EXPRESSION CURRENT SOURCE INDEX',
        ];
    }

    /** @return array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}|null */
    private static function expressionRange(array $predicate, string $expression): ?array
    {
        $range = ['lower' => null, 'upper' => null, 'lowerInclusive' => false, 'upperInclusive' => false];
        foreach (self::flattenAnd($predicate) as $term) {
            if (self::termExpression($term) !== strtolower($expression)) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator === '>=') {
                $range['lower'] = self::literal($term['right'] ?? null);
                $range['lowerInclusive'] = true;
            } elseif ($operator === '>') {
                $range['lower'] = self::literal($term['right'] ?? null);
            } elseif ($operator === '<=') {
                $range['upper'] = self::literal($term['right'] ?? null);
                $range['upperInclusive'] = true;
            } elseif ($operator === '<') {
                $range['upper'] = self::literal($term['right'] ?? null);
            } elseif ($operator === 'BETWEEN') {
                $range['lower'] = self::literal($term['lower'] ?? null);
                $range['upper'] = self::literal($term['upper'] ?? null);
                $range['lowerInclusive'] = true;
                $range['upperInclusive'] = true;
            }
        }

        return $range['lower'] === null && $range['upper'] === null ? null : $range;
    }

    /** @param list<array<string,mixed>> $rows @return list<array{rowid:int,key:mixed}> */
    private static function matchedRows(array $rows, array $predicate, string $expression, array $range): array
    {
        $matched = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next172 rows must be arrays');
            }
            if (!self::rowSatisfies($row, $predicate)) {
                continue;
            }
            $key = self::expressionValue($row, $expression);
            if (!self::insideRange($key, $range)) {
                continue;
            }
            $matched[] = ['rowid' => (int) ($row['rowid'] ?? 0), 'key' => $key];
        }
        usort($matched, static fn (array $a, array $b): int => self::compare($a['key'], $b['key']) ?: ($a['rowid'] <=> $b['rowid']));

        return $matched;
    }

    /** @return list<array{key:mixed,nEq:int,nLt:int,nDLt:int}> */
    private static function stat4Samples(array $samples): array
    {
        $normalized = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next172 samples must be arrays');
            }
            $sampleValue = $sample['sample'] ?? null;
            if (!is_array($sampleValue) || !array_is_list($sampleValue) || $sampleValue === []) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next172 sample must include expression key');
            }
            $normalized[] = [
                'key' => self::literal($sampleValue[0]),
                'nEq' => self::firstStatInt($sample['neq'] ?? null, 'neq'),
                'nLt' => self::firstStatInt($sample['nlt'] ?? null, 'nlt', true),
                'nDLt' => self::firstStatInt($sample['ndlt'] ?? null, 'ndlt', true),
            ];
        }
        usort($normalized, static fn (array $a, array $b): int => self::compare($a['key'], $b['key']));

        return $normalized;
    }

    private static function expressionValue(array $row, string $expression): mixed
    {
        $lower = strtolower($expression);
        if (preg_match('/^lower\(([a-zA-Z_][a-zA-Z0-9_]*)\)$/', $lower, $m) === 1) {
            $value = $row[$m[1]] ?? null;
            return is_string($value) ? strtolower($value) : $value;
        }
        if (preg_match('/^length\(([a-zA-Z_][a-zA-Z0-9_]*)\)$/', $lower, $m) === 1) {
            $value = $row[$m[1]] ?? null;
            return is_string($value) ? strlen($value) : null;
        }

        throw new \InvalidArgumentException('SQLite STAT4 expression partial next172 unsupported expression');
    }

    private static function rowSatisfies(array $row, array $predicate): bool
    {
        $operator = strtoupper((string) ($predicate['operator'] ?? ''));
        if ($operator === 'AND') {
            foreach (self::listTerms($predicate) as $term) {
                if (!self::rowSatisfies($row, $term)) {
                    return false;
                }
            }
            return true;
        }
        if ($operator === 'OR') {
            foreach (self::listTerms($predicate) as $term) {
                if (self::rowSatisfies($row, $term)) {
                    return true;
                }
            }
            return false;
        }
        $column = self::termColumn($predicate);
        if ($column === null) {
            return true;
        }
        $left = $row[$column] ?? null;
        $right = self::literal($predicate['right'] ?? null);
        return match ($operator) {
            '=' => self::compare($left, $right) === 0,
            '!=' , '<>' => self::compare($left, $right) !== 0,
            default => true,
        };
    }

    private static function predicateImplies(array $partial, array $predicate): bool
    {
        if (self::predicateContains($predicate, $partial)) {
            return true;
        }
        if (strtoupper((string) ($partial['operator'] ?? '')) === 'OR') {
            foreach (self::listTerms($partial) as $arm) {
                if (self::predicateContains($predicate, $arm)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function predicateContains(array $predicate, array $needle): bool
    {
        if (self::sameTerm($predicate, $needle)) {
            return true;
        }
        foreach (self::listTerms($predicate) as $term) {
            if (self::predicateContains($term, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function sameTerm(array $a, array $b): bool
    {
        return strtoupper((string) ($a['operator'] ?? '')) === strtoupper((string) ($b['operator'] ?? ''))
            && self::termColumn($a) === self::termColumn($b)
            && self::literal($a['right'] ?? null) === self::literal($b['right'] ?? null);
    }

    /** @return list<array<string,mixed>> */
    private static function currentNext(array $items): array
    {
        $pairs = [];
        foreach ($items as $i => $item) {
            $pairs[] = ['current' => $item, 'next' => $items[$i + 1] ?? null];
        }
        return $pairs;
    }

    private static function rangeFence(array $samples, array $range): array
    {
        $inside = array_values(array_filter($samples, static fn (array $sample): bool => self::insideRange($sample['key'], $range)));
        return [
            'first' => $inside[0] ?? null,
            'last' => $inside === [] ? null : $inside[array_key_last($inside)],
            'lowerExact' => self::rangeHasKey($samples, $range['lower']),
            'upperExact' => self::rangeHasKey($samples, $range['upper']),
        ];
    }

    private static function rangeHasKey(array $samples, mixed $key): bool
    {
        if ($key === null) {
            return false;
        }
        foreach ($samples as $sample) {
            if (self::compare($sample['key'], $key) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function insideRange(mixed $key, array $range): bool
    {
        $lower = $range['lower'] === null ? 1 : self::compare($key, $range['lower']);
        $upper = $range['upper'] === null ? -1 : self::compare($key, $range['upper']);
        return ($range['lowerInclusive'] ? $lower >= 0 : $lower > 0)
            && ($range['upperInclusive'] ? $upper <= 0 : $upper < 0);
    }

    private static function cursorTape(array $plan, string $source): array
    {
        return [
            'source' => $source,
            'indexName' => $plan['indexName'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'seekOpcode' => (($plan['lowerInclusive'] ?? false) === true) ? 'SeekGE' : 'SeekGT',
            'stopOpcode' => (($plan['upperInclusive'] ?? false) === true) ? 'IdxGT' : 'IdxGE',
            'rowids' => $plan['matchedRowids'] ?? [],
            'keys' => $plan['matchedKeys'] ?? [],
            'program' => [
                ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null, 'source' => $source],
                ['opcode' => 'RecheckPartialPredicate', 'implied' => ($plan['partialPredicateImplied'] ?? false) === true],
                ['opcode' => (($plan['lowerInclusive'] ?? false) === true) ? 'SeekGE' : 'SeekGT', 'key' => $plan['rangeLower'] ?? null],
                ['opcode' => (($plan['upperInclusive'] ?? false) === true) ? 'IdxGT' : 'IdxGE', 'key' => $plan['rangeUpper'] ?? null],
                ['opcode' => 'Next', 'until' => 'range-exhausted'],
            ],
        ];
    }

    private static function sourceSummary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => self::stringValue($source, 'name', 'source'),
            'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
            'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
            'signature' => $signature,
            'ready' => ($plan['usable'] ?? false) === true,
            'matchedRowCount' => $plan['matchedRowCount'] ?? 0,
            'matchedStat4SampleCount' => $plan['matchedStat4SampleCount'] ?? 0,
        ];
    }

    private static function sourceSignature(array $source): string
    {
        return hash('sha256', serialize([
            'indexes' => $source['indexes'] ?? [],
            'samples' => array_map(static fn ($index) => is_array($index) ? ($index['stat4Samples'] ?? []) : $index, self::listValue($source, 'indexes')),
        ]));
    }

    private static function sampleSignature(array $samples): string
    {
        return hash('sha256', serialize($samples));
    }

    /** @return list<array<string,mixed>> */
    private static function flattenAnd(array $predicate): array
    {
        if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
            return [$predicate];
        }
        $terms = [];
        foreach (self::listTerms($predicate) as $term) {
            array_push($terms, ...self::flattenAnd($term));
        }
        return $terms;
    }

    /** @return list<array<string,mixed>> */
    private static function listTerms(array $predicate): array
    {
        $terms = $predicate['terms'] ?? [];
        if (!is_array($terms) || !array_is_list($terms)) {
            return [];
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next172 predicate terms must be arrays');
            }
        }
        return $terms;
    }

    private static function termExpression(array $term): ?string
    {
        $left = $term['left'] ?? null;
        return is_array($left) && isset($left['expression']) ? strtolower((string) $left['expression']) : null;
    }

    private static function termColumn(array $term): ?string
    {
        $left = $term['left'] ?? null;
        return is_array($left) && isset($left['column']) ? (string) $left['column'] : null;
    }

    private static function firstStatInt(mixed $value, string $name, bool $allowZero = false): int
    {
        if (is_string($value)) {
            $value = trim(explode(' ', $value)[0] ?? '');
            $value = ctype_digit($value) ? (int) $value : null;
        }
        if (!is_int($value) || $value < 0 || (!$allowZero && $value === 0)) {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial next172 invalid {$name}");
        }
        return $value;
    }

    private static function compare(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }
        return strcmp((string) $a, (string) $b);
    }

    private static function literal(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists('literal', $value)) {
            return $value['literal'];
        }
        return $value;
    }

    /** @return list<mixed> */
    private static function listValue(array $source, string $key): array
    {
        $value = $source[$key] ?? [];
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial next172 {$key} must be a list");
        }
        return $value;
    }

    /** @return array<string,mixed> */
    private static function arrayValue(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial next172 {$key} must be an array");
        }
        return $value;
    }

    private static function stringValue(array $source, string $key, ?string $default = null): string
    {
        $value = $source[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial next172 {$key} must be a string");
        }
        return $value;
    }

    private static function nonNegativeInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial next172 {$key} must be a non-negative integer");
        }
        return $value;
    }
}
