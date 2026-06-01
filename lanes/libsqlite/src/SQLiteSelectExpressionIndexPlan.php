<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectExpressionIndexPlan
{
    /**
     * @param list<array{sql:string,rootPage?:int,name?:string}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @return null|array<string,mixed>
     */
    public static function choose(array $indexDefinitions, array $predicate): ?array
    {
        $plans = self::usablePlans($indexDefinitions, $predicate);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>,coveringExpressions?:list<array<string,string>>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return null|array<string,mixed>
     */
    public static function chooseLowestCost(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = [], array $neededExpressions = []): ?array
    {
        $plans = self::usablePlans($indexDefinitions, $predicate, $orderBy, $neededColumns, $neededExpressions);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>,coveringExpressions?:list<array<string,string>>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = [], array $neededExpressions = []): array
    {
        return self::usablePlans($indexDefinitions, $predicate, $orderBy, $neededColumns, $neededExpressions);
    }

    /**
     * Choose an additive bounded range plan without changing the legacy
     * single-term expression-index ranking path.
     *
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>,coveringExpressions?:list<array<string,string>>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return null|array<string,mixed>
     */
    public static function chooseBoundedRangeCost(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = [], array $neededExpressions = []): ?array
    {
        $plans = self::boundedRangePlans($indexDefinitions, $predicate, $orderBy, $neededColumns, $neededExpressions);

        return $plans[0] ?? null;
    }

    /**
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>,coveringExpressions?:list<array<string,string>>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return list<array<string,mixed>>
     */
    public static function boundedRangePlans(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = [], array $neededExpressions = []): array
    {
        $terms = self::flattenAndTerms($predicate);
        $constraints = self::boundedRangeConstraints($terms);
        $plans = [];

        foreach ($constraints as $constraint) {
            foreach ($indexDefinitions as $index) {
                $sql = $index['sql'] ?? null;
                if (!is_string($sql) || $sql === '') {
                    throw new \InvalidArgumentException('SQLite SELECT expression-index bounded range planner needs index SQL text');
                }

                $expression = self::expressionForType($sql, $constraint['type']);
                if (
                    $expression === null
                    || strcasecmp($expression->columnName, $constraint['column']) !== 0
                    || (
                        $expression instanceof SQLiteJsonExtractIndexExpression
                        && (
                            (($constraint['path'] ?? null) !== $expression->path)
                            || !self::jsonExpressionKindMatches($constraint['type'], $expression->functionName)
                        )
                    )
                ) {
                    continue;
                }
                if (!self::constraintCollationMatchesExpression($constraint, $expression)) {
                    continue;
                }
                $matchedConstraint = self::constraintWithExpressionCollation($constraint, $expression);
                if ($expression->partial && !self::constraintImpliesPartialPredicate($expression->partialPredicate, $matchedConstraint, $terms)) {
                    continue;
                }
                if (!self::constraintCompatibleWithType($matchedConstraint, $expression->collation)) {
                    continue;
                }

                $estimated = self::estimatedRows($index, $matchedConstraint);
                $trailingColumns = SQLiteCreateIndex::columnsAfterFirstExpression($sql);
                $orderCompatible = self::orderCompatible($expression, $matchedConstraint['type'], $trailingColumns, $matchedConstraint, $orderBy);
                $covering = self::covering($index, $expression, $matchedConstraint['type'], $neededColumns, $neededExpressions, $trailingColumns);

                $plans[] = [
                    'usable' => true,
                    'rootPage' => $index['rootPage'] ?? null,
                    'name' => $index['name'] ?? null,
                    'type' => $matchedConstraint['type'],
                    'column' => $expression->columnName,
                    'operator' => 'range-bounded',
                    'values' => $matchedConstraint['values'],
                    'path' => $matchedConstraint['path'] ?? null,
                    'collation' => $expression->collation,
                    'queryCollation' => $matchedConstraint['collation'],
                    'collationMatched' => strcasecmp($matchedConstraint['collation'], $expression->collation) === 0,
                    'descending' => $expression->descending,
                    'partial' => $expression->partial,
                    'residualPredicateRequired' => true,
                    'estimatedRows' => $estimated['rows'],
                    'estimatedCost' => self::estimatedCost($constraint, $estimated['rows'], $expression->partial, $orderCompatible, $covering),
                    'stat4Used' => $estimated['stat4Used'],
                    'stat4Estimate' => $estimated['stat4Estimate'],
                    'stat4MatchedSamples' => $estimated['stat4MatchedSamples'],
                    'stat4CurrentNext' => $estimated['stat4CurrentNext'],
                    'stat4MatchedCurrentNext' => $estimated['stat4MatchedCurrentNext'],
                    'stat4RangeCurrentNext' => $estimated['stat4RangeCurrentNext'],
                    'orderBySatisfied' => $orderCompatible,
                    'covering' => $covering,
                    'coveringExpressions' => self::coveredExpressionNames($index, $expression, $matchedConstraint['type'], $neededExpressions),
                    'trailingColumns' => array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $trailingColumns),
                    'legacyPlansUnaffected' => true,
                ];
            }
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) ($left['name'] ?? '')]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) ($right['name'] ?? '')];
        });

        return $plans;
    }

    /**
     * Preview the current-source covering row stream selected by STAT4
     * expression-index evidence. This is intentionally additive: it does not
     * alter the existing planner ranking path.
     *
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>,coveringExpressions?:list<array<string,string>>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return null|array<string,mixed>
     */
    public static function stat4ExpressionCoveringCurrentSourcePlan(array $indexDefinitions, array $predicate, array $currentRows, array $orderBy = [], array $neededColumns = [], array $neededExpressions = []): ?array
    {
        $plan = self::chooseLowestCost($indexDefinitions, $predicate, $orderBy, $neededColumns, $neededExpressions);
        if ($plan === null || !$plan['stat4Used'] || !$plan['covering']) {
            return null;
        }

        $matchedKeys = [];
        foreach ($plan['stat4MatchedCurrentNext'] as $pair) {
            if (!is_array($pair) || !isset($pair['current']) || !is_array($pair['current']) || !array_key_exists('key', $pair['current'])) {
                continue;
            }
            $matchedKeys[self::stat4KeySignature($pair['current']['key'])] = $pair['current']['key'];
        }
        if ($matchedKeys === []) {
            return null;
        }

        $rows = [];
        foreach ($currentRows as $offset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index current-source rows must be arrays');
            }
            if (!self::currentSourceRowSatisfiesPredicate($predicate, $row)) {
                continue;
            }

            $key = self::currentSourceExpressionValue($plan, $row);
            if (!array_key_exists(self::stat4KeySignature($key), $matchedKeys)) {
                continue;
            }

            $rows[] = [
                'sourceOffset' => $offset,
                'rowid' => $row['rowid'] ?? $row['_rowid_'] ?? null,
                'key' => $key,
                'covering' => self::currentSourceCoveringPayload($row, $neededColumns),
                'coveringExpressions' => self::currentSourceCoveringExpressionPayload($plan, $row, $neededExpressions),
            ];
        }

        usort($rows, static function (array $left, array $right) use ($plan): int {
            $comparison = self::compareStat4Keys($left['key'], $right['key']);
            if ((bool) ($plan['descending'] ?? false)) {
                $comparison *= -1;
            }
            if ($comparison !== 0) {
                return $comparison;
            }

            return ((int) ($left['rowid'] ?? $left['sourceOffset'])) <=> ((int) ($right['rowid'] ?? $right['sourceOffset']));
        });

        $currentNextRows = [];
        foreach ($rows as $offset => $row) {
            $currentNextRows[] = [
                'current' => $row,
                'next' => $rows[$offset + 1] ?? null,
            ];
        }

        return [
            'usable' => true,
            'name' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'type' => $plan['type'],
            'column' => $plan['column'],
            'path' => $plan['path'] ?? null,
            'operator' => $plan['operator'],
            'values' => $plan['values'],
            'estimatedRows' => $plan['estimatedRows'],
            'stat4Estimate' => $plan['stat4Estimate'],
            'stat4MatchedSamples' => $plan['stat4MatchedSamples'],
            'stat4MatchedCurrentNext' => $plan['stat4MatchedCurrentNext'],
            'orderBySatisfied' => $plan['orderBySatisfied'],
            'covering' => true,
            'coveredRowCount' => count($rows),
            'currentNextRows' => $currentNextRows,
            'dependencies' => ['sqlite-stat4-expression-covering-current-source-next109'],
        ];
    }

    /**
     * Build a bounded SQLite OR-clause plan where every OR arm is independently
     * usable through a partial covering expression index.
     *
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>,coveringExpressions?:list<array<string,string>>,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return null|array<string,mixed>
     */
    public static function partialCoveringOrPlan(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = [], array $neededExpressions = []): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT expression-index OR predicate'));
        if ($operator !== 'OR') {
            throw new \InvalidArgumentException('SQLite SELECT expression-index OR planner needs an OR predicate');
        }

        $arms = $predicate['terms'] ?? null;
        if (!is_array($arms) || !array_is_list($arms) || $arms === []) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index OR planner needs a non-empty term list');
        }

        $armPlans = [];
        $indexNames = [];
        $estimatedRows = 0;
        $estimatedCost = 0;
        $orderBySatisfied = true;
        $stat4Used = false;
        $stat4CurrentNext = [];
        $residualPredicateRequired = false;
        foreach ($arms as $position => $arm) {
            if (!is_array($arm)) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index OR planner terms must be predicates');
            }

            $plan = self::chooseLowestCost($indexDefinitions, $arm, $orderBy, $neededColumns, $neededExpressions);
            if ($plan === null || !$plan['partial'] || !$plan['covering']) {
                return null;
            }

            $estimatedRows += (int) $plan['estimatedRows'];
            $estimatedCost += (int) $plan['estimatedCost'];
            $orderBySatisfied = $orderBySatisfied && (bool) $plan['orderBySatisfied'];
            $stat4Used = $stat4Used || (bool) $plan['stat4Used'];
            $residualPredicateRequired = $residualPredicateRequired || (bool) $plan['residualPredicateRequired'];
            $stat4CurrentNext[] = [
                'position' => $position,
                'name' => $plan['name'] ?? null,
                'operator' => $plan['operator'],
                'values' => $plan['values'],
                'currentNext' => $plan['stat4CurrentNext'],
            ];
            if (is_string($plan['name'] ?? null)) {
                $indexNames[$plan['name']] = true;
            }

            $armPlans[] = [
                'position' => $position,
                'name' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'operator' => $plan['operator'],
                'values' => $plan['values'],
                'estimatedRows' => $plan['estimatedRows'],
                'estimatedCost' => $plan['estimatedCost'],
                'orderBySatisfied' => $plan['orderBySatisfied'],
                'trailingColumns' => $plan['trailingColumns'],
                'coveringExpressions' => $plan['coveringExpressions'] ?? [],
                'stat4Used' => $plan['stat4Used'],
                'stat4Estimate' => $plan['stat4Estimate'],
                'stat4CurrentNext' => $plan['stat4CurrentNext'],
                'residualPredicateRequired' => $plan['residualPredicateRequired'],
                'type' => $plan['type'],
                'column' => $plan['column'],
                'path' => $plan['path'] ?? null,
            ];
        }

        $uniqueIndexNames = array_keys($indexNames);
        sort($uniqueIndexNames, SORT_STRING);
        $sameIndexPointRewrite = self::sameIndexPointOrRewrite($armPlans);

        return [
            'usable' => true,
            'type' => 'or-partial-covering',
            'strategy' => $sameIndexPointRewrite === null ? 'or-rowid-union' : 'or-to-in-partial-expression',
            'partial' => true,
            'covering' => true,
            'arms' => $armPlans,
            'armCount' => count($armPlans),
            'indexNames' => $uniqueIndexNames,
            'usesSingleIndex' => count($uniqueIndexNames) === 1,
            'estimatedRows' => $sameIndexPointRewrite['estimatedRows'] ?? $estimatedRows,
            'estimatedCost' => $estimatedCost + max(0, count($armPlans) - 1) * 4,
            'orderBySatisfied' => $orderBySatisfied,
            'dedupeRowidsRequired' => count($armPlans) > 1,
            'stat4Used' => $stat4Used,
            'stat4CurrentNext' => $stat4CurrentNext,
            'residualPredicateRequired' => $residualPredicateRequired,
            'inRewrite' => $sameIndexPointRewrite,
        ];
    }

    /**
     * @param list<array<string,mixed>> $armPlans
     * @return null|array{index:string|null,type:string,column:string,path:string|null,values:list<mixed>,estimatedRows:int}
     */
    private static function sameIndexPointOrRewrite(array $armPlans): ?array
    {
        if (count($armPlans) < 2) {
            return null;
        }

        $first = $armPlans[0];
        if (($first['operator'] ?? null) !== 'point') {
            return null;
        }

        $index = $first['name'] ?? null;
        $type = $first['type'] ?? null;
        $column = $first['column'] ?? null;
        $path = $first['path'] ?? null;
        if (!is_string($type) || !is_string($column)) {
            return null;
        }

        $values = [];
        $estimatedRowsByValue = [];
        foreach ($armPlans as $arm) {
            if (
                ($arm['operator'] ?? null) !== 'point'
                || ($arm['name'] ?? null) !== $index
                || ($arm['type'] ?? null) !== $type
                || ($arm['column'] ?? null) !== $column
                || ($arm['path'] ?? null) !== $path
            ) {
                return null;
            }

            $value = $arm['values'] ?? null;
            $key = serialize($value);
            if (!array_key_exists($key, $estimatedRowsByValue)) {
                $values[] = $value;
                $estimatedRowsByValue[$key] = max(1, (int) ($arm['estimatedRows'] ?? 1));
                continue;
            }

            $estimatedRowsByValue[$key] = min(
                $estimatedRowsByValue[$key],
                max(1, (int) ($arm['estimatedRows'] ?? 1))
            );
        }

        return [
            'index' => is_string($index) ? $index : null,
            'type' => $type,
            'column' => $column,
            'path' => is_string($path) ? $path : null,
            'values' => $values,
            'estimatedRows' => array_sum($estimatedRowsByValue),
        ];
    }

    /**
     * @param list<array{sql:string,rootPage?:int,name?:string,estimatedRows?:int,coveringColumns?:list<string>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return list<array<string,mixed>>
     */
    private static function usablePlans(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = [], array $neededExpressions = []): array
    {
        $terms = self::flattenAndTerms($predicate);
        $plans = [];
        foreach ($terms as $term) {
            $constraint = self::constraintFromPredicate($term);
            if ($constraint === null) {
                continue;
            }

            foreach ($indexDefinitions as $index) {
                $sql = $index['sql'] ?? null;
                if (!is_string($sql) || $sql === '') {
                    throw new \InvalidArgumentException('SQLite SELECT expression-index planner needs index SQL text');
                }

                $expression = self::expressionForType($sql, $constraint['type']);
                if (
                    $expression === null
                    || strcasecmp($expression->columnName, $constraint['column']) !== 0
                    || (
                        $expression instanceof SQLiteJsonExtractIndexExpression
                        && (
                            (($constraint['path'] ?? null) !== $expression->path)
                            || !self::jsonExpressionKindMatches($constraint['type'], $expression->functionName)
                        )
                    )
                ) {
                    continue;
                }
                if (!self::constraintCollationMatchesExpression($constraint, $expression)) {
                    continue;
                }
                $matchedConstraint = self::constraintWithExpressionCollation($constraint, $expression);
                if ($expression->partial && !self::constraintImpliesPartialPredicate($expression->partialPredicate, $matchedConstraint, $terms)) {
                    continue;
                }
                if (!self::constraintCompatibleWithType($matchedConstraint, $expression->collation)) {
                    continue;
                }

                $estimated = self::estimatedRows($index, $matchedConstraint);
                $estimatedRows = $estimated['rows'];
                $trailingColumns = SQLiteCreateIndex::columnsAfterFirstExpression($sql);
                $orderCompatible = self::orderCompatible($expression, $matchedConstraint['type'], $trailingColumns, $matchedConstraint, $orderBy);
                $covering = self::covering($index, $expression, $matchedConstraint['type'], $neededColumns, $neededExpressions, $trailingColumns);
                $estimatedCost = self::estimatedCost($matchedConstraint, $estimatedRows, $expression->partial, $orderCompatible, $covering);

                $plans[] = [
                    'usable' => true,
                    'rootPage' => $index['rootPage'] ?? null,
                    'name' => $index['name'] ?? null,
                    'type' => $matchedConstraint['type'],
                    'column' => $expression->columnName,
                    'operator' => $matchedConstraint['operator'],
                    'values' => $matchedConstraint['values'],
                    'path' => $matchedConstraint['path'] ?? null,
                    'collation' => $expression->collation,
                    'queryCollation' => $matchedConstraint['collation'],
                    'collationMatched' => strcasecmp($matchedConstraint['collation'], $expression->collation) === 0,
                    'descending' => $expression->descending,
                    'partial' => $expression->partial,
                    'residualPredicateRequired' => $matchedConstraint['residualPredicateRequired'],
                    'estimatedRows' => $estimatedRows,
                    'estimatedCost' => $estimatedCost,
                    'stat4Used' => $estimated['stat4Used'],
                    'stat4Estimate' => $estimated['stat4Estimate'],
                    'stat4MatchedSamples' => $estimated['stat4MatchedSamples'],
                    'stat4CurrentNext' => $estimated['stat4CurrentNext'],
                    'stat4MatchedCurrentNext' => $estimated['stat4MatchedCurrentNext'],
                    'stat4RangeCurrentNext' => $estimated['stat4RangeCurrentNext'],
                    'orderBySatisfied' => $orderCompatible,
                    'covering' => $covering,
                    'coveringExpressions' => self::coveredExpressionNames($index, $expression, $matchedConstraint['type'], $neededExpressions),
                    'trailingColumns' => array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $trailingColumns),
                ];
            }
        }
        foreach ($indexDefinitions as $index) {
            $sql = $index['sql'] ?? null;
            if (!is_string($sql) || $sql === '') {
                throw new \InvalidArgumentException('SQLite SELECT expression-index planner needs index SQL text');
            }

            $expression = SQLiteCreateIndex::firstColumn($sql);
            if ($expression === null || !$expression->partial || !self::partialPredicateImpliedByOrdinaryTerms($expression->partialPredicate, $terms)) {
                continue;
            }
            if (self::termsContainSearchConstraintForColumn($terms, $expression->columnName)) {
                continue;
            }

            $scanConstraint = [
                'operator' => 'scan',
                'values' => null,
                'collation' => $expression->collation,
                'residualPredicateRequired' => true,
            ];
            $trailingColumns = SQLiteCreateIndex::columnsAfterFirstExpression($sql);
            $orderCompatible = self::orderCompatible($expression, 'column', $trailingColumns, $scanConstraint, $orderBy);
            if (!$orderCompatible) {
                continue;
            }
            $covering = self::covering($index, $expression, 'column', $neededColumns, [], $trailingColumns);
            $estimatedRows = max(1, (int) floor((int) ($index['estimatedRows'] ?? 1000) * 0.5));

            $plans[] = [
                'usable' => true,
                'rootPage' => $index['rootPage'] ?? null,
                'name' => $index['name'] ?? null,
                'type' => 'column',
                'column' => $expression->columnName,
                'operator' => 'scan',
                'values' => null,
                'path' => null,
                'collation' => $expression->collation,
                'queryCollation' => $expression->collation,
                'collationMatched' => true,
                'descending' => $expression->descending,
                'partial' => true,
                'residualPredicateRequired' => true,
                'estimatedRows' => $estimatedRows,
                'estimatedCost' => self::estimatedCost($scanConstraint, $estimatedRows, true, true, $covering),
                'stat4Used' => false,
                'stat4Estimate' => null,
                'stat4MatchedSamples' => [],
                'stat4CurrentNext' => [],
                'stat4MatchedCurrentNext' => [],
                'stat4RangeCurrentNext' => [],
                'orderBySatisfied' => true,
                'covering' => $covering,
                'coveringExpressions' => [],
                'trailingColumns' => array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $trailingColumns),
            ];
        }

        usort($plans, static function (array $left, array $right): int {
            return [$left['estimatedCost'], $left['estimatedRows'], (string) ($left['name'] ?? '')]
                <=> [$right['estimatedCost'], $right['estimatedRows'], (string) ($right['name'] ?? '')];
        });

        return $plans;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndTerms(array $predicate): array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT expression-index predicate'));
        if ($operator !== 'AND') {
            return [$predicate];
        }

        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index AND predicate needs a non-empty term list');
        }

        $flattened = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index AND predicate terms must be predicates');
            }
            array_push($flattened, ...self::flattenAndTerms($term));
        }

        return $flattened;
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return list<array{type:string,column:string,operator:string,values:array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool},path?:string,residualPredicateRequired:bool}>
     */
    private static function boundedRangeConstraints(array $terms): array
    {
        $rangeConstraints = [];
        foreach ($terms as $term) {
            $constraint = self::constraintFromPredicate($term);
            if ($constraint !== null && str_starts_with($constraint['operator'], 'range-')) {
                $rangeConstraints[] = $constraint;
            }
        }

        $groups = [];
        foreach ($rangeConstraints as $constraint) {
            $key = implode("\0", [
                $constraint['type'],
                strtolower($constraint['column']),
                (string) ($constraint['path'] ?? ''),
                strtoupper((string) ($constraint['collation'] ?? '')),
            ]);
            $groups[$key]['type'] = $constraint['type'];
            $groups[$key]['column'] = $constraint['column'];
            $groups[$key]['path'] = $constraint['path'] ?? null;
            $groups[$key]['collation'] = $constraint['collation'] ?? null;
            if ($constraint['operator'] === 'range->' || $constraint['operator'] === 'range->=') {
                if (
                    !isset($groups[$key]['lower'])
                    || self::lowerBoundIsTighter($constraint, $groups[$key]['lower'])
                ) {
                    $groups[$key]['lower'] = $constraint;
                }
                continue;
            }
            if ($constraint['operator'] === 'range-<' || $constraint['operator'] === 'range-<=') {
                if (
                    !isset($groups[$key]['upper'])
                    || self::upperBoundIsTighter($constraint, $groups[$key]['upper'])
                ) {
                    $groups[$key]['upper'] = $constraint;
                }
            }
        }

        $bounded = [];
        foreach ($groups as $group) {
            $left = $group['lower'] ?? null;
            $right = $group['upper'] ?? null;
            if (!is_array($left) || !is_array($right)) {
                continue;
            }

            $comparison = self::compareStat4Keys($left['values'], $right['values'], (string) ($group['collation'] ?? 'BINARY'));
            if ($comparison > 0) {
                continue;
            }
            if ($comparison === 0 && ($left['operator'] !== 'range->=' || $right['operator'] !== 'range-<=')) {
                continue;
            }

            $bounded[] = [
                'type' => $group['type'],
                'column' => $group['column'],
                'path' => $group['path'],
                'collation' => $group['collation'],
                'operator' => 'range-bounded',
                'values' => [
                    'lower' => $left['values'],
                    'upper' => $right['values'],
                    'lowerInclusive' => $left['operator'] === 'range->=',
                    'upperInclusive' => $right['operator'] === 'range-<=',
                ],
                'residualPredicateRequired' => true,
            ];
        }

        return $bounded;
    }

    /**
     * @param array{operator:string,values:mixed} $candidate
     * @param array{operator:string,values:mixed} $current
     */
    private static function lowerBoundIsTighter(array $candidate, array $current): bool
    {
        $comparison = self::compareStat4Keys($candidate['values'], $current['values'], (string) ($candidate['collation'] ?? 'BINARY'));
        if ($comparison !== 0) {
            return $comparison > 0;
        }

        return $candidate['operator'] === 'range->' && $current['operator'] === 'range->=';
    }

    /**
     * @param array{operator:string,values:mixed} $candidate
     * @param array{operator:string,values:mixed} $current
     */
    private static function upperBoundIsTighter(array $candidate, array $current): bool
    {
        $comparison = self::compareStat4Keys($candidate['values'], $current['values'], (string) ($candidate['collation'] ?? 'BINARY'));
        if ($comparison !== 0) {
            return $comparison < 0;
        }

        return $candidate['operator'] === 'range-<' && $current['operator'] === 'range-<=';
    }

    /**
     * @return null|array{type:string,column:string,operator:string,values:mixed,collation?:string,residualPredicateRequired:bool}
     */
    private static function constraintFromPredicate(array $predicate): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT expression-index predicate'));
        if ($operator === '=' || $operator === '==') {
            return self::binaryConstraint($predicate, 'point');
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::binaryConstraint($predicate, 'range-' . $operator);
        }
        if ($operator === 'IN') {
            $left = self::expressionOperand($predicate['left'] ?? null);
            $values = $predicate['values'] ?? null;
            if ($left === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return [
                'type' => $left['type'],
                'column' => $left['column'],
                'path' => $left['path'] ?? null,
                'operator' => 'IN',
                'values' => self::literalList($values),
                'collation' => $left['collation'] ?? null,
                'residualPredicateRequired' => true,
            ];
        }
        if ($operator === 'BETWEEN') {
            $left = self::expressionOperand($predicate['left'] ?? null);
            if ($left === null || !array_key_exists('lower', $predicate) || !array_key_exists('upper', $predicate)) {
                return null;
            }

            return [
                'type' => $left['type'],
                'column' => $left['column'],
                'path' => $left['path'] ?? null,
                'operator' => 'BETWEEN',
                'values' => [
                    'lower' => self::literalValue($predicate['lower']),
                    'upper' => self::literalValue($predicate['upper']),
                ],
                'collation' => $left['collation'] ?? null,
                'residualPredicateRequired' => true,
            ];
        }

        return null;
    }

    /**
     * @return null|array{type:string,column:string,operator:string,values:mixed,collation?:string,residualPredicateRequired:bool}
     */
    private static function binaryConstraint(array $predicate, string $operator): ?array
    {
        $left = self::expressionOperand($predicate['left'] ?? null);
        $right = self::expressionOperand($predicate['right'] ?? null);
        if ($left !== null && $right === null && array_key_exists('right', $predicate)) {
            return [
                'type' => $left['type'],
                'column' => $left['column'],
                'path' => $left['path'] ?? null,
                'operator' => $operator,
                'values' => self::literalValue($predicate['right']),
                'collation' => $left['collation'] ?? null,
                'residualPredicateRequired' => true,
            ];
        }
        if ($right !== null && $left === null && array_key_exists('left', $predicate)) {
            return [
                'type' => $right['type'],
                'column' => $right['column'],
                'path' => $right['path'] ?? null,
                'operator' => self::reverseRangeOperator($operator),
                'values' => self::literalValue($predicate['left']),
                'collation' => $right['collation'] ?? null,
                'residualPredicateRequired' => true,
            ];
        }

        return null;
    }

    /**
     * @return null|array{type:string,column:string,path?:string,collation?:string}
     */
    private static function expressionOperand(mixed $operand): ?array
    {
        if (!is_array($operand)) {
            return null;
        }

        $function = strtolower((string) ($operand['function'] ?? ''));
        $column = $operand['column'] ?? null;
        if (!is_string($column) || $column === '') {
            return null;
        }

        return match ($function) {
            'lower' => self::collatedExpressionOperand(['type' => 'lower', 'column' => $column], $operand),
            'upper' => self::collatedExpressionOperand(['type' => 'upper', 'column' => $column], $operand),
            'length' => self::collatedExpressionOperand(['type' => 'length', 'column' => $column], $operand),
            'cast_integer' => self::collatedExpressionOperand(['type' => 'integer-cast', 'column' => $column], $operand),
            'json_extract' => self::collatedExpressionOperand(self::jsonPathOperand($operand, 'json-extract', $column), $operand),
            'jsonb_extract' => self::collatedExpressionOperand(self::jsonPathOperand($operand, 'jsonb-extract', $column), $operand),
            'json_text_operator' => self::collatedExpressionOperand(self::jsonPathOperand($operand, 'json-text-operator', $column), $operand),
            'json_value_operator' => self::collatedExpressionOperand(self::jsonPathOperand($operand, 'json-value-operator', $column), $operand),
            '' => self::collatedExpressionOperand(['type' => 'column', 'column' => $column], $operand),
            default => null,
        };
    }

    /**
     * @param null|array{type:string,column:string,path?:string,collation?:string} $expression
     * @return null|array{type:string,column:string,path?:string,collation?:string}
     */
    private static function collatedExpressionOperand(?array $expression, array $operand): ?array
    {
        if ($expression === null) {
            return null;
        }

        $collation = $operand['collation'] ?? null;
        if ($collation === null) {
            return $expression;
        }
        if (!is_string($collation) || $collation === '') {
            return null;
        }

        $expression['collation'] = strtoupper($collation);

        return $expression;
    }

    /**
     * @return null|array{type:string,column:string,path:string}
     */
    private static function jsonPathOperand(array $operand, string $type, string $column): ?array
    {
        $path = $operand['path'] ?? null;
        if (!is_string($path) || !SQLiteJsonPath::isWellFormed($path)) {
            return null;
        }

        return ['type' => $type, 'column' => $column, 'path' => $path];
    }

    private static function expressionForType(string $sql, string $type): SQLiteIndexColumn|SQLiteJsonExtractIndexExpression|null
    {
        return match ($type) {
            'column' => SQLiteCreateIndex::firstColumn($sql),
            'lower' => SQLiteCreateIndex::firstLowerExpression($sql),
            'upper' => SQLiteCreateIndex::firstUpperExpression($sql),
            'length' => SQLiteCreateIndex::firstLengthExpression($sql),
            'integer-cast' => SQLiteCreateIndex::firstIntegerCastExpression($sql),
            'json-extract', 'jsonb-extract' => SQLiteCreateIndex::firstJsonExtractExpression($sql),
            'json-text-operator' => SQLiteCreateIndex::firstJsonTextOperatorExpression($sql),
            'json-value-operator' => SQLiteCreateIndex::firstJsonValueOperatorExpression($sql),
            default => null,
        };
    }

    private static function jsonExpressionKindMatches(string $constraintType, string $functionName): bool
    {
        return match ($constraintType) {
            'json-extract' => $functionName === 'json_extract',
            'jsonb-extract' => $functionName === 'jsonb_extract',
            'json-text-operator' => $functionName === '->>',
            'json-value-operator' => $functionName === '->',
            default => true,
        };
    }

    /**
     * @param array{type:string,column:string,operator:string,values:mixed,collation?:string,residualPredicateRequired:bool} $constraint
     */
    private static function constraintCompatibleWithType(array $constraint, string $collation): bool
    {
        if ($constraint['operator'] === 'range-bounded' && is_array($constraint['values'])) {
            $rangeValues = [
                $constraint['values']['lower'] ?? null,
                $constraint['values']['upper'] ?? null,
            ];
            if ($constraint['type'] === 'lower' || $constraint['type'] === 'upper') {
                return is_string($rangeValues[0]) && is_string($rangeValues[1]);
            }
            if ($constraint['type'] === 'length' || $constraint['type'] === 'integer-cast') {
                return self::valuesAreIntegersOrNull($rangeValues);
            }

            return $collation !== '';
        }
        if ($constraint['type'] === 'column') {
            return $collation !== '';
        }
        if ($constraint['type'] === 'lower' || $constraint['type'] === 'upper') {
            return is_string($constraint['values']) || is_array($constraint['values']);
        }
        if ($constraint['type'] === 'length' || $constraint['type'] === 'integer-cast') {
            return self::valuesAreIntegersOrNull($constraint['values']);
        }
        if ($constraint['type'] === 'json-text-operator') {
            return $collation !== '' && (is_string($constraint['values']) || is_int($constraint['values']) || is_float($constraint['values']) || is_array($constraint['values']) || $constraint['values'] === null);
        }
        if ($constraint['type'] === 'json-extract' || $constraint['type'] === 'jsonb-extract' || $constraint['type'] === 'json-value-operator') {
            return $collation !== '';
        }

        return $collation !== '';
    }

    /**
     * @param array{collation?:string} $constraint
     */
    private static function constraintCollationMatchesExpression(array $constraint, SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression): bool
    {
        $queryCollation = $constraint['collation'] ?? null;
        if ($queryCollation === null) {
            return true;
        }
        if (!is_string($queryCollation) || $queryCollation === '') {
            return false;
        }

        return strcasecmp($queryCollation, $expression->collation) === 0;
    }

    /**
     * @param array<string,mixed> $constraint
     * @return array<string,mixed>
     */
    private static function constraintWithExpressionCollation(array $constraint, SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression): array
    {
        $constraint['collation'] = strtoupper((string) ($constraint['collation'] ?? $expression->collation));

        return $constraint;
    }

    /**
     * @param array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool} $constraint
     * @param list<array<string,mixed>> $terms
     */
    private static function constraintImpliesPartialPredicate(?SQLiteIndexPredicate $predicate, array $constraint, array $terms): bool
    {
        if ($predicate === null) {
            return false;
        }

        return self::partialPredicateImpliedByAvailableConstraints($predicate, $constraint, $terms);
    }

    /**
     * @param array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool} $constraint
     * @param list<array<string,mixed>> $terms
     */
    private static function partialPredicateImpliedByAvailableConstraints(SQLiteIndexPredicate $predicate, array $constraint, array $terms): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value) || $predicate->value === []) {
                return false;
            }
            foreach ($predicate->value as $subPredicate) {
                if (
                    !$subPredicate instanceof SQLiteIndexPredicate
                    || !self::partialPredicateImpliedByAvailableConstraints($subPredicate, $constraint, $terms)
                ) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }
            foreach ($predicate->value as $subPredicate) {
                if (
                    $subPredicate instanceof SQLiteIndexPredicate
                    && self::partialPredicateImpliedByAvailableConstraints($subPredicate, $constraint, $terms)
                ) {
                    return true;
                }
            }

            return false;
        }

        if (
            (
                self::partialPredicateIsSafeNonNull($predicate, self::constraintLookupName($constraint))
                || self::partialPredicateIsSafeNonNull($predicate, $constraint['column'])
            )
            && self::constraintHasNonNullSearchValue($constraint['values'])
        ) {
            return true;
        }
        if (self::expressionConstraintImpliesPartialPredicate($predicate, $constraint)) {
            return true;
        }
        foreach ($terms as $term) {
            $expressionConstraint = self::constraintFromPredicate($term);
            if ($expressionConstraint !== null && self::expressionConstraintImpliesPartialPredicate($predicate, $expressionConstraint)) {
                return true;
            }
        }
        foreach ($terms as $term) {
            $ordinaryConstraint = self::ordinaryConstraintFromPredicate($term);
            if ($ordinaryConstraint !== null && self::ordinaryConstraintImpliesPartialPredicate($predicate, $ordinaryConstraint)) {
                return true;
            }
        }
        if (self::combinedOrdinaryConstraintsImplyPartialPredicate($predicate, $terms)) {
            return true;
        }

        return false;
    }

    /**
     * @param array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool} $constraint
     */
    private static function expressionConstraintImpliesPartialPredicate(SQLiteIndexPredicate $predicate, array $constraint): bool
    {
        $lookupName = self::constraintLookupName($constraint);
        if ($constraint['operator'] === 'point') {
            return $predicate->isImpliedByPointLookup($lookupName, $constraint['values'], (string) ($constraint['collation'] ?? 'BINARY'))
                || $predicate->isExpressionInListImpliedByPointLookup($lookupName, $constraint['values'], (string) ($constraint['collation'] ?? 'BINARY'));
        }
        if ($constraint['operator'] === 'IN' && is_array($constraint['values'])) {
            return $predicate->isImpliedByInListLookup($lookupName, $constraint['values'], (string) ($constraint['collation'] ?? 'BINARY'));
        }
        if ($constraint['operator'] === 'BETWEEN' && is_array($constraint['values'])) {
            return $predicate->isImpliedByRangeLookup(
                $lookupName,
                $constraint['values']['lower'] ?? null,
                $constraint['values']['upper'] ?? null,
                true,
                (string) ($constraint['collation'] ?? 'BINARY')
            );
        }
        if ($constraint['operator'] === 'range-bounded' && is_array($constraint['values'])) {
            return $predicate->isImpliedByRangeLookup(
                $lookupName,
                $constraint['values']['lower'] ?? null,
                $constraint['values']['upper'] ?? null,
                (bool) ($constraint['values']['upperInclusive'] ?? false),
                (string) ($constraint['collation'] ?? 'BINARY')
            );
        }
        if (str_starts_with($constraint['operator'], 'range-')) {
            return match ($constraint['operator']) {
                'range->' => $predicate->isImpliedByRangeLookup($lookupName, $constraint['values'], null, false, (string) ($constraint['collation'] ?? 'BINARY')),
                'range->=' => $predicate->isImpliedByRangeLookup($lookupName, $constraint['values'], null, true, (string) ($constraint['collation'] ?? 'BINARY')),
                'range-<' => $predicate->isImpliedByRangeLookup($lookupName, null, $constraint['values'], false, (string) ($constraint['collation'] ?? 'BINARY')),
                'range-<=' => $predicate->isImpliedByRangeLookup($lookupName, null, $constraint['values'], true, (string) ($constraint['collation'] ?? 'BINARY')),
                default => false,
            };
        }

        return false;
    }

    /**
     * @param array{type:string,column:string} $constraint
     */
    private static function constraintLookupName(array $constraint): string
    {
        return '__expr__:' . $constraint['type'] . ':' . strtolower($constraint['column']);
    }

    /**
     * SQLite may use a partial index when multiple AND-connected WHERE terms
     * jointly prove the partial-index predicate. The bounded planner mostly
     * reasons term-by-term, so collect ordinary column lower/upper bounds here
     * for partial predicates like "WHERE key_name BETWEEN 'a' AND 'm'".
     *
     * @param list<array<string,mixed>> $terms
     */
    private static function combinedOrdinaryConstraintsImplyPartialPredicate(SQLiteIndexPredicate $predicate, array $terms): bool
    {
        $boundsByColumn = [];
        foreach ($terms as $term) {
            $constraint = self::ordinaryConstraintFromPredicate($term);
            if ($constraint === null) {
                continue;
            }

            $columnKey = strtolower($constraint['column']);
            $boundsByColumn[$columnKey]['column'] = $constraint['column'];
            if ($constraint['operator'] === 'BETWEEN' && is_array($constraint['values'])) {
                $boundsByColumn[$columnKey]['lower'][] = $constraint['values']['lower'] ?? null;
                $boundsByColumn[$columnKey]['upper'][] = [
                    'value' => $constraint['values']['upper'] ?? null,
                    'inclusive' => true,
                ];
                continue;
            }
            if ($constraint['operator'] === 'point') {
                $boundsByColumn[$columnKey]['lower'][] = $constraint['values'];
                $boundsByColumn[$columnKey]['upper'][] = [
                    'value' => $constraint['values'],
                    'inclusive' => true,
                ];
                continue;
            }
            if ($constraint['operator'] === 'range->' || $constraint['operator'] === 'range->=') {
                $boundsByColumn[$columnKey]['lower'][] = $constraint['values'];
                continue;
            }
            if ($constraint['operator'] === 'range-<' || $constraint['operator'] === 'range-<=') {
                $boundsByColumn[$columnKey]['upper'][] = [
                    'value' => $constraint['values'],
                    'inclusive' => $constraint['operator'] === 'range-<=',
                ];
            }
        }

        foreach ($boundsByColumn as $bounds) {
            $column = $bounds['column'] ?? null;
            if (!is_string($column)) {
                continue;
            }
            $lowerBounds = $bounds['lower'] ?? [];
            $upperBounds = $bounds['upper'] ?? [];
            if (!is_array($lowerBounds) || !is_array($upperBounds)) {
                continue;
            }

            foreach ($lowerBounds as $lowerBound) {
                foreach ($upperBounds as $upperBound) {
                    if (
                        is_array($upperBound)
                        && array_key_exists('value', $upperBound)
                        && is_bool($upperBound['inclusive'])
                        && $predicate->isImpliedByRangeLookup($column, $lowerBound, $upperBound['value'], $upperBound['inclusive'])
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function ordinaryConstraintFromPredicate(array $predicate): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator', 'SQLite SELECT expression-index predicate'));
        if ($operator === '=' || $operator === '==') {
            return self::ordinaryBinaryConstraint($predicate, 'point');
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::ordinaryBinaryConstraint($predicate, 'range-' . $operator);
        }
        if ($operator === 'IN') {
            $left = self::columnOperand($predicate['left'] ?? null);
            $values = $predicate['values'] ?? null;
            if ($left === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return [
                'column' => $left,
                'operator' => 'IN',
                'values' => self::literalList($values),
            ];
        }
        if ($operator === 'BETWEEN') {
            $left = self::columnOperand($predicate['left'] ?? null);
            if ($left === null || !array_key_exists('lower', $predicate) || !array_key_exists('upper', $predicate)) {
                return null;
            }

            return [
                'column' => $left,
                'operator' => 'BETWEEN',
                'values' => [
                    'lower' => self::literalValue($predicate['lower']),
                    'upper' => self::literalValue($predicate['upper']),
                ],
            ];
        }
        if ($operator === 'IS NOT NULL') {
            $left = self::columnOperand($predicate['left'] ?? null);
            if ($left === null) {
                return null;
            }

            return [
                'column' => $left,
                'operator' => 'is-not-null',
                'values' => true,
            ];
        }

        return null;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function ordinaryBinaryConstraint(array $predicate, string $operator): ?array
    {
        $left = self::columnOperand($predicate['left'] ?? null);
        $right = self::columnOperand($predicate['right'] ?? null);
        if ($left !== null && $right === null && array_key_exists('right', $predicate)) {
            return [
                'column' => $left,
                'operator' => $operator,
                'values' => self::literalValue($predicate['right']),
            ];
        }
        if ($right !== null && $left === null && array_key_exists('left', $predicate)) {
            return [
                'column' => $right,
                'operator' => self::reverseRangeOperator($operator),
                'values' => self::literalValue($predicate['left']),
            ];
        }

        return null;
    }

    private static function columnOperand(mixed $operand): ?string
    {
        if (!is_array($operand) || array_key_exists('function', $operand)) {
            return null;
        }

        $column = $operand['column'] ?? null;
        if (!is_string($column) || $column === '') {
            return null;
        }

        return $column;
    }

    /**
     * @param array{column:string,operator:string,values:mixed} $constraint
     */
    private static function ordinaryConstraintImpliesPartialPredicate(SQLiteIndexPredicate $predicate, array $constraint): bool
    {
        if ($constraint['operator'] === 'point') {
            return $predicate->isImpliedByPointLookup($constraint['column'], $constraint['values']);
        }
        if ($constraint['operator'] === 'IN' && is_array($constraint['values'])) {
            return $predicate->isImpliedByInListLookup($constraint['column'], $constraint['values']);
        }
        if ($constraint['operator'] === 'is-not-null') {
            return $predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL
                && strcasecmp($predicate->columnName, $constraint['column']) === 0;
        }
        if ($constraint['operator'] === 'BETWEEN' && is_array($constraint['values'])) {
            return $predicate->isImpliedByRangeLookup(
                $constraint['column'],
                $constraint['values']['lower'] ?? null,
                $constraint['values']['upper'] ?? null,
                true
            );
        }
        if (str_starts_with($constraint['operator'], 'range-')) {
            return match ($constraint['operator']) {
                'range->' => $predicate->isImpliedByRangeLookup($constraint['column'], $constraint['values'], null, false),
                'range->=' => $predicate->isImpliedByRangeLookup($constraint['column'], $constraint['values'], null, true),
                'range-<' => $predicate->isImpliedByRangeLookup($constraint['column'], null, $constraint['values'], false),
                'range-<=' => $predicate->isImpliedByRangeLookup($constraint['column'], null, $constraint['values'], true),
                default => false,
            };
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function partialPredicateImpliedByOrdinaryTerms(?SQLiteIndexPredicate $predicate, array $terms): bool
    {
        if ($predicate === null) {
            return false;
        }
        foreach ($terms as $term) {
            $constraint = self::ordinaryConstraintFromPredicate($term);
            if ($constraint !== null && self::ordinaryConstraintImpliesPartialPredicate($predicate, $constraint)) {
                return true;
            }
        }

        return self::combinedOrdinaryConstraintsImplyPartialPredicate($predicate, $terms);
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function termsContainSearchConstraintForColumn(array $terms, string $column): bool
    {
        foreach ($terms as $term) {
            $constraint = self::constraintFromPredicate($term);
            if ($constraint !== null && strcasecmp($constraint['column'], $column) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function partialPredicateIsSafeNonNull(SQLiteIndexPredicate $predicate, string $column): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            return is_array($predicate->value)
                && $predicate->value !== []
                && array_reduce(
                    $predicate->value,
                    static fn (bool $carry, mixed $subPredicate): bool => $carry
                        && $subPredicate instanceof SQLiteIndexPredicate
                        && self::partialPredicateIsSafeNonNull($subPredicate, $column),
                    true
                );
        }
        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            return is_array($predicate->value)
                && array_reduce(
                    $predicate->value,
                    static fn (bool $carry, mixed $subPredicate): bool => $carry
                        || (
                            $subPredicate instanceof SQLiteIndexPredicate
                            && self::partialPredicateIsSafeNonNull($subPredicate, $column)
                        ),
                    false
                );
        }

        return strcasecmp($predicate->columnName, $column) === 0
            && $predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL;
    }

    private static function constraintHasNonNullSearchValue(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    if (self::constraintHasNonNullSearchValue($item)) {
                        return true;
                    }
                    continue;
                }
                if ($item !== null) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null;
    }

    private static function valuesAreIntegersOrNull(mixed $value): bool
    {
        if (is_int($value) || $value === null) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (is_array($item)) {
                if (!self::valuesAreIntegersOrNull($item)) {
                    return false;
                }
                continue;
            }
            if (!is_int($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{estimatedRows?:int,stat4Samples?:list<array{neq:int|list<int>|string,nlt:int|list<int>|string,ndlt?:int|list<int>|string,sample:list<mixed>}>>} $index
     * @param array{type:string,column:string,operator:string,values:mixed,residualPredicateRequired:bool} $constraint
     * @return array{rows:int,stat4Used:bool,stat4Estimate:int|null,stat4MatchedSamples:int,stat4CurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,stat4MatchedCurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,stat4RangeCurrentNext:array<string,mixed>|null}
     */
    private static function estimatedRows(array $index, array $constraint): array
    {
        $baseRows = $index['estimatedRows'] ?? 1000;
        if (!is_int($baseRows) || $baseRows < 1) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index estimatedRows must be a positive integer');
        }

        $fallback = max(1, min($baseRows, (int) ceil($baseRows * self::selectivity($constraint))));
        $stat4 = self::stat4Estimate($index['stat4Samples'] ?? [], $constraint, $baseRows);
        if ($stat4 === null) {
            return [
                'rows' => $fallback,
                'stat4Used' => false,
                'stat4Estimate' => null,
                'stat4MatchedSamples' => 0,
                'stat4CurrentNext' => [],
                'stat4MatchedCurrentNext' => [],
                'stat4RangeCurrentNext' => null,
            ];
        }

        return [
            'rows' => max(1, min($baseRows, $stat4['rows'])),
            'stat4Used' => true,
            'stat4Estimate' => $stat4['rows'],
            'stat4MatchedSamples' => $stat4['matchedSamples'],
            'stat4CurrentNext' => $stat4['currentNext'],
            'stat4MatchedCurrentNext' => $stat4['matchedCurrentNext'],
            'stat4RangeCurrentNext' => $stat4['rangeCurrentNext'],
        ];
    }

    /**
     * @param mixed $samples
     * @param array{operator:string,values:mixed} $constraint
     * @return null|array{rows:int,matchedSamples:int,currentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,matchedCurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,rangeCurrentNext:array<string,mixed>|null}
     */
    private static function stat4Estimate(mixed $samples, array $constraint, int $baseRows): ?array
    {
        if ($samples === [] || $samples === null) {
            return null;
        }
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index stat4Samples must be a list');
        }

        $normalized = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index stat4Samples rows must be arrays');
            }
            $sampleValues = $sample['sample'] ?? null;
            if (!is_array($sampleValues) || !array_is_list($sampleValues) || $sampleValues === []) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index stat4 sample must contain at least one key value');
            }
            $key = self::literalValue($sampleValues[0]);
            $normalized[] = [
                'key' => $key,
                'neq' => self::stat4FirstInteger($sample['neq'] ?? null, 'neq'),
                'nlt' => self::stat4FirstInteger($sample['nlt'] ?? null, 'nlt', true),
                'ndlt' => self::stat4FirstInteger($sample['ndlt'] ?? 0, 'ndlt', true),
                'sample' => $sampleValues,
            ];
        }

        $collation = (string) ($constraint['collation'] ?? 'BINARY');
        usort($normalized, static fn (array $left, array $right): int => self::compareStat4Keys($left['key'], $right['key'], $collation));
        $currentNext = self::stat4CurrentNext($normalized);
        $matched = [];
        $operator = $constraint['operator'];
        $values = $constraint['values'];

        if ($operator === 'point') {
            $matched = self::stat4MatchingSamples($normalized, $operator, $values, $collation);

            return [
                'rows' => self::stat4EqualityRows($normalized, $values, $baseRows, $collation),
                'matchedSamples' => count($matched),
                'currentNext' => $currentNext,
                'matchedCurrentNext' => self::stat4CurrentNext($matched),
                'rangeCurrentNext' => null,
            ];
        }
        if ($operator === 'IN') {
            $rows = 0;
            $seen = [];
            foreach (is_array($values) ? $values : [] as $value) {
                if ($value === null) {
                    continue;
                }
                $key = serialize($value);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $rows += self::stat4EqualityRows($normalized, $value, $baseRows, $collation);
            }
            $matched = self::stat4MatchingSamples($normalized, $operator, $values, $collation);

            return [
                'rows' => max(1, min($baseRows, $rows)),
                'matchedSamples' => count($matched),
                'currentNext' => $currentNext,
                'matchedCurrentNext' => self::stat4CurrentNext($matched),
                'rangeCurrentNext' => null,
            ];
        }
        if ($operator === 'BETWEEN' && is_array($values)) {
            $lower = $values['lower'] ?? null;
            $upper = $values['upper'] ?? null;
            $rows = self::stat4LessThanRows($normalized, $upper, true, $baseRows, $collation)
                - self::stat4LessThanRows($normalized, $lower, false, $baseRows, $collation);
            $matched = self::stat4MatchingSamples($normalized, $operator, $values, $collation);

            return [
                'rows' => max(1, min($baseRows, $rows)),
                'matchedSamples' => count($matched),
                'currentNext' => $currentNext,
                'matchedCurrentNext' => self::stat4CurrentNext($matched),
                'rangeCurrentNext' => self::stat4RangeCurrentNext($normalized, $lower, $upper, true, true, $collation),
            ];
        }
        if ($operator === 'range-bounded' && is_array($values)) {
            $lower = $values['lower'] ?? null;
            $upper = $values['upper'] ?? null;
            $lowerInclusive = (bool) ($values['lowerInclusive'] ?? false);
            $upperInclusive = (bool) ($values['upperInclusive'] ?? false);
            $rows = self::stat4LessThanRows($normalized, $upper, $upperInclusive, $baseRows, $collation)
                - self::stat4LessThanRows($normalized, $lower, !$lowerInclusive, $baseRows, $collation);
            $matched = self::stat4MatchingSamples($normalized, $operator, $values, $collation);

            return [
                'rows' => max(1, min($baseRows, $rows)),
                'matchedSamples' => count($matched),
                'currentNext' => $currentNext,
                'matchedCurrentNext' => self::stat4CurrentNext($matched),
                'rangeCurrentNext' => self::stat4RangeCurrentNext($normalized, $lower, $upper, $lowerInclusive, $upperInclusive, $collation),
            ];
        }
        if (str_starts_with($operator, 'range-')) {
            $rows = match ($operator) {
                'range-<' => self::stat4LessThanRows($normalized, $values, false, $baseRows, $collation),
                'range-<=' => self::stat4LessThanRows($normalized, $values, true, $baseRows, $collation),
                'range->' => $baseRows - self::stat4LessThanRows($normalized, $values, true, $baseRows, $collation),
                'range->=' => $baseRows - self::stat4LessThanRows($normalized, $values, false, $baseRows, $collation),
                default => null,
            };
            if ($rows !== null) {
                $matched = self::stat4MatchingSamples($normalized, $operator, $values, $collation);

                return [
                    'rows' => max(1, min($baseRows, $rows)),
                    'matchedSamples' => count($matched),
                    'currentNext' => $currentNext,
                    'matchedCurrentNext' => self::stat4CurrentNext($matched),
                    'rangeCurrentNext' => self::stat4SingleRangeCurrentNext($normalized, $operator, $values, $collation),
                ];
            }
        }

        return null;
    }

    private static function stat4FirstInteger(mixed $value, string $field, bool $allowZero = false): int
    {
        if (is_string($value) && preg_match('/^\d+(?:\s+\d+)*$/', trim($value)) === 1) {
            $parts = preg_split('/\s+/', trim($value));
            $value = (int) ($parts[0] ?? 0);
        } elseif (is_array($value) && array_is_list($value)) {
            $value = $value[0] ?? null;
        }
        if (!is_int($value) || $value < ($allowZero ? 0 : 1)) {
            $kind = $allowZero ? 'unsigned integer' : 'positive integer';
            throw new \InvalidArgumentException("SQLite SELECT expression-index stat4 {$field} must start with a {$kind}");
        }

        return $value;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     */
    private static function stat4MatchingSamples(array $samples, string $operator, mixed $values, string $collation = 'BINARY'): array
    {
        return array_values(array_filter(
            $samples,
            static fn (array $sample): bool => self::stat4SampleMatches($sample['key'], $operator, $values, $collation),
        ));
    }

    private static function stat4SampleMatches(mixed $key, string $operator, mixed $values, string $collation = 'BINARY'): bool
    {
        if ($operator === 'point') {
            return self::compareStat4Keys($key, $values, $collation) === 0;
        }
        if ($operator === 'IN' && is_array($values)) {
            foreach ($values as $value) {
                if ($value !== null && self::compareStat4Keys($key, $value, $collation) === 0) {
                    return true;
                }
            }

            return false;
        }
        if ($operator === 'BETWEEN' && is_array($values)) {
            return self::compareStat4Keys($key, $values['lower'] ?? null, $collation) >= 0
                && self::compareStat4Keys($key, $values['upper'] ?? null, $collation) <= 0;
        }
        if ($operator === 'range-bounded' && is_array($values)) {
            $lowerComparison = self::compareStat4Keys($key, $values['lower'] ?? null, $collation);
            $upperComparison = self::compareStat4Keys($key, $values['upper'] ?? null, $collation);
            $lowerMatches = (bool) ($values['lowerInclusive'] ?? false) ? $lowerComparison >= 0 : $lowerComparison > 0;
            $upperMatches = (bool) ($values['upperInclusive'] ?? false) ? $upperComparison <= 0 : $upperComparison < 0;

            return $lowerMatches && $upperMatches;
        }
        if ($operator === 'range-<') {
            return self::compareStat4Keys($key, $values, $collation) < 0;
        }
        if ($operator === 'range-<=') {
            return self::compareStat4Keys($key, $values, $collation) <= 0;
        }
        if ($operator === 'range->') {
            return self::compareStat4Keys($key, $values, $collation) > 0;
        }
        if ($operator === 'range->=') {
            return self::compareStat4Keys($key, $values, $collation) >= 0;
        }

        return false;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     */
    private static function stat4EqualityRows(array $samples, mixed $value, int $baseRows, string $collation = 'BINARY'): int
    {
        foreach ($samples as $sample) {
            if (self::compareStat4Keys($sample['key'], $value, $collation) === 0) {
                return $sample['neq'];
            }
        }

        $distinct = max(1, max(array_map(static fn (array $sample): int => $sample['ndlt'], $samples)) + 1);

        return max(1, (int) ceil($baseRows / $distinct));
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     */
    private static function stat4LessThanRows(array $samples, mixed $value, bool $inclusive, int $baseRows, string $collation = 'BINARY'): int
    {
        $previous = 0;
        foreach ($samples as $sample) {
            $comparison = self::compareStat4Keys($sample['key'], $value, $collation);
            if ($comparison > 0 || ($comparison === 0 && !$inclusive)) {
                return max(0, min($baseRows, $sample['nlt']));
            }
            $previous = $sample['nlt'] + $sample['neq'];
            if ($comparison === 0) {
                return max(0, min($baseRows, $inclusive ? $previous : $sample['nlt']));
            }
        }

        return max(0, min($baseRows, $previous));
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
     */
    private static function stat4CurrentNext(array $samples): array
    {
        $pairs = [];
        foreach ($samples as $offset => $sample) {
            $pairs[] = [
                'current' => [
                    'key' => $sample['key'],
                    'neq' => $sample['neq'],
                    'nlt' => $sample['nlt'],
                    'ndlt' => $sample['ndlt'],
                ],
                'next' => isset($samples[$offset + 1]) ? [
                    'key' => $samples[$offset + 1]['key'],
                    'neq' => $samples[$offset + 1]['neq'],
                    'nlt' => $samples[$offset + 1]['nlt'],
                    'ndlt' => $samples[$offset + 1]['ndlt'],
                ] : null,
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @return array{lower:array<string,mixed>|null,upper:array<string,mixed>|null,lowerInclusive:bool,upperInclusive:bool,emptyGap:bool}
     */
    private static function stat4RangeCurrentNext(array $samples, mixed $lower, mixed $upper, bool $lowerInclusive, bool $upperInclusive, string $collation = 'BINARY'): array
    {
        $lowerPair = self::stat4BoundaryCurrentNext($samples, $lower, 'lower', $collation);
        $upperPair = self::stat4BoundaryCurrentNext($samples, $upper, 'upper', $collation);

        return [
            'lower' => $lowerPair,
            'upper' => $upperPair,
            'lowerInclusive' => $lowerInclusive,
            'upperInclusive' => $upperInclusive,
            'emptyGap' => $lowerPair !== null
                && $upperPair !== null
                && ($lowerPair['current']['key'] ?? null) === ($upperPair['current']['key'] ?? null)
                && ($lowerPair['next']['key'] ?? null) === ($upperPair['next']['key'] ?? null)
                && self::compareStat4Keys($lower, $upper, $collation) < 0,
        ];
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @return array{lower:array<string,mixed>|null,upper:array<string,mixed>|null,lowerInclusive:bool,upperInclusive:bool,emptyGap:bool}
     */
    private static function stat4SingleRangeCurrentNext(array $samples, string $operator, mixed $value, string $collation = 'BINARY'): array
    {
        return match ($operator) {
            'range->' => self::stat4RangeCurrentNext($samples, $value, null, false, false, $collation),
            'range->=' => self::stat4RangeCurrentNext($samples, $value, null, true, false, $collation),
            'range-<' => self::stat4RangeCurrentNext($samples, null, $value, false, false, $collation),
            'range-<=' => self::stat4RangeCurrentNext($samples, null, $value, false, true, $collation),
            default => ['lower' => null, 'upper' => null, 'lowerInclusive' => false, 'upperInclusive' => false, 'emptyGap' => false],
        };
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>}> $samples
     * @return array{current:array<string,mixed>|null,next:array<string,mixed>|null,side:string,value:mixed,exact:bool}|null
     */
    private static function stat4BoundaryCurrentNext(array $samples, mixed $value, string $side, string $collation = 'BINARY'): ?array
    {
        if ($value === null || $samples === []) {
            return null;
        }

        $previous = null;
        foreach ($samples as $offset => $sample) {
            $comparison = self::compareStat4Keys($sample['key'], $value, $collation);
            if ($comparison >= 0) {
                return [
                    'current' => $comparison === 0 ? self::stat4BoundarySample($sample) : ($previous === null ? null : self::stat4BoundarySample($previous)),
                    'next' => $comparison === 0
                        ? (isset($samples[$offset + 1]) ? self::stat4BoundarySample($samples[$offset + 1]) : null)
                        : self::stat4BoundarySample($sample),
                    'side' => $side,
                    'value' => $value,
                    'exact' => $comparison === 0,
                ];
            }
            $previous = $sample;
        }

        return [
            'current' => $previous === null ? null : self::stat4BoundarySample($previous),
            'next' => null,
            'side' => $side,
            'value' => $value,
            'exact' => false,
        ];
    }

    /**
     * @param array{key:mixed,neq:int,nlt:int,ndlt:int,sample:list<mixed>} $sample
     * @return array{key:mixed,neq:int,nlt:int,ndlt:int}
     */
    private static function stat4BoundarySample(array $sample): array
    {
        return [
            'key' => $sample['key'],
            'neq' => $sample['neq'],
            'nlt' => $sample['nlt'],
            'ndlt' => $sample['ndlt'],
        ];
    }

    private static function compareStat4Keys(mixed $left, mixed $right, string $collation = 'BINARY'): int
    {
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        if (strcasecmp($collation, 'NOCASE') === 0) {
            return strcasecmp((string) $left, (string) $right);
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function stat4KeySignature(mixed $key): string
    {
        return get_debug_type($key) . ':' . serialize($key);
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $row
     */
    private static function currentSourceExpressionValue(array $plan, array $row): mixed
    {
        $column = $plan['column'] ?? null;
        if (!is_string($column) || $column === '') {
            throw new \InvalidArgumentException('SQLite SELECT expression-index current-source plan needs an expression column');
        }
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index current-source row is missing the expression column');
        }

        $value = $row[$column];

        return match ($plan['type'] ?? null) {
            'lower' => is_string($value) ? strtolower($value) : null,
            'upper' => is_string($value) ? strtoupper($value) : null,
            'length' => is_string($value) ? strlen($value) : null,
            'integer-cast' => is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0),
            'jsonb-extract', 'json-extract', 'json-text-operator', 'json-value-operator' => self::currentSourceJsonValue($value, $plan['path'] ?? null),
            default => throw new \InvalidArgumentException('SQLite SELECT expression-index current-source plan has an unsupported expression type'),
        };
    }

    private static function currentSourceJsonValue(mixed $json, mixed $path): mixed
    {
        if (!is_string($path) || $path === '' || !str_starts_with($path, '$.')) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index current-source JSON plan needs a simple object path');
        }
        if (!is_string($json)) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $value = $decoded;
        foreach (explode('.', substr($path, 2)) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value;
    }

    /**
     * @param array<string,mixed> $predicate
     * @param array<string,mixed> $row
     */
    private static function currentSourceRowSatisfiesPredicate(array $predicate, array $row): bool
    {
        foreach (self::flattenAndTerms($predicate) as $term) {
            $operator = strtoupper(self::requiredString($term, 'operator', 'SQLite SELECT expression-index current-source predicate'));
            if ($operator !== '=' && $operator !== '==') {
                continue;
            }

            $leftColumn = self::ordinaryColumnOperand($term['left'] ?? null);
            $rightColumn = self::ordinaryColumnOperand($term['right'] ?? null);
            if ($leftColumn !== null && $rightColumn === null && array_key_exists('right', $term)) {
                if (($row[$leftColumn] ?? null) !== self::literalValue($term['right'])) {
                    return false;
                }
                continue;
            }
            if ($rightColumn !== null && $leftColumn === null && array_key_exists('left', $term)) {
                if (($row[$rightColumn] ?? null) !== self::literalValue($term['left'])) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function ordinaryColumnOperand(mixed $operand): ?string
    {
        if (!is_array($operand) || array_key_exists('function', $operand)) {
            return null;
        }
        $column = $operand['column'] ?? null;

        return is_string($column) && $column !== '' ? $column : null;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function currentSourceCoveringPayload(array $row, array $neededColumns): array
    {
        $payload = [];
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT expression-index current-source covering columns must be names');
            }
            $payload[$column] = $row[$column] ?? null;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $row
     * @param list<array<string,string>> $neededExpressions
     * @return array<string,mixed>
     */
    private static function currentSourceCoveringExpressionPayload(array $plan, array $row, array $neededExpressions): array
    {
        $payload = [];
        foreach ($neededExpressions as $expression) {
            $normalized = self::neededExpressionOperand($expression);
            if ($normalized === null) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index current-source covering expressions must be supported expression operands');
            }

            $payload[self::expressionDisplayName($expression)] = self::currentSourceExpressionValue([
                'type' => $normalized['type'],
                'column' => $normalized['column'],
                'path' => $normalized['path'] ?? null,
            ], $row);
        }

        return $payload;
    }

    /**
     * @param array{operator:string,values:mixed} $constraint
     */
    private static function selectivity(array $constraint): float
    {
        if ($constraint['operator'] === 'point') {
            return 0.01;
        }
        if ($constraint['operator'] === 'IN') {
            $values = is_array($constraint['values']) ? array_values(array_filter(
                $constraint['values'],
                static fn (mixed $value): bool => $value !== null
            )) : [];

            return min(0.5, max(0.01, count(array_unique($values, SORT_REGULAR)) * 0.015));
        }
        if ($constraint['operator'] === 'BETWEEN') {
            return 0.1;
        }
        if ($constraint['operator'] === 'range-bounded') {
            return 0.08;
        }
        if (str_starts_with($constraint['operator'], 'range-')) {
            return 0.25;
        }

        return 1.0;
    }

    /**
     * @param list<SQLiteIndexColumn> $trailingColumns
     * @param array{operator:string} $constraint
     * @param list<array<string,string>> $orderBy
     */
    private static function orderCompatible(SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression, string $expressionType, array $trailingColumns, array $constraint, array $orderBy): bool
    {
        if ($orderBy === []) {
            return false;
        }
        $firstOrder = $orderBy[0] ?? null;
        if (
            is_array($firstOrder)
            && self::orderTermMatchesExpression($firstOrder, $expression, $expressionType)
            && self::orderTermCollationMatchesExpression($firstOrder, $expression)
        ) {
            $direction = self::orderDirection($firstOrder);
            if ($expression->descending !== ($direction === 'DESC')) {
                return false;
            }
            $firstOrderIsExpression = self::expressionOperand($firstOrder) !== null;
            if (count($orderBy) === 1) {
                return true;
            }
            if (!$firstOrderIsExpression) {
                return false;
            }

            return self::trailingOrderCompatible($trailingColumns, array_slice($orderBy, 1));
        }

        if ($constraint['operator'] !== 'point' || count($orderBy) > count($trailingColumns)) {
            return false;
        }

        return self::trailingOrderCompatible($trailingColumns, $orderBy);
    }

    /**
     * @param array<string,string> $order
     */
    private static function orderTermMatchesExpression(array $order, SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression, string $expressionType): bool
    {
        $operand = self::expressionOperand($order);
        if (($operand['type'] ?? null) === 'column') {
            $operand = null;
        }
        if ($operand !== null) {
            if (strcasecmp($operand['column'], $expression->columnName) !== 0) {
                return false;
            }
            if ($expression instanceof SQLiteJsonExtractIndexExpression) {
                return ($operand['path'] ?? null) === $expression->path
                    && self::jsonExpressionKindMatches($operand['type'], $expression->functionName);
            }

            return strcasecmp($operand['type'], $expressionType) === 0;
        }

        $column = $order['column'] ?? null;

        return is_string($column) && strcasecmp($column, $expression->columnName) === 0;
    }

    /**
     * @param array<string,string> $order
     */
    private static function orderTermCollationMatchesExpression(array $order, SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression): bool
    {
        $collation = $order['collation'] ?? null;
        if ($collation === null) {
            return true;
        }
        if (!is_string($collation) || $collation === '') {
            throw new \InvalidArgumentException('SQLite SELECT expression-index ORDER BY collation must be a collation name');
        }

        return strcasecmp($collation, $expression->collation) === 0;
    }

    /**
     * @param list<SQLiteIndexColumn> $trailingColumns
     * @param list<array<string,string>> $orderBy
     */
    private static function trailingOrderCompatible(array $trailingColumns, array $orderBy): bool
    {
        if (count($orderBy) > count($trailingColumns)) {
            return false;
        }

        foreach ($orderBy as $offset => $order) {
            $column = $order['column'] ?? null;
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT expression-index ORDER BY column must be a column name');
            }

            $direction = self::orderDirection($order);

            $indexColumn = $trailingColumns[$offset] ?? null;
            if ($indexColumn === null || strcasecmp($indexColumn->columnName, $column) !== 0) {
                return false;
            }
            if ($indexColumn->descending !== ($direction === 'DESC')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,string> $order
     */
    private static function orderDirection(array $order): string
    {
        $direction = strtoupper((string) ($order['direction'] ?? 'ASC'));
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException('SQLite SELECT expression-index ORDER BY direction must be ASC or DESC');
        }

        return $direction;
    }

    /**
     * @param array{coveringColumns?:list<string>,coveringExpressions?:list<array<string,string>>} $index
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @param list<SQLiteIndexColumn> $trailingColumns
     */
    private static function covering(array $index, SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression, string $expressionType, array $neededColumns, array $neededExpressions, array $trailingColumns): bool
    {
        if ($neededColumns === [] && $neededExpressions === []) {
            return false;
        }

        return self::columnsCovered($index, $neededColumns, $trailingColumns)
            && self::expressionsCovered($index, $expression, $expressionType, $neededExpressions);
    }

    /**
     * @param array{coveringColumns?:list<string>} $index
     * @param list<string> $neededColumns
     * @param list<SQLiteIndexColumn> $trailingColumns
     */
    private static function columnsCovered(array $index, array $neededColumns, array $trailingColumns): bool
    {
        if ($neededColumns === []) {
            return true;
        }
        $columns = $index['coveringColumns'] ?? array_map(
            static fn (SQLiteIndexColumn $column): string => $column->columnName,
            $trailingColumns,
        );
        if (!is_array($columns) || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index coveringColumns must be a list');
        }

        $normalized = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite SELECT expression-index coveringColumns must contain column names');
            }
            $normalized[strtolower($column)] = true;
        }

        foreach ($neededColumns as $neededColumn) {
            if (!is_string($neededColumn) || $neededColumn === '') {
                throw new \InvalidArgumentException('SQLite SELECT expression-index needed columns must be column names');
            }
            if (!isset($normalized[strtolower($neededColumn)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,string>> $neededExpressions
     */
    private static function expressionsCovered(array $index, SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression, string $expressionType, array $neededExpressions): bool
    {
        $available = self::availableExpressionCoverage($index, $expression, $expressionType);
        foreach ($neededExpressions as $neededExpression) {
            $normalized = self::neededExpressionOperand($neededExpression);
            if ($normalized === null) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index needed expressions must be supported expression operands');
            }
            if (!isset($available[self::expressionCoverageKey($normalized)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,string>> $neededExpressions
     * @return list<string>
     */
    private static function coveredExpressionNames(array $index, SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression, string $expressionType, array $neededExpressions): array
    {
        if (!self::expressionsCovered($index, $expression, $expressionType, $neededExpressions)) {
            return [];
        }

        return array_map(
            static fn (array $neededExpression): string => self::expressionDisplayName($neededExpression),
            $neededExpressions
        );
    }

    /**
     * @param array{coveringExpressions?:list<array<string,string>>} $index
     * @return array<string,true>
     */
    private static function availableExpressionCoverage(array $index, SQLiteIndexColumn|SQLiteJsonExtractIndexExpression $expression, string $expressionType): array
    {
        $available = [];
        $first = [
            'type' => $expressionType,
            'column' => $expression->columnName,
            'collation' => $expression->collation,
        ];
        if ($expression instanceof SQLiteJsonExtractIndexExpression) {
            $first['path'] = $expression->path;
        }
        $available[self::expressionCoverageKey($first)] = true;
        unset($first['collation']);
        $available[self::expressionCoverageKey($first)] = true;

        $extra = $index['coveringExpressions'] ?? [];
        if (!is_array($extra) || !array_is_list($extra)) {
            throw new \InvalidArgumentException('SQLite SELECT expression-index coveringExpressions must be a list');
        }
        foreach ($extra as $operand) {
            if (!is_array($operand)) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index coveringExpressions must contain expression operands');
            }
            $normalized = self::neededExpressionOperand($operand);
            if ($normalized === null) {
                throw new \InvalidArgumentException('SQLite SELECT expression-index coveringExpressions must be supported expression operands');
            }
            $available[self::expressionCoverageKey($normalized)] = true;
        }

        return $available;
    }

    /**
     * @param array{type:string,column:string,path?:string} $expression
     */
    private static function expressionCoverageKey(array $expression): string
    {
        return strtolower($expression['type'])
            . ':' . strtolower($expression['column'])
            . ':' . (string) ($expression['path'] ?? '')
            . ':' . strtoupper((string) ($expression['collation'] ?? ''));
    }

    private static function expressionDisplayName(array $operand): string
    {
        $function = strtolower($operand['function']);
        $name = $function . '(' . $operand['column'];
        if (isset($operand['path']) && $function !== 'jsonb_extract') {
            $name .= ',' . $operand['path'];
        }

        return $name . ')';
    }

    /**
     * @return null|array{type:string,column:string}
     */
    private static function neededExpressionOperand(array $operand): ?array
    {
        return self::expressionOperand($operand);
    }

    /**
     * @param array{operator:string} $constraint
     */
    private static function estimatedCost(array $constraint, int $estimatedRows, bool $partial, bool $orderCompatible, bool $covering): int
    {
        $cost = match ($constraint['operator']) {
            'point' => 5,
            'IN' => 12,
            'BETWEEN' => 18,
            'range-bounded' => 16,
            default => str_starts_with($constraint['operator'], 'range-') ? 25 : 100,
        };
        $cost += $estimatedRows;
        if ($partial) {
            $cost -= 3;
        }
        if ($orderCompatible) {
            $cost -= 8;
        }
        if ($covering) {
            $cost -= 5;
        }

        return max(1, $cost);
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    private static function literalList(array $values): array
    {
        return array_map(static fn (mixed $value): mixed => self::literalValue($value), $values);
    }

    private static function literalValue(mixed $value): mixed
    {
        if ($value instanceof SQLiteBlobValue || $value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite SELECT expression-index constraints need scalar, BLOB, or NULL values');
    }

    private static function reverseRangeOperator(string $operator): string
    {
        return match ($operator) {
            'range-<' => 'range->',
            'range-<=' => 'range->=',
            'range->' => 'range-<',
            'range->=' => 'range-<=',
            default => $operator,
        };
    }

    private static function requiredString(array $payload, string $key, string $context): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$context} needs {$key}");
        }

        return $value;
    }
}
