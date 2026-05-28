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
            $column = self::normalizeConstraintColumn((string) $constraint['column']);
            $constraint['column'] = $column;
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
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,idxNum:int,idxStr:string,runnable:bool,arguments:list<mixed>,filterArguments:list<mixed>,constraintUsage:list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}>,filterCurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,currentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,used:list<array<string,mixed>>,residual:list<array<string,mixed>>,orderByConsumed:bool,estimatedCost:int,estimatedRows:int}
     */
    public static function xBestIndexPlan(string $function, array $constraints, array $orderBy = []): array
    {
        $indexedConstraints = [];
        foreach ($constraints as $index => $constraint) {
            $indexedConstraints[] = $constraint + ['constraintIndex' => $index];
        }

        $plan = self::plan($function, $indexedConstraints);
        $usage = [];
        foreach ($plan['used'] as $constraint) {
            $usage[] = self::constraintUsage($constraint, true);
        }
        foreach ($plan['residual'] as $constraint) {
            $constraintIndex = $constraint['constraintIndex'] ?? null;
            if (!is_int($constraintIndex) || self::constraintIndexAlreadyUsed($usage, $constraintIndex)) {
                continue;
            }
            $usage[] = self::constraintUsage($constraint, false);
        }

        usort($usage, static fn (array $left, array $right): int => $left['constraintIndex'] <=> $right['constraintIndex']);
        $filterArguments = self::filterArguments($plan['used']);
        $filterUsage = self::filterConstraintUsage($usage);

        return [
            'function' => $plan['function'],
            'idxNum' => self::jsonTableIdxNum($plan),
            'idxStr' => self::jsonTableIdxStr($plan),
            'runnable' => $plan['runnable'],
            'arguments' => $plan['arguments'],
            'filterArguments' => $filterArguments,
            'constraintUsage' => $usage,
            'filterCurrentNext' => self::constraintCurrentNext($filterUsage),
            'currentNext' => self::constraintCurrentNext($usage),
            'used' => $plan['used'],
            'residual' => $plan['residual'],
            'orderByConsumed' => self::jsonTableOrderByConsumed($orderBy),
            'estimatedCost' => $plan['estimatedCost'],
            'estimatedRows' => $plan['estimatedRows'],
        ];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $currentConstraints
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $nextConstraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,current:array<string,mixed>,next:array<string,mixed>,replanRequired:bool,replanReason:string,currentArguments:list<mixed>,nextArguments:list<mixed>,argumentTransitions:list<array{index:int,current:mixed,next:mixed,changed:bool}>,usageTransitions:list<array{index:int,current:array<string,mixed>|null,next:array<string,mixed>|null,changed:bool}>,currentReaderPolicy:string,nextReaderPolicy:string,dependencies:list<string>}
     */
    public static function constraintPlannerCurrentNext72(
        string $function,
        array $currentConstraints,
        array $nextConstraints,
        array $orderBy = [],
    ): array {
        $current = self::xBestIndexPlan($function, $currentConstraints, $orderBy);
        $next = self::xBestIndexPlan($function, $nextConstraints, $orderBy);
        $argumentTransitions = self::argumentTransitions($current['filterArguments'], $next['filterArguments']);
        $usageTransitions = self::usageTransitions($current['constraintUsage'], $next['constraintUsage']);

        $replanRequired = self::jsonTablePlanSignature($current) !== self::jsonTablePlanSignature($next);

        return [
            'function' => $current['function'],
            'current' => $current,
            'next' => $next,
            'replanRequired' => $replanRequired,
            'replanReason' => self::jsonTableReplanReason($current, $next, $argumentTransitions, $usageTransitions),
            'currentArguments' => $current['filterArguments'],
            'nextArguments' => $next['filterArguments'],
            'argumentTransitions' => $argumentTransitions,
            'usageTransitions' => $usageTransitions,
            'currentReaderPolicy' => 'keep-current-json-table-plan-until-statement-reset',
            'nextReaderPolicy' => $replanRequired ? 'prepare-next-json-table-xbestindex-plan' : 'reuse-current-json-table-plan',
            'dependencies' => ['sqlite-json-table-constraint-planner-current-next72'],
        ];
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
     * @return array{function:string,runnable:bool,idxNum:int,idxStr:string,filterArguments:list<mixed>,constraintUsage:list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}>,filterCurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null}>,rowCurrentNext:list<array{current:array<string,mixed>,next:array<string,mixed>|null,currentIndex:int,nextIndex:int|null,currentId:int|null,nextId:int|null,sameParent:bool,samePath:bool}>,estimatedCost:int,estimatedRows:int}
     */
    public static function currentNextConstraintPlan(string $function, array $constraints, array $orderBy = []): array
    {
        $indexPlan = self::xBestIndexPlan($function, $constraints, $orderBy);
        $validatedPlan = self::validatedPlan($function, $constraints);
        if (!$validatedPlan['runnable'] && ($validatedPlan['jsonInputKind'] === 'jsonb' || $validatedPlan['jsonInputKind'] === 'sql-null')) {
            $rows = [];
        } else {
            $rows = $indexPlan['orderByConsumed']
                ? self::filteredRows($function, $constraints)
                : self::orderedRows($function, $constraints, $orderBy);
        }

        $rowPairs = [];
        $count = count($rows);
        for ($index = 0; $index < $count; $index++) {
            $current = $rows[$index];
            $next = $rows[$index + 1] ?? null;

            $rowPairs[] = [
                'current' => $current,
                'next' => $next,
                'currentIndex' => $index,
                'nextIndex' => $next === null ? null : $index + 1,
                'currentId' => isset($current['id']) ? (int) $current['id'] : null,
                'nextId' => isset($next['id']) ? (int) $next['id'] : null,
                'sameParent' => $next !== null && ($current['parent'] ?? null) === ($next['parent'] ?? null),
                'samePath' => $next !== null && ($current['path'] ?? null) === ($next['path'] ?? null),
            ];
        }

        return [
            'function' => $indexPlan['function'],
            'runnable' => $indexPlan['runnable'],
            'idxNum' => $indexPlan['idxNum'],
            'idxStr' => $indexPlan['idxStr'],
            'filterArguments' => $indexPlan['filterArguments'],
            'constraintUsage' => $indexPlan['constraintUsage'],
            'filterCurrentNext' => $indexPlan['filterCurrentNext'],
            'rowCurrentNext' => $rowPairs,
            'estimatedCost' => $indexPlan['estimatedCost'],
            'estimatedRows' => $indexPlan['estimatedRows'],
        ];
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
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<string> $partitionBy
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null,partitionKey:string,currentIndex:int,nextIndex:int|null,currentRank:int,nextRank:int|null,samePeer:bool,samePartition:bool}>
     */
    public static function rankedCurrentNextRows(
        string $function,
        array $constraints,
        array $orderBy,
        array $partitionBy = [],
        int $ntileBuckets = 1,
        string $valueColumn = 'atom',
    ): array {
        $rows = self::windowedRows($function, $constraints, $orderBy, $partitionBy, $ntileBuckets, $valueColumn);
        if ($rows === []) {
            return [];
        }

        $normalizedPartitionBy = array_map(
            static fn (string $column): string => strtolower($column),
            $partitionBy,
        );
        $pairs = [];
        foreach ($rows as $index => $row) {
            $next = $rows[$index + 1] ?? null;
            $partitionKey = self::partitionKey($row, $normalizedPartitionBy);
            $nextPartitionKey = $next === null ? null : self::partitionKey($next, $normalizedPartitionBy);
            $samePartition = $next !== null && $partitionKey === $nextPartitionKey;
            $currentRank = (int) $row['window_rank'];
            $nextRank = $samePartition ? (int) $next['window_rank'] : null;

            $pairs[] = [
                'current' => $row,
                'next' => $samePartition ? $next : null,
                'partitionKey' => $partitionKey,
                'currentIndex' => $index,
                'nextIndex' => $samePartition ? $index + 1 : null,
                'currentRank' => $currentRank,
                'nextRank' => $nextRank,
                'samePeer' => $nextRank !== null && $currentRank === $nextRank,
                'samePartition' => $samePartition,
            ];
        }

        return $pairs;
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

    /**
     * @param list<array<string,mixed>> $currentHostRows
     * @param list<array<string,mixed>> $nextHostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,current:list<array<string,mixed>>,next:list<array<string,mixed>>,transitions:list<array<string,mixed>>,replanRequired:bool,replanReasons:list<string>,currentReaderPolicy:string,nextReaderPolicy:string,dependencies:list<string>}
     */
    public static function lateralConstraintPlannerCurrentNext75(
        array $currentHostRows,
        array $nextHostRows,
        string $jsonColumn,
        string $function,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral planner requires a host JSON column');
        }
        if ($rootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral planner root column must be non-empty when provided');
        }

        $function = self::normalizeFunction($function);
        $current = self::lateralHostPlans($currentHostRows, $jsonColumn, $function, $constraints, $rootColumn, $orderBy);
        $next = self::lateralHostPlans($nextHostRows, $jsonColumn, $function, $constraints, $rootColumn, $orderBy);
        $count = max(count($current), count($next));
        $transitions = [];
        $replanReasons = [];

        for ($index = 0; $index < $count; $index++) {
            $currentPlan = $current[$index] ?? null;
            $nextPlan = $next[$index] ?? null;
            $reason = self::lateralTransitionReason($currentPlan, $nextPlan);
            if ($reason !== 'stable-lateral-json-plan') {
                $replanReasons[$reason] = true;
            }

            $transitions[] = [
                'index' => $index,
                'current' => $currentPlan,
                'next' => $nextPlan,
                'changed' => $reason !== 'stable-lateral-json-plan',
                'reason' => $reason,
                'currentFilterArguments' => $currentPlan['filterArguments'] ?? [],
                'nextFilterArguments' => $nextPlan['filterArguments'] ?? [],
                'argumentTransitions' => self::argumentTransitions(
                    $currentPlan['filterArguments'] ?? [],
                    $nextPlan['filterArguments'] ?? [],
                ),
            ];
        }

        return [
            'function' => $function,
            'current' => $current,
            'next' => $next,
            'transitions' => $transitions,
            'replanRequired' => $replanReasons !== [],
            'replanReasons' => array_keys($replanReasons),
            'currentReaderPolicy' => 'keep-current-lateral-json-table-plan-until-host-row-advances',
            'nextReaderPolicy' => $replanReasons === []
                ? 'reuse-current-lateral-json-table-plan'
                : 'prepare-next-lateral-json-table-plan-for-host-row',
            'dependencies' => ['sqlite-json-table-lateral-planner-constraint-current-next75'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentHostRows
     * @param list<array<string,mixed>> $nextHostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<string> $jsonColumns
     * @return array{function:string,current:list<array<string,mixed>>,next:list<array<string,mixed>>,transitions:list<array<string,mixed>>,currentReaderPolicy:string,nextReaderPolicy:string,dependencies:list<string>}
     */
    public static function lateralRowidCurrentNext81(
        array $currentHostRows,
        array $nextHostRows,
        string $jsonColumn,
        string $function,
        array $constraints = [],
        ?string $rootColumn = null,
        array $jsonColumns = self::VISIBLE_COLUMNS,
        string $joinType = 'inner',
        string $jsonPrefix = 'json_',
    ): array {
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral rowid requires a host JSON column');
        }
        if ($rootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral rowid root column must be non-empty when provided');
        }

        $function = self::normalizeFunction($function);
        $current = self::lateralRowidRows($currentHostRows, $jsonColumn, $function, $constraints, $rootColumn, $jsonColumns, $joinType, $jsonPrefix);
        $next = self::lateralRowidRows($nextHostRows, $jsonColumn, $function, $constraints, $rootColumn, $jsonColumns, $joinType, $jsonPrefix);
        $count = max(count($current), count($next));
        $transitions = [];
        for ($index = 0; $index < $count; $index++) {
            $currentRow = $current[$index] ?? null;
            $nextRow = $next[$index] ?? null;
            $currentRowid = $currentRow[$jsonPrefix . 'rowid'] ?? null;
            $nextRowid = $nextRow[$jsonPrefix . 'rowid'] ?? null;
            $reason = self::lateralRowidTransitionReason($currentRow, $nextRow, $jsonPrefix);
            $transitions[] = [
                'index' => $index,
                'current' => $currentRow,
                'next' => $nextRow,
                'currentRowid' => $currentRowid,
                'nextRowid' => $nextRowid,
                'rowidChanged' => $currentRowid !== $nextRowid,
                'hostChanged' => ($currentRow['__host_index'] ?? null) !== ($nextRow['__host_index'] ?? null),
                'changed' => $reason !== 'stable-lateral-json-rowid',
                'reason' => $reason,
            ];
        }

        return [
            'function' => $function,
            'current' => $current,
            'next' => $next,
            'transitions' => $transitions,
            'currentReaderPolicy' => 'keep-current-lateral-json-rowid-until-host-row-advances',
            'nextReaderPolicy' => self::lateralRowidRowsSignature($current, $jsonPrefix) === self::lateralRowidRowsSignature($next, $jsonPrefix)
                ? 'reuse-current-lateral-json-rowid-tape'
                : 'materialize-next-lateral-json-rowid-tape',
            'dependencies' => ['sqlite-json-table-lateral-rowid-current-next81'],
        ];
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,current:array<string,mixed>,next:array<string,mixed>,replanRequired:bool,replanReasons:list<string>,sourceTransitions:list<array{field:string,current:mixed,next:mixed,changed:bool}>,argumentTransitions:list<array{index:int,current:mixed,next:mixed,changed:bool}>,usageTransitions:list<array{index:int,current:array<string,mixed>|null,next:array<string,mixed>|null,changed:bool}>,currentRows:list<array<string,mixed>>,nextRows:list<array<string,mixed>>,currentReaderPolicy:string,nextReaderPolicy:string,dependencies:list<string>}
     */
    public static function currentSourceConstraintPlannerNext86(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table current-source planner requires a JSON source column');
        }
        if ($rootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table current-source planner root column must be non-empty when provided');
        }

        $function = self::normalizeFunction($function);
        $current = self::sourceConstraintPlan86($function, $currentSource, $jsonColumn, $constraints, $rootColumn, $orderBy);
        $next = self::sourceConstraintPlan86($function, $nextSource, $jsonColumn, $constraints, $rootColumn, $orderBy);
        $sourceTransitions = self::sourceTransitions86($current, $next);
        $argumentTransitions = self::argumentTransitions($current['filterArguments'], $next['filterArguments']);
        $usageTransitions = self::usageTransitions($current['constraintUsage'], $next['constraintUsage']);
        $replanReasons = self::currentSourceReplanReasons86($current, $next, $sourceTransitions, $argumentTransitions, $usageTransitions);

        return [
            'function' => $function,
            'current' => $current,
            'next' => $next,
            'replanRequired' => $replanReasons !== [],
            'replanReasons' => $replanReasons,
            'sourceTransitions' => $sourceTransitions,
            'argumentTransitions' => $argumentTransitions,
            'usageTransitions' => $usageTransitions,
            'currentRows' => $current['rows'],
            'nextRows' => $next['rows'],
            'currentReaderPolicy' => 'pin-current-json-table-source-until-cursor-reset',
            'nextReaderPolicy' => $replanReasons === []
                ? 'reuse-current-json-table-source-plan'
                : 'prepare-next-json-table-source-plan',
            'dependencies' => ['sqlite-json-table-constraint-planner-current-source-next86'],
        ];
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceHiddenConstraintPlannerNext88(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceConstraintPlannerNext86(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentHiddenResiduals = self::hiddenResidualConstraints88($plan['current']['constraintUsage']);
        $nextHiddenResiduals = self::hiddenResidualConstraints88($plan['next']['constraintUsage']);
        $rowCountTransition = [
            'current' => count($plan['currentRows']),
            'next' => count($plan['nextRows']),
            'changed' => count($plan['currentRows']) !== count($plan['nextRows']),
        ];

        $hiddenReasons = [];
        if ($currentHiddenResiduals !== [] || $nextHiddenResiduals !== []) {
            $hiddenReasons[] = 'hidden-residual-constraint-present';
        }
        if ($currentHiddenResiduals !== $nextHiddenResiduals) {
            $hiddenReasons[] = 'hidden-residual-usage-changed';
        }
        if ($rowCountTransition['changed']) {
            $hiddenReasons[] = 'hidden-residual-rowset-changed';
        }

        $plan['currentHiddenResiduals'] = $currentHiddenResiduals;
        $plan['nextHiddenResiduals'] = $nextHiddenResiduals;
        $plan['rowCountTransition'] = $rowCountTransition;
        $plan['next88ReplanReasons'] = array_values(array_unique(array_merge($plan['replanReasons'], $hiddenReasons)));
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-constraint-planner-current-source-next88'],
        )));

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $currentHostRows
     * @param list<array<string,mixed>> $nextHostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,current:list<array<string,mixed>>,next:list<array<string,mixed>>,transitions:list<array<string,mixed>>,replanRequired:bool,replanReasons:list<string>,currentReaderPolicy:string,nextReaderPolicy:string,leftJoin:bool,dependencies:list<string>}
     */
    public static function lateralHiddenPlannerCurrentSourceNext90(
        array $currentHostRows,
        array $nextHostRows,
        string $jsonColumn,
        string $function,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
        string $joinType = 'inner',
    ): array {
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden planner requires a host JSON column');
        }
        if ($rootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden planner root column must be non-empty when provided');
        }

        $function = self::normalizeFunction($function);
        $joinType = strtolower($joinType);
        if ($joinType !== 'inner' && $joinType !== 'left') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden planner join type must be inner or left');
        }

        $count = max(count($currentHostRows), count($nextHostRows));
        $current = [];
        $next = [];
        $transitions = [];
        $reasons = [];
        for ($index = 0; $index < $count; $index++) {
            $currentRow = $currentHostRows[$index] ?? null;
            $nextRow = $nextHostRows[$index] ?? null;
            if ($currentRow !== null && !array_key_exists($jsonColumn, $currentRow)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral hidden current host row is missing {$jsonColumn}");
            }
            if ($nextRow !== null && !array_key_exists($jsonColumn, $nextRow)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral hidden next host row is missing {$jsonColumn}");
            }
            if ($rootColumn !== null) {
                if ($currentRow !== null && !array_key_exists($rootColumn, $currentRow)) {
                    throw new \InvalidArgumentException("SQLite JSON table lateral hidden current host row is missing {$rootColumn}");
                }
                if ($nextRow !== null && !array_key_exists($rootColumn, $nextRow)) {
                    throw new \InvalidArgumentException("SQLite JSON table lateral hidden next host row is missing {$rootColumn}");
                }
            }

            $pair = null;
            if ($currentRow !== null && $nextRow !== null) {
                $pair = self::currentSourceHiddenConstraintPlannerNext88(
                    $function,
                    $currentRow,
                    $nextRow,
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $currentPlan = self::lateralHiddenHostPlan90($index, $currentRow, $pair, 'current', $joinType);
                $nextPlan = self::lateralHiddenHostPlan90($index, $nextRow, $pair, 'next', $joinType);
            } elseif ($currentRow !== null) {
                $single = self::currentSourceHiddenConstraintPlannerNext88(
                    $function,
                    $currentRow,
                    $currentRow,
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $currentPlan = self::lateralHiddenHostPlan90($index, $currentRow, $single, 'current', $joinType);
                $nextPlan = null;
            } else {
                $single = self::currentSourceHiddenConstraintPlannerNext88(
                    $function,
                    $nextRow,
                    $nextRow,
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $currentPlan = null;
                $nextPlan = self::lateralHiddenHostPlan90($index, $nextRow, $single, 'next', $joinType);
            }

            if ($currentPlan !== null) {
                $current[] = $currentPlan;
            }
            if ($nextPlan !== null) {
                $next[] = $nextPlan;
            }

            $reason = self::lateralHiddenTransitionReason90($currentPlan, $nextPlan, $pair);
            if ($reason !== 'stable-lateral-hidden-json-plan') {
                $reasons[$reason] = true;
            }
            if ($pair !== null) {
                foreach ($pair['next88ReplanReasons'] as $pairReason) {
                    if ($pairReason === 'hidden-residual-constraint-present') {
                        continue;
                    }
                    $reasons[$pairReason] = true;
                }
            }

            $transitions[] = [
                'index' => $index,
                'current' => $currentPlan,
                'next' => $nextPlan,
                'changed' => $reason !== 'stable-lateral-hidden-json-plan',
                'reason' => $reason,
                'currentRows' => $currentPlan['rowCount'] ?? 0,
                'nextRows' => $nextPlan['rowCount'] ?? 0,
                'rowCountChanged' => ($currentPlan['rowCount'] ?? 0) !== ($nextPlan['rowCount'] ?? 0),
                'currentNullExtended' => $currentPlan['nullExtended'] ?? false,
                'nextNullExtended' => $nextPlan['nullExtended'] ?? false,
                'hiddenResidualChanged' => ($currentPlan['hiddenResidualColumns'] ?? []) !== ($nextPlan['hiddenResidualColumns'] ?? []),
                'pairReplanReasons' => $pair['next88ReplanReasons'] ?? [],
            ];
        }

        return [
            'function' => $function,
            'current' => $current,
            'next' => $next,
            'transitions' => $transitions,
            'replanRequired' => $reasons !== [],
            'replanReasons' => array_keys($reasons),
            'currentReaderPolicy' => 'pin-current-lateral-hidden-json-source-until-host-row-advances',
            'nextReaderPolicy' => $reasons === []
                ? 'reuse-current-lateral-hidden-json-source-tape'
                : 'prepare-next-lateral-hidden-json-source-tape',
            'leftJoin' => $joinType === 'left',
            'dependencies' => [
                'sqlite-json-table-hidden-constraint-planner-current-source-next88',
                'sqlite-json-table-lateral-hidden-planner-current-source-next90',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $hostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    private static function lateralHostPlans(
        array $hostRows,
        string $jsonColumn,
        string $function,
        array $constraints,
        ?string $rootColumn,
        array $orderBy,
    ): array {
        $plans = [];
        foreach ($hostRows as $index => $hostRow) {
            if (!array_key_exists($jsonColumn, $hostRow)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral host row is missing {$jsonColumn}");
            }

            $hostConstraints = [
                ['column' => 'json', 'operator' => '=', 'value' => $hostRow[$jsonColumn]],
            ];
            if ($rootColumn !== null) {
                if (!array_key_exists($rootColumn, $hostRow)) {
                    throw new \InvalidArgumentException("SQLite JSON table lateral host row is missing {$rootColumn}");
                }
                if ($hostRow[$rootColumn] !== null) {
                    $hostConstraints[] = ['column' => 'root', 'operator' => '=', 'value' => $hostRow[$rootColumn]];
                }
            }

            $rowConstraints = array_merge($hostConstraints, $constraints);
            $indexPlan = self::xBestIndexPlan($function, $rowConstraints, $orderBy);
            $validatedPlan = self::validatedPlan($function, $rowConstraints);
            if (!$validatedPlan['runnable'] || $validatedPlan['jsonInputKind'] === 'sql-null') {
                $indexPlan['runnable'] = false;
                $indexPlan['estimatedCost'] = 1000000;
                $indexPlan['estimatedRows'] = 0;
            }

            $plans[] = [
                'hostIndex' => $index,
                'hostRow' => $hostRow,
                'jsonValue' => $hostRow[$jsonColumn],
                'rootValue' => $rootColumn === null ? '$' : ($hostRow[$rootColumn] ?? null),
                'runnable' => $indexPlan['runnable'],
                'idxNum' => $indexPlan['idxNum'],
                'idxStr' => $indexPlan['idxStr'],
                'filterArguments' => $indexPlan['filterArguments'],
                'constraintUsage' => $indexPlan['constraintUsage'],
                'filterCurrentNext' => $indexPlan['filterCurrentNext'],
                'orderByConsumed' => $indexPlan['orderByConsumed'],
                'estimatedCost' => $indexPlan['estimatedCost'],
                'estimatedRows' => $indexPlan['estimatedRows'],
                'jsonInputKind' => $validatedPlan['jsonInputKind'],
                'jsonValid' => $validatedPlan['jsonValid'],
                'jsonError' => $validatedPlan['jsonError'],
            ];
        }

        return $plans;
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    private static function sourceConstraintPlan86(
        string $function,
        array $source,
        string $jsonColumn,
        array $constraints,
        ?string $rootColumn,
        array $orderBy,
    ): array {
        if (!array_key_exists($jsonColumn, $source)) {
            throw new \InvalidArgumentException("SQLite JSON table current-source row is missing {$jsonColumn}");
        }

        $sourceConstraints = [
            ['column' => 'json', 'operator' => '=', 'value' => $source[$jsonColumn]],
        ];
        if ($rootColumn !== null) {
            if (!array_key_exists($rootColumn, $source)) {
                throw new \InvalidArgumentException("SQLite JSON table current-source row is missing {$rootColumn}");
            }
            if ($source[$rootColumn] !== null) {
                $sourceConstraints[] = ['column' => 'root', 'operator' => '=', 'value' => $source[$rootColumn]];
            }
        }

        $rowConstraints = array_merge($sourceConstraints, $constraints);
        $indexPlan = self::xBestIndexPlan($function, $rowConstraints, $orderBy);
        $validatedPlan = self::validatedPlan($function, $rowConstraints);
        if (!$validatedPlan['runnable'] || $validatedPlan['jsonInputKind'] === 'sql-null') {
            $indexPlan['runnable'] = false;
            $indexPlan['estimatedCost'] = 1000000;
            $indexPlan['estimatedRows'] = 0;
            $rows = [];
        } else {
            $rows = $indexPlan['orderByConsumed']
                ? self::filteredRows($function, $rowConstraints)
                : self::orderedRows($function, $rowConstraints, $orderBy);
        }

        return [
            'source' => $source,
            'jsonColumn' => $jsonColumn,
            'jsonValue' => $source[$jsonColumn],
            'rootColumn' => $rootColumn,
            'rootValue' => $rootColumn === null ? '$' : ($source[$rootColumn] ?? null),
            'runnable' => $indexPlan['runnable'],
            'idxNum' => $indexPlan['idxNum'],
            'idxStr' => $indexPlan['idxStr'],
            'filterArguments' => $indexPlan['filterArguments'],
            'constraintUsage' => $indexPlan['constraintUsage'],
            'filterCurrentNext' => $indexPlan['filterCurrentNext'],
            'currentNext' => $indexPlan['currentNext'],
            'orderByConsumed' => $indexPlan['orderByConsumed'],
            'estimatedCost' => $indexPlan['estimatedCost'],
            'estimatedRows' => $indexPlan['estimatedRows'],
            'jsonInputKind' => $validatedPlan['jsonInputKind'],
            'jsonValid' => $validatedPlan['jsonValid'],
            'jsonError' => $validatedPlan['jsonError'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function sourceTransitions86(array $current, array $next): array
    {
        return [
            [
                'field' => 'json',
                'current' => $current['jsonValue'],
                'next' => $next['jsonValue'],
                'changed' => $current['jsonValue'] !== $next['jsonValue'],
            ],
            [
                'field' => 'root',
                'current' => $current['rootValue'],
                'next' => $next['rootValue'],
                'changed' => $current['rootValue'] !== $next['rootValue'],
            ],
            [
                'field' => 'jsonInputKind',
                'current' => $current['jsonInputKind'],
                'next' => $next['jsonInputKind'],
                'changed' => $current['jsonInputKind'] !== $next['jsonInputKind'],
            ],
            [
                'field' => 'jsonValid',
                'current' => $current['jsonValid'],
                'next' => $next['jsonValid'],
                'changed' => $current['jsonValid'] !== $next['jsonValid'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $sourceTransitions
     * @param list<array{index:int,current:mixed,next:mixed,changed:bool}> $argumentTransitions
     * @param list<array{index:int,current:array<string,mixed>|null,next:array<string,mixed>|null,changed:bool}> $usageTransitions
     * @return list<string>
     */
    private static function currentSourceReplanReasons86(
        array $current,
        array $next,
        array $sourceTransitions,
        array $argumentTransitions,
        array $usageTransitions,
    ): array {
        $reasons = [];
        foreach ($sourceTransitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }
            $reasons[] = match ($transition['field']) {
                'json' => 'source-json-changed',
                'root' => 'source-root-changed',
                'jsonInputKind' => 'source-json-kind-changed',
                'jsonValid' => 'source-json-validity-changed',
                default => 'source-state-changed',
            };
        }
        if ($current['runnable'] !== $next['runnable']) {
            $reasons[] = $next['runnable'] ? 'next-source-plan-becomes-runnable' : 'next-source-plan-becomes-unrunnable';
        }
        if ($current['idxNum'] !== $next['idxNum'] || $current['idxStr'] !== $next['idxStr']) {
            $reasons[] = 'source-constraint-tape-changed';
        }
        foreach ($argumentTransitions as $transition) {
            if ($transition['changed']) {
                $reasons[] = 'source-argument-tape-changed';
                break;
            }
        }
        foreach ($usageTransitions as $transition) {
            if ($transition['changed']) {
                $reasons[] = 'source-usage-tape-changed';
                break;
            }
        }
        if ($current['orderByConsumed'] !== $next['orderByConsumed']) {
            $reasons[] = 'source-orderby-consumption-changed';
        }
        if ($current['estimatedRows'] !== $next['estimatedRows'] || $current['estimatedCost'] !== $next['estimatedCost']) {
            $reasons[] = 'source-estimate-changed';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<array<string,mixed>> $hostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<string> $jsonColumns
     * @return list<array<string,mixed>>
     */
    private static function lateralRowidRows(
        array $hostRows,
        string $jsonColumn,
        string $function,
        array $constraints,
        ?string $rootColumn,
        array $jsonColumns,
        string $joinType,
        string $jsonPrefix,
    ): array {
        if ($jsonColumns === []) {
            throw new \InvalidArgumentException('SQLite JSON table lateral rowid projection must include at least one JSON column');
        }
        $joinType = strtolower($joinType);
        if ($joinType !== 'inner' && $joinType !== 'left') {
            throw new \InvalidArgumentException('SQLite JSON table lateral rowid join type must be inner or left');
        }
        if ($jsonPrefix === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral rowid prefix must be non-empty');
        }

        $rows = [];
        $columns = array_values(array_unique(array_map(
            static fn (string $column): string => strtolower($column),
            array_merge($jsonColumns, ['rowid', '_rowid_', 'oid']),
        )));
        foreach ($hostRows as $hostIndex => $hostRow) {
            if (!array_key_exists($jsonColumn, $hostRow)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral rowid host row is missing {$jsonColumn}");
            }

            $rowConstraints = [
                ['column' => 'json', 'operator' => '=', 'value' => $hostRow[$jsonColumn]],
            ];
            if ($rootColumn !== null) {
                if (!array_key_exists($rootColumn, $hostRow)) {
                    throw new \InvalidArgumentException("SQLite JSON table lateral rowid host row is missing {$rootColumn}");
                }
                if ($hostRow[$rootColumn] !== null) {
                    $rowConstraints[] = ['column' => 'root', 'operator' => '=', 'value' => $hostRow[$rootColumn]];
                }
            }
            $rowConstraints = array_merge($rowConstraints, $constraints);
            $plan = self::validatedPlan($function, $rowConstraints);
            $jsonRows = $plan['runnable'] ? self::filteredRows($function, $rowConstraints) : [];
            if ($jsonRows === [] && $joinType === 'left') {
                $rows[] = self::lateralRowidJoinedRow($hostRow, $hostIndex, self::nullJsonProjection($columns), $jsonPrefix);
                continue;
            }

            foreach ($jsonRows as $jsonRow) {
                $rows[] = self::lateralRowidJoinedRow($hostRow, $hostIndex, self::projectJsonRowWithRowid($jsonRow, $columns), $jsonPrefix);
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $hostRow
     * @param array<string,mixed> $pair
     * @return array<string,mixed>
     */
    private static function lateralHiddenHostPlan90(
        int $hostIndex,
        array $hostRow,
        array $pair,
        string $side,
        string $joinType,
    ): array {
        $rows = $side === 'current' ? $pair['currentRows'] : $pair['nextRows'];
        $sourcePlan = $side === 'current' ? $pair['current'] : $pair['next'];
        $hiddenResiduals = $side === 'current' ? $pair['currentHiddenResiduals'] : $pair['nextHiddenResiduals'];
        $nullExtended = $joinType === 'left' && $rows === [];

        return [
            'hostIndex' => $hostIndex,
            'hostRow' => $hostRow,
            'jsonValue' => $sourcePlan['jsonValue'],
            'rootValue' => $sourcePlan['rootValue'],
            'runnable' => $sourcePlan['runnable'],
            'rowCount' => count($rows),
            'nullExtended' => $nullExtended,
            'idxStr' => $sourcePlan['idxStr'],
            'filterArguments' => $sourcePlan['filterArguments'],
            'hiddenResidualColumns' => array_column($hiddenResiduals, 'column'),
            'hiddenResiduals' => $hiddenResiduals,
            'orderByConsumed' => $sourcePlan['orderByConsumed'],
            'estimatedRows' => $sourcePlan['estimatedRows'],
            'jsonInputKind' => $sourcePlan['jsonInputKind'],
            'jsonValid' => $sourcePlan['jsonValid'],
        ];
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     * @param array<string,mixed>|null $pair
     */
    private static function lateralHiddenTransitionReason90(?array $current, ?array $next, ?array $pair): string
    {
        if ($current === null) {
            return 'next-lateral-hidden-host-row-added';
        }
        if ($next === null) {
            return 'current-lateral-hidden-host-row-removed';
        }
        $pairReasons = array_values(array_filter(
            $pair['next88ReplanReasons'] ?? [],
            static fn (string $reason): bool => $reason !== 'hidden-residual-constraint-present',
        ));
        if ($pairReasons !== []) {
            return 'lateral-hidden-source-plan-changed';
        }
        if (($current['rowCount'] ?? 0) !== ($next['rowCount'] ?? 0)) {
            return 'lateral-hidden-row-count-changed';
        }
        if (($current['nullExtended'] ?? false) !== ($next['nullExtended'] ?? false)) {
            return 'lateral-hidden-null-extension-changed';
        }
        if (($current['hiddenResidualColumns'] ?? []) !== ($next['hiddenResidualColumns'] ?? [])) {
            return 'lateral-hidden-residual-usage-changed';
        }

        return 'stable-lateral-hidden-json-plan';
    }

    /**
     * @param array<string,mixed> $hostRow
     * @param array<string,mixed> $jsonRow
     * @return array<string,mixed>
     */
    private static function lateralRowidJoinedRow(array $hostRow, int $hostIndex, array $jsonRow, string $jsonPrefix): array
    {
        $joined = $hostRow;
        $joined['__host_index'] = $hostIndex;
        foreach ($jsonRow as $column => $value) {
            $joined[$jsonPrefix . $column] = $value;
        }

        return $joined;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function projectJsonRowWithRowid(array $row, array $columns): array
    {
        $projected = [];
        foreach ($columns as $column) {
            if ($column === 'rowid' || $column === '_rowid_' || $column === 'oid') {
                $projected[$column] = $row['id'] ?? null;
                continue;
            }
            if (!self::rowHasColumn($row, $column)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral rowid projection column {$column} is not available");
            }

            $projected[$column] = self::rowColumnValue($row, $column);
        }

        return $projected;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{host:mixed,rowid:mixed,key:mixed,fullkey:mixed}>
     */
    private static function lateralRowidRowsSignature(array $rows, string $jsonPrefix): array
    {
        return array_map(
            static fn (array $row): array => [
                'host' => $row['__host_index'] ?? null,
                'rowid' => $row[$jsonPrefix . 'rowid'] ?? null,
                'key' => $row[$jsonPrefix . 'key'] ?? null,
                'fullkey' => $row[$jsonPrefix . 'fullkey'] ?? null,
            ],
            $rows,
        );
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function lateralRowidTransitionReason(?array $current, ?array $next, string $jsonPrefix): string
    {
        if ($current === null) {
            return 'next-lateral-json-row-added';
        }
        if ($next === null) {
            return 'current-lateral-json-row-removed';
        }
        if (($current['__host_index'] ?? null) !== ($next['__host_index'] ?? null)) {
            return 'lateral-host-row-boundary-changed';
        }
        if (($current[$jsonPrefix . 'rowid'] ?? null) !== ($next[$jsonPrefix . 'rowid'] ?? null)) {
            return 'lateral-json-rowid-changed';
        }
        if (($current[$jsonPrefix . 'fullkey'] ?? null) !== ($next[$jsonPrefix . 'fullkey'] ?? null)) {
            return 'lateral-json-fullkey-changed';
        }

        return 'stable-lateral-json-rowid';
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function lateralTransitionReason(?array $current, ?array $next): string
    {
        if ($current === null) {
            return 'next-host-row-added';
        }
        if ($next === null) {
            return 'current-host-row-removed';
        }
        if (($current['runnable'] ?? false) !== ($next['runnable'] ?? false)) {
            return ($next['runnable'] ?? false) ? 'next-lateral-plan-becomes-runnable' : 'next-lateral-plan-becomes-unrunnable';
        }
        if (($current['idxStr'] ?? '') !== ($next['idxStr'] ?? '')) {
            return 'lateral-constraint-operator-tape-changed';
        }
        if (($current['filterArguments'] ?? []) !== ($next['filterArguments'] ?? [])) {
            return 'lateral-filter-argument-tape-changed';
        }
        if (($current['orderByConsumed'] ?? false) !== ($next['orderByConsumed'] ?? false)) {
            return 'lateral-orderby-consumption-changed';
        }

        return 'stable-lateral-json-plan';
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
     * @param array<string,mixed> $plan
     */
    private static function jsonTableIdxNum(array $plan): int
    {
        $idxNum = 0;
        foreach ($plan['used'] as $constraint) {
            $column = strtolower((string) $constraint['column']);
            if (($constraint['constraint'] ?? null) === 'VISIBLE') {
                $idxNum |= 4;
                continue;
            }
            if ($column === 'json') {
                $idxNum |= 1;
                continue;
            }
            if ($column === 'root') {
                $idxNum |= 2;
                continue;
            }
            if ($column === 'limit') {
                $idxNum |= 8;
                continue;
            }
            if ($column === 'offset') {
                $idxNum |= 16;
            }
        }

        return $idxNum;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function jsonTableIdxStr(array $plan): string
    {
        $parts = [];
        foreach ($plan['used'] as $constraint) {
            $column = strtolower((string) $constraint['column']);
            $operator = strtoupper((string) $constraint['operator']);
            $kind = ($constraint['constraint'] ?? null) === 'VISIBLE' ? 'visible' : 'hidden';
            $parts[] = "{$kind}:{$column}:{$operator}";
        }

        return implode('|', $parts);
    }

    /**
     * @param array<string,mixed> $constraint
     * @return array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}
     */
    private static function constraintUsage(array $constraint, bool $used): array
    {
        $constraintIndex = $constraint['constraintIndex'] ?? null;
        if (!is_int($constraintIndex)) {
            throw new \InvalidArgumentException('SQLite JSON table xBestIndex constraints must preserve numeric indexes');
        }

        return [
            'constraintIndex' => $constraintIndex,
            'column' => strtolower((string) $constraint['column']),
            'operator' => strtoupper((string) $constraint['operator']),
            'argvIndex' => $used ? (int) ($constraint['argvIndex'] ?? 0) : null,
            'omit' => $used ? (bool) ($constraint['omit'] ?? false) : false,
            'usable' => (bool) ($constraint['usable'] ?? true),
            'kind' => $used
                ? (($constraint['constraint'] ?? null) === 'VISIBLE' ? 'visible' : 'hidden')
                : 'residual',
        ];
    }

    /**
     * @param list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}> $usage
     */
    private static function constraintIndexAlreadyUsed(array $usage, int $constraintIndex): bool
    {
        foreach ($usage as $entry) {
            if ($entry['constraintIndex'] === $constraintIndex) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}> $usage
     * @return list<array{constraintIndex:int,column:string,operator:string,usable:bool}>
     */
    private static function hiddenResidualConstraints88(array $usage): array
    {
        $hidden = [];
        foreach ($usage as $entry) {
            if ($entry['kind'] !== 'residual') {
                continue;
            }
            if ($entry['column'] !== 'json' && $entry['column'] !== 'root') {
                continue;
            }

            $hidden[] = [
                'constraintIndex' => $entry['constraintIndex'],
                'column' => $entry['column'],
                'operator' => $entry['operator'],
                'usable' => $entry['usable'],
            ];
        }

        return $hidden;
    }

    /**
     * @param list<array<string,mixed>> $used
     * @return list<mixed>
     */
    private static function filterArguments(array $used): array
    {
        $arguments = [];
        foreach ($used as $constraint) {
            $argvIndex = (int) ($constraint['argvIndex'] ?? 0);
            if ($argvIndex <= 0) {
                continue;
            }

            $arguments[$argvIndex] = $constraint['value'] ?? null;
        }

        if ($arguments === []) {
            return [];
        }

        ksort($arguments);

        return array_values($arguments);
    }

    /**
     * @param list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}> $usage
     * @return list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}>
     */
    private static function filterConstraintUsage(array $usage): array
    {
        $filtered = array_values(array_filter(
            $usage,
            static fn (array $constraint): bool => $constraint['argvIndex'] !== null && $constraint['argvIndex'] > 0,
        ));
        usort($filtered, static function (array $left, array $right): int {
            $argvOrder = ($left['argvIndex'] ?? 0) <=> ($right['argvIndex'] ?? 0);

            return $argvOrder !== 0 ? $argvOrder : $left['constraintIndex'] <=> $right['constraintIndex'];
        });

        return $filtered;
    }

    /**
     * @param list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}> $usage
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
     */
    private static function constraintCurrentNext(array $usage): array
    {
        $pairs = [];
        $count = count($usage);
        for ($index = 0; $index < $count; $index++) {
            $pairs[] = [
                'current' => $usage[$index],
                'next' => $usage[$index + 1] ?? null,
            ];
        }

        return $pairs;
    }

    /**
     * @param list<mixed> $current
     * @param list<mixed> $next
     * @return list<array{index:int,current:mixed,next:mixed,changed:bool}>
     */
    private static function argumentTransitions(array $current, array $next): array
    {
        $count = max(count($current), count($next));
        $transitions = [];
        for ($index = 0; $index < $count; $index++) {
            $currentValue = $current[$index] ?? null;
            $nextValue = $next[$index] ?? null;
            $transitions[] = [
                'index' => $index,
                'current' => $currentValue,
                'next' => $nextValue,
                'changed' => $currentValue !== $nextValue || !array_key_exists($index, $current) || !array_key_exists($index, $next),
            ];
        }

        return $transitions;
    }

    /**
     * @param list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}> $current
     * @param list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}> $next
     * @return list<array{index:int,current:array<string,mixed>|null,next:array<string,mixed>|null,changed:bool}>
     */
    private static function usageTransitions(array $current, array $next): array
    {
        $count = max(count($current), count($next));
        $transitions = [];
        for ($index = 0; $index < $count; $index++) {
            $currentUsage = $current[$index] ?? null;
            $nextUsage = $next[$index] ?? null;
            $transitions[] = [
                'index' => $index,
                'current' => $currentUsage,
                'next' => $nextUsage,
                'changed' => $currentUsage !== $nextUsage,
            ];
        }

        return $transitions;
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function jsonTablePlanSignature(array $plan): array
    {
        return [
            'runnable' => $plan['runnable'],
            'idxNum' => $plan['idxNum'],
            'idxStr' => $plan['idxStr'],
            'arguments' => $plan['filterArguments'],
            'orderByConsumed' => $plan['orderByConsumed'],
            'estimatedRows' => $plan['estimatedRows'],
            'estimatedCost' => $plan['estimatedCost'],
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @param list<array{index:int,current:mixed,next:mixed,changed:bool}> $argumentTransitions
     * @param list<array{index:int,current:array<string,mixed>|null,next:array<string,mixed>|null,changed:bool}> $usageTransitions
     */
    private static function jsonTableReplanReason(array $current, array $next, array $argumentTransitions, array $usageTransitions): string
    {
        if ($current['runnable'] !== $next['runnable']) {
            return $next['runnable'] ? 'next-plan-becomes-runnable' : 'next-plan-becomes-unrunnable';
        }
        if ($current['idxNum'] !== $next['idxNum']) {
            return 'constraint-class-bits-changed';
        }
        if ($current['idxStr'] !== $next['idxStr']) {
            return 'constraint-operator-tape-changed';
        }
        foreach ($argumentTransitions as $transition) {
            if ($transition['changed']) {
                return 'constraint-argument-tape-changed';
            }
        }
        foreach ($usageTransitions as $transition) {
            if ($transition['changed']) {
                return 'constraint-usage-tape-changed';
            }
        }
        if ($current['orderByConsumed'] !== $next['orderByConsumed']) {
            return 'order-by-consumption-changed';
        }
        if ($current['estimatedRows'] !== $next['estimatedRows'] || $current['estimatedCost'] !== $next['estimatedCost']) {
            return 'planner-estimate-changed';
        }

        return 'stable-current-next-plan';
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     */
    private static function jsonTableOrderByConsumed(array $orderBy): bool
    {
        if ($orderBy === []) {
            return false;
        }

        foreach ($orderBy as $term) {
            $column = strtolower($term['column']);
            $direction = strtoupper($term['direction'] ?? 'ASC');
            if (!in_array($column, ['id', 'rowid', '_rowid_', 'oid'], true) || $direction !== 'ASC') {
                return false;
            }
        }

        return true;
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

    private static function normalizeConstraintColumn(string $column): string
    {
        $column = strtolower($column);

        return self::isRowIdAlias($column) ? 'id' : $column;
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
