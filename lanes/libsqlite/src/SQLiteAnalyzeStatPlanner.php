<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAnalyzeStatPlanner
{
    /**
     * @param list<array{tbl:string,idx:?string,stat:string}> $statRows
     * @param list<array{name:string,table:string,columns:list<string>,unique?:bool}> $indexes
     * @param list<array{column:string,operator:string,value?:mixed,values?:list<mixed>}> $constraints
     * @return array<string,mixed>
     */
    public static function choose(array $statRows, array $indexes, string $table, array $constraints): array
    {
        $plans = self::rankedPlans($statRows, $indexes, $table, $constraints);

        return $plans[0] ?? [
            'access' => 'table-scan',
            'table' => $table,
            'estimatedRows' => self::tableRows($statRows, $table),
            'estimatedCost' => self::tableRows($statRows, $table),
            'matchedColumns' => [],
            'detail' => 'SCAN ' . $table,
        ];
    }

    /**
     * @param list<array{tbl:string,idx:?string,stat:string}> $statRows
     * @param list<array{name:string,table:string,columns:list<string>,unique?:bool}> $indexes
     * @param list<array{column:string,operator:string,value?:mixed,values?:list<mixed>}> $constraints
     * @return list<array<string,mixed>>
     */
    public static function rankedPlans(array $statRows, array $indexes, string $table, array $constraints): array
    {
        $tableRows = self::tableRows($statRows, $table);
        $stats = self::statsByIndex($statRows, $table);
        $plans = [];

        foreach ($indexes as $index) {
            if (($index['table'] ?? null) !== $table) {
                continue;
            }

            $name = self::requiredString($index, 'name', 'SQLite ANALYZE planner index');
            $columns = self::requiredStringList($index, 'columns', 'SQLite ANALYZE planner index');
            $numbers = $stats[$name] ?? [$tableRows];
            $totalRows = max(1, $numbers[0] ?? $tableRows);
            $matched = self::matchedPrefix($columns, $constraints);
            if ($matched === []) {
                continue;
            }

            $estimatedRows = $totalRows;
            $rangeUsed = false;
            foreach ($matched as $position => $constraint) {
                $operator = strtoupper($constraint['operator']);
                $distinctAverage = max(1, $numbers[$position + 1] ?? $totalRows);
                if (in_array($operator, ['=', '==', 'IS'], true)) {
                    $estimatedRows = min($estimatedRows, $distinctAverage);
                    continue;
                }
                if ($operator === 'IN') {
                    $values = $constraint['values'] ?? [];
                    $estimatedRows = min($estimatedRows, max(1, $distinctAverage * max(1, count($values))));
                    continue;
                }
                if (in_array($operator, ['>', '>=', '<', '<=', 'BETWEEN'], true)) {
                    $estimatedRows = min($estimatedRows, max(1, (int) ceil($distinctAverage * 4)));
                    $rangeUsed = true;
                    break;
                }
                break;
            }

            $allEquality = true;
            foreach ($matched as $constraint) {
                if (!in_array(strtoupper($constraint['operator']), ['=', '==', 'IS'], true)) {
                    $allEquality = false;
                    break;
                }
            }

            if (($index['unique'] ?? false) && count($matched) >= count($columns) && !$rangeUsed && $allEquality) {
                $estimatedRows = 1;
            }

            $estimatedRows = max(1, min($tableRows, $estimatedRows));
            $plans[] = [
                'access' => 'index',
                'table' => $table,
                'index' => $name,
                'matchedColumns' => array_map(static fn (array $constraint): string => $constraint['column'], $matched),
                'matchedConstraints' => $matched,
                'estimatedRows' => $estimatedRows,
                'estimatedCost' => $estimatedRows + max(1, count($matched)),
                'stat' => implode(' ', $numbers),
                'detail' => 'SEARCH ' . $table . ' USING INDEX ' . $name . ' (' . implode(',', array_map(
                    static fn (array $constraint): string => $constraint['column'] . $constraint['operator'] . '?',
                    $matched
                )) . ')',
            ];
        }

        usort($plans, static fn (array $left, array $right): int => [
            $left['estimatedCost'],
            -count($left['matchedColumns']),
            (string) $left['index'],
        ] <=> [
            $right['estimatedCost'],
            -count($right['matchedColumns']),
            (string) $right['index'],
        ]);

        return $plans;
    }

    /**
     * @param list<array{tbl:string,idx:?string,stat:string}> $statRows
     */
    private static function tableRows(array $statRows, string $table): int
    {
        foreach ($statRows as $row) {
            if (($row['tbl'] ?? null) === $table && (($row['idx'] ?? null) === null || ($row['idx'] ?? '') === '')) {
                return max(1, self::parseStat($row['stat'])[0] ?? 1);
            }
        }

        foreach ($statRows as $row) {
            if (($row['tbl'] ?? null) === $table) {
                return max(1, self::parseStat($row['stat'])[0] ?? 1);
            }
        }

        return 1000000;
    }

    /**
     * @param list<array{tbl:string,idx:?string,stat:string}> $statRows
     * @return array<string,list<int>>
     */
    private static function statsByIndex(array $statRows, string $table): array
    {
        $stats = [];
        foreach ($statRows as $row) {
            if (($row['tbl'] ?? null) !== $table || !is_string($row['idx'] ?? null) || $row['idx'] === '') {
                continue;
            }
            $stats[$row['idx']] = self::parseStat($row['stat']);
        }

        return $stats;
    }

    /**
     * @return list<int>
     */
    private static function parseStat(string $stat): array
    {
        $parts = preg_split('/\s+/', trim($stat));
        if ($parts === false || $parts === ['']) {
            throw new \InvalidArgumentException('SQLite sqlite_stat1 row needs numeric stat values');
        }

        return array_map(static function (string $part): int {
            if (!preg_match('/^\d+$/', $part)) {
                throw new \InvalidArgumentException('SQLite sqlite_stat1 stat values must be unsigned integers');
            }

            return max(1, (int) $part);
        }, $parts);
    }

    /**
     * @param list<string> $columns
     * @param list<array{column:string,operator:string,value?:mixed,values?:list<mixed>}> $constraints
     * @return list<array{column:string,operator:string,value?:mixed,values?:list<mixed>}>
     */
    private static function matchedPrefix(array $columns, array $constraints): array
    {
        $byColumn = [];
        foreach ($constraints as $constraint) {
            $column = strtolower(self::requiredString($constraint, 'column', 'SQLite ANALYZE planner constraint'));
            if (!self::usableOperator($constraint['operator'] ?? null)) {
                continue;
            }

            $byColumn[$column][] = $constraint;
        }

        $matched = [];
        foreach ($columns as $column) {
            $constraint = self::bestColumnConstraint($byColumn[strtolower($column)] ?? []);
            if ($constraint === null || !self::usableOperator($constraint['operator'] ?? null)) {
                break;
            }
            $matched[] = $constraint;
            if (!in_array(strtoupper($constraint['operator']), ['=', '==', 'IS', 'IN'], true)) {
                break;
            }
        }

        return $matched;
    }

    /**
     * @param list<array{column:string,operator:string,value?:mixed,values?:list<mixed>}> $constraints
     * @return null|array{column:string,operator:string,value?:mixed,values?:list<mixed>,rangeConstraints?:list<array{operator:string,value?:mixed,values?:list<mixed>}>}
     */
    private static function bestColumnConstraint(array $constraints): ?array
    {
        $equalities = [];
        $inLists = [];
        $ranges = [];
        foreach ($constraints as $constraint) {
            $operator = strtoupper($constraint['operator']);
            if (in_array($operator, ['=', '==', 'IS'], true)) {
                $equalities[] = $constraint;
                continue;
            }
            if ($operator === 'IN') {
                $inLists[] = $constraint;
                continue;
            }
            if (in_array($operator, ['>', '>=', '<', '<=', 'BETWEEN'], true)) {
                $ranges[] = $constraint;
            }
        }

        if ($equalities !== []) {
            return self::normalizeOperator($equalities[0]);
        }

        if ($inLists !== []) {
            return self::normalizeOperator($inLists[0]);
        }

        if ($ranges === []) {
            return null;
        }

        $lower = null;
        $upper = null;
        $between = null;
        foreach ($ranges as $range) {
            $operator = strtoupper($range['operator']);
            if ($operator === 'BETWEEN') {
                $between = $range;
                continue;
            }
            if ($operator === '>' || $operator === '>=') {
                $lower = $range;
                continue;
            }
            if ($operator === '<' || $operator === '<=') {
                $upper = $range;
            }
        }

        if ($lower !== null && $upper !== null) {
            return [
                'column' => self::requiredString($lower, 'column', 'SQLite ANALYZE planner range constraint'),
                'operator' => 'BETWEEN',
                'values' => [$lower['value'] ?? null, $upper['value'] ?? null],
                'rangeConstraints' => [
                    ['operator' => strtoupper($lower['operator']), 'value' => $lower['value'] ?? null],
                    ['operator' => strtoupper($upper['operator']), 'value' => $upper['value'] ?? null],
                ],
            ];
        }

        if ($between !== null) {
            return self::normalizeOperator($between);
        }

        return self::normalizeOperator($ranges[0]);
    }

    /**
     * @param array{column:string,operator:string,value?:mixed,values?:list<mixed>} $constraint
     * @return array{column:string,operator:string,value?:mixed,values?:list<mixed>}
     */
    private static function normalizeOperator(array $constraint): array
    {
        $constraint['operator'] = strtoupper($constraint['operator']);

        return $constraint;
    }

    private static function usableOperator(mixed $operator): bool
    {
        return is_string($operator) && in_array(strtoupper($operator), ['=', '==', 'IS', 'IN', '>', '>=', '<', '<=', 'BETWEEN'], true);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function requiredString(array $row, string $key, string $context): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException($context . ' needs ' . $key);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     * @return list<string>
     */
    private static function requiredStringList(array $row, string $key, string $context): array
    {
        $value = $row[$key] ?? null;
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException($context . ' needs ' . $key);
        }
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException($context . ' needs string ' . $key);
            }
        }

        return array_values($value);
    }
}
