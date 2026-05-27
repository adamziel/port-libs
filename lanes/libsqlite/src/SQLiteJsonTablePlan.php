<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonTablePlan
{
    private const VISIBLE_COLUMNS = ['key', 'value', 'type', 'atom', 'id', 'parent', 'fullkey', 'path'];

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{function:string,runnable:bool,arguments:list<mixed>,json:mixed,root:string,limit:int|null,offset:int,used:list<array<string,mixed>>,residual:list<array<string,mixed>>,estimatedCost:int,estimatedRows:int}
     */
    public static function plan(string $function, array $constraints): array
    {
        $function = self::normalizeFunction($function);
        $json = null;
        $root = '$';
        $hasJson = false;
        $hasRoot = false;
        $limit = null;
        $offset = 0;
        $hasLimit = false;
        $hasOffset = false;
        $used = [];
        $residual = [];

        foreach ($constraints as $constraint) {
            $column = strtolower($constraint['column']);
            $operator = strtoupper($constraint['operator']);
            $usable = $constraint['usable'] ?? true;
            if ($column === 'limit' || $column === 'offset') {
                if (!$usable) {
                    continue;
                }
                if ($operator !== '=') {
                    throw new \InvalidArgumentException("SQLite JSON table {$column} constraint must use equality");
                }
                if ($column === 'limit' && !$hasLimit) {
                    $limit = self::assertLimitValue($constraint['value']);
                    $hasLimit = true;
                    $used[] = $constraint + ['argvIndex' => 0, 'omit' => true, 'constraint' => 'LIMIT'];
                    continue;
                }
                if ($column === 'offset' && !$hasOffset) {
                    $offset = self::assertOffsetValue($constraint['value']);
                    $hasOffset = true;
                    $used[] = $constraint + ['argvIndex' => 0, 'omit' => true, 'constraint' => 'OFFSET'];
                    continue;
                }

                continue;
            }

            if (!$usable || $operator !== '=') {
                if ($usable && self::isVisiblePushdownConstraint($column, $operator, $constraint['value'] ?? null)) {
                    $used[] = $constraint + [
                        'argvIndex' => count($used) + 1,
                        'omit' => false,
                        'constraint' => 'VISIBLE',
                    ];
                }
                $residual[] = $constraint;
                continue;
            }

            if ($column === 'json' && !$hasJson) {
                self::assertJsonValue($constraint['value']);
                $json = $constraint['value'];
                $hasJson = true;
                $used[] = $constraint + ['argvIndex' => 1, 'omit' => true];
                continue;
            }

            if ($column === 'root' && !$hasRoot) {
                if (!is_string($constraint['value'])) {
                    throw new \InvalidArgumentException('SQLite JSON table root constraint must be text');
                }
                if (!SQLiteJsonPath::isWellFormed($constraint['value'])) {
                    throw new \InvalidArgumentException('SQLite JSON table root constraint is not a well-formed path');
                }
                $root = $constraint['value'];
                $hasRoot = true;
                $used[] = $constraint + ['argvIndex' => 2, 'omit' => true];
                continue;
            }

            if (self::isVisiblePushdownConstraint($column, $operator, $constraint['value'] ?? null)) {
                $used[] = $constraint + [
                    'argvIndex' => count($used) + 1,
                    'omit' => false,
                    'constraint' => 'VISIBLE',
                ];
            }
            $residual[] = $constraint;
        }

        $estimatedRows = $hasJson ? ($root === '$' ? 100 : 10) : 0;
        $estimatedCost = $hasJson ? ($root === '$' ? 100 : 20) : 1000000;
        foreach ($used as $constraint) {
            if (($constraint['constraint'] ?? null) !== 'VISIBLE') {
                continue;
            }

            [$estimatedRows, $estimatedCost] = self::applyVisiblePushdownEstimate(
                $estimatedRows,
                $estimatedCost,
                strtolower((string) $constraint['column']),
                strtoupper((string) $constraint['operator']),
                $constraint['value'] ?? null,
            );
        }
        if ($limit !== null) {
            $estimatedRows = min($estimatedRows, $limit);
        }
        if ($offset > 0) {
            $estimatedRows = max(0, $estimatedRows - $offset);
        }

        return [
            'function' => $function,
            'runnable' => $hasJson,
            'arguments' => $hasJson ? [$json, $root] : [],
            'json' => $json,
            'root' => $root,
            'limit' => $limit,
            'offset' => $offset,
            'used' => $used,
            'residual' => $residual,
            'estimatedCost' => $estimatedCost,
            'estimatedRows' => $estimatedRows,
        ];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{function:string,runnable:bool,arguments:list<mixed>,json:mixed,root:string,limit:int|null,offset:int,used:list<array<string,mixed>>,residual:list<array<string,mixed>>,estimatedCost:int,estimatedRows:int,jsonValid:bool|null,jsonError:string|null,jsonInputKind:string}
     */
    public static function validatedPlan(string $function, array $constraints): array
    {
        $plan = self::plan($function, $constraints);
        $validation = self::validateJsonInput($plan['json']);

        if ($plan['runnable'] && $validation['jsonValid'] === false) {
            $plan['runnable'] = false;
            $plan['estimatedCost'] = 1000000;
            $plan['estimatedRows'] = 0;
        }

        return $plan + $validation;
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return list<array<string,mixed>>
     */
    public static function rows(string $function, array $constraints): array
    {
        $plan = self::plan($function, $constraints);
        if (!$plan['runnable']) {
            return [];
        }

        $rows = $plan['function'] === 'json_each'
            ? SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', $plan['arguments'])
            : SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', $plan['arguments']);

        return self::applyLimitOffset($rows, $plan['limit'], $plan['offset']);
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return list<array<string,mixed>>
     */
    public static function filteredRows(string $function, array $constraints): array
    {
        $plan = self::plan($function, $constraints);
        if (!$plan['runnable']) {
            return [];
        }

        $rows = $plan['function'] === 'json_each'
            ? SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', $plan['arguments'])
            : SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', $plan['arguments']);

        if ($plan['residual'] !== []) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => self::rowMatchesResidualConstraints($row, $plan['residual']),
            ));
        }

        return self::applyLimitOffset($rows, $plan['limit'], $plan['offset']);
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    public static function orderedRows(string $function, array $constraints, array $orderBy, ?int $limit = null, int $offset = 0): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite JSON table ORDER BY limit must be non-negative');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite JSON table ORDER BY offset must be non-negative');
        }

        $rows = self::filteredRows($function, $constraints);
        if ($orderBy !== []) {
            usort($rows, static fn (array $left, array $right): int => self::compareRowsForOrderBy($left, $right, $orderBy));
        }

        if ($offset > 0 || $limit !== null) {
            return array_slice($rows, $offset, $limit);
        }

        return $rows;
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $partitionBy
     * @return list<array<string,mixed>>
     */
    public static function windowedRows(
        string $function,
        array $constraints,
        array $orderBy,
        array $partitionBy = [],
        int $ntileBuckets = 1,
        string $valueColumn = 'atom',
    ): array {
        if ($orderBy === []) {
            throw new \InvalidArgumentException('SQLite JSON table window rows require ORDER BY terms');
        }
        if ($ntileBuckets <= 0) {
            throw new \InvalidArgumentException('SQLite JSON table window ntile buckets must be positive');
        }

        $valueColumn = strtolower($valueColumn);
        $normalizedPartitionBy = array_map(
            static fn (string $column): string => strtolower($column),
            $partitionBy,
        );

        $rows = self::orderedRows($function, $constraints, $orderBy);
        if ($rows === []) {
            return [];
        }

        $partitions = [];
        foreach ($rows as $index => $row) {
            if (!self::rowHasColumn($row, $valueColumn)) {
                throw new \InvalidArgumentException("SQLite JSON table window value column {$valueColumn} is not available");
            }
            $partitionKey = self::partitionKey($row, $normalizedPartitionBy);
            $partitions[$partitionKey][] = $index;
        }

        foreach ($partitions as $indexes) {
            $count = count($indexes);
            $rank = 1;
            $denseRank = 1;
            $seen = 0;
            $tileAssignments = self::ntileAssignments($count, $ntileBuckets);
            for ($position = 0; $position < $count; $position++) {
                $index = $indexes[$position];
                if ($position > 0) {
                    $previousIndex = $indexes[$position - 1];
                    if (self::compareRowsForOrderBy($rows[$previousIndex], $rows[$index], $orderBy) !== 0) {
                        $rank = $position + 1;
                        $denseRank++;
                    }
                }

                $peerEnd = $position;
                while (
                    $peerEnd + 1 < $count
                    && self::compareRowsForOrderBy($rows[$index], $rows[$indexes[$peerEnd + 1]], $orderBy) === 0
                ) {
                    $peerEnd++;
                }
                $seen = max($seen, $peerEnd + 1);

                $firstIndex = $indexes[0];
                $lastIndex = $indexes[$count - 1];
                $previousIndex = $indexes[$position - 1] ?? null;
                $nextIndex = $indexes[$position + 1] ?? null;
                $rows[$index]['window_row_number'] = $position + 1;
                $rows[$index]['window_rank'] = $rank;
                $rows[$index]['window_dense_rank'] = $denseRank;
                $rows[$index]['window_percent_rank'] = $count === 1 ? 0.0 : (float) (($rank - 1) / ($count - 1));
                $rows[$index]['window_cume_dist'] = (float) ($seen / $count);
                $rows[$index]['window_ntile'] = $tileAssignments[$position];
                $rows[$index]['window_lag'] = $previousIndex === null ? null : self::rowColumnValue($rows[$previousIndex], $valueColumn);
                $rows[$index]['window_lead'] = $nextIndex === null ? null : self::rowColumnValue($rows[$nextIndex], $valueColumn);
                $rows[$index]['window_first_value'] = self::rowColumnValue($rows[$firstIndex], $valueColumn);
                $rows[$index]['window_last_value'] = self::rowColumnValue($rows[$lastIndex], $valueColumn);
            }
        }

        return $rows;
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return list<array<string,mixed>>
     */
    public static function visibleRows(string $function, array $constraints): array
    {
        return self::projectedRows($function, $constraints, self::VISIBLE_COLUMNS);
    }

    public static function invalidInputCanBeSkipped(mixed $value): bool
    {
        $validation = self::validateJsonInput($value);

        return $validation['jsonValid'] === false;
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $baseConstraints
     * @param list<list<array{column:string,operator:string,value:mixed,usable?:bool}>> $alternatives
     * @return array{function:string,runnable:bool,branches:list<array<string,mixed>>,used:list<array<string,mixed>>,residual:list<array<string,mixed>>,estimatedCost:int,estimatedRows:int}
     */
    public static function alternativePlan(string $function, array $baseConstraints, array $alternatives): array
    {
        if ($alternatives === []) {
            throw new \InvalidArgumentException('SQLite JSON table alternative plan requires at least one branch');
        }

        $function = self::normalizeFunction($function);
        $branches = [];
        $used = [];
        $residual = [];
        $estimatedRows = 0;
        $estimatedCost = 0;
        $runnable = false;

        foreach ($alternatives as $index => $alternative) {
            if (!is_array($alternative) || $alternative === []) {
                throw new \InvalidArgumentException('SQLite JSON table alternative plan branches must be non-empty constraint lists');
            }

            $branch = self::plan($function, array_merge($baseConstraints, $alternative));
            $branch['branch'] = $index;
            $branches[] = $branch;

            foreach ($branch['used'] as $constraint) {
                $used[] = $constraint + ['branch' => $index];
            }
            foreach ($branch['residual'] as $constraint) {
                $residual[] = $constraint + ['branch' => $index];
            }

            if ($branch['runnable']) {
                $runnable = true;
                $estimatedRows += $branch['estimatedRows'];
                $estimatedCost += $branch['estimatedCost'];
            }
        }

        return [
            'function' => $function,
            'runnable' => $runnable,
            'branches' => $branches,
            'used' => $used,
            'residual' => $residual,
            'estimatedCost' => $runnable ? max(1, $estimatedCost) : 1000000,
            'estimatedRows' => $runnable ? $estimatedRows : 0,
        ];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $baseConstraints
     * @param list<list<array{column:string,operator:string,value:mixed,usable?:bool}>> $alternatives
     * @return list<array<string,mixed>>
     */
    public static function filteredAlternativeRows(string $function, array $baseConstraints, array $alternatives): array
    {
        $plan = self::alternativePlan($function, $baseConstraints, $alternatives);
        if (!$plan['runnable']) {
            return [];
        }

        $rows = [];
        $seen = [];
        foreach ($plan['branches'] as $branch) {
            if (!$branch['runnable']) {
                continue;
            }

            $branchRows = self::filteredRows($function, array_merge(
                $baseConstraints,
                $alternatives[(int) $branch['branch']],
            ));
            foreach ($branchRows as $row) {
                $key = self::rowIdentityKey($row);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    public static function projectedRows(string $function, array $constraints, array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite JSON table projection must include at least one column');
        }

        $normalizedColumns = array_map(
            static fn (string $column): string => strtolower($column),
            $columns,
        );

        return array_map(
            static function (array $row) use ($normalizedColumns): array {
                $projected = [];
                foreach ($normalizedColumns as $column) {
                    if (!self::rowHasColumn($row, $column)) {
                        throw new \InvalidArgumentException("SQLite JSON table projection column {$column} is not available");
                    }

                    $projected[$column] = self::rowColumnValue($row, $column);
                }

                return $projected;
            },
            self::filteredRows($function, $constraints),
        );
    }

    /**
     * @param list<array<string,mixed>> $hostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<string> $jsonColumns
     * @return list<array<string,mixed>>
     */
    public static function hostJoinRows(
        array $hostRows,
        string $jsonColumn,
        string $function,
        array $constraints = [],
        array $jsonColumns = self::VISIBLE_COLUMNS,
        string $joinType = 'inner',
        string $jsonPrefix = 'json_',
    ): array {
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table host join requires a host JSON column');
        }
        if ($jsonColumns === []) {
            throw new \InvalidArgumentException('SQLite JSON table host join projection must include at least one JSON column');
        }

        $joinType = strtolower($joinType);
        if ($joinType !== 'inner' && $joinType !== 'left') {
            throw new \InvalidArgumentException('SQLite JSON table host join type must be inner or left');
        }
        if ($jsonPrefix === '') {
            throw new \InvalidArgumentException('SQLite JSON table host join prefix must be non-empty');
        }

        $normalizedColumns = array_map(
            static fn (string $column): string => strtolower($column),
            $jsonColumns,
        );
        foreach ($normalizedColumns as $column) {
            if ($column === '') {
                throw new \InvalidArgumentException('SQLite JSON table host join projection columns must be non-empty');
            }
        }

        $joined = [];
        foreach ($hostRows as $hostRow) {
            if (!array_key_exists($jsonColumn, $hostRow)) {
                throw new \InvalidArgumentException("SQLite JSON table host row is missing {$jsonColumn}");
            }

            $rowConstraints = array_merge(
                [['column' => 'json', 'operator' => '=', 'value' => $hostRow[$jsonColumn]]],
                $constraints,
            );
            $plan = self::validatedPlan($function, $rowConstraints);
            $jsonRows = $plan['runnable'] ? self::projectedRows($function, $rowConstraints, $normalizedColumns) : [];

            if ($jsonRows === [] && $joinType === 'left') {
                $joined[] = self::joinedHostRow($hostRow, self::nullJsonProjection($normalizedColumns), $jsonPrefix);
                continue;
            }

            foreach ($jsonRows as $jsonRow) {
                $joined[] = self::joinedHostRow($hostRow, $jsonRow, $jsonPrefix);
            }
        }

        return $joined;
    }

    private static function normalizeFunction(string $function): string
    {
        if (strcasecmp($function, 'json_each') === 0) {
            return 'json_each';
        }
        if (strcasecmp($function, 'json_tree') === 0) {
            return 'json_tree';
        }

        throw new \InvalidArgumentException('SQLite JSON table plan function must be json_each or json_tree');
    }

    private static function assertJsonValue(mixed $value): void
    {
        if ($value instanceof SQLiteBlobValue || $value instanceof SQLiteJsonSubtypeValue || $value === null || is_string($value)) {
            return;
        }

        throw new \InvalidArgumentException('SQLite JSON table json constraint must be text, BLOB, JSON subtype, or NULL');
    }

    private static function assertLimitValue(mixed $value): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException('SQLite JSON table LIMIT constraint must be an integer');
        }
        if ($value < 0) {
            throw new \InvalidArgumentException('SQLite JSON table LIMIT constraint must be non-negative');
        }

        return $value;
    }

    private static function assertOffsetValue(mixed $value): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException('SQLite JSON table OFFSET constraint must be an integer');
        }
        if ($value < 0) {
            throw new \InvalidArgumentException('SQLite JSON table OFFSET constraint must be non-negative');
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function applyLimitOffset(array $rows, ?int $limit, int $offset): array
    {
        if ($offset === 0 && $limit === null) {
            return $rows;
        }

        return array_slice($rows, $offset, $limit);
    }

    private static function isVisiblePushdownConstraint(string $column, string $operator, mixed $value): bool
    {
        if (!in_array($column, ['key', 'type', 'atom', 'id', 'parent', 'fullkey', 'path'], true)) {
            return false;
        }

        return match ($operator) {
            '=', 'IS', 'IS NOT', 'IS NULL', 'IS NOT NULL', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM',
            '<', '<=', '>', '>=', 'IN', 'BETWEEN' => true,
            'LIKE', 'GLOB' => is_string($value)
                || (is_array($value) && isset($value['pattern']) && is_string($value['pattern'])),
            default => false,
        };
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function applyVisiblePushdownEstimate(int $rows, int $cost, string $column, string $operator, mixed $value): array
    {
        if ($rows <= 0 || $cost >= 1000000) {
            return [$rows, $cost];
        }

        $selectivity = match ($operator) {
            'IS NULL', 'IS NOT NULL', 'IS DISTINCT FROM', 'IS NOT DISTINCT FROM' => 2,
            '=', 'IS' => in_array($column, ['id', 'fullkey'], true) ? 8 : 4,
            'IN' => is_array($value) ? max(2, min(6, count($value))) : 2,
            'BETWEEN', '<', '<=', '>', '>=' => 2,
            'LIKE', 'GLOB' => self::patternHasFixedPrefix($value) ? 3 : 1,
            default => 1,
        };

        if ($selectivity <= 1) {
            return [$rows, $cost];
        }

        return [
            max(1, intdiv($rows + $selectivity - 1, $selectivity)),
            max(1, intdiv($cost + $selectivity - 1, $selectivity)),
        ];
    }

    private static function patternHasFixedPrefix(mixed $value): bool
    {
        $pattern = is_array($value) ? ($value['pattern'] ?? null) : $value;
        if (!is_string($pattern) || $pattern === '') {
            return false;
        }

        $firstWildcard = strcspn($pattern, '%_*?[');

        return $firstWildcard > 0;
    }

    /**
     * @return array{jsonValid:bool|null,jsonError:string|null,jsonInputKind:string}
     */
    private static function validateJsonInput(mixed $value): array
    {
        if ($value === null) {
            return [
                'jsonValid' => null,
                'jsonError' => null,
                'jsonInputKind' => 'sql-null',
            ];
        }

        if ($value instanceof SQLiteJsonSubtypeValue) {
            $validSubtype = SQLiteJsonValidity::jsonValid(
                $value->json,
                SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT,
            );

            return [
                'jsonValid' => $validSubtype,
                'jsonError' => $validSubtype ? null : 'malformed JSON subtype',
                'jsonInputKind' => 'json-subtype',
            ];
        }

        if ($value instanceof SQLiteBlobValue) {
            if (SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                return SQLiteJsonB::isStrictlyWellFormed($value->bytes)
                    ? [
                        'jsonValid' => true,
                        'jsonError' => null,
                        'jsonInputKind' => 'jsonb',
                    ]
                    : [
                        'jsonValid' => false,
                        'jsonError' => 'malformed JSONB',
                        'jsonInputKind' => 'jsonb',
                    ];
            }

            $validTextBlob = SQLiteJsonValidity::jsonValid(
                $value,
                SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT,
            );

            return [
                'jsonValid' => $validTextBlob,
                'jsonError' => $validTextBlob ? null : 'malformed JSON text BLOB',
                'jsonInputKind' => 'text-blob',
            ];
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('SQLite JSON table json constraint must be text, BLOB, JSON subtype, or NULL');
        }

        $validText = SQLiteJsonValidity::jsonValid(
            $value,
            SQLiteJsonValidity::FLAG_STRICT_TEXT | SQLiteJsonValidity::FLAG_JSON5_TEXT,
        );

        return [
            'jsonValid' => $validText,
            'jsonError' => $validText ? null : 'malformed JSON text',
            'jsonInputKind' => 'text',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $constraints
     */
    private static function rowMatchesResidualConstraints(array $row, array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            $column = strtolower((string) $constraint['column']);
            if (!self::rowHasColumn($row, $column)) {
                throw new \InvalidArgumentException("SQLite JSON table residual column {$column} is not available");
            }

            if (!self::compareResidualValue(self::rowColumnValue($row, $column), strtoupper((string) $constraint['operator']), $constraint['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function compareResidualValue(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=' => self::valuesAreEqual($actual, $expected),
            'IS' => self::valuesAreNotDistinct($actual, $expected),
            'IS NULL' => $actual === null,
            '!=', '<>' => !self::valuesAreEqual($actual, $expected),
            'IS NOT' => !self::valuesAreNotDistinct($actual, $expected),
            'IS NOT NULL' => $actual !== null,
            'IS DISTINCT FROM' => !self::valuesAreNotDistinct($actual, $expected),
            'IS NOT DISTINCT FROM' => self::valuesAreNotDistinct($actual, $expected),
            'LIKE' => self::compareResidualLike($actual, $expected),
            'NOT LIKE' => !self::compareResidualLike($actual, $expected),
            'GLOB' => self::compareResidualGlob($actual, $expected),
            'NOT GLOB' => !self::compareResidualGlob($actual, $expected),
            'REGEXP' => self::compareResidualRegexp($actual, $expected),
            'NOT REGEXP' => !self::compareResidualRegexp($actual, $expected),
            'MATCH' => self::compareResidualMatch($actual, $expected),
            'NOT MATCH' => !self::compareResidualMatch($actual, $expected),
            'IN' => self::compareResidualIn($actual, $expected),
            'NOT IN' => self::compareResidualNotIn($actual, $expected),
            'BETWEEN' => self::compareResidualBetween($actual, $expected),
            'NOT BETWEEN' => self::compareResidualNotBetween($actual, $expected),
            '<', '<=', '>', '>=' => self::compareResidualOrderedPredicate($actual, $operator, $expected),
            default => throw new \InvalidArgumentException("SQLite JSON table residual operator {$operator} is not supported"),
        };
    }

    private static function compareResidualBetween(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected) || count($expected) !== 2) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator BETWEEN expects a two-value list');
        }

        [$lower, $upper] = array_values($expected);
        if ($actual === null || $lower === null || $upper === null) {
            return false;
        }

        return self::compareResidualOrdered($actual, $lower) >= 0
            && self::compareResidualOrdered($actual, $upper) <= 0;
    }

    private static function compareResidualNotBetween(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected) || count($expected) !== 2) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator BETWEEN expects a two-value list');
        }

        [$lower, $upper] = array_values($expected);
        if ($actual === null || $lower === null || $upper === null) {
            return false;
        }

        return self::compareResidualOrdered($actual, $lower) < 0
            || self::compareResidualOrdered($actual, $upper) > 0;
    }


    private static function compareResidualIn(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator IN expects a list value');
        }
        if ($actual === null) {
            return false;
        }

        foreach ($expected as $value) {
            if ($value !== null && self::valuesAreEqual($actual, $value)) {
                return true;
            }
        }

        return false;
    }

    private static function compareResidualNotIn(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator NOT IN expects a list value');
        }
        if ($actual === null || in_array(null, $expected, true)) {
            return false;
        }

        return !self::compareResidualIn($actual, $expected);
    }

    private static function compareResidualLike(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }
        if (!is_string($actual)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator LIKE expects text row values');
        }

        $caseSensitive = false;
        $escape = null;
        if (is_array($expected)) {
            if (!array_key_exists('pattern', $expected)) {
                throw new \InvalidArgumentException('SQLite JSON table residual operator LIKE expects a pattern payload');
            }
            $caseSensitive = (bool) ($expected['caseSensitive'] ?? false);
            $escape = $expected['escape'] ?? null;
            $expected = $expected['pattern'];
        }

        if (!is_string($expected) || ($escape !== null && !is_string($escape))) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator LIKE expects text values');
        }

        return SQLiteDatabase::likeMatches($actual, $expected, $escape, $caseSensitive);
    }

    private static function compareResidualGlob(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }
        if (!is_string($actual) || !is_string($expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator GLOB expects text values');
        }

        return SQLiteDatabase::globMatches($actual, $expected);
    }

    private static function compareResidualRegexp(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }
        if (!is_string($actual)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator REGEXP expects text row values');
        }
        if (!is_array($expected) || !array_key_exists('pattern', $expected) || !array_key_exists('regexp', $expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator REGEXP expects a pattern and callback payload');
        }
        if (!is_string($expected['pattern']) || !is_callable($expected['regexp'])) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator REGEXP expects a text pattern and callable callback');
        }

        return SQLiteDatabase::regexpMatches($actual, $expected['pattern'], $expected['regexp']);
    }

    private static function compareResidualMatch(mixed $actual, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }
        if (!is_string($actual)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator MATCH expects text row values');
        }
        if (!is_array($expected) || !array_key_exists('pattern', $expected) || !array_key_exists('match', $expected)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator MATCH expects a pattern and callback payload');
        }
        if (!is_string($expected['pattern']) || !is_callable($expected['match'])) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator MATCH expects a text pattern and callable callback');
        }

        $matched = $expected['match']($expected['pattern'], $actual);
        if (!is_bool($matched)) {
            throw new \InvalidArgumentException('SQLite JSON table residual operator MATCH callback must return bool');
        }

        return $matched;
    }

    private static function compareResidualOrderedPredicate(mixed $actual, string $operator, mixed $expected): bool
    {
        if ($actual === null || $expected === null) {
            return false;
        }

        $comparison = self::compareResidualOrdered($actual, $expected);

        return match ($operator) {
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            default => throw new \InvalidArgumentException("SQLite JSON table residual operator {$operator} is not supported"),
        };
    }

    private static function compareResidualOrdered(mixed $actual, mixed $expected): int
    {
        $actualClass = self::sqliteSortClass($actual);
        $expectedClass = self::sqliteSortClass($expected);
        if ($actualClass !== $expectedClass) {
            return $actualClass <=> $expectedClass;
        }

        if ($actualClass === 1) {
            return ((float) $actual) <=> ((float) $expected);
        }

        if ($actualClass === 2) {
            return strcmp((string) $actual, (string) $expected);
        }

        throw new \InvalidArgumentException('SQLite JSON table residual ordered comparison supports only NULL, numeric, and text values');
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function compareRowsForOrderBy(array $left, array $right, array $orderBy): int
    {
        foreach ($orderBy as $term) {
            $column = strtolower($term['column']);
            if (!self::rowHasColumn($left, $column) || !self::rowHasColumn($right, $column)) {
                throw new \InvalidArgumentException("SQLite JSON table ORDER BY column {$column} is not available");
            }

            $direction = strtoupper($term['direction'] ?? 'ASC');
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite JSON table ORDER BY direction must be ASC or DESC');
            }

            $comparison = self::compareResidualOrdered(self::rowColumnValue($left, $column), self::rowColumnValue($right, $column));
            if ($comparison !== 0) {
                return $direction === 'DESC' ? -$comparison : $comparison;
            }
        }

        return 0;
    }

    /**
     * @param list<string> $partitionBy
     */
    private static function partitionKey(array $row, array $partitionBy): string
    {
        if ($partitionBy === []) {
            return '__all__';
        }

        $values = [];
        foreach ($partitionBy as $column) {
            if (!self::rowHasColumn($row, $column)) {
                throw new \InvalidArgumentException("SQLite JSON table window partition column {$column} is not available");
            }
            $values[] = self::rowColumnValue($row, $column);
        }

        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<int>
     */
    private static function ntileAssignments(int $count, int $buckets): array
    {
        $baseSize = intdiv($count, $buckets);
        $largerBuckets = $count % $buckets;
        $assignments = [];
        for ($bucket = 1; $bucket <= min($buckets, $count); $bucket++) {
            $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
            array_push($assignments, ...array_fill(0, $size, $bucket));
        }

        return $assignments;
    }

    private static function sqliteSortClass(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }

        throw new \InvalidArgumentException('SQLite JSON table residual ordered comparison supports only NULL, numeric, and text values');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowHasColumn(array $row, string $column): bool
    {
        return array_key_exists($column, $row) || self::isRowIdAlias($column);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowColumnValue(array $row, string $column): mixed
    {
        if (self::isRowIdAlias($column)) {
            return $row['id'];
        }

        return $row[$column];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdentityKey(array $row): string
    {
        return json_encode([
            $row['json'] ?? null,
            $row['root'] ?? null,
            $row['id'] ?? null,
            $row['fullkey'] ?? null,
        ], JSON_THROW_ON_ERROR);
    }

    private static function isRowIdAlias(string $column): bool
    {
        return $column === 'rowid' || $column === '_rowid_' || $column === 'oid';
    }

    private static function valuesAreNotDistinct(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return self::valuesAreEqual($left, $right);
    }

    private static function valuesAreEqual(mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
    }

    /**
     * @param array<string,mixed> $hostRow
     * @param array<string,mixed> $jsonRow
     * @return array<string,mixed>
     */
    private static function joinedHostRow(array $hostRow, array $jsonRow, string $jsonPrefix): array
    {
        foreach ($jsonRow as $column => $value) {
            $hostRow[$jsonPrefix . $column] = $value;
        }

        return $hostRow;
    }

    /**
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function nullJsonProjection(array $columns): array
    {
        $projection = [];
        foreach ($columns as $column) {
            $projection[$column] = null;
        }

        return $projection;
    }
}
