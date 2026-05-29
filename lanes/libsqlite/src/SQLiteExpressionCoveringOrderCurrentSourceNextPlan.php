<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteExpressionCoveringOrderCurrentSourceNextPlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{expression:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $predicate, array $orderBy, array $neededColumns): array
    {
        $prepared = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns);
        $current = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns);

        $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
        $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
        $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
        $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
        $preparedSignature = self::indexSignature($preparedSource);
        $currentSignature = self::indexSignature($currentSource);
        $stale = $preparedCookie !== $currentCookie
            || $preparedStat4 !== $currentStat4
            || $preparedSignature !== $currentSignature;
        $selected = $stale ? $current : $prepared;
        $coveringOrder = ($selected['covering'] ?? false) === true
            && ($selected['orderBySatisfied'] ?? false) === true
            && ($selected['rangeUsable'] ?? false) === true;
        $segments = self::segments($selected);

        return [
            'status' => $coveringOrder ? 'expression-covering-order-current-source-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => $preparedCookie !== $currentCookie,
            'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
            'indexSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedSource' => self::sourceSummary($preparedSource, $prepared, $preparedSignature),
            'currentSource' => self::sourceSummary($currentSource, $current, $currentSignature),
            'selectedPlan' => $selected,
            'expressionCoveringOrderPlan' => $coveringOrder,
            'tableLookupElided' => $coveringOrder,
            'tempSortElided' => $coveringOrder,
            'cursorTape' => [
                'source' => $stale ? 'current' : 'prepared',
                'indexName' => $selected['name'] ?? null,
                'rootPage' => $selected['rootPage'] ?? null,
                'expression' => $selected['expression'] ?? null,
                'expressionOpcode' => $selected['expressionOpcode'] ?? null,
                'seekOpcode' => self::seekOpcode($selected),
                'stopOpcode' => self::stopOpcode($selected),
                'nextOpcode' => self::nextOpcode($orderBy),
                'scanDirection' => self::scanDirection($orderBy),
                'rangeLower' => $selected['rangeLower'] ?? null,
                'rangeUpper' => $selected['rangeUpper'] ?? null,
                'rangeLowerExact' => ($selected['lowerInclusive'] ?? false) === true,
                'rangeUpperExact' => ($selected['upperInclusive'] ?? false) === true,
                'currentNextSegments' => $segments,
                'currentNextCount' => count($segments),
                'matchedExpressionKeys' => array_map(static fn (array $segment): mixed => $segment['currentKey'], $segments),
                'outputColumns' => self::outputColumns($neededColumns),
                'deferredSeekOpcode' => $coveringOrder ? null : 'DeferredSeek',
                'sorterOpen' => !$coveringOrder && $orderBy !== [],
                'tableLookupElided' => $coveringOrder,
                'tempSortElided' => $coveringOrder,
                'program' => self::program($coveringOrder, $selected, $orderBy, $neededColumns),
            ],
            'currentSourceFence' => [
                'schemaCookie' => $currentCookie,
                'stat4Generation' => $currentStat4,
                'indexSignature' => $currentSignature,
                'orderSignature' => self::orderSignature($orderBy),
            ],
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' EXPRESSION COVERING ORDER USING '
                . ($stale ? self::stringValue($currentSource, 'name') : self::stringValue($preparedSource, 'name'))
                . ' ' . (string) ($selected['detail'] ?? 'NO PLAN'),
            'dependencies' => [
                'SQLiteCreateIndex expression-column parsing',
                'sqlite-expression-covering-order-current-source-next103',
            ],
            'dependency_closure' => 'no new support component needed; next103 composes native expression-index parsing, STAT4 samples, and covering ORDER BY cursor diagnostics',
            'non_overlap' => 'avoids accepted SQL expression ORDER BY execution and next99 column-order cursor coverage by asserting lower(option_name) expression-index covering order current-source cursor materialization',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array{expression:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns): array
    {
        $indexes = self::listValue($source, 'indexes');
        $plans = [];
        foreach ($indexes as $index) {
            $sql = self::stringValue($index, 'sql');
            $expression = SQLiteCreateIndex::firstLowerExpression($sql);
            if ($expression === null) {
                continue;
            }
            $columns = SQLiteCreateIndex::columnsAfterFirstExpression($sql);
            $available = array_merge([$expression->columnName], array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns));
            $range = self::expressionRange($predicate, 'lower(' . strtolower($expression->columnName) . ')');
            $partialImplied = $expression->partialPredicate === null || self::partialPredicateImplied($expression->partialPredicate, $predicate);
            $orderSatisfied = self::orderSatisfied($orderBy, $expression);
            $covering = self::covers($available, $neededColumns);
            $samples = self::samples(self::listValue($index, 'stat4Samples'), $range);
            if (!$partialImplied || $range === null) {
                continue;
            }
            $cost = max(1, (int) ($index['estimatedRows'] ?? count($samples)) - ($orderSatisfied ? 8 : 0) - ($covering ? 64 : 0));
            $plans[] = [
                'status' => 'usable',
                'usable' => true,
                'name' => self::stringValue($index, 'name', self::indexName($sql)),
                'rootPage' => self::nonNegativeInt($index, 'rootPage'),
                'expression' => 'lower(' . $expression->columnName . ')',
                'expressionOpcode' => 'Function0 lower(1)',
                'expressionColumn' => $expression->columnName,
                'collation' => $expression->collation,
                'descending' => $expression->descending,
                'storedColumns' => array_values($available),
                'covering' => $covering,
                'orderBySatisfied' => $orderSatisfied,
                'rangeUsable' => $range !== null,
                'rangeLower' => $range['lower'] ?? null,
                'rangeUpper' => $range['upper'] ?? null,
                'lowerInclusive' => ($range['lowerInclusive'] ?? false) === true,
                'upperInclusive' => ($range['upperInclusive'] ?? false) === true,
                'partialPredicateImplied' => $partialImplied,
                'stat4Used' => $samples !== [],
                'stat4MatchedSamples' => count($samples),
                'stat4MatchedCurrentNext' => self::currentNext($samples),
                'estimatedRows' => max(1, array_sum(array_map(static fn (array $sample): int => $sample['neq'], $samples))),
                'estimatedCost' => $cost,
                'detail' => 'SEARCH ' . self::stringValue($index, 'name', self::indexName($sql)) . ' USING EXPRESSION RANGE COVERING ORDER',
            ];
        }

        usort($plans, static function (array $left, array $right): int {
            $leftCoveringOrder = ($left['covering'] ?? false) === true && ($left['orderBySatisfied'] ?? false) === true ? 0 : 1;
            $rightCoveringOrder = ($right['covering'] ?? false) === true && ($right['orderBySatisfied'] ?? false) === true ? 0 : 1;

            return [
                $leftCoveringOrder,
                $left['estimatedCost'],
                $left['name'],
            ] <=> [
                $rightCoveringOrder,
                $right['estimatedCost'],
                $right['name'],
            ];
        });

        return $plans[0] ?? [
            'status' => 'unusable',
            'usable' => false,
            'covering' => false,
            'orderBySatisfied' => false,
            'rangeUsable' => false,
            'detail' => 'SCAN TABLE; NO USABLE EXPRESSION COVERING ORDER',
        ];
    }

    /**
     * @return array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}|null
     */
    private static function expressionRange(array $predicate, string $expression): ?array
    {
        $range = ['lower' => null, 'upper' => null, 'lowerInclusive' => false, 'upperInclusive' => false];
        foreach (self::flattenAndTerms($predicate) as $term) {
            $left = self::predicateExpression($term['left'] ?? null);
            if ($left !== $expression) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator === '>=') {
                $range['lower'] = $term['right'] ?? null;
                $range['lowerInclusive'] = true;
            } elseif ($operator === '>') {
                $range['lower'] = $term['right'] ?? null;
                $range['lowerInclusive'] = false;
            } elseif ($operator === '<=') {
                $range['upper'] = $term['right'] ?? null;
                $range['upperInclusive'] = true;
            } elseif ($operator === '<') {
                $range['upper'] = $term['right'] ?? null;
                $range['upperInclusive'] = false;
            } elseif ($operator === 'BETWEEN') {
                $range['lower'] = $term['lower'] ?? null;
                $range['upper'] = $term['upper'] ?? null;
                $range['lowerInclusive'] = true;
                $range['upperInclusive'] = true;
            }
        }

        return $range['lower'] === null && $range['upper'] === null ? null : $range;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndTerms(array $predicate): array
    {
        if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
            return [$predicate];
        }
        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite expression covering order predicate needs AND terms');
        }
        $flattened = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite expression covering order predicates must be arrays');
            }
            array_push($flattened, ...self::flattenAndTerms($term));
        }

        return $flattened;
    }

    private static function predicateExpression(mixed $value): ?string
    {
        if (!is_array($value)) {
            return null;
        }
        if (isset($value['expression']) && is_string($value['expression'])) {
            return strtolower(preg_replace('/\s+/', '', $value['expression']));
        }
        if (($value['function'] ?? null) === 'lower' && isset($value['column']) && is_string($value['column'])) {
            return 'lower(' . strtolower($value['column']) . ')';
        }

        return null;
    }

    private static function partialPredicateImplied(SQLiteIndexPredicate $partial, array $predicate): bool
    {
        foreach (self::flattenAndTerms($predicate) as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || !isset($left['column']) || !is_string($left['column'])) {
                continue;
            }
            if (strcasecmp($left['column'], $partial->columnName) !== 0) {
                continue;
            }
            $operator = (string) ($term['operator'] ?? '');
            $matchesOperator = ($operator === '=' && $partial->operator === SQLiteIndexPredicate::EQUALS)
                || $operator === $partial->operator;
            if ($matchesOperator && ($term['right'] ?? null) === $partial->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{expression:string,direction?:string}> $orderBy
     */
    private static function orderSatisfied(array $orderBy, SQLiteIndexColumn $expression): bool
    {
        if ($orderBy === []) {
            return false;
        }
        $first = $orderBy[0];
        $term = strtolower(preg_replace('/\s+/', '', (string) ($first['expression'] ?? '')));
        $direction = strtoupper((string) ($first['direction'] ?? 'ASC'));
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException('SQLite expression covering order ORDER BY direction must be ASC or DESC');
        }

        return $term === 'lower(' . strtolower($expression->columnName) . ')'
            && $expression->descending === ($direction === 'DESC');
    }

    /**
     * @param list<string> $available
     * @param list<string> $needed
     */
    private static function covers(array $available, array $needed): bool
    {
        $set = [];
        foreach ($available as $column) {
            $set[strtolower($column)] = true;
        }
        foreach ($needed as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite expression covering order needs output column names');
            }
            if (!isset($set[strtolower($column)])) {
                return false;
            }
        }

        return $needed !== [];
    }

    /**
     * @param list<array<string,mixed>> $rawSamples
     * @param array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}|null $range
     * @return list<array{key:mixed,neq:int,nlt:int,ndlt:int}>
     */
    private static function samples(array $rawSamples, ?array $range): array
    {
        $samples = [];
        foreach ($rawSamples as $sample) {
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || $values === []) {
                throw new \InvalidArgumentException('SQLite expression covering order STAT4 samples need sample values');
            }
            $key = $values[0];
            if ($range !== null && !self::within($key, $range)) {
                continue;
            }
            $samples[] = [
                'key' => $key,
                'neq' => self::firstCounter($sample['neq'] ?? null),
                'nlt' => self::firstCounter($sample['nlt'] ?? null),
                'ndlt' => self::firstCounter($sample['ndlt'] ?? null),
            ];
        }
        usort($samples, static fn (array $left, array $right): int => self::compareValues($left['key'], $right['key']));

        return $samples;
    }

    private static function within(mixed $key, array $range): bool
    {
        if ($range['lower'] !== null) {
            $comparison = self::compareValues($key, $range['lower']);
            if ($comparison < 0 || ($comparison === 0 && !$range['lowerInclusive'])) {
                return false;
            }
        }
        if ($range['upper'] !== null) {
            $comparison = self::compareValues($key, $range['upper']);
            if ($comparison > 0 || ($comparison === 0 && !$range['upperInclusive'])) {
                return false;
            }
        }

        return true;
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function firstCounter(mixed $value): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value)) {
            $parts = preg_split('/\s+/', trim($value));
            $first = $parts[0] ?? null;
            if ($first !== null && ctype_digit($first)) {
                return (int) $first;
            }
        }
        if (is_array($value) && isset($value[0]) && is_int($value[0]) && $value[0] >= 0) {
            return $value[0];
        }

        throw new \InvalidArgumentException('SQLite expression covering order STAT4 counters must be non-negative');
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int}> $samples
     * @return list<array{current:array{key:mixed,neq:int,nlt:int,ndlt:int},next:array{key:mixed,neq:int,nlt:int,ndlt:int}|null}>
     */
    private static function currentNext(array $samples): array
    {
        $pairs = [];
        foreach ($samples as $offset => $sample) {
            $pairs[] = ['current' => $sample, 'next' => $samples[$offset + 1] ?? null];
        }

        return $pairs;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array{position:int,currentKey:mixed,nextKey:mixed,neq:int,nlt:int,ndlt:int,advance:string}>
     */
    private static function segments(array $plan): array
    {
        $pairs = $plan['stat4MatchedCurrentNext'] ?? [];
        if (!is_array($pairs) || !array_is_list($pairs)) {
            return [];
        }
        $segments = [];
        foreach ($pairs as $offset => $pair) {
            if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                continue;
            }
            $next = is_array($pair['next'] ?? null) ? $pair['next'] : null;
            $segments[] = [
                'position' => $offset,
                'currentKey' => $pair['current']['key'],
                'nextKey' => $next['key'] ?? null,
                'neq' => $pair['current']['neq'],
                'nlt' => $pair['current']['nlt'],
                'ndlt' => $pair['current']['ndlt'],
                'advance' => $next === null ? 'eof' : 'next',
            ];
        }

        return $segments;
    }

    private static function seekOpcode(array $plan): string
    {
        return ($plan['lowerInclusive'] ?? false) === true ? 'SeekGE' : 'SeekGT';
    }

    private static function stopOpcode(array $plan): string
    {
        return ($plan['upperInclusive'] ?? false) === true ? 'IdxGT' : 'IdxGE';
    }

    /**
     * @param list<array{expression:string,direction?:string}> $orderBy
     */
    private static function nextOpcode(array $orderBy): string
    {
        return self::scanDirection($orderBy) === 'descending' ? 'Prev' : 'Next';
    }

    /**
     * @param list<array{expression:string,direction?:string}> $orderBy
     */
    private static function scanDirection(array $orderBy): string
    {
        return strtoupper((string) ($orderBy[0]['direction'] ?? 'ASC')) === 'DESC' ? 'descending' : 'ascending';
    }

    /**
     * @param list<string> $neededColumns
     * @return list<array{column:string,opcode:string,source:string}>
     */
    private static function outputColumns(array $neededColumns): array
    {
        $columns = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite expression covering order needs output column names');
            }
            $columns[] = ['column' => $column, 'opcode' => 'Column', 'source' => 'index'];
        }

        return $columns;
    }

    /**
     * @param list<array{expression:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function program(bool $coveringOrder, array $plan, array $orderBy, array $neededColumns): array
    {
        $program = [
            ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null],
            ['opcode' => 'Function0', 'function' => 'lower', 'column' => $plan['expressionColumn'] ?? null],
            ['opcode' => self::seekOpcode($plan), 'expression' => $plan['expression'] ?? null],
            ['opcode' => self::stopOpcode($plan), 'expression' => $plan['expression'] ?? null],
        ];
        if (!$coveringOrder) {
            $program[] = ['opcode' => 'DeferredSeek', 'target' => 'table'];
        }
        if (!$coveringOrder && $orderBy !== []) {
            $program[] = ['opcode' => 'SorterOpen', 'orderBy' => $orderBy];
        }
        foreach ($neededColumns as $column) {
            $program[] = ['opcode' => 'Column', 'source' => $coveringOrder ? 'index' : 'table', 'column' => $column];
        }
        $program[] = ['opcode' => self::nextOpcode($orderBy), 'target' => 'index'];

        return $program;
    }

    private static function orderSignature(array $orderBy): string
    {
        $parts = [];
        foreach ($orderBy as $term) {
            $expression = $term['expression'] ?? null;
            if (!is_string($expression) || $expression === '') {
                throw new \InvalidArgumentException('SQLite expression covering order needs ORDER BY expression terms');
            }
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite expression covering order ORDER BY direction must be ASC or DESC');
            }
            $parts[] = strtolower(preg_replace('/\s+/', '', $expression)) . ' ' . $direction;
        }

        return implode(',', $parts);
    }

    private static function sourceSummary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => self::stringValue($source, 'name'),
            'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
            'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
            'indexSignature' => $signature,
            'status' => $plan['status'] ?? 'unusable',
            'selectedIndex' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'expression' => $plan['expression'] ?? null,
            'covering' => (bool) ($plan['covering'] ?? false),
            'orderBySatisfied' => (bool) ($plan['orderBySatisfied'] ?? false),
            'stat4MatchedSamples' => $plan['stat4MatchedSamples'] ?? 0,
            'estimatedRows' => $plan['estimatedRows'] ?? 0,
            'estimatedCost' => $plan['estimatedCost'] ?? 0,
        ];
    }

    private static function indexSignature(array $source): string
    {
        $parts = [];
        foreach (self::listValue($source, 'indexes') as $index) {
            $parts[] = self::stringValue($index, 'name', self::indexName(self::stringValue($index, 'sql')))
                . '|' . (string) self::nonNegativeInt($index, 'rootPage')
                . '|' . preg_replace('/\s+/', ' ', trim(self::stringValue($index, 'sql')))
                . '|' . hash('sha256', serialize($index['stat4Samples'] ?? []));
        }
        sort($parts, SORT_STRING);

        return hash('sha256', implode("\n", $parts));
    }

    private static function indexName(string $sql): string
    {
        if (preg_match('/CREATE\s+INDEX\s+([^\s(]+)/i', $sql, $matches) === 1) {
            return trim($matches[1], '"`[]');
        }

        return 'unknown-expression-index';
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function stringValue(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite expression covering order needs {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function nonNegativeInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite expression covering order needs non-negative integer {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     * @return list<array<string,mixed>>
     */
    private static function listValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite expression covering order needs list {$key}");
        }

        return $value;
    }
}
