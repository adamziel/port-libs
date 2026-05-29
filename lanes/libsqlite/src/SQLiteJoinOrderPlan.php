<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJoinOrderPlan
{
    /**
     * @param list<array{tbl:string,idx:?string,stat:string}> $statRows
     * @param list<array{name:string,table:string,columns:list<string>,unique?:bool}> $indexes
     * @param list<string> $tables
     * @param array<string,list<array{column:string,operator:string,value?:mixed,values?:list<mixed>}>> $constraintsByTable
     * @param list<array{leftTable:string,leftColumn?:string,rightTable:string,rightColumn?:string,joinType?:string}> $joinTerms
     * @return array<string,mixed>
     */
    public static function choose(array $statRows, array $indexes, array $tables, array $constraintsByTable = [], array $joinTerms = []): array
    {
        $ranked = self::rankedOrders($statRows, $indexes, $tables, $constraintsByTable, $joinTerms);

        return $ranked[0] ?? ['loops' => [], 'estimatedRows' => 0, 'estimatedCost' => 0, 'detail' => []];
    }

    /**
     * @param list<array{tbl:string,idx:?string,stat:string}> $statRows
     * @param list<array{name:string,table:string,columns:list<string>,unique?:bool}> $indexes
     * @param list<string> $tables
     * @param array<string,list<array{column:string,operator:string,value?:mixed,values?:list<mixed>}>> $constraintsByTable
     * @param list<array{leftTable:string,leftColumn?:string,rightTable:string,rightColumn?:string,joinType?:string}> $joinTerms
     * @return list<array<string,mixed>>
     */
    public static function rankedOrders(array $statRows, array $indexes, array $tables, array $constraintsByTable = [], array $joinTerms = []): array
    {
        self::validateTables($tables);
        self::validateJoinTerms($joinTerms);

        $orders = [];
        foreach (self::permutations($tables) as $order) {
            $loops = [];
            $available = [];
            $cost = 0;
            $rows = 1;
            $valid = true;
            foreach ($order as $position => $table) {
                $constraints = $constraintsByTable[$table] ?? [];
                $joinConstraints = self::joinConstraintsFor($table, $available, $joinTerms);
                if ($position > 0 && $joinConstraints === [] && !self::connectedToAvailable($table, $available, $joinTerms)) {
                    $valid = false;
                    break;
                }
                if (!self::respectsJoinOrderFences($table, $available, $joinTerms)) {
                    $valid = false;
                    break;
                }

                $plan = SQLiteAnalyzeStatPlanner::choose($statRows, $indexes, $table, array_merge($constraints, $joinConstraints));
                $loopRows = max(1, (int) $plan['estimatedRows']);
                $rows *= $loopRows;
                $cost += $rows + ($plan['access'] === 'table-scan' ? $loopRows : 0);
                $loops[] = [
                    'table' => $table,
                    'position' => $position,
                    'access' => $plan['access'],
                    'index' => $plan['index'] ?? null,
                    'matchedColumns' => $plan['matchedColumns'],
                    'joinColumns' => array_values(array_map(static fn (array $constraint): string => $constraint['column'], $joinConstraints)),
                    'joinFence' => self::joinFenceFor($table, $available, $joinTerms),
                    'estimatedRows' => $loopRows,
                    'detail' => $plan['detail'],
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
                'detail' => array_column($loops, 'detail'),
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

        return $orders;
    }

    /**
     * @param list<string> $tables
     */
    private static function validateTables(array $tables): void
    {
        if ($tables === []) {
            throw new \InvalidArgumentException('SQLite join-order planner needs at least one table');
        }
        $seen = [];
        foreach ($tables as $table) {
            if (!is_string($table) || $table === '') {
                throw new \InvalidArgumentException('SQLite join-order planner table names must be non-empty strings');
            }
            $key = strtolower($table);
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException('SQLite join-order planner table names must be unique');
            }
            $seen[$key] = true;
        }
    }

    /**
     * @param list<array{leftTable:string,leftColumn?:string,rightTable:string,rightColumn?:string,joinType?:string}> $joinTerms
     */
    private static function validateJoinTerms(array $joinTerms): void
    {
        foreach ($joinTerms as $term) {
            foreach (['leftTable', 'rightTable'] as $key) {
                if (!is_string($term[$key] ?? null) || $term[$key] === '') {
                    throw new \InvalidArgumentException('SQLite join-order planner join terms need non-empty ' . $key);
                }
            }
            $joinType = strtoupper((string) ($term['joinType'] ?? 'INNER'));
            if (!in_array($joinType, ['INNER', 'JOIN', 'CROSS', 'LEFT', 'LEFT OUTER', 'RIGHT', 'RIGHT OUTER', 'FULL', 'FULL OUTER'], true)) {
                throw new \InvalidArgumentException('SQLite join-order planner joinType is not supported');
            }
            if ($joinType === 'CROSS') {
                continue;
            }
            foreach (['leftColumn', 'rightColumn'] as $key) {
                if (!is_string($term[$key] ?? null) || $term[$key] === '') {
                    throw new \InvalidArgumentException('SQLite join-order planner join terms need non-empty ' . $key);
                }
            }
        }
    }

    /**
     * @param array<string,bool> $available
     * @param list<array{leftTable:string,leftColumn?:string,rightTable:string,rightColumn?:string,joinType?:string}> $joinTerms
     * @return list<array{column:string,operator:string,value:mixed}>
     */
    private static function joinConstraintsFor(string $table, array $available, array $joinTerms): array
    {
        $constraints = [];
        foreach ($joinTerms as $term) {
            if (strtoupper((string) ($term['joinType'] ?? 'INNER')) === 'CROSS') {
                continue;
            }
            if (strcasecmp($term['leftTable'], $table) === 0 && isset($available[$term['rightTable']])) {
                $constraints[] = ['column' => $term['leftColumn'], 'operator' => '=', 'value' => ['outerTable' => $term['rightTable'], 'outerColumn' => $term['rightColumn']]];
            }
            if (strcasecmp($term['rightTable'], $table) === 0 && isset($available[$term['leftTable']])) {
                $constraints[] = ['column' => $term['rightColumn'], 'operator' => '=', 'value' => ['outerTable' => $term['leftTable'], 'outerColumn' => $term['leftColumn']]];
            }
        }

        return $constraints;
    }

    /**
     * @param array<string,bool> $available
     * @param list<array{leftTable:string,leftColumn?:string,rightTable:string,rightColumn?:string,joinType?:string}> $joinTerms
     */
    private static function connectedToAvailable(string $table, array $available, array $joinTerms): bool
    {
        foreach ($joinTerms as $term) {
            if (strcasecmp($term['leftTable'], $table) === 0 && isset($available[$term['rightTable']])) {
                return true;
            }
            if (strcasecmp($term['rightTable'], $table) === 0 && isset($available[$term['leftTable']])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,bool> $available
     * @param list<array{leftTable:string,leftColumn?:string,rightTable:string,rightColumn?:string,joinType?:string}> $joinTerms
     */
    private static function respectsJoinOrderFences(string $table, array $available, array $joinTerms): bool
    {
        foreach ($joinTerms as $term) {
            $joinType = strtoupper((string) ($term['joinType'] ?? 'INNER'));
            if (in_array($joinType, ['CROSS', 'LEFT', 'LEFT OUTER'], true)
                && strcasecmp($term['rightTable'], $table) === 0
                && !isset($available[$term['leftTable']])
            ) {
                return false;
            }
            if (in_array($joinType, ['RIGHT', 'RIGHT OUTER'], true)
                && strcasecmp($term['leftTable'], $table) === 0
                && !isset($available[$term['rightTable']])
            ) {
                return false;
            }
            if (in_array($joinType, ['FULL', 'FULL OUTER'], true)) {
                if (strcasecmp($term['rightTable'], $table) === 0 && !isset($available[$term['leftTable']])) {
                    return false;
                }
                if (strcasecmp($term['leftTable'], $table) === 0 && isset($available[$term['rightTable']])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<string,bool> $available
     * @param list<array{leftTable:string,leftColumn?:string,rightTable:string,rightColumn?:string,joinType?:string}> $joinTerms
     * @return null|array{type:string,outerTable:string}
     */
    private static function joinFenceFor(string $table, array $available, array $joinTerms): ?array
    {
        foreach ($joinTerms as $term) {
            $joinType = strtoupper((string) ($term['joinType'] ?? 'INNER'));
            if (in_array($joinType, ['CROSS', 'LEFT', 'LEFT OUTER', 'FULL', 'FULL OUTER'], true)
                && strcasecmp($term['rightTable'], $table) === 0
                && isset($available[$term['leftTable']])
            ) {
                return ['type' => $joinType, 'outerTable' => $term['leftTable']];
            }
            if (in_array($joinType, ['RIGHT', 'RIGHT OUTER'], true)
                && strcasecmp($term['leftTable'], $table) === 0
                && isset($available[$term['rightTable']])
            ) {
                return ['type' => $joinType, 'outerTable' => $term['rightTable']];
            }
        }

        return null;
    }

    /**
     * @param list<string> $items
     * @return list<list<string>>
     */
    private static function permutations(array $items): array
    {
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
}
