<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4CoveringOrOrderPlan
{
    /**
     * @param list<array<string,mixed>> $indexDefinitions
     * @param array<string,mixed> $predicate
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $neededColumns
     * @return null|array<string,mixed>
     */
    public static function choose(array $indexDefinitions, array $predicate, array $orderBy = [], array $neededColumns = []): ?array
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator'));
        if ($operator !== 'OR') {
            return null;
        }

        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite STAT4 covering OR planner needs a non-empty OR term list');
        }

        $arms = [];
        foreach ($terms as $ordinal => $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite STAT4 covering OR terms must be predicates');
            }

            $plan = SQLiteSkipScanCoveringStat4Plan::choose($indexDefinitions, $term, $orderBy, $neededColumns);
            if ($plan === null || ($plan['covering'] ?? false) !== true || ($plan['stat4Used'] ?? false) !== true) {
                return null;
            }

            $arms[] = self::armSummary($plan, $ordinal);
        }

        $allOrderSatisfied = self::allTrue($arms, 'orderBySatisfied');
        $indexNames = array_values(array_unique(array_map(
            static fn (array $arm): string => (string) $arm['name'],
            $arms
        )));
        $rangeColumns = array_values(array_unique(array_map(
            static fn (array $arm): string => (string) $arm['rangeColumn'],
            $arms
        )));
        $estimatedRows = array_sum(array_map(
            static fn (array $arm): int => (int) $arm['estimatedRows'],
            $arms
        ));
        $estimatedCost = 14 + array_sum(array_map(
            static fn (array $arm): int => (int) $arm['estimatedCost'],
            $arms
        ));
        if ($allOrderSatisfied) {
            $estimatedCost -= 12;
        }

        return [
            'usable' => true,
            'strategy' => count($indexNames) === 1 ? 'stat4-covering-single-index-or' : 'stat4-covering-multi-index-or',
            'armCount' => count($arms),
            'arms' => $arms,
            'indexNames' => $indexNames,
            'rangeColumns' => $rangeColumns,
            'covering' => true,
            'stat4Used' => true,
            'orderBySatisfied' => $allOrderSatisfied,
            'rowidUnionRequired' => count($arms) > 1,
            'mergeOrderRequired' => count($arms) > 1 && $allOrderSatisfied,
            'tempSortRequired' => !$allOrderSatisfied && $orderBy !== [],
            'estimatedRows' => max(1, $estimatedRows),
            'estimatedCost' => max(1, $estimatedCost),
            'stat4CurrentNextCount' => array_sum(array_map(
                static fn (array $arm): int => count($arm['stat4CurrentNext']),
                $arms
            )),
            'detail' => self::detail($indexNames, $arms, $allOrderSatisfied),
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function armSummary(array $plan, int $ordinal): array
    {
        return [
            'ordinal' => $ordinal,
            'name' => (string) $plan['name'],
            'rootPage' => $plan['rootPage'] ?? null,
            'rangeColumn' => (string) $plan['rangeColumn'],
            'rangeConstraint' => $plan['rangeConstraint'],
            'usedColumns' => $plan['usedColumns'],
            'skippedColumns' => $plan['skippedColumns'],
            'skipScanLoops' => $plan['skipScanLoops'],
            'orderBySatisfied' => (bool) $plan['orderBySatisfied'],
            'covering' => true,
            'stat4Used' => true,
            'estimatedRows' => (int) $plan['estimatedRows'],
            'estimatedCost' => (int) $plan['estimatedCost'],
            'stat4LoopEstimates' => $plan['stat4LoopEstimates'],
            'stat4CurrentNext' => $plan['stat4CurrentNext'],
            'detail' => (string) $plan['detail'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $arms
     */
    private static function allTrue(array $arms, string $key): bool
    {
        foreach ($arms as $arm) {
            if (($arm[$key] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $indexNames
     * @param list<array<string,mixed>> $arms
     */
    private static function detail(array $indexNames, array $arms, bool $allOrderSatisfied): string
    {
        $detail = 'MULTI-INDEX OR USING STAT4 COVERING ' . implode(',', $indexNames);
        $detail .= ' ARMS ' . count($arms);
        if ($allOrderSatisfied) {
            $detail .= ' MERGE ORDER';
        }

        return $detail;
    }

    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 covering OR planner requires {$key}");
        }

        return $value;
    }
}
