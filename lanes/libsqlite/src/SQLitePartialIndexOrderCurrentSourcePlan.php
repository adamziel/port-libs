<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePartialIndexOrderCurrentSourcePlan
{
    /**
     * @param list<array{sql:string,name?:string,rootPage?:int,estimatedRows?:int,distinctValues?:array<string,int>}> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function plan(array $indexDefinitions, array $predicate, array $orderBy, array $neededColumns = []): array
    {
        self::validateOrderBy($orderBy);

        $plans = SQLiteMultiColumnRangePlan::rankedPlans($indexDefinitions, $predicate, $orderBy, $neededColumns);
        if ($plans === []) {
            return [
                'status' => 'unusable',
                'usable' => false,
                'partialIndexOrderUsable' => false,
                'orderBySatisfied' => false,
                'blockSortRequired' => $orderBy !== [],
                'currentSource' => null,
                'nextSource' => 'table-scan',
                'detail' => 'SCAN TABLE; NO USABLE PARTIAL INDEX ORDER',
            ];
        }

        $selected = self::selectPlan($plans);
        $partialOrderUsable = ($selected['partial'] ?? false) === true
            && ($selected['orderBySatisfied'] ?? false) === true
            && $orderBy !== [];
        $covering = ($selected['covering'] ?? false) === true;
        $residual = ($selected['residualPredicateRequired'] ?? false) === true;

        return $selected + [
            'status' => 'usable',
            'usable' => true,
            'candidateCount' => count($plans),
            'partialIndexOrderUsable' => $partialOrderUsable,
            'partialPredicateImplied' => ($selected['partial'] ?? false) === true,
            'currentSource' => $partialOrderUsable ? 'partial-index-order' : 'index-range',
            'currentSourceColumns' => $selected['usedColumns'] ?? [],
            'currentSourceOrderColumns' => array_map(static fn (array $term): string => (string) $term['column'], $orderBy),
            'currentSourceRangeColumn' => $selected['rangeColumn'] ?? null,
            'currentSourceLoops' => self::loopCount($selected),
            'nextSource' => $covering ? 'covering-index' : 'table-rowid-lookup',
            'nextResidualPredicateRequired' => $residual,
            'deferredTableLookup' => !$covering,
            'blockSortRequired' => !$partialOrderUsable && $orderBy !== [],
            'orderByMode' => self::orderByMode($selected, $partialOrderUsable, $orderBy),
            'detail' => self::detail($selected, $partialOrderUsable, $covering, $residual, $orderBy),
        ];
    }

    /**
     * @param list<array<string,mixed>> $plans
     * @return array<string,mixed>
     */
    private static function selectPlan(array $plans): array
    {
        foreach ($plans as $plan) {
            if (($plan['partial'] ?? false) === true && ($plan['orderBySatisfied'] ?? false) === true) {
                return $plan;
            }
        }

        return $plans[0];
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function validateOrderBy(array $orderBy): void
    {
        foreach ($orderBy as $term) {
            if (!isset($term['column']) || !is_string($term['column']) || $term['column'] === '') {
                throw new \InvalidArgumentException('SQLite partial-index ORDER BY needs column terms');
            }
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite partial-index ORDER BY direction must be ASC or DESC');
            }
        }
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function loopCount(array $plan): int
    {
        $loops = 1;
        $constraints = $plan['equalityConstraints'] ?? [];
        if (!is_array($constraints)) {
            return $loops;
        }

        foreach ($constraints as $constraint) {
            if (!is_array($constraint) || ($constraint['operator'] ?? null) !== 'IN') {
                continue;
            }
            $values = $constraint['values'] ?? [];
            if (!is_array($values)) {
                continue;
            }
            $nonNull = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
            $loops *= max(1, count(array_unique($nonNull, SORT_REGULAR)));
        }

        return $loops;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function orderByMode(array $plan, bool $partialOrderUsable, array $orderBy): string
    {
        if ($orderBy === []) {
            return 'none';
        }
        if ($partialOrderUsable) {
            return 'partial-current-source';
        }
        if (($plan['orderBySatisfied'] ?? false) === true) {
            return 'index-current-source';
        }

        return 'next-temp-sort';
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function detail(array $plan, bool $partialOrderUsable, bool $covering, bool $residual, array $orderBy): string
    {
        $name = (string) ($plan['name'] ?? 'unknown-index');
        $range = (string) ($plan['rangeColumn'] ?? 'unknown');
        $detail = 'SEARCH ' . $name . ' USING CURRENT ' . $range . ' RANGE';
        if (($plan['partial'] ?? false) === true) {
            $detail .= ' PARTIAL-PREDICATE IMPLIED';
        }
        if ($partialOrderUsable) {
            $detail .= ' ORDER BY FROM PARTIAL INDEX';
        } elseif ($orderBy !== [] && ($plan['orderBySatisfied'] ?? false) !== true) {
            $detail .= ' USE TEMP B-TREE FOR ORDER BY';
        }
        $detail .= $covering ? ' COVERING' : ' DEFER TABLE LOOKUP';
        if ($residual) {
            $detail .= ' NEXT RESIDUAL';
        }

        return $detail;
    }
}
