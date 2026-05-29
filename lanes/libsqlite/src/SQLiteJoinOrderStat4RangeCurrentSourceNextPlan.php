<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJoinOrderStat4RangeCurrentSourceNextPlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource): array
    {
        $prepared = self::sourcePlan($preparedSource);
        $current = self::sourcePlan($currentSource);
        $stale = self::sourceSignature($preparedSource) !== self::sourceSignature($currentSource);
        $selected = $stale ? $current : $prepared;

        return [
            'status' => 'join-order-stat4-range-current-source-ready',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::nonNegativeInt($preparedSource, 'schemaCookie') !== self::nonNegativeInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::nonNegativeInt($preparedSource, 'stat4Generation') !== self::nonNegativeInt($currentSource, 'stat4Generation'),
            'indexSignatureChanged' => self::indexSignature($preparedSource) !== self::indexSignature($currentSource),
            'preparedPlan' => $prepared,
            'currentPlan' => $current,
            'selectedPlan' => $selected,
            'joinOrderChanged' => $prepared['tables'] !== $current['tables'],
            'estimatedRowsDelta' => $current['estimatedRows'] - $prepared['estimatedRows'],
            'estimatedCostDelta' => $current['estimatedCost'] - $prepared['estimatedCost'],
            'currentSourceFence' => [
                'schemaCookie' => self::nonNegativeInt($currentSource, 'schemaCookie'),
                'stat4Generation' => self::nonNegativeInt($currentSource, 'stat4Generation'),
                'indexSignature' => self::indexSignature($currentSource),
            ],
            'dependencies' => [
                'SQLiteMultiColumnRangePlan',
                'SQLiteJoinOrderStat4RangeCurrentSourceNextPlan',
                'sqlite-join-order-stat4-range-current-source-next116',
            ],
            'detail' => ($stale ? 'REPREPARE JOIN ORDER STAT4 RANGE USING CURRENT SOURCE ' : 'REUSE JOIN ORDER STAT4 RANGE PREPARED SOURCE ')
                . self::stringValue($stale ? $currentSource : $preparedSource, 'name') . ' '
                . implode(' -> ', $selected['detail']),
            'non_overlap' => 'avoids accepted expression-index range-cost and STAT4 range-order slices by ranking connected multi-table join orders from current-source STAT4 range estimates',
            'dependency_closure' => 'no new support component needed; next116 composes native planner metadata, STAT4 range estimates, and connected join-order diagnostics',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source): array
    {
        $tables = self::stringList($source, 'tables');
        $joins = self::joinTerms($source['joinTerms'] ?? []);
        $constraints = self::predicateMap($source['predicates'] ?? []);
        $indexes = self::indexesByTable($source['indexes'] ?? []);
        $tableRows = self::tableRows($source['tableRows'] ?? []);
        $needed = self::neededColumns($source['neededColumns'] ?? []);

        $orders = [];
        foreach (self::permutations($tables) as $order) {
            $available = [];
            $loops = [];
            $cost = 0;
            $rows = 1;
            $valid = true;
            foreach ($order as $position => $table) {
                $joinConstraints = self::joinConstraintsFor($table, $available, $joins);
                if ($position > 0 && $joinConstraints === []) {
                    $valid = false;
                    break;
                }

                $plan = self::tableAccessPlan(
                    $table,
                    $constraints[$table] ?? null,
                    $indexes[$table] ?? [],
                    $needed[$table] ?? [],
                    $tableRows[$table] ?? 1000,
                    $joinConstraints,
                );
                $loopRows = max(1, (int) $plan['estimatedRows']);
                $rows *= $loopRows;
                $cost += $rows + ($plan['access'] === 'table-scan' ? $loopRows : 0);
                $loops[] = $plan + [
                    'table' => $table,
                    'position' => $position,
                    'joinColumns' => array_values($joinConstraints),
                ];
                $available[$table] = true;
            }
            if (!$valid) {
                continue;
            }
            $orders[] = [
                'tables' => $order,
                'loops' => $loops,
                'estimatedRows' => $rows,
                'estimatedCost' => $cost,
                'detail' => array_map(static fn (array $loop): string => (string) $loop['detail'], $loops),
            ];
        }

        usort($orders, static fn (array $left, array $right): int => [
            $left['estimatedCost'],
            $left['estimatedRows'],
            implode(',', $left['tables']),
        ] <=> [
            $right['estimatedCost'],
            $right['estimatedRows'],
            implode(',', $right['tables']),
        ]);

        if ($orders === []) {
            return ['tables' => [], 'loops' => [], 'estimatedRows' => 0, 'estimatedCost' => 0, 'detail' => []];
        }

        $best = $orders[0];
        $best['rankedOrders'] = array_map(
            static fn (array $order): array => [
                'tables' => $order['tables'],
                'estimatedRows' => $order['estimatedRows'],
                'estimatedCost' => $order['estimatedCost'],
            ],
            $orders,
        );

        return $best;
    }

    /**
     * @param list<array<string,mixed>> $indexes
     * @param list<string> $neededColumns
     * @param list<string> $joinConstraints
     * @return array<string,mixed>
     */
    private static function tableAccessPlan(string $table, ?array $predicate, array $indexes, array $neededColumns, int $fallbackRows, array $joinConstraints): array
    {
        $plan = $predicate === null ? null : SQLiteMultiColumnRangePlan::choose($indexes, $predicate, [], $neededColumns);
        if ($plan === null) {
            return [
                'access' => 'table-scan',
                'index' => null,
                'matchedColumns' => [],
                'rangeColumn' => null,
                'estimatedRows' => $fallbackRows,
                'estimatedCost' => $fallbackRows * 2,
                'stat4Used' => false,
                'stat4MatchedSamples' => 0,
                'stat4RangeCurrentNext' => null,
                'covering' => false,
                'detail' => 'SCAN ' . $table,
            ];
        }

        $matched = $plan['usedColumns'];
        foreach ($joinConstraints as $column) {
            if (!in_array($column, $matched, true)) {
                $matched[] = $column;
            }
        }

        return [
            'access' => 'index',
            'index' => $plan['name'],
            'matchedColumns' => $matched,
            'rangeColumn' => $plan['rangeColumn'],
            'estimatedRows' => $plan['estimatedRows'],
            'estimatedCost' => $plan['estimatedCost'],
            'stat4Used' => $plan['stat4Used'],
            'stat4MatchedSamples' => $plan['stat4MatchedSamples'],
            'stat4RangeCurrentNext' => $plan['stat4RangeCurrentNext'],
            'covering' => $plan['covering'],
            'detail' => 'SEARCH ' . $table . ' USING INDEX ' . $plan['name'] . ' (' . implode(',', $matched) . ')',
        ];
    }

    /**
     * @param array<string,bool> $available
     * @param list<array{leftTable:string,leftColumn:string,rightTable:string,rightColumn:string}> $joinTerms
     * @return list<string>
     */
    private static function joinConstraintsFor(string $table, array $available, array $joinTerms): array
    {
        $constraints = [];
        foreach ($joinTerms as $term) {
            if (strcasecmp($term['leftTable'], $table) === 0 && isset($available[$term['rightTable']])) {
                $constraints[] = $term['leftColumn'];
            }
            if (strcasecmp($term['rightTable'], $table) === 0 && isset($available[$term['leftTable']])) {
                $constraints[] = $term['rightColumn'];
            }
        }

        return $constraints;
    }

    /**
     * @param list<string> $items
     * @return list<list<string>>
     */
    private static function permutations(array $items): array
    {
        if ($items === []) {
            throw new \InvalidArgumentException('SQLite join-order STAT4 range planner needs at least one table');
        }
        if (count($items) === 1) {
            return [$items];
        }

        $result = [];
        foreach ($items as $index => $item) {
            $remaining = $items;
            array_splice($remaining, $index, 1);
            foreach (self::permutations($remaining) as $suffix) {
                array_unshift($suffix, $item);
                $result[] = $suffix;
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $source
     * @return list<string>
     */
    private static function stringList(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite join-order STAT4 range planner needs non-empty {$key}");
        }
        $seen = [];
        $strings = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("SQLite join-order STAT4 range planner {$key} values must be strings");
            }
            $lower = strtolower($item);
            if (isset($seen[$lower])) {
                throw new \InvalidArgumentException("SQLite join-order STAT4 range planner {$key} values must be unique");
            }
            $seen[$lower] = true;
            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @return list<array{leftTable:string,leftColumn:string,rightTable:string,rightColumn:string}>
     */
    private static function joinTerms(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite join-order STAT4 range planner joinTerms must be a list');
        }
        $joins = [];
        foreach ($value as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite join-order STAT4 range planner join terms must be arrays');
            }
            foreach (['leftTable', 'leftColumn', 'rightTable', 'rightColumn'] as $key) {
                if (!is_string($term[$key] ?? null) || $term[$key] === '') {
                    throw new \InvalidArgumentException("SQLite join-order STAT4 range planner join terms need {$key}");
                }
            }
            $joins[] = [
                'leftTable' => $term['leftTable'],
                'leftColumn' => $term['leftColumn'],
                'rightTable' => $term['rightTable'],
                'rightColumn' => $term['rightColumn'],
            ];
        }

        return $joins;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function predicateMap(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite join-order STAT4 range planner predicates must be a map');
        }
        $map = [];
        foreach ($value as $table => $predicate) {
            if (!is_string($table) || $table === '' || !is_array($predicate)) {
                throw new \InvalidArgumentException('SQLite join-order STAT4 range planner predicates must be table predicate arrays');
            }
            $map[$table] = $predicate;
        }

        return $map;
    }

    /**
     * @return array<string,list<array<string,mixed>>>
     */
    private static function indexesByTable(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite join-order STAT4 range planner indexes must be a map');
        }
        $map = [];
        foreach ($value as $table => $indexes) {
            if (!is_string($table) || !is_array($indexes) || !array_is_list($indexes)) {
                throw new \InvalidArgumentException('SQLite join-order STAT4 range planner indexes must be table-index lists');
            }
            $map[$table] = $indexes;
        }

        return $map;
    }

    /**
     * @return array<string,int>
     */
    private static function tableRows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $rows = [];
        foreach ($value as $table => $count) {
            if (!is_string($table) || !is_int($count) || $count < 1) {
                throw new \InvalidArgumentException('SQLite join-order STAT4 range planner tableRows must be positive integer counts');
            }
            $rows[$table] = $count;
        }

        return $rows;
    }

    /**
     * @return array<string,list<string>>
     */
    private static function neededColumns(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $needed = [];
        foreach ($value as $table => $columns) {
            if (!is_string($table) || !is_array($columns) || !array_is_list($columns)) {
                throw new \InvalidArgumentException('SQLite join-order STAT4 range planner neededColumns must be table-column lists');
            }
            foreach ($columns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite join-order STAT4 range planner needed columns must be strings');
                }
            }
            $needed[$table] = $columns;
        }

        return $needed;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceSignature(array $source): string
    {
        return self::nonNegativeInt($source, 'schemaCookie') . '|'
            . self::nonNegativeInt($source, 'stat4Generation') . '|'
            . self::indexSignature($source);
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function indexSignature(array $source): string
    {
        return hash('sha256', serialize($source['indexes'] ?? []));
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function stringValue(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite join-order STAT4 range planner needs {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function nonNegativeInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite join-order STAT4 range planner needs non-negative integer {$key}");
        }

        return $value;
    }
}
