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
            'orderByConsumed' => self::jsonTableOrderByConsumed($orderBy, $plan['used']),
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
    public static function currentSourceConstraintCostOrderNext113(
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

        $currentProfile = self::jsonTableCostOrderProfile113($plan['current'], $orderBy);
        $nextProfile = self::jsonTableCostOrderProfile113($plan['next'], $orderBy);
        $transitions = self::jsonTableCostOrderTransitions113($currentProfile, $nextProfile);
        $reasons = self::jsonTableCostOrderReplanReasons113($transitions);

        $plan['currentCostOrder'] = $currentProfile;
        $plan['nextCostOrder'] = $nextProfile;
        $plan['costOrderTransitions'] = $transitions;
        $plan['next113ReplanReasons'] = array_values(array_unique(array_merge($plan['replanReasons'], $reasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-cost-order-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next113ReplanReasons'] === []
            ? 'reuse-current-json-table-cost-order-source-plan'
            : 'prepare-next-json-table-cost-order-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-constraint-cost-order-current-source-next113'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourcePathOrderByCostNext131(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourcePathConstraintPushdownNext123(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentCoverage = self::orderByConstraintCoverage120($plan['current']['used'], $orderBy);
        $nextCoverage = self::orderByConstraintCoverage120($plan['next']['used'], $orderBy);
        $currentPartialOrder = self::jsonTablePartialOrderCostProfile124($plan['current'], $plan['currentCostOrder'], $currentCoverage);
        $nextPartialOrder = self::jsonTablePartialOrderCostProfile124($plan['next'], $plan['nextCostOrder'], $nextCoverage);
        $currentProfile = self::jsonTablePathOrderByCostProfile131($plan['currentPathConstraint'], $currentPartialOrder, $plan['currentRows']);
        $nextProfile = self::jsonTablePathOrderByCostProfile131($plan['nextPathConstraint'], $nextPartialOrder, $plan['nextRows']);
        $transitions = self::jsonTablePathOrderByCostTransitions131($currentProfile, $nextProfile);
        $reasons = self::jsonTablePathOrderByCostReplanReasons131($transitions);

        $plan['currentPathOrderByCost'] = $currentProfile;
        $plan['nextPathOrderByCost'] = $nextProfile;
        $plan['pathOrderByCostTransitions'] = $transitions;
        $plan['next131ReplanReasons'] = array_values(array_unique(array_merge($plan['next123ReplanReasons'], $reasons)));
        $plan['replanRequired'] = $plan['next131ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-path-orderby-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next131ReplanReasons'] === []
            ? 'reuse-current-json-table-path-orderby-cost-source-plan'
            : 'prepare-next-json-table-path-orderby-cost-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-path-orderby-cost-current-source-next131'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceGeneratedPathCostNext134(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $generatedPathColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        if ($generatedPathColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table generated-path cost planner requires a generated path source column');
        }

        $plan = self::currentSourcePathOrderByCostNext131(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTableGeneratedPathCostProfile134(
            $currentSource,
            $generatedPathColumn,
            $plan['currentPathOrderByCost'],
            $plan['currentRows'],
        );
        $nextProfile = self::jsonTableGeneratedPathCostProfile134(
            $nextSource,
            $generatedPathColumn,
            $plan['nextPathOrderByCost'],
            $plan['nextRows'],
        );
        $transitions = self::jsonTableGeneratedPathCostTransitions134($currentProfile, $nextProfile);
        $reasons = self::jsonTableGeneratedPathCostReplanReasons134($transitions);

        $plan['generatedPathColumn'] = $generatedPathColumn;
        $plan['currentGeneratedPathCost'] = $currentProfile;
        $plan['nextGeneratedPathCost'] = $nextProfile;
        $plan['generatedPathCostTransitions'] = $transitions;
        $plan['next134ReplanReasons'] = array_values(array_unique(array_merge($plan['next131ReplanReasons'], $reasons)));
        $plan['replanRequired'] = $plan['next134ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-generated-path-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next134ReplanReasons'] === []
            ? 'reuse-current-json-table-generated-path-cost-source-plan'
            : 'prepare-next-json-table-generated-path-cost-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-generated-path-cost-current-source-next134'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceNestedPathPlannerNext121(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
    ): array {
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested path planner requires a JSON source column');
        }
        if ($baseRootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested path planner requires a base root column');
        }
        if ($nestedPathColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested path planner requires a nested path column');
        }

        $currentRoot = self::composeNestedRootPath121($currentSource, $baseRootColumn, $nestedPathColumn, 'current');
        $nextRoot = self::composeNestedRootPath121($nextSource, $baseRootColumn, $nestedPathColumn, 'next');
        $current = $currentSource + ['__sqlite_json_table_nested_root_next121' => $currentRoot['root']];
        $next = $nextSource + ['__sqlite_json_table_nested_root_next121' => $nextRoot['root']];
        $plan = self::currentSourceConstraintCostOrderNext113(
            $function,
            $current,
            $next,
            $jsonColumn,
            $constraints,
            '__sqlite_json_table_nested_root_next121',
            $orderBy,
        );

        $rootTransition = [
            'current' => $currentRoot['root'],
            'next' => $nextRoot['root'],
            'changed' => $currentRoot['root'] !== $nextRoot['root'],
        ];
        $nestedPathTransition = [
            'current' => $currentRoot['nestedPath'],
            'next' => $nextRoot['nestedPath'],
            'changed' => $currentRoot['nestedPath'] !== $nextRoot['nestedPath'],
        ];
        $baseRootTransition = [
            'current' => $currentRoot['baseRoot'],
            'next' => $nextRoot['baseRoot'],
            'changed' => $currentRoot['baseRoot'] !== $nextRoot['baseRoot'],
        ];

        $nestedReasons = [];
        if ($baseRootTransition['changed']) {
            $nestedReasons[] = 'json-table-nested-base-root-changed';
        }
        if ($nestedPathTransition['changed']) {
            $nestedReasons[] = 'json-table-nested-path-changed';
        }
        if ($rootTransition['changed']) {
            $nestedReasons[] = 'json-table-nested-root-changed';
        }
        if (count($plan['currentRows']) !== count($plan['nextRows'])) {
            $nestedReasons[] = 'json-table-nested-row-count-changed';
        }

        $plan['currentNestedPath'] = $currentRoot;
        $plan['nextNestedPath'] = $nextRoot;
        $plan['nestedPathTransitions'] = [
            'baseRoot' => $baseRootTransition,
            'nestedPath' => $nestedPathTransition,
            'composedRoot' => $rootTransition,
        ];
        $plan['next121ReplanReasons'] = array_values(array_unique(array_merge($plan['next113ReplanReasons'], $nestedReasons)));
        $plan['replanRequired'] = $plan['next121ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-nested-path-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next121ReplanReasons'] === []
            ? 'reuse-current-json-table-nested-path-source-plan'
            : 'prepare-next-json-table-nested-path-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-nested-path-planner-current-source-next121'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceNestedConstraintCostNext125(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceNestedPathPlannerNext121(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
        );

        $currentProfile = self::jsonTableIndexedConstraintCostProfile119($plan['current'], $plan['currentCostOrder']);
        $nextProfile = self::jsonTableIndexedConstraintCostProfile119($plan['next'], $plan['nextCostOrder']);
        $transitions = self::jsonTableIndexedConstraintTransitions119($currentProfile, $nextProfile);
        $reasons = self::jsonTableIndexedConstraintReplanReasons119($transitions);

        $currentProfile['nestedRoot'] = $plan['currentNestedPath']['root'];
        $currentProfile['nestedPathMode'] = $plan['currentNestedPath']['mode'];
        $currentProfile['matchedRowCount'] = count($plan['currentRows']);
        $currentProfile['matchedFullkeys'] = array_values(array_map(
            static fn (array $row): mixed => $row['fullkey'] ?? null,
            $plan['currentRows'],
        ));
        $nextProfile['nestedRoot'] = $plan['nextNestedPath']['root'];
        $nextProfile['nestedPathMode'] = $plan['nextNestedPath']['mode'];
        $nextProfile['matchedRowCount'] = count($plan['nextRows']);
        $nextProfile['matchedFullkeys'] = array_values(array_map(
            static fn (array $row): mixed => $row['fullkey'] ?? null,
            $plan['nextRows'],
        ));

        $nestedTransitions = [
            [
                'field' => 'nestedRoot',
                'current' => $currentProfile['nestedRoot'],
                'next' => $nextProfile['nestedRoot'],
                'changed' => $currentProfile['nestedRoot'] !== $nextProfile['nestedRoot'],
            ],
            [
                'field' => 'nestedPathMode',
                'current' => $currentProfile['nestedPathMode'],
                'next' => $nextProfile['nestedPathMode'],
                'changed' => $currentProfile['nestedPathMode'] !== $nextProfile['nestedPathMode'],
            ],
            [
                'field' => 'matchedRowCount',
                'current' => $currentProfile['matchedRowCount'],
                'next' => $nextProfile['matchedRowCount'],
                'changed' => $currentProfile['matchedRowCount'] !== $nextProfile['matchedRowCount'],
            ],
            [
                'field' => 'matchedFullkeys',
                'current' => $currentProfile['matchedFullkeys'],
                'next' => $nextProfile['matchedFullkeys'],
                'changed' => $currentProfile['matchedFullkeys'] !== $nextProfile['matchedFullkeys'],
            ],
        ];
        $nestedReasons = [];
        foreach ($nestedTransitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $nestedReasons[] = match ($transition['field']) {
                'nestedRoot' => 'json-table-nested-constraint-root-changed',
                'nestedPathMode' => 'json-table-nested-constraint-mode-changed',
                'matchedRowCount' => 'json-table-nested-constraint-row-count-changed',
                'matchedFullkeys' => 'json-table-nested-constraint-output-changed',
                default => 'json-table-nested-constraint-cost-changed',
            };
        }

        $plan['currentNestedConstraintCost'] = $currentProfile;
        $plan['nextNestedConstraintCost'] = $nextProfile;
        $plan['nestedConstraintCostTransitions'] = array_merge($transitions, $nestedTransitions);
        $plan['next125ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next121ReplanReasons'],
            $reasons,
            $nestedReasons,
        )));
        $plan['replanRequired'] = $plan['next125ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-nested-constraint-cost-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next125ReplanReasons'] === []
            ? 'reuse-current-json-table-nested-constraint-cost-plan'
            : 'prepare-next-json-table-nested-constraint-cost-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-nested-constraint-cost-current-source-next125'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourcePathConstraintPushdownNext123(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceIndexedConstraintCostNext119(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTablePathConstraintProfile123($plan['current'], $plan['currentIndexedConstraintCost']);
        $nextProfile = self::jsonTablePathConstraintProfile123($plan['next'], $plan['nextIndexedConstraintCost']);
        $transitions = self::jsonTablePathConstraintTransitions123($currentProfile, $nextProfile);
        $reasons = self::jsonTablePathConstraintReplanReasons123($transitions);

        $plan['currentPathConstraint'] = $currentProfile;
        $plan['nextPathConstraint'] = $nextProfile;
        $plan['pathConstraintTransitions'] = $transitions;
        $plan['next123ReplanReasons'] = array_values(array_unique(array_merge($plan['next119ReplanReasons'], $reasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-path-constraint-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next123ReplanReasons'] === []
            ? 'reuse-current-json-table-path-constraint-source-plan'
            : 'prepare-next-json-table-path-constraint-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-path-constraint-pushdown-current-source-next123'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceNestedConstraintOrderNext127(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
    ): array {
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested constraint order planner requires a JSON source column');
        }
        if ($baseRootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested constraint order planner requires a base root column');
        }
        if ($nestedPathColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested constraint order planner requires a nested path column');
        }

        $currentRoot = self::composeNestedRootPath121($currentSource, $baseRootColumn, $nestedPathColumn, 'current');
        $nextRoot = self::composeNestedRootPath121($nextSource, $baseRootColumn, $nestedPathColumn, 'next');
        $current = $currentSource + ['__sqlite_json_table_nested_constraint_order_root_next127' => $currentRoot['root']];
        $next = $nextSource + ['__sqlite_json_table_nested_constraint_order_root_next127' => $nextRoot['root']];

        $plan = self::currentSourceConstraintOrderByCostNext124(
            $function,
            $current,
            $next,
            $jsonColumn,
            $constraints,
            '__sqlite_json_table_nested_constraint_order_root_next127',
            $orderBy,
        );

        $transitions = [
            'baseRoot' => [
                'current' => $currentRoot['baseRoot'],
                'next' => $nextRoot['baseRoot'],
                'changed' => $currentRoot['baseRoot'] !== $nextRoot['baseRoot'],
            ],
            'nestedPath' => [
                'current' => $currentRoot['nestedPath'],
                'next' => $nextRoot['nestedPath'],
                'changed' => $currentRoot['nestedPath'] !== $nextRoot['nestedPath'],
            ],
            'composedRoot' => [
                'current' => $currentRoot['root'],
                'next' => $nextRoot['root'],
                'changed' => $currentRoot['root'] !== $nextRoot['root'],
            ],
            'consumedPrefixColumns' => [
                'current' => $plan['currentPartialOrderCost']['consumedPrefixColumns'],
                'next' => $plan['nextPartialOrderCost']['consumedPrefixColumns'],
                'changed' => $plan['currentPartialOrderCost']['consumedPrefixColumns'] !== $plan['nextPartialOrderCost']['consumedPrefixColumns'],
            ],
            'suffixColumns' => [
                'current' => $plan['currentPartialOrderCost']['suffixColumns'],
                'next' => $plan['nextPartialOrderCost']['suffixColumns'],
                'changed' => $plan['currentPartialOrderCost']['suffixColumns'] !== $plan['nextPartialOrderCost']['suffixColumns'],
            ],
        ];

        $nestedReasons = [];
        if ($transitions['baseRoot']['changed']) {
            $nestedReasons[] = 'json-table-nested-constraint-order-base-root-changed';
        }
        if ($transitions['nestedPath']['changed']) {
            $nestedReasons[] = 'json-table-nested-constraint-order-path-changed';
        }
        if ($transitions['composedRoot']['changed']) {
            $nestedReasons[] = 'json-table-nested-constraint-order-root-changed';
        }
        if ($transitions['consumedPrefixColumns']['changed']) {
            $nestedReasons[] = 'json-table-nested-constraint-order-prefix-changed';
        }
        if ($transitions['suffixColumns']['changed']) {
            $nestedReasons[] = 'json-table-nested-constraint-order-suffix-changed';
        }
        if (count($plan['currentRows']) !== count($plan['nextRows'])) {
            $nestedReasons[] = 'json-table-nested-constraint-order-row-count-changed';
        }

        $plan['currentNestedConstraintOrder'] = self::nestedConstraintOrderProfile127($currentRoot, $plan['currentPartialOrderCost'], $plan['currentOrderConstraintCoverage']);
        $plan['nextNestedConstraintOrder'] = self::nestedConstraintOrderProfile127($nextRoot, $plan['nextPartialOrderCost'], $plan['nextOrderConstraintCoverage']);
        $plan['nestedConstraintOrderTransitions'] = $transitions;
        $plan['next127ReplanReasons'] = array_values(array_unique(array_merge($plan['next124ReplanReasons'], $nestedReasons)));
        $plan['replanRequired'] = $plan['next127ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-nested-constraint-order-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next127ReplanReasons'] === []
            ? 'reuse-current-json-table-nested-constraint-order-source-plan'
            : 'prepare-next-json-table-nested-constraint-order-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-nested-constraint-order-current-source-next127'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceNestedPathRowidNext133(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceNestedHiddenCostNext129(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
        );

        $currentProfile = self::nestedPathRowidProfile133($plan['currentNestedHiddenCost'], $plan['current'], $constraints);
        $nextProfile = self::nestedPathRowidProfile133($plan['nextNestedHiddenCost'], $plan['next'], $constraints);
        $transitions = self::nestedPathRowidTransitions133($currentProfile, $nextProfile);
        $reasons = self::nestedPathRowidReplanReasons133($transitions);

        $plan['currentNestedPathRowid'] = $currentProfile;
        $plan['nextNestedPathRowid'] = $nextProfile;
        $plan['nestedPathRowidTransitions'] = $transitions;
        $plan['next133ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next129ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next133ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-nested-path-rowid-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next133ReplanReasons'] === []
            ? 'reuse-current-json-table-nested-path-rowid-source-plan'
            : 'prepare-next-json-table-nested-path-rowid-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-nested-path-rowid-current-source-next133'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return array<string,mixed>
     */
    public static function currentSourceHiddenGeneratedOrderNext132(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
        array $generatedOrder = [],
    ): array {
        if ($generatedOrder === []) {
            throw new \InvalidArgumentException('SQLite JSON table hidden generated order requires generated order terms');
        }

        $plan = self::currentSourceIndexedHiddenOrderNext122(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTableHiddenGeneratedOrderProfile132($plan['current'], $plan['currentIndexedHiddenOrder'], $generatedOrder);
        $nextProfile = self::jsonTableHiddenGeneratedOrderProfile132($plan['next'], $plan['nextIndexedHiddenOrder'], $generatedOrder);
        $transitions = self::jsonTableHiddenGeneratedOrderTransitions132($currentProfile, $nextProfile);
        $reasons = self::jsonTableHiddenGeneratedOrderReplanReasons132($transitions);

        $plan['currentHiddenGeneratedOrder'] = $currentProfile;
        $plan['nextHiddenGeneratedOrder'] = $nextProfile;
        $plan['hiddenGeneratedOrderTransitions'] = $transitions;
        $plan['next132ReplanReasons'] = array_values(array_unique(array_merge($plan['next122ReplanReasons'], $reasons)));
        $plan['replanRequired'] = $plan['next132ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-generated-order-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next132ReplanReasons'] === []
            ? 'reuse-current-json-table-hidden-generated-order-plan'
            : 'prepare-next-json-table-hidden-generated-order-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-generated-order-current-source-next132'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceNestedHiddenCostNext129(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
    ): array {
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested hidden cost planner requires a JSON source column');
        }
        if ($baseRootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested hidden cost planner requires a base root column');
        }
        if ($nestedPathColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table nested hidden cost planner requires a nested path column');
        }

        $currentRoot = self::composeNestedRootPath121($currentSource, $baseRootColumn, $nestedPathColumn, 'current');
        $nextRoot = self::composeNestedRootPath121($nextSource, $baseRootColumn, $nestedPathColumn, 'next');
        $rootColumn = '__sqlite_json_table_nested_hidden_root_next129';
        $current = $currentSource + [$rootColumn => $currentRoot['root']];
        $next = $nextSource + [$rootColumn => $nextRoot['root']];

        $plan = self::currentSourcePathHiddenRowidCostNext126(
            $function,
            $current,
            $next,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::nestedHiddenCostProfile129($currentRoot, $plan['current'], $plan['currentPathHiddenRowidCost']);
        $nextProfile = self::nestedHiddenCostProfile129($nextRoot, $plan['next'], $plan['nextPathHiddenRowidCost']);
        $transitions = self::nestedHiddenCostTransitions129($currentProfile, $nextProfile);
        $reasons = self::nestedHiddenCostReplanReasons129($transitions);

        $plan['currentNestedHiddenCost'] = $currentProfile;
        $plan['nextNestedHiddenCost'] = $nextProfile;
        $plan['nestedHiddenCostTransitions'] = $transitions;
        $plan['next129ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next126ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next129ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-nested-hidden-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next129ReplanReasons'] === []
            ? 'reuse-current-json-table-nested-hidden-cost-source-plan'
            : 'prepare-next-json-table-nested-hidden-cost-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-nested-hidden-cost-current-source-next129'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceHiddenPathOrderByNext128(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourcePathHiddenRowidCostNext126(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentOrderCoverage = self::orderByConstraintCoverage120($plan['current']['used'], $orderBy);
        $nextOrderCoverage = self::orderByConstraintCoverage120($plan['next']['used'], $orderBy);
        $currentPartialOrder = self::jsonTablePartialOrderCostProfile124(
            $plan['current'],
            $plan['currentCostOrder'],
            $currentOrderCoverage,
        );
        $nextPartialOrder = self::jsonTablePartialOrderCostProfile124(
            $plan['next'],
            $plan['nextCostOrder'],
            $nextOrderCoverage,
        );
        $currentProfile = self::jsonTableHiddenPathOrderByProfile128(
            $plan['currentPathHiddenRowidCost'],
            $currentPartialOrder,
        );
        $nextProfile = self::jsonTableHiddenPathOrderByProfile128(
            $plan['nextPathHiddenRowidCost'],
            $nextPartialOrder,
        );
        $transitions = self::jsonTableHiddenPathOrderByTransitions128($currentProfile, $nextProfile);
        $reasons = self::jsonTableHiddenPathOrderByReplanReasons128($transitions);

        $plan['currentHiddenPathOrderBy'] = $currentProfile;
        $plan['nextHiddenPathOrderBy'] = $nextProfile;
        $plan['hiddenPathOrderByTransitions'] = $transitions;
        $plan['next128ReplanReasons'] = array_values(array_unique(array_merge($plan['next126ReplanReasons'], $reasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-path-orderby-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next128ReplanReasons'] === []
            ? 'reuse-current-json-table-hidden-path-orderby-source-plan'
            : 'prepare-next-json-table-hidden-path-orderby-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-path-orderby-current-source-next128'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourcePathHiddenRowidCostNext126(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourcePathConstraintPushdownNext123(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTablePathHiddenRowidCostProfile126(
            $plan['current'],
            $plan['currentIndexedConstraintCost'],
            $plan['currentPathConstraint'],
        );
        $nextProfile = self::jsonTablePathHiddenRowidCostProfile126(
            $plan['next'],
            $plan['nextIndexedConstraintCost'],
            $plan['nextPathConstraint'],
        );
        $transitions = self::jsonTablePathHiddenRowidCostTransitions126($currentProfile, $nextProfile);
        $reasons = self::jsonTablePathHiddenRowidCostReplanReasons126($transitions);

        $plan['currentPathHiddenRowidCost'] = $currentProfile;
        $plan['nextPathHiddenRowidCost'] = $nextProfile;
        $plan['pathHiddenRowidCostTransitions'] = $transitions;
        $plan['next126ReplanReasons'] = array_values(array_unique(array_merge($plan['next123ReplanReasons'], $reasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-path-rowid-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next126ReplanReasons'] === []
            ? 'reuse-current-json-table-path-rowid-cost-source-plan'
            : 'prepare-next-json-table-path-rowid-cost-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-path-hidden-rowid-cost-current-source-next126'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceIndexedHiddenOrderNext122(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceIndexedConstraintCostNext119(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTableIndexedHiddenOrderProfile122($plan['current'], $plan['currentIndexedConstraintCost'], $orderBy);
        $nextProfile = self::jsonTableIndexedHiddenOrderProfile122($plan['next'], $plan['nextIndexedConstraintCost'], $orderBy);
        $transitions = self::jsonTableIndexedHiddenOrderTransitions122($currentProfile, $nextProfile);
        $reasons = self::jsonTableIndexedHiddenOrderReplanReasons122($transitions);

        $plan['currentIndexedHiddenOrder'] = $currentProfile;
        $plan['nextIndexedHiddenOrder'] = $nextProfile;
        $plan['indexedHiddenOrderTransitions'] = $transitions;
        $plan['next122ReplanReasons'] = array_values(array_unique(array_merge($plan['next119ReplanReasons'], $reasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-indexed-hidden-order-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next122ReplanReasons'] === []
            ? 'reuse-current-json-table-indexed-hidden-order-plan'
            : 'prepare-next-json-table-indexed-hidden-order-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-indexed-hidden-order-current-source-next122'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceConstraintOrderByCostNext124(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceOrderByConstraintNext120(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTablePartialOrderCostProfile124(
            $plan['current'],
            $plan['currentCostOrder'],
            $plan['currentOrderConstraintCoverage'],
        );
        $nextProfile = self::jsonTablePartialOrderCostProfile124(
            $plan['next'],
            $plan['nextCostOrder'],
            $plan['nextOrderConstraintCoverage'],
        );
        $transitions = self::jsonTablePartialOrderCostTransitions124($currentProfile, $nextProfile);
        $reasons = self::jsonTablePartialOrderCostReplanReasons124($transitions);

        $plan['currentPartialOrderCost'] = $currentProfile;
        $plan['nextPartialOrderCost'] = $nextProfile;
        $plan['partialOrderCostTransitions'] = $transitions;
        $plan['next124ReplanReasons'] = array_values(array_unique(array_merge($plan['next120ReplanReasons'], $reasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-partial-order-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next124ReplanReasons'] === []
            ? 'reuse-current-json-table-partial-order-cost-source-plan'
            : 'prepare-next-json-table-partial-order-cost-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-constraint-orderby-cost-current-source-next124'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceOrderByConstraintNext120(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceConstraintCostOrderNext113(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $plan['currentOrderConstraintCoverage'] = self::orderByConstraintCoverage120(
            $plan['current']['used'],
            $orderBy,
        );
        $plan['nextOrderConstraintCoverage'] = self::orderByConstraintCoverage120(
            $plan['next']['used'],
            $orderBy,
        );
        $plan['next120ReplanReasons'] = $plan['next113ReplanReasons'];
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-orderby-constraint-current-source-next120'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceIndexedConstraintCostNext119(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceConstraintCostOrderNext113(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTableIndexedConstraintCostProfile119($plan['current'], $plan['currentCostOrder']);
        $nextProfile = self::jsonTableIndexedConstraintCostProfile119($plan['next'], $plan['nextCostOrder']);
        $transitions = self::jsonTableIndexedConstraintTransitions119($currentProfile, $nextProfile);
        $reasons = self::jsonTableIndexedConstraintReplanReasons119($transitions);

        $plan['currentIndexedConstraintCost'] = $currentProfile;
        $plan['nextIndexedConstraintCost'] = $nextProfile;
        $plan['indexedConstraintTransitions'] = $transitions;
        $plan['next119ReplanReasons'] = array_values(array_unique(array_merge($plan['next113ReplanReasons'], $reasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-indexed-constraint-cost-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next119ReplanReasons'] === []
            ? 'reuse-current-json-table-indexed-constraint-cost-plan'
            : 'prepare-next-json-table-indexed-constraint-cost-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-indexed-constraint-cost-current-source-next119'],
        )));

        return $plan;
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
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceHiddenRowidPlannerNext94(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceHiddenConstraintPlannerNext88(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $sourceRowidResiduals = self::sourceRowidResidualConstraints94($constraints);
        $currentRowidResiduals = $sourceRowidResiduals !== []
            ? $sourceRowidResiduals
            : self::hiddenRowidResidualConstraints94($plan['current']['constraintUsage']);
        $nextRowidResiduals = $sourceRowidResiduals !== []
            ? $sourceRowidResiduals
            : self::hiddenRowidResidualConstraints94($plan['next']['constraintUsage']);
        $rowidTransition = [
            'current' => self::rowidsFromRows94($plan['currentRows']),
            'next' => self::rowidsFromRows94($plan['nextRows']),
        ];
        $rowidTransition['changed'] = $rowidTransition['current'] !== $rowidTransition['next'];

        $rowTransitions = self::sourceRowTransitions94($plan['currentRows'], $plan['nextRows']);
        $rowidReasons = [];
        if ($currentRowidResiduals !== [] || $nextRowidResiduals !== []) {
            $rowidReasons[] = 'hidden-rowid-residual-constraint-present';
        }
        if ($currentRowidResiduals !== $nextRowidResiduals) {
            $rowidReasons[] = 'hidden-rowid-residual-usage-changed';
        }
        if ($rowidTransition['changed']) {
            $rowidReasons[] = 'hidden-rowid-rowset-changed';
        }
        foreach ($rowTransitions as $transition) {
            if ($transition['reason'] !== 'stable-hidden-rowid-source-row') {
                $rowidReasons[] = $transition['reason'];
            }
        }

        $plan['currentRowidResiduals'] = $currentRowidResiduals;
        $plan['nextRowidResiduals'] = $nextRowidResiduals;
        $plan['rowidTransition'] = $rowidTransition;
        $plan['rowTransitions'] = $rowTransitions;
        $plan['currentRowidSummary'] = self::sourceRowidSummary94($plan['current']);
        $plan['nextRowidSummary'] = self::sourceRowidSummary94($plan['next']);
        $plan['next94ReplanReasons'] = array_values(array_unique(array_merge($plan['next88ReplanReasons'], $rowidReasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-rowid-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next94ReplanReasons'] === ['hidden-rowid-residual-constraint-present']
            ? 'reuse-current-json-table-hidden-rowid-source'
            : 'prepare-next-json-table-hidden-rowid-source';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-rowid-source-current-next94'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function currentSourceRowidHiddenConstraintPlannerNext99(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceHiddenRowidPlannerNext94(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentAliases = self::rowidAliasConstraintProvenance99($constraints, $plan['current']['constraintUsage']);
        $nextAliases = self::rowidAliasConstraintProvenance99($constraints, $plan['next']['constraintUsage']);
        $aliasTransition = [
            'current' => array_column($currentAliases, 'originalColumn'),
            'next' => array_column($nextAliases, 'originalColumn'),
        ];
        $aliasTransition['changed'] = $aliasTransition['current'] !== $aliasTransition['next'];

        $plan['currentRowidAliasConstraints'] = $currentAliases;
        $plan['nextRowidAliasConstraints'] = $nextAliases;
        $plan['rowidAliasTransition'] = $aliasTransition;
        $plan['next99ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next94ReplanReasons'],
            $aliasTransition['changed'] ? ['hidden-rowid-alias-provenance-changed'] : [],
        )));
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-rowid-hidden-constraint-current-source-next99'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,sourceColumn?:string,value?:mixed,operator?:string,usable?:bool}> $constraintSources
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,current:array<string,mixed>,next:array<string,mixed>,constraintValueTransitions:list<array<string,mixed>>,currentRows:list<array<string,mixed>>,nextRows:list<array<string,mixed>>,replanRequired:bool,replanReasons:list<string>,currentReaderPolicy:string,nextReaderPolicy:string,dependencies:list<string>}
     */
    public static function hiddenConstraintSourceCurrentSourceNext102(
        string $function,
        array $currentSource,
        array $nextSource,
        array $constraintSources,
        array $orderBy = [],
    ): array {
        if ($constraintSources === []) {
            throw new \InvalidArgumentException('SQLite JSON table hidden constraint source next102 requires constraint sources');
        }

        $function = self::normalizeFunction($function);
        $currentConstraints = self::constraintsFromSource102($currentSource, $constraintSources);
        $nextConstraints = self::constraintsFromSource102($nextSource, $constraintSources);
        $current = self::sourceConstraintPlan102($function, $currentSource, $currentConstraints, $constraintSources, $orderBy);
        $next = self::sourceConstraintPlan102($function, $nextSource, $nextConstraints, $constraintSources, $orderBy);
        $argumentTransitions = self::argumentTransitions($current['filterArguments'], $next['filterArguments']);
        $usageTransitions = self::usageTransitions($current['constraintUsage'], $next['constraintUsage']);
        $constraintValueTransitions = self::constraintValueTransitions102($current['constraintSources'], $next['constraintSources']);
        $replanReasons = self::constraintSourceReplanReasons102($current, $next, $constraintValueTransitions, $argumentTransitions, $usageTransitions);

        return [
            'function' => $function,
            'current' => $current,
            'next' => $next,
            'constraintValueTransitions' => $constraintValueTransitions,
            'currentRows' => $current['rows'],
            'nextRows' => $next['rows'],
            'replanRequired' => $replanReasons !== [],
            'replanReasons' => $replanReasons,
            'currentReaderPolicy' => 'pin-current-json-table-hidden-constraint-source-until-cursor-reset',
            'nextReaderPolicy' => $replanReasons === []
                ? 'reuse-current-json-table-hidden-constraint-source'
                : 'prepare-next-json-table-hidden-constraint-source',
            'dependencies' => ['sqlite-json-table-hidden-constraint-source-current-source-next102'],
        ];
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
     * @param list<array<string,mixed>> $currentHostRows
     * @param list<array<string,mixed>> $nextHostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,current:list<array<string,mixed>>,next:list<array<string,mixed>>,transitions:list<array<string,mixed>>,replanRequired:bool,replanReasons:list<string>,currentReaderPolicy:string,nextReaderPolicy:string,dependencies:list<string>}
     */
    public static function lateralPlannerCurrentSourceNext100(
        array $currentHostRows,
        array $nextHostRows,
        string $hostKeyColumn,
        string $jsonColumn,
        string $function,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        if ($hostKeyColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral current-source planner requires a host key column');
        }
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral current-source planner requires a host JSON column');
        }
        if ($rootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral current-source planner root column must be non-empty when provided');
        }

        $function = self::normalizeFunction($function);
        $currentByKey = self::hostRowsByKey100($currentHostRows, $hostKeyColumn, 'current');
        $nextByKey = self::hostRowsByKey100($nextHostRows, $hostKeyColumn, 'next');
        $keys = array_values(array_unique(array_merge(array_keys($currentByKey), array_keys($nextByKey))));

        $current = [];
        $next = [];
        $transitions = [];
        $reasons = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            $currentEntry = $currentByKey[$key] ?? null;
            $nextEntry = $nextByKey[$key] ?? null;
            $pair = null;
            $currentPlan = null;
            $nextPlan = null;

            if ($currentEntry !== null && $nextEntry !== null) {
                $pair = self::currentSourceConstraintPlannerNext86(
                    $function,
                    $currentEntry['row'],
                    $nextEntry['row'],
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $currentPlan = self::lateralCurrentSourceHostPlan100($currentEntry['index'], $key, $currentEntry['row'], $pair, 'current');
                $nextPlan = self::lateralCurrentSourceHostPlan100($nextEntry['index'], $key, $nextEntry['row'], $pair, 'next');
            } elseif ($currentEntry !== null) {
                $single = self::currentSourceConstraintPlannerNext86(
                    $function,
                    $currentEntry['row'],
                    $currentEntry['row'],
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $currentPlan = self::lateralCurrentSourceHostPlan100($currentEntry['index'], $key, $currentEntry['row'], $single, 'current');
            } elseif ($nextEntry !== null) {
                $single = self::currentSourceConstraintPlannerNext86(
                    $function,
                    $nextEntry['row'],
                    $nextEntry['row'],
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $nextPlan = self::lateralCurrentSourceHostPlan100($nextEntry['index'], $key, $nextEntry['row'], $single, 'next');
            }

            if ($currentPlan !== null) {
                $current[] = $currentPlan;
            }
            if ($nextPlan !== null) {
                $next[] = $nextPlan;
            }

            $reason = self::lateralCurrentSourceTransitionReason100($currentPlan, $nextPlan, $pair);
            if ($reason !== 'stable-lateral-current-source-json-plan') {
                $reasons[$reason] = true;
            }
            foreach (($pair['replanReasons'] ?? []) as $pairReason) {
                $reasons[$pairReason] = true;
            }

            $transitions[] = [
                'hostKey' => $key,
                'current' => $currentPlan,
                'next' => $nextPlan,
                'currentHostIndex' => $currentEntry['index'] ?? null,
                'nextHostIndex' => $nextEntry['index'] ?? null,
                'hostReordered' => $currentEntry !== null && $nextEntry !== null && $currentEntry['index'] !== $nextEntry['index'],
                'changed' => $reason !== 'stable-lateral-current-source-json-plan',
                'reason' => $reason,
                'currentRows' => $currentPlan['rowCount'] ?? 0,
                'nextRows' => $nextPlan['rowCount'] ?? 0,
                'rowCountChanged' => ($currentPlan['rowCount'] ?? 0) !== ($nextPlan['rowCount'] ?? 0),
                'currentFilterArguments' => $currentPlan['filterArguments'] ?? [],
                'nextFilterArguments' => $nextPlan['filterArguments'] ?? [],
                'argumentTransitions' => self::argumentTransitions(
                    $currentPlan['filterArguments'] ?? [],
                    $nextPlan['filterArguments'] ?? [],
                ),
                'pairReplanReasons' => $pair['replanReasons'] ?? [],
            ];
        }

        return [
            'function' => $function,
            'current' => $current,
            'next' => $next,
            'transitions' => $transitions,
            'replanRequired' => $reasons !== [],
            'replanReasons' => array_keys($reasons),
            'currentReaderPolicy' => 'pin-current-lateral-json-source-by-host-key-until-cursor-reset',
            'nextReaderPolicy' => $reasons === []
                ? 'reuse-current-lateral-json-source-by-host-key'
                : 'prepare-next-lateral-json-source-by-host-key',
            'dependencies' => [
                'sqlite-json-table-constraint-planner-current-source-next86',
                'sqlite-json-table-lateral-planner-current-source-next100',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentHostRows
     * @param list<array<string,mixed>> $nextHostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,hostKeyColumn:string,current:list<array<string,mixed>>,next:list<array<string,mixed>>,transitions:list<array<string,mixed>>,hostOrderTransition:array{current:list<mixed>,next:list<mixed>,changed:bool},replanRequired:bool,replanReasons:list<string>,currentReaderPolicy:string,nextReaderPolicy:string,leftJoin:bool,dependencies:list<string>}
     */
    public static function lateralHiddenConstraintCurrentSourceNext103(
        array $currentHostRows,
        array $nextHostRows,
        string $hostKeyColumn,
        string $jsonColumn,
        string $function,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
        string $joinType = 'inner',
    ): array {
        if ($hostKeyColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden keyed planner requires a host key column');
        }
        if ($jsonColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden keyed planner requires a host JSON column');
        }
        if ($rootColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden keyed planner root column must be non-empty when provided');
        }

        $function = self::normalizeFunction($function);
        $joinType = strtolower($joinType);
        if ($joinType !== 'inner' && $joinType !== 'left') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden keyed planner join type must be inner or left');
        }

        $currentIndex = self::keyedHostRows103($currentHostRows, $hostKeyColumn, $jsonColumn, $rootColumn, 'current');
        $nextIndex = self::keyedHostRows103($nextHostRows, $hostKeyColumn, $jsonColumn, $rootColumn, 'next');
        $hostKeys = array_values(array_unique(array_merge($currentIndex['keys'], $nextIndex['keys'])));

        $current = [];
        $next = [];
        $transitions = [];
        $reasons = [];
        foreach ($hostKeys as $ordinal => $hostKey) {
            $currentEntry = $currentIndex['rows'][$hostKey] ?? null;
            $nextEntry = $nextIndex['rows'][$hostKey] ?? null;
            $pair = null;

            if ($currentEntry !== null && $nextEntry !== null) {
                $pair = self::currentSourceHiddenConstraintPlannerNext88(
                    $function,
                    $currentEntry['row'],
                    $nextEntry['row'],
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $currentPlan = self::lateralHiddenHostPlan90($currentEntry['ordinal'], $currentEntry['row'], $pair, 'current', $joinType);
                $nextPlan = self::lateralHiddenHostPlan90($nextEntry['ordinal'], $nextEntry['row'], $pair, 'next', $joinType);
            } elseif ($currentEntry !== null) {
                $single = self::currentSourceHiddenConstraintPlannerNext88(
                    $function,
                    $currentEntry['row'],
                    $currentEntry['row'],
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $currentPlan = self::lateralHiddenHostPlan90($currentEntry['ordinal'], $currentEntry['row'], $single, 'current', $joinType);
                $nextPlan = null;
            } else {
                $single = self::currentSourceHiddenConstraintPlannerNext88(
                    $function,
                    $nextEntry['row'],
                    $nextEntry['row'],
                    $jsonColumn,
                    $constraints,
                    $rootColumn,
                    $orderBy,
                );
                $currentPlan = null;
                $nextPlan = self::lateralHiddenHostPlan90($nextEntry['ordinal'], $nextEntry['row'], $single, 'next', $joinType);
            }

            if ($currentPlan !== null) {
                $currentPlan['hostKey'] = $currentEntry['value'];
                $current[] = $currentPlan;
            }
            if ($nextPlan !== null) {
                $nextPlan['hostKey'] = $nextEntry['value'];
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
                'ordinal' => $ordinal,
                'hostKey' => $currentEntry['value'] ?? $nextEntry['value'],
                'hostKeyToken' => $hostKey,
                'currentOrdinal' => $currentEntry['ordinal'] ?? null,
                'nextOrdinal' => $nextEntry['ordinal'] ?? null,
                'ordinalChanged' => ($currentEntry['ordinal'] ?? null) !== ($nextEntry['ordinal'] ?? null),
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
            'hostKeyColumn' => $hostKeyColumn,
            'current' => $current,
            'next' => $next,
            'transitions' => $transitions,
            'hostOrderTransition' => [
                'current' => $currentIndex['values'],
                'next' => $nextIndex['values'],
                'changed' => $currentIndex['keys'] !== $nextIndex['keys'],
            ],
            'replanRequired' => $reasons !== [],
            'replanReasons' => array_keys($reasons),
            'currentReaderPolicy' => 'pin-current-lateral-hidden-keyed-json-source-until-host-key-advances',
            'nextReaderPolicy' => $reasons === []
                ? 'reuse-current-lateral-hidden-keyed-json-source-tape'
                : 'prepare-next-lateral-hidden-keyed-json-source-tape',
            'leftJoin' => $joinType === 'left',
            'dependencies' => [
                'sqlite-json-table-hidden-constraint-planner-current-source-next88',
                'sqlite-json-table-lateral-hidden-planner-current-source-next90',
                'sqlite-json-table-lateral-hidden-constraint-current-source-next103',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentHostRows
     * @param list<array<string,mixed>> $nextHostRows
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function lateralRowidHiddenCurrentSourceNext105(
        array $currentHostRows,
        array $nextHostRows,
        string $hostKeyColumn,
        string $jsonColumn,
        string $function,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
        string $joinType = 'inner',
    ): array {
        $plan = self::lateralHiddenConstraintCurrentSourceNext103(
            $currentHostRows,
            $nextHostRows,
            $hostKeyColumn,
            $jsonColumn,
            $function,
            $constraints,
            $rootColumn,
            $orderBy,
            $joinType,
        );

        $currentByHost = [];
        foreach ($plan['current'] as $hostPlan) {
            $currentByHost[self::hostKeyToken103($hostPlan['hostKey'])] = self::lateralHiddenRowidHostSummary105($hostPlan);
        }
        $nextByHost = [];
        foreach ($plan['next'] as $hostPlan) {
            $nextByHost[self::hostKeyToken103($hostPlan['hostKey'])] = self::lateralHiddenRowidHostSummary105($hostPlan);
        }

        $rowidReasons = [];
        $rowidTransitions = [];
        foreach ($plan['transitions'] as $transition) {
            $hostKeyToken = (string) $transition['hostKeyToken'];
            $currentSummary = $currentByHost[$hostKeyToken] ?? null;
            $nextSummary = $nextByHost[$hostKeyToken] ?? null;
            $rowidTransition = self::lateralHiddenRowidTransition105($transition, $currentSummary, $nextSummary);
            $rowidTransitions[] = $rowidTransition;
            foreach ($rowidTransition['rowTransitions'] as $rowTransition) {
                if ($rowTransition['reason'] !== 'stable-hidden-rowid-source-row') {
                    $rowidReasons[$rowTransition['reason']] = true;
                }
            }
            if ($rowidTransition['rowidChanged']) {
                $rowidReasons['lateral-hidden-rowid-tape-changed'] = true;
            }
            if ($rowidTransition['rowidResidualChanged']) {
                $rowidReasons['lateral-hidden-rowid-residual-usage-changed'] = true;
            }
        }

        $currentAliases = self::rowidAliasConstraintProvenance99($constraints, []);
        $aliasTransition = [
            'current' => array_column($currentAliases, 'originalColumn'),
            'next' => array_column($currentAliases, 'originalColumn'),
            'changed' => false,
        ];

        $plan['rowidTransitions'] = $rowidTransitions;
        $plan['currentRowidByHost'] = $currentByHost;
        $plan['nextRowidByHost'] = $nextByHost;
        $plan['rowidAliasConstraints'] = $currentAliases;
        $plan['rowidAliasTransition'] = $aliasTransition;
        $plan['replanReasons'] = array_values(array_unique(array_merge($plan['replanReasons'], array_keys($rowidReasons))));
        $plan['replanRequired'] = $plan['replanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-lateral-hidden-rowid-source-until-host-key-advances';
        $plan['nextReaderPolicy'] = $plan['replanRequired']
            ? 'prepare-next-lateral-hidden-rowid-source-tape'
            : 'reuse-current-lateral-hidden-rowid-source-tape';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            [
                'sqlite-json-table-lateral-rowid-current-next81',
                'sqlite-json-table-rowid-hidden-constraint-current-source-next99',
                'sqlite-json-table-lateral-rowid-hidden-current-source-next105',
            ],
        )));

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $currentHostRows
     * @param list<array<string,mixed>> $nextHostRows
     * @param list<array{column:string,sourceColumn?:string,value?:mixed,operator?:string,usable?:bool}> $constraintSources
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{function:string,hostKeyColumn:string,current:list<array<string,mixed>>,next:list<array<string,mixed>>,transitions:list<array<string,mixed>>,hostOrderTransition:array{current:list<mixed>,next:list<mixed>,changed:bool},replanRequired:bool,replanReasons:list<string>,currentReaderPolicy:string,nextReaderPolicy:string,leftJoin:bool,dependencies:list<string>}
     */
    public static function lateralConstraintHiddenCurrentSourceNext118(
        array $currentHostRows,
        array $nextHostRows,
        string $hostKeyColumn,
        string $function,
        array $constraintSources,
        array $orderBy = [],
        string $joinType = 'inner',
    ): array {
        if ($hostKeyColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden current-source next118 requires a host key column');
        }
        if ($constraintSources === []) {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden current-source next118 requires constraint sources');
        }

        $function = self::normalizeFunction($function);
        $joinType = strtolower($joinType);
        if ($joinType !== 'inner' && $joinType !== 'left') {
            throw new \InvalidArgumentException('SQLite JSON table lateral hidden current-source next118 join type must be inner or left');
        }

        $currentIndex = self::keyedHostRows118($currentHostRows, $hostKeyColumn, $constraintSources, 'current');
        $nextIndex = self::keyedHostRows118($nextHostRows, $hostKeyColumn, $constraintSources, 'next');
        $hostKeys = array_values(array_unique(array_merge($currentIndex['keys'], $nextIndex['keys'])));

        $current = [];
        $next = [];
        $transitions = [];
        $reasons = [];
        foreach ($hostKeys as $ordinal => $hostKey) {
            $currentEntry = $currentIndex['rows'][$hostKey] ?? null;
            $nextEntry = $nextIndex['rows'][$hostKey] ?? null;
            $pair = null;

            if ($currentEntry !== null && $nextEntry !== null) {
                $pair = self::hiddenConstraintSourceCurrentSourceNext102(
                    $function,
                    $currentEntry['row'],
                    $nextEntry['row'],
                    $constraintSources,
                    $orderBy,
                );
                $currentPlan = self::lateralConstraintHiddenHostPlan118($currentEntry['ordinal'], $currentEntry['row'], $pair, 'current', $joinType);
                $nextPlan = self::lateralConstraintHiddenHostPlan118($nextEntry['ordinal'], $nextEntry['row'], $pair, 'next', $joinType);
            } elseif ($currentEntry !== null) {
                $single = self::hiddenConstraintSourceCurrentSourceNext102(
                    $function,
                    $currentEntry['row'],
                    $currentEntry['row'],
                    $constraintSources,
                    $orderBy,
                );
                $currentPlan = self::lateralConstraintHiddenHostPlan118($currentEntry['ordinal'], $currentEntry['row'], $single, 'current', $joinType);
                $nextPlan = null;
            } else {
                $single = self::hiddenConstraintSourceCurrentSourceNext102(
                    $function,
                    $nextEntry['row'],
                    $nextEntry['row'],
                    $constraintSources,
                    $orderBy,
                );
                $currentPlan = null;
                $nextPlan = self::lateralConstraintHiddenHostPlan118($nextEntry['ordinal'], $nextEntry['row'], $single, 'next', $joinType);
            }

            if ($currentPlan !== null) {
                $currentPlan['hostKey'] = $currentEntry['value'];
                $current[] = $currentPlan;
            }
            if ($nextPlan !== null) {
                $nextPlan['hostKey'] = $nextEntry['value'];
                $next[] = $nextPlan;
            }

            $reason = self::lateralConstraintHiddenTransitionReason118($currentPlan, $nextPlan, $pair);
            if ($reason !== 'stable-lateral-hidden-current-source') {
                $reasons[$reason] = true;
            }
            foreach (($pair['replanReasons'] ?? []) as $pairReason) {
                $reasons[$pairReason] = true;
            }

            $transitions[] = [
                'ordinal' => $ordinal,
                'hostKey' => $currentEntry['value'] ?? $nextEntry['value'],
                'hostKeyToken' => $hostKey,
                'currentOrdinal' => $currentEntry['ordinal'] ?? null,
                'nextOrdinal' => $nextEntry['ordinal'] ?? null,
                'ordinalChanged' => ($currentEntry['ordinal'] ?? null) !== ($nextEntry['ordinal'] ?? null),
                'current' => $currentPlan,
                'next' => $nextPlan,
                'changed' => $reason !== 'stable-lateral-hidden-current-source',
                'reason' => $reason,
                'currentRows' => $currentPlan['rowCount'] ?? 0,
                'nextRows' => $nextPlan['rowCount'] ?? 0,
                'rowCountChanged' => ($currentPlan['rowCount'] ?? 0) !== ($nextPlan['rowCount'] ?? 0),
                'currentNullExtended' => $currentPlan['nullExtended'] ?? false,
                'nextNullExtended' => $nextPlan['nullExtended'] ?? false,
                'constraintValueTransitions' => $pair['constraintValueTransitions'] ?? [],
                'pairReplanReasons' => $pair['replanReasons'] ?? [],
            ];
        }

        return [
            'function' => $function,
            'hostKeyColumn' => $hostKeyColumn,
            'current' => $current,
            'next' => $next,
            'transitions' => $transitions,
            'hostOrderTransition' => [
                'current' => $currentIndex['values'],
                'next' => $nextIndex['values'],
                'changed' => $currentIndex['keys'] !== $nextIndex['keys'],
            ],
            'replanRequired' => $reasons !== [],
            'replanReasons' => array_keys($reasons),
            'currentReaderPolicy' => 'pin-current-lateral-hidden-current-source-until-host-key-advances',
            'nextReaderPolicy' => $reasons === []
                ? 'reuse-current-lateral-hidden-current-source-tape'
                : 'prepare-next-lateral-hidden-current-source-tape',
            'leftJoin' => $joinType === 'left',
            'dependencies' => [
                'sqlite-json-table-hidden-constraint-source-current-source-next102',
                'sqlite-json-table-lateral-hidden-current-source-next118',
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
     * @param list<array<string,mixed>> $hostRows
     * @return array<string,array{index:int,row:array<string,mixed>}>
     */
    private static function hostRowsByKey100(array $hostRows, string $hostKeyColumn, string $side): array
    {
        $rows = [];
        foreach ($hostRows as $index => $hostRow) {
            if (!array_key_exists($hostKeyColumn, $hostRow)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral {$side} host row is missing {$hostKeyColumn}");
            }
            $key = self::hostKey100($hostRow[$hostKeyColumn]);
            if (isset($rows[$key])) {
                throw new \InvalidArgumentException("SQLite JSON table lateral {$side} host key {$key} is duplicated");
            }
            $rows[$key] = ['index' => $index, 'row' => $hostRow];
        }

        return $rows;
    }

    private static function hostKey100(mixed $value): string
    {
        if (is_int($value) || is_string($value)) {
            return (string) $value;
        }
        if ($value === null) {
            throw new \InvalidArgumentException('SQLite JSON table lateral host key cannot be NULL');
        }

        throw new \InvalidArgumentException('SQLite JSON table lateral host key must be text or integer');
    }

    /**
     * @param array<string,mixed> $hostRow
     * @param array<string,mixed> $pair
     * @return array<string,mixed>
     */
    private static function lateralCurrentSourceHostPlan100(
        int $hostIndex,
        string $hostKey,
        array $hostRow,
        array $pair,
        string $side,
    ): array {
        $rows = $side === 'current' ? $pair['currentRows'] : $pair['nextRows'];
        $sourcePlan = $side === 'current' ? $pair['current'] : $pair['next'];

        return [
            'hostKey' => $hostKey,
            'hostIndex' => $hostIndex,
            'hostRow' => $hostRow,
            'jsonValue' => $sourcePlan['jsonValue'],
            'rootValue' => $sourcePlan['rootValue'],
            'runnable' => $sourcePlan['runnable'],
            'rowCount' => count($rows),
            'idxStr' => $sourcePlan['idxStr'],
            'filterArguments' => $sourcePlan['filterArguments'],
            'constraintUsage' => $sourcePlan['constraintUsage'],
            'orderByConsumed' => $sourcePlan['orderByConsumed'],
            'estimatedRows' => $sourcePlan['estimatedRows'],
            'jsonInputKind' => $sourcePlan['jsonInputKind'],
            'jsonValid' => $sourcePlan['jsonValid'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     * @param array<string,mixed>|null $pair
     */
    private static function lateralCurrentSourceTransitionReason100(?array $current, ?array $next, ?array $pair): string
    {
        if ($current === null) {
            return 'next-lateral-current-source-host-row-added';
        }
        if ($next === null) {
            return 'current-lateral-current-source-host-row-removed';
        }
        if (($pair['replanReasons'] ?? []) !== []) {
            return 'lateral-current-source-plan-changed';
        }
        if (($current['hostIndex'] ?? null) !== ($next['hostIndex'] ?? null)) {
            return 'lateral-current-source-host-row-reordered';
        }
        if (($current['rowCount'] ?? 0) !== ($next['rowCount'] ?? 0)) {
            return 'lateral-current-source-row-count-changed';
        }

        return 'stable-lateral-current-source-json-plan';
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
            'used' => $indexPlan['used'],
            'residual' => $indexPlan['residual'],
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
     * @param array<string,mixed> $source
     * @return array{baseRoot:string,nestedPath:string,root:string,mode:string}
     */
    private static function composeNestedRootPath121(array $source, string $baseRootColumn, string $nestedPathColumn, string $side): array
    {
        if (!array_key_exists($baseRootColumn, $source)) {
            throw new \InvalidArgumentException("SQLite JSON table nested path {$side} source row is missing {$baseRootColumn}");
        }
        if (!array_key_exists($nestedPathColumn, $source)) {
            throw new \InvalidArgumentException("SQLite JSON table nested path {$side} source row is missing {$nestedPathColumn}");
        }

        $baseRoot = $source[$baseRootColumn];
        $nestedPath = $source[$nestedPathColumn];
        if (!is_string($baseRoot)) {
            throw new \InvalidArgumentException('SQLite JSON table nested path base root must be text');
        }
        if (!is_string($nestedPath)) {
            throw new \InvalidArgumentException('SQLite JSON table nested path fragment must be text');
        }
        if (!SQLiteJsonPath::isWellFormed($baseRoot)) {
            throw new \InvalidArgumentException('SQLite JSON table nested path base root is not a well-formed path');
        }

        if ($nestedPath === '') {
            $root = $baseRoot;
            $mode = 'base-root';
        } elseif ($nestedPath[0] === '$') {
            $root = $nestedPath;
            $mode = 'absolute-nested-root';
        } elseif ($nestedPath[0] === '[') {
            $root = $baseRoot . $nestedPath;
            $mode = 'array-fragment';
        } elseif ($nestedPath[0] === '.') {
            $root = $baseRoot . $nestedPath;
            $mode = 'object-fragment';
        } else {
            $root = $baseRoot . '.' . $nestedPath;
            $mode = 'bare-label-fragment';
        }

        if (!SQLiteJsonPath::isWellFormed($root)) {
            throw new \InvalidArgumentException('SQLite JSON table nested path composed root is not a well-formed path');
        }

        return [
            'baseRoot' => $baseRoot,
            'nestedPath' => $nestedPath,
            'root' => $root,
            'mode' => $mode,
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{orderBy:list<array{column:string,direction:string}>,orderByConsumed:bool,requiresSorter:bool,baseEstimatedCost:int,baseEstimatedRows:int,sortPenalty:int,effectiveEstimatedCost:int,costClass:string,rowOrder:list<int|null>,firstOrderKey:mixed,lastOrderKey:mixed}
     */
    private static function jsonTableCostOrderProfile113(array $plan, array $orderBy): array
    {
        $normalizedOrderBy = self::normalizeOrderByTerms113($orderBy);
        $rows = $plan['rows'];
        $rowCount = count($rows);
        $requiresSorter = $normalizedOrderBy !== [] && !$plan['orderByConsumed'] && $rowCount > 1;
        $sortPenalty = $requiresSorter ? self::jsonTableSortPenalty113($rowCount, $normalizedOrderBy) : 0;
        $baseCost = (int) $plan['estimatedCost'];
        $effectiveCost = $baseCost >= 1000000 ? $baseCost : $baseCost + $sortPenalty;

        return [
            'orderBy' => $normalizedOrderBy,
            'orderByConsumed' => (bool) $plan['orderByConsumed'],
            'requiresSorter' => $requiresSorter,
            'baseEstimatedCost' => $baseCost,
            'baseEstimatedRows' => (int) $plan['estimatedRows'],
            'sortPenalty' => $sortPenalty,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableCostClass113($plan, $requiresSorter, $effectiveCost),
            'rowOrder' => array_map(
                static fn (array $row): ?int => isset($row['id']) ? (int) $row['id'] : null,
                $rows,
            ),
            'firstOrderKey' => self::jsonTableOrderKey113($rows[0] ?? null, $normalizedOrderBy),
            'lastOrderKey' => self::jsonTableOrderKey113($rows[$rowCount - 1] ?? null, $normalizedOrderBy),
        ];
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array{column:string,direction:string}>
     */
    private static function normalizeOrderByTerms113(array $orderBy): array
    {
        $terms = [];
        foreach ($orderBy as $term) {
            $column = self::normalizeConstraintColumn((string) $term['column']);
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite JSON table ORDER BY direction must be ASC or DESC');
            }
            $terms[] = ['column' => $column, 'direction' => $direction];
        }

        return $terms;
    }

    /**
     * @param list<array{column:string,direction:string}> $orderBy
     */
    private static function jsonTableSortPenalty113(int $rowCount, array $orderBy): int
    {
        $width = max(1, count($orderBy));
        $comparisons = max(1, $rowCount * max(1, (int) ceil(log(max(2, $rowCount), 2))));

        return $comparisons * $width;
    }

    private static function jsonTableCostClass113(array $plan, bool $requiresSorter, int $effectiveCost): string
    {
        if (!$plan['runnable']) {
            return 'unrunnable-json-table';
        }
        if ($requiresSorter) {
            return 'runnable-json-table-sort-required';
        }
        if ($plan['orderByConsumed']) {
            return 'runnable-json-table-streaming-order';
        }
        if ($effectiveCost <= 10) {
            return 'runnable-json-table-narrow-visible-scan';
        }

        return 'runnable-json-table-scan';
    }

    /**
     * @param array<string,mixed>|null $row
     * @param list<array{column:string,direction:string}> $orderBy
     * @return list<mixed>
     */
    private static function jsonTableOrderKey113(?array $row, array $orderBy): array
    {
        if ($row === null || $orderBy === []) {
            return [];
        }

        $key = [];
        foreach ($orderBy as $term) {
            $column = $term['column'];
            $key[] = self::rowHasColumn($row, $column) ? self::rowColumnValue($row, $column) : null;
        }

        return $key;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableCostOrderTransitions113(array $current, array $next): array
    {
        return [
            [
                'field' => 'orderBy',
                'current' => $current['orderBy'],
                'next' => $next['orderBy'],
                'changed' => $current['orderBy'] !== $next['orderBy'],
            ],
            [
                'field' => 'orderByConsumed',
                'current' => $current['orderByConsumed'],
                'next' => $next['orderByConsumed'],
                'changed' => $current['orderByConsumed'] !== $next['orderByConsumed'],
            ],
            [
                'field' => 'requiresSorter',
                'current' => $current['requiresSorter'],
                'next' => $next['requiresSorter'],
                'changed' => $current['requiresSorter'] !== $next['requiresSorter'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'rowOrder',
                'current' => $current['rowOrder'],
                'next' => $next['rowOrder'],
                'changed' => $current['rowOrder'] !== $next['rowOrder'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableCostOrderReplanReasons113(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'orderBy', 'orderByConsumed' => 'json-table-order-consumption-changed',
                'requiresSorter' => 'json-table-sorter-requirement-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-cost-class-changed',
                'rowOrder' => 'json-table-output-order-changed',
                default => 'json-table-cost-order-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $costOrder
     * @return array{indexedConstraints:list<array<string,mixed>>,selected:array<string,mixed>|null,selectedSignature:string|null,scanStrategy:string,indexedEstimatedRows:int,indexedEstimatedCost:int,sortPenalty:int,effectiveEstimatedCost:int,costClass:string,rowCount:int}
     */
    private static function jsonTableIndexedConstraintCostProfile119(array $plan, array $costOrder): array
    {
        $indexed = [];
        foreach ($plan['constraintUsage'] as $usage) {
            if (($usage['kind'] ?? null) !== 'visible' || ($usage['usable'] ?? true) !== true) {
                continue;
            }

            $argumentIndex = $usage['argvIndex'] === null ? null : (int) $usage['argvIndex'] - 1;
            $candidateUsage = $usage;
            $candidateUsage['value'] = $argumentIndex !== null && array_key_exists($argumentIndex, $plan['filterArguments'])
                ? $plan['filterArguments'][$argumentIndex]
                : null;
            $candidate = self::jsonTableIndexedConstraintCandidate119($candidateUsage, (int) $plan['estimatedRows'], (int) $plan['estimatedCost']);
            if ($candidate !== null) {
                $indexed[] = $candidate;
            }
        }

        usort(
            $indexed,
            static fn (array $left, array $right): int => [$left['indexedEstimatedCost'], $left['rank'], $left['constraintIndex']]
                <=> [$right['indexedEstimatedCost'], $right['rank'], $right['constraintIndex']],
        );

        $selected = $indexed[0] ?? null;
        $sortPenalty = (int) $costOrder['sortPenalty'];
        if (!$plan['runnable']) {
            $indexedRows = 0;
            $indexedCost = 1000000;
            $effectiveCost = 1000000;
            $scanStrategy = 'unrunnable-json-table';
        } elseif ($selected === null) {
            $indexedRows = (int) $plan['estimatedRows'];
            $indexedCost = (int) $plan['estimatedCost'];
            $effectiveCost = (int) $costOrder['effectiveEstimatedCost'];
            $scanStrategy = 'full-json-table-scan';
        } else {
            $indexedRows = (int) $selected['indexedEstimatedRows'];
            $indexedCost = (int) $selected['indexedEstimatedCost'];
            $effectiveCost = $indexedCost + $sortPenalty;
            $scanStrategy = 'indexed-json-table-constraint';
        }

        return [
            'indexedConstraints' => $indexed,
            'selected' => $selected,
            'selectedSignature' => $selected === null ? null : self::jsonTableIndexedConstraintSignature119($selected),
            'scanStrategy' => $scanStrategy,
            'indexedEstimatedRows' => $indexedRows,
            'indexedEstimatedCost' => $indexedCost,
            'sortPenalty' => $sortPenalty,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableIndexedConstraintCostClass119($scanStrategy, $selected, $effectiveCost),
            'rowCount' => count($plan['rows']),
        ];
    }

    /**
     * @param array<string,mixed> $usage
     * @return array<string,mixed>|null
     */
    private static function jsonTableIndexedConstraintCandidate119(array $usage, int $baseRows, int $baseCost): ?array
    {
        $column = self::normalizeConstraintColumn((string) $usage['column']);
        $operator = strtoupper((string) $usage['operator']);
        if (!in_array($column, ['id', 'fullkey', 'path', 'key'], true)) {
            return null;
        }

        $rank = match ($column) {
            'id' => 1,
            'fullkey' => 2,
            'path' => 3,
            default => 4,
        };
        $selectivity = match ($operator) {
            '=', 'IS', 'IS NOT DISTINCT FROM' => $column === 'id' || $column === 'fullkey' ? 16 : 8,
            'IN' => is_array($usage['current'] ?? $usage['value'] ?? null)
                ? max(4, min(12, count($usage['current'] ?? $usage['value'])))
                : 4,
            'BETWEEN', '<', '<=', '>', '>=' => $column === 'id' ? 6 : 3,
            'LIKE', 'GLOB' => self::patternHasFixedPrefix($usage['current'] ?? $usage['value'] ?? null) ? 5 : 2,
            'IS NULL', 'IS NOT NULL', 'IS DISTINCT FROM' => 2,
            default => 1,
        };

        if ($selectivity <= 1) {
            return null;
        }

        $rows = max(1, intdiv(max(1, $baseRows) + $selectivity - 1, $selectivity));
        $cost = max(1, intdiv(max(1, $baseCost) + $selectivity - 1, $selectivity));

        return [
            'constraintIndex' => (int) $usage['constraintIndex'],
            'column' => $column,
            'operator' => $operator,
            'argvIndex' => $usage['argvIndex'],
            'omit' => (bool) $usage['omit'],
            'rank' => $rank,
            'selectivity' => $selectivity,
            'indexedEstimatedRows' => $rows,
            'indexedEstimatedCost' => $cost,
            'value' => $usage['current'] ?? $usage['value'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed>|null $selected
     */
    private static function jsonTableIndexedConstraintCostClass119(string $scanStrategy, ?array $selected, int $effectiveCost): string
    {
        if ($scanStrategy === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($selected === null) {
            return $effectiveCost <= 10 ? 'json-table-narrow-full-scan' : 'json-table-full-scan';
        }
        if ($selected['column'] === 'id' && in_array($selected['operator'], ['=', 'IS', 'IS NOT DISTINCT FROM'], true)) {
            return 'json-table-rowid-point-lookup';
        }
        if ($effectiveCost <= 4) {
            return 'json-table-indexed-narrow-scan';
        }

        return 'json-table-indexed-range-scan';
    }

    /**
     * @param array<string,mixed> $selected
     */
    private static function jsonTableIndexedConstraintSignature119(array $selected): string
    {
        return implode(':', [
            (string) $selected['constraintIndex'],
            (string) $selected['column'],
            (string) $selected['operator'],
            json_encode($selected['value'], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableIndexedConstraintTransitions119(array $current, array $next): array
    {
        return [
            [
                'field' => 'selectedSignature',
                'current' => $current['selectedSignature'],
                'next' => $next['selectedSignature'],
                'changed' => $current['selectedSignature'] !== $next['selectedSignature'],
            ],
            [
                'field' => 'scanStrategy',
                'current' => $current['scanStrategy'],
                'next' => $next['scanStrategy'],
                'changed' => $current['scanStrategy'] !== $next['scanStrategy'],
            ],
            [
                'field' => 'indexedEstimatedCost',
                'current' => $current['indexedEstimatedCost'],
                'next' => $next['indexedEstimatedCost'],
                'changed' => $current['indexedEstimatedCost'] !== $next['indexedEstimatedCost'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'rowCount',
                'current' => $current['rowCount'],
                'next' => $next['rowCount'],
                'changed' => $current['rowCount'] !== $next['rowCount'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableIndexedConstraintReplanReasons119(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'selectedSignature' => 'json-table-indexed-constraint-changed',
                'scanStrategy' => 'json-table-indexed-scan-strategy-changed',
                'indexedEstimatedCost', 'effectiveEstimatedCost', 'costClass' => 'json-table-indexed-cost-changed',
                'rowCount' => 'json-table-indexed-row-count-changed',
                default => 'json-table-indexed-constraint-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $indexedCost
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{hiddenOrderBy:list<array{column:string,direction:string}>,hasHiddenOrder:bool,requiresHiddenSorter:bool,sourceHiddenKey:list<mixed>,rowHiddenKeys:list<list<mixed>>,firstHiddenKey:list<mixed>,lastHiddenKey:list<mixed>,selectedSignature:string|null,indexedEffectiveCost:int,hiddenSortPenalty:int,effectiveEstimatedCost:int,costClass:string,rowCount:int}
     */
    private static function jsonTableIndexedHiddenOrderProfile122(array $plan, array $indexedCost, array $orderBy): array
    {
        $hiddenOrderBy = self::jsonTableHiddenOrderTerms122($orderBy);
        $rows = $plan['rows'];
        $rowCount = count($rows);
        $hasHiddenOrder = $hiddenOrderBy !== [];
        $requiresHiddenSorter = $hasHiddenOrder && $rowCount > 1;
        $sourceHiddenKey = self::jsonTableHiddenOrderSourceKey122($plan, $hiddenOrderBy);
        $rowHiddenKeys = [];
        foreach ($rows as $row) {
            $rowKey = $sourceHiddenKey;
            if ($hasHiddenOrder) {
                $rowKey[] = isset($row['id']) ? (int) $row['id'] : null;
            }
            $rowHiddenKeys[] = $rowKey;
        }

        $hiddenSortPenalty = $requiresHiddenSorter
            ? self::jsonTableSortPenalty113($rowCount, $hiddenOrderBy)
            : 0;
        $indexedEffectiveCost = (int) $indexedCost['indexedEstimatedCost'];
        $effectiveEstimatedCost = $indexedEffectiveCost >= 1000000
            ? $indexedEffectiveCost
            : $indexedEffectiveCost + $hiddenSortPenalty;

        return [
            'hiddenOrderBy' => $hiddenOrderBy,
            'hasHiddenOrder' => $hasHiddenOrder,
            'requiresHiddenSorter' => $requiresHiddenSorter,
            'sourceHiddenKey' => $sourceHiddenKey,
            'rowHiddenKeys' => $rowHiddenKeys,
            'firstHiddenKey' => $rowHiddenKeys[0] ?? [],
            'lastHiddenKey' => $rowHiddenKeys[$rowCount - 1] ?? [],
            'selectedSignature' => $indexedCost['selectedSignature'],
            'indexedEffectiveCost' => $indexedEffectiveCost,
            'hiddenSortPenalty' => $hiddenSortPenalty,
            'effectiveEstimatedCost' => $effectiveEstimatedCost,
            'costClass' => self::jsonTableIndexedHiddenOrderCostClass122($indexedCost, $hasHiddenOrder, $requiresHiddenSorter, $effectiveEstimatedCost),
            'rowCount' => $rowCount,
        ];
    }

    /**
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array{column:string,direction:string}>
     */
    private static function jsonTableHiddenOrderTerms122(array $orderBy): array
    {
        $terms = [];
        foreach (self::normalizeOrderByTerms113($orderBy) as $term) {
            if ($term['column'] === 'json' || $term['column'] === 'root') {
                $terms[] = $term;
            }
        }

        return $terms;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array{column:string,direction:string}> $hiddenOrderBy
     * @return list<mixed>
     */
    private static function jsonTableHiddenOrderSourceKey122(array $plan, array $hiddenOrderBy): array
    {
        $key = [];
        foreach ($hiddenOrderBy as $term) {
            $value = $term['column'] === 'root' ? '$' : null;
            if ($term['column'] === 'json' && array_key_exists('jsonValue', $plan)) {
                $value = $plan['jsonValue'];
            } elseif ($term['column'] === 'root' && array_key_exists('rootValue', $plan)) {
                $value = $plan['rootValue'];
            }
            foreach ($plan['constraintSources'] ?? [] as $constraint) {
                if (($constraint['column'] ?? null) === $term['column']) {
                    $value = $constraint['value'] ?? null;
                    break;
                }
            }
            $key[] = $value;
        }

        return $key;
    }

    /**
     * @param array<string,mixed> $indexedCost
     */
    private static function jsonTableIndexedHiddenOrderCostClass122(array $indexedCost, bool $hasHiddenOrder, bool $requiresHiddenSorter, int $effectiveCost): string
    {
        if (($indexedCost['scanStrategy'] ?? null) === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if (!$hasHiddenOrder) {
            return (string) $indexedCost['costClass'];
        }
        if ($requiresHiddenSorter) {
            return 'json-table-indexed-hidden-order-sort-required';
        }
        if (($indexedCost['selected'] ?? null) !== null) {
            return $effectiveCost <= 4 ? 'json-table-indexed-hidden-order-narrow' : 'json-table-indexed-hidden-order';
        }

        return 'json-table-hidden-order-full-scan';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableIndexedHiddenOrderTransitions122(array $current, array $next): array
    {
        return [
            [
                'field' => 'hiddenOrderBy',
                'current' => $current['hiddenOrderBy'],
                'next' => $next['hiddenOrderBy'],
                'changed' => $current['hiddenOrderBy'] !== $next['hiddenOrderBy'],
            ],
            [
                'field' => 'sourceHiddenKey',
                'current' => $current['sourceHiddenKey'],
                'next' => $next['sourceHiddenKey'],
                'changed' => $current['sourceHiddenKey'] !== $next['sourceHiddenKey'],
            ],
            [
                'field' => 'requiresHiddenSorter',
                'current' => $current['requiresHiddenSorter'],
                'next' => $next['requiresHiddenSorter'],
                'changed' => $current['requiresHiddenSorter'] !== $next['requiresHiddenSorter'],
            ],
            [
                'field' => 'rowHiddenKeys',
                'current' => $current['rowHiddenKeys'],
                'next' => $next['rowHiddenKeys'],
                'changed' => $current['rowHiddenKeys'] !== $next['rowHiddenKeys'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableIndexedHiddenOrderReplanReasons122(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'hiddenOrderBy' => 'json-table-hidden-order-terms-changed',
                'sourceHiddenKey' => 'json-table-hidden-order-source-changed',
                'requiresHiddenSorter' => 'json-table-hidden-order-sorter-changed',
                'rowHiddenKeys' => 'json-table-hidden-output-order-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-hidden-order-cost-changed',
                default => 'json-table-hidden-order-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $hiddenOrder
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return array{generatedOrderBy:list<array{name:string,source:string,path:string,direction:string}>,hasGeneratedOrder:bool,requiresGeneratedSorter:bool,generatedSortPenalty:int,hiddenSourceKey:list<mixed>,rowGeneratedKeys:list<list<mixed>>,orderedRowids:list<int|null>,firstGeneratedKey:list<mixed>,lastGeneratedKey:list<mixed>,generatedOutputTape:list<array{rowid:int|null,key:list<mixed>,fullkey:mixed}>,hiddenEffectiveCost:int,effectiveEstimatedCost:int,costClass:string,rowCount:int}
     */
    private static function jsonTableHiddenGeneratedOrderProfile132(array $plan, array $hiddenOrder, array $generatedOrder): array
    {
        $terms = self::normalizeGeneratedOrderTerms132($generatedOrder);
        $rows = $plan['rows'];
        $rowCount = count($rows);
        $hiddenSourceKey = $hiddenOrder['sourceHiddenKey'] ?? [];
        $rowEntries = [];
        foreach ($rows as $row) {
            $key = [];
            foreach ($terms as $term) {
                $key[] = self::generatedOrderValue132($row, $term);
            }
            $rowEntries[] = [
                'rowid' => isset($row['id']) ? (int) $row['id'] : null,
                'key' => $key,
                'fullkey' => $row['fullkey'] ?? null,
            ];
        }

        if ($terms !== []) {
            usort(
                $rowEntries,
                static fn (array $left, array $right): int => self::compareGeneratedOrderEntries132($left, $right, $terms),
            );
        }

        $generatedSortPenalty = $rowCount > 1 ? self::jsonTableSortPenalty113($rowCount, $terms) : 0;
        $hiddenEffectiveCost = (int) ($hiddenOrder['effectiveEstimatedCost'] ?? 1000000);
        $effectiveEstimatedCost = $hiddenEffectiveCost >= 1000000
            ? $hiddenEffectiveCost
            : $hiddenEffectiveCost + $generatedSortPenalty;

        return [
            'generatedOrderBy' => $terms,
            'hasGeneratedOrder' => $terms !== [],
            'requiresGeneratedSorter' => $terms !== [] && $rowCount > 1,
            'generatedSortPenalty' => $generatedSortPenalty,
            'hiddenSourceKey' => $hiddenSourceKey,
            'rowGeneratedKeys' => array_values(array_map(static fn (array $entry): array => $entry['key'], $rowEntries)),
            'orderedRowids' => array_values(array_map(static fn (array $entry): ?int => $entry['rowid'], $rowEntries)),
            'firstGeneratedKey' => $rowEntries[0]['key'] ?? [],
            'lastGeneratedKey' => $rowEntries[$rowCount - 1]['key'] ?? [],
            'generatedOutputTape' => $rowEntries,
            'hiddenEffectiveCost' => $hiddenEffectiveCost,
            'effectiveEstimatedCost' => $effectiveEstimatedCost,
            'costClass' => self::jsonTableHiddenGeneratedOrderCostClass132($hiddenOrder, $terms, $terms !== [] && $rowCount > 1, $effectiveEstimatedCost),
            'rowCount' => $rowCount,
        ];
    }

    /**
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return list<array{name:string,source:string,path:string,direction:string}>
     */
    private static function normalizeGeneratedOrderTerms132(array $generatedOrder): array
    {
        $terms = [];
        foreach ($generatedOrder as $term) {
            $name = strtolower((string) ($term['name'] ?? ''));
            $source = strtolower((string) ($term['source'] ?? 'value'));
            $path = (string) ($term['path'] ?? '');
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($name === '' || $path === '') {
                throw new \InvalidArgumentException('SQLite JSON table generated order terms require a name and path');
            }
            if (!in_array($source, ['value', 'json', 'atom'], true)) {
                throw new \InvalidArgumentException('SQLite JSON table generated order source must be value, json, or atom');
            }
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite JSON table generated order direction must be ASC or DESC');
            }
            if (!SQLiteJsonPath::isWellFormed($path)) {
                throw new \InvalidArgumentException('SQLite JSON table generated order path is not well-formed');
            }

            $terms[] = [
                'name' => $name,
                'source' => $source,
                'path' => $path,
                'direction' => $direction,
            ];
        }

        return $terms;
    }

    /**
     * @param array{name:string,source:string,path:string,direction:string} $term
     */
    private static function generatedOrderValue132(array $row, array $term): mixed
    {
        $source = self::rowColumnValue($row, $term['source']);
        if ($source === null) {
            return null;
        }
        if (!is_string($source) && !$source instanceof SQLiteBlobValue) {
            $source = SQLiteJsonCanonical::encodeDecodedJson($source);
        }

        return SQLiteJsonExtract::extract($source, $term['path']);
    }

    /**
     * @param list<array{name:string,source:string,path:string,direction:string}> $terms
     */
    private static function compareGeneratedOrderEntries132(array $left, array $right, array $terms): int
    {
        foreach ($terms as $index => $term) {
            $comparison = self::compareResidualOrdered($left['key'][$index] ?? null, $right['key'][$index] ?? null);
            if ($comparison !== 0) {
                return $term['direction'] === 'DESC' ? -$comparison : $comparison;
            }
        }

        return ((int) ($left['rowid'] ?? 0)) <=> ((int) ($right['rowid'] ?? 0));
    }

    /**
     * @param array<string,mixed> $hiddenOrder
     * @param list<array{name:string,source:string,path:string,direction:string}> $terms
     */
    private static function jsonTableHiddenGeneratedOrderCostClass132(array $hiddenOrder, array $terms, bool $requiresSorter, int $effectiveCost): string
    {
        if (($hiddenOrder['costClass'] ?? null) === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($requiresSorter) {
            return 'json-table-hidden-generated-order-sort-required';
        }
        if ($terms !== []) {
            return $effectiveCost <= 6 ? 'json-table-hidden-generated-order-narrow' : 'json-table-hidden-generated-order';
        }

        return (string) ($hiddenOrder['costClass'] ?? 'json-table-hidden-generated-order');
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableHiddenGeneratedOrderTransitions132(array $current, array $next): array
    {
        return [
            [
                'field' => 'generatedOrderBy',
                'current' => $current['generatedOrderBy'],
                'next' => $next['generatedOrderBy'],
                'changed' => $current['generatedOrderBy'] !== $next['generatedOrderBy'],
            ],
            [
                'field' => 'hiddenSourceKey',
                'current' => $current['hiddenSourceKey'],
                'next' => $next['hiddenSourceKey'],
                'changed' => $current['hiddenSourceKey'] !== $next['hiddenSourceKey'],
            ],
            [
                'field' => 'rowGeneratedKeys',
                'current' => $current['rowGeneratedKeys'],
                'next' => $next['rowGeneratedKeys'],
                'changed' => $current['rowGeneratedKeys'] !== $next['rowGeneratedKeys'],
            ],
            [
                'field' => 'orderedRowids',
                'current' => $current['orderedRowids'],
                'next' => $next['orderedRowids'],
                'changed' => $current['orderedRowids'] !== $next['orderedRowids'],
            ],
            [
                'field' => 'requiresGeneratedSorter',
                'current' => $current['requiresGeneratedSorter'],
                'next' => $next['requiresGeneratedSorter'],
                'changed' => $current['requiresGeneratedSorter'] !== $next['requiresGeneratedSorter'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableHiddenGeneratedOrderReplanReasons132(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'generatedOrderBy' => 'json-table-hidden-generated-order-terms-changed',
                'hiddenSourceKey' => 'json-table-hidden-generated-source-changed',
                'rowGeneratedKeys' => 'json-table-hidden-generated-keys-changed',
                'orderedRowids' => 'json-table-hidden-generated-output-order-changed',
                'requiresGeneratedSorter' => 'json-table-hidden-generated-sorter-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-hidden-generated-cost-changed',
                default => 'json-table-hidden-generated-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $indexedCost
     * @return array{pathConstraints:list<array<string,mixed>>,selectedPath:array<string,mixed>|null,selectedPathSignature:string|null,pathScanStrategy:string,pathEstimatedRows:int,pathEstimatedCost:int,effectiveEstimatedCost:int,costClass:string,pathRowCount:int,pathTape:list<string>,firstPath:string|null,lastPath:string|null}
     */
    private static function jsonTablePathConstraintProfile123(array $plan, array $indexedCost): array
    {
        $pathConstraints = [];
        foreach ($indexedCost['indexedConstraints'] as $constraint) {
            if (($constraint['column'] ?? null) === 'path') {
                $pathConstraints[] = $constraint;
            }
        }

        $selected = null;
        if (($indexedCost['selected']['column'] ?? null) === 'path') {
            $selected = $indexedCost['selected'];
        } elseif ($pathConstraints !== []) {
            $selected = $pathConstraints[0];
        }

        $pathTape = self::jsonTablePathTape123($plan['rows']);
        if (!$plan['runnable']) {
            $strategy = 'unrunnable-json-table';
            $rows = 0;
            $cost = 1000000;
            $effectiveCost = 1000000;
            $costClass = 'unrunnable-json-table';
        } elseif ($selected === null) {
            $strategy = 'full-json-table-scan';
            $rows = (int) $plan['estimatedRows'];
            $cost = (int) $plan['estimatedCost'];
            $effectiveCost = (int) $indexedCost['effectiveEstimatedCost'];
            $costClass = $indexedCost['costClass'] === 'unrunnable-json-table'
                ? 'unrunnable-json-table'
                : 'json-table-path-full-scan';
        } else {
            $strategy = 'path-constraint-pushdown';
            $rows = (int) $selected['indexedEstimatedRows'];
            $cost = (int) $selected['indexedEstimatedCost'];
            $effectiveCost = $cost + (int) $indexedCost['sortPenalty'];
            $costClass = in_array($selected['operator'], ['=', 'IS', 'IS NOT DISTINCT FROM'], true)
                ? 'json-table-path-point-lookup'
                : 'json-table-path-range-scan';
        }

        return [
            'pathConstraints' => $pathConstraints,
            'selectedPath' => $selected,
            'selectedPathSignature' => $selected === null ? null : self::jsonTableIndexedConstraintSignature119($selected),
            'pathScanStrategy' => $strategy,
            'pathEstimatedRows' => $rows,
            'pathEstimatedCost' => $cost,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => $costClass,
            'pathRowCount' => count($pathTape),
            'pathTape' => $pathTape,
            'firstPath' => $pathTape[0] ?? null,
            'lastPath' => $pathTape === [] ? null : $pathTape[array_key_last($pathTape)],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function jsonTablePathTape123(array $rows): array
    {
        $paths = [];
        foreach ($rows as $row) {
            $path = $row['path'] ?? null;
            if (is_string($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTablePathConstraintTransitions123(array $current, array $next): array
    {
        return [
            [
                'field' => 'selectedPathSignature',
                'current' => $current['selectedPathSignature'],
                'next' => $next['selectedPathSignature'],
                'changed' => $current['selectedPathSignature'] !== $next['selectedPathSignature'],
            ],
            [
                'field' => 'pathScanStrategy',
                'current' => $current['pathScanStrategy'],
                'next' => $next['pathScanStrategy'],
                'changed' => $current['pathScanStrategy'] !== $next['pathScanStrategy'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'pathRowCount',
                'current' => $current['pathRowCount'],
                'next' => $next['pathRowCount'],
                'changed' => $current['pathRowCount'] !== $next['pathRowCount'],
            ],
            [
                'field' => 'pathTape',
                'current' => $current['pathTape'],
                'next' => $next['pathTape'],
                'changed' => $current['pathTape'] !== $next['pathTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTablePathConstraintReplanReasons123(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'selectedPathSignature' => 'json-table-path-constraint-changed',
                'pathScanStrategy' => 'json-table-path-scan-strategy-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-path-cost-changed',
                'pathRowCount' => 'json-table-path-row-count-changed',
                'pathTape' => 'json-table-path-tape-changed',
                default => 'json-table-path-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $pathRowid
     * @param array<string,mixed> $partialOrder
     * @return array{compositeSignature:string|null,scanStrategy:string,pathOrderPrefix:list<string>,orderSuffix:list<string>,orderByConsumed:bool,blockSortRequired:bool,requiresOrderSort:bool,effectiveEstimatedCost:int,costClass:string,pathRowidTape:list<array{path:string,rowid:int}>,orderedRowids:list<int>,firstOrderedPathRowid:array{path:string,rowid:int}|null,lastOrderedPathRowid:array{path:string,rowid:int}|null}
     */
    private static function jsonTableHiddenPathOrderByProfile128(array $pathRowid, array $partialOrder): array
    {
        $pathRowidTape = $pathRowid['pathRowidTape'] ?? [];
        $orderedRowids = array_values(array_map(
            static fn (mixed $value): int => (int) $value,
            $partialOrder['rowOrder'] ?? [],
        ));
        $orderSuffix = $partialOrder['suffixColumns'] ?? [];
        $blockSortRequired = (bool) ($partialOrder['blockSortRequired'] ?? false);
        $requiresOrderSort = $orderSuffix !== [] || $blockSortRequired;
        $pathCost = (int) ($pathRowid['effectiveEstimatedCost'] ?? 1000000);
        $orderCost = (int) ($partialOrder['effectiveEstimatedCost'] ?? 1000000);
        $effectiveCost = max($pathCost, $orderCost);
        if ($pathCost < 1000000 && $orderCost < 1000000 && $requiresOrderSort) {
            $effectiveCost += (int) ($partialOrder['blockSortPenalty'] ?? $partialOrder['baseSortPenalty'] ?? 0);
        }

        return [
            'compositeSignature' => $pathRowid['compositeSignature'] ?? null,
            'scanStrategy' => (string) ($pathRowid['scanStrategy'] ?? 'full-json-table-scan'),
            'pathOrderPrefix' => self::hiddenPathOrderPrefix128($pathRowid, $partialOrder),
            'orderSuffix' => array_values(array_map('strval', $orderSuffix)),
            'orderByConsumed' => !$requiresOrderSort,
            'blockSortRequired' => $blockSortRequired,
            'requiresOrderSort' => $requiresOrderSort,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableHiddenPathOrderByCostClass128($pathRowid, $partialOrder, $requiresOrderSort, $effectiveCost),
            'pathRowidTape' => $pathRowidTape,
            'orderedRowids' => $orderedRowids,
            'firstOrderedPathRowid' => self::firstOrderedPathRowid128($pathRowidTape, $orderedRowids),
            'lastOrderedPathRowid' => self::lastOrderedPathRowid128($pathRowidTape, $orderedRowids),
        ];
    }

    /**
     * @param array<string,mixed> $pathRowid
     * @param array<string,mixed> $partialOrder
     * @return list<string>
     */
    private static function hiddenPathOrderPrefix128(array $pathRowid, array $partialOrder): array
    {
        $prefix = [];
        if (($pathRowid['pathSignature'] ?? null) !== null) {
            $prefix[] = 'path';
        }
        if (($pathRowid['rowidSignature'] ?? null) !== null) {
            $prefix[] = 'id';
        }

        foreach ($partialOrder['consumedPrefixColumns'] ?? [] as $column) {
            $column = (string) $column;
            if (!in_array($column, $prefix, true)) {
                $prefix[] = $column;
            }
        }

        return $prefix;
    }

    /**
     * @param array<string,mixed> $pathRowid
     * @param array<string,mixed> $partialOrder
     */
    private static function jsonTableHiddenPathOrderByCostClass128(
        array $pathRowid,
        array $partialOrder,
        bool $requiresOrderSort,
        int $effectiveCost,
    ): string {
        if (($pathRowid['scanStrategy'] ?? null) === 'unrunnable-json-table' || ($partialOrder['costClass'] ?? null) === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if (!$requiresOrderSort) {
            return 'json-table-hidden-path-order-consumed';
        }
        if (($pathRowid['compositeSignature'] ?? null) !== null) {
            return $effectiveCost <= 8
                ? 'json-table-hidden-path-rowid-block-sort'
                : 'json-table-hidden-path-rowid-order-sort';
        }
        if (($pathRowid['pathSignature'] ?? null) !== null) {
            return 'json-table-hidden-path-order-sort';
        }

        return 'json-table-hidden-order-sort';
    }

    /**
     * @param list<array{path:string,rowid:int}> $pathRowidTape
     * @param list<int> $orderedRowids
     * @return array{path:string,rowid:int}|null
     */
    private static function firstOrderedPathRowid128(array $pathRowidTape, array $orderedRowids): ?array
    {
        foreach ($orderedRowids as $rowid) {
            foreach ($pathRowidTape as $entry) {
                if (($entry['rowid'] ?? null) === $rowid) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * @param list<array{path:string,rowid:int}> $pathRowidTape
     * @param list<int> $orderedRowids
     * @return array{path:string,rowid:int}|null
     */
    private static function lastOrderedPathRowid128(array $pathRowidTape, array $orderedRowids): ?array
    {
        for ($index = count($orderedRowids) - 1; $index >= 0; $index--) {
            foreach ($pathRowidTape as $entry) {
                if (($entry['rowid'] ?? null) === $orderedRowids[$index]) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableHiddenPathOrderByTransitions128(array $current, array $next): array
    {
        return [
            [
                'field' => 'compositeSignature',
                'current' => $current['compositeSignature'],
                'next' => $next['compositeSignature'],
                'changed' => $current['compositeSignature'] !== $next['compositeSignature'],
            ],
            [
                'field' => 'pathOrderPrefix',
                'current' => $current['pathOrderPrefix'],
                'next' => $next['pathOrderPrefix'],
                'changed' => $current['pathOrderPrefix'] !== $next['pathOrderPrefix'],
            ],
            [
                'field' => 'orderSuffix',
                'current' => $current['orderSuffix'],
                'next' => $next['orderSuffix'],
                'changed' => $current['orderSuffix'] !== $next['orderSuffix'],
            ],
            [
                'field' => 'requiresOrderSort',
                'current' => $current['requiresOrderSort'],
                'next' => $next['requiresOrderSort'],
                'changed' => $current['requiresOrderSort'] !== $next['requiresOrderSort'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'orderedRowids',
                'current' => $current['orderedRowids'],
                'next' => $next['orderedRowids'],
                'changed' => $current['orderedRowids'] !== $next['orderedRowids'],
            ],
            [
                'field' => 'pathRowidTape',
                'current' => $current['pathRowidTape'],
                'next' => $next['pathRowidTape'],
                'changed' => $current['pathRowidTape'] !== $next['pathRowidTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableHiddenPathOrderByReplanReasons128(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'compositeSignature' => 'json-table-hidden-path-rowid-signature-changed',
                'pathOrderPrefix' => 'json-table-hidden-path-order-prefix-changed',
                'orderSuffix', 'requiresOrderSort' => 'json-table-hidden-path-order-sorter-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-hidden-path-order-cost-changed',
                'orderedRowids', 'pathRowidTape' => 'json-table-hidden-path-order-output-changed',
                default => 'json-table-hidden-path-order-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array{column:string,sourceColumn?:string,value?:mixed,operator?:string,usable?:bool}> $constraintSources
     * @return list<array{column:string,operator:string,value:mixed,usable?:bool,sourceColumn:string|null,literal:bool}>
     */
    private static function constraintsFromSource102(array $source, array $constraintSources): array
    {
        $constraints = [];
        foreach ($constraintSources as $constraint) {
            $column = self::normalizeConstraintColumn((string) ($constraint['column'] ?? ''));
            $operator = strtoupper((string) ($constraint['operator'] ?? '='));
            if ($operator === '') {
                throw new \InvalidArgumentException('SQLite JSON table hidden constraint source operator must be non-empty');
            }

            $literal = !array_key_exists('sourceColumn', $constraint);
            $sourceColumn = null;
            if ($literal) {
                if (!array_key_exists('value', $constraint)) {
                    throw new \InvalidArgumentException('SQLite JSON table hidden constraint source needs value or sourceColumn');
                }
                $value = $constraint['value'];
            } else {
                $sourceColumn = (string) $constraint['sourceColumn'];
                if ($sourceColumn === '') {
                    throw new \InvalidArgumentException('SQLite JSON table hidden constraint sourceColumn must be non-empty');
                }
                if (!array_key_exists($sourceColumn, $source)) {
                    throw new \InvalidArgumentException("SQLite JSON table hidden constraint source row is missing {$sourceColumn}");
                }
                $value = $source[$sourceColumn];
            }

            $constraints[] = [
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
                'usable' => (bool) ($constraint['usable'] ?? true),
                'sourceColumn' => $sourceColumn,
                'literal' => $literal,
            ];
        }

        return $constraints;
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool,sourceColumn:string|null,literal:bool}> $constraints
     * @param list<array{column:string,sourceColumn?:string,value?:mixed,operator?:string,usable?:bool}> $constraintSources
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array<string,mixed>
     */
    private static function sourceConstraintPlan102(
        string $function,
        array $source,
        array $constraints,
        array $constraintSources,
        array $orderBy,
    ): array {
        $rootIsSqlNull = false;
        foreach ($constraints as $constraint) {
            if ($constraint['column'] === 'root' && $constraint['operator'] === '=' && $constraint['value'] === null) {
                $rootIsSqlNull = true;
                break;
            }
        }

        if ($rootIsSqlNull) {
            $planConstraints = array_values(array_filter(
                $constraints,
                static fn (array $constraint): bool => !($constraint['column'] === 'root' && $constraint['operator'] === '=' && $constraint['value'] === null),
            ));
        } else {
            $planConstraints = $constraints;
        }

        $indexPlan = self::xBestIndexPlan($function, $planConstraints, $orderBy);
        $validatedPlan = self::validatedPlan($function, $planConstraints);
        if ($rootIsSqlNull || !$validatedPlan['runnable'] || $validatedPlan['jsonInputKind'] === 'sql-null') {
            $indexPlan['runnable'] = false;
            $indexPlan['estimatedCost'] = 1000000;
            $indexPlan['estimatedRows'] = 0;
            $rows = [];
        } else {
            $rows = $indexPlan['orderByConsumed']
                ? self::filteredRows($function, $planConstraints)
                : self::orderedRows($function, $planConstraints, $orderBy);
        }

        return [
            'source' => $source,
            'constraintSources' => self::constraintSourceMetadata102($constraints),
            'runnable' => $indexPlan['runnable'],
            'idxNum' => $indexPlan['idxNum'],
            'idxStr' => $indexPlan['idxStr'],
            'filterArguments' => $rootIsSqlNull ? [] : $indexPlan['filterArguments'],
            'constraintUsage' => $indexPlan['constraintUsage'],
            'filterCurrentNext' => $indexPlan['filterCurrentNext'],
            'currentNext' => $indexPlan['currentNext'],
            'orderByConsumed' => $indexPlan['orderByConsumed'],
            'estimatedCost' => $indexPlan['estimatedCost'],
            'estimatedRows' => $indexPlan['estimatedRows'],
            'jsonInputKind' => $validatedPlan['jsonInputKind'],
            'jsonValid' => $rootIsSqlNull ? null : $validatedPlan['jsonValid'],
            'jsonError' => $rootIsSqlNull ? 'SQL NULL root path' : $validatedPlan['jsonError'],
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool,sourceColumn:string|null,literal:bool}> $constraints
     * @return list<array{index:int,column:string,operator:string,value:mixed,sourceColumn:string|null,literal:bool,hidden:bool,usable:bool}>
     */
    private static function constraintSourceMetadata102(array $constraints): array
    {
        $metadata = [];
        foreach ($constraints as $index => $constraint) {
            $column = strtolower((string) $constraint['column']);
            $metadata[] = [
                'index' => $index,
                'column' => $column,
                'operator' => strtoupper((string) $constraint['operator']),
                'value' => $constraint['value'] ?? null,
                'sourceColumn' => $constraint['sourceColumn'],
                'literal' => $constraint['literal'],
                'hidden' => in_array($column, ['json', 'root', 'id'], true),
                'usable' => (bool) ($constraint['usable'] ?? true),
            ];
        }

        return $metadata;
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return list<array{index:int,column:string,sourceColumn:string|null,current:mixed,next:mixed,changed:bool,hidden:bool}>
     */
    private static function constraintValueTransitions102(array $current, array $next): array
    {
        $count = max(count($current), count($next));
        $transitions = [];
        for ($index = 0; $index < $count; $index++) {
            $currentConstraint = $current[$index] ?? [];
            $nextConstraint = $next[$index] ?? [];
            $currentValue = $currentConstraint['value'] ?? null;
            $nextValue = $nextConstraint['value'] ?? null;
            $transitions[] = [
                'index' => $index,
                'column' => (string) ($currentConstraint['column'] ?? $nextConstraint['column'] ?? ''),
                'sourceColumn' => $currentConstraint['sourceColumn'] ?? $nextConstraint['sourceColumn'] ?? null,
                'current' => $currentValue,
                'next' => $nextValue,
                'changed' => $currentValue !== $nextValue,
                'hidden' => (bool) (($currentConstraint['hidden'] ?? false) || ($nextConstraint['hidden'] ?? false)),
            ];
        }

        return $transitions;
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @param list<array<string,mixed>> $constraintValueTransitions
     * @param list<array{index:int,current:mixed,next:mixed,changed:bool}> $argumentTransitions
     * @param list<array{index:int,current:array<string,mixed>|null,next:array<string,mixed>|null,changed:bool}> $usageTransitions
     * @return list<string>
     */
    private static function constraintSourceReplanReasons102(
        array $current,
        array $next,
        array $constraintValueTransitions,
        array $argumentTransitions,
        array $usageTransitions,
    ): array {
        $reasons = [];
        foreach ($constraintValueTransitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }
            $reasons[] = $transition['hidden'] ? 'hidden-constraint-source-value-changed' : 'visible-constraint-source-value-changed';
        }
        if ($current['runnable'] !== $next['runnable']) {
            $reasons[] = $next['runnable'] ? 'next-hidden-constraint-source-becomes-runnable' : 'next-hidden-constraint-source-becomes-unrunnable';
        }
        if ($current['jsonInputKind'] !== $next['jsonInputKind']) {
            $reasons[] = 'hidden-constraint-source-json-kind-changed';
        }
        if ($current['jsonValid'] !== $next['jsonValid']) {
            $reasons[] = 'hidden-constraint-source-json-validity-changed';
        }
        if ($current['idxNum'] !== $next['idxNum'] || $current['idxStr'] !== $next['idxStr']) {
            $reasons[] = 'hidden-constraint-source-tape-changed';
        }
        foreach ($argumentTransitions as $transition) {
            if ($transition['changed']) {
                $reasons[] = 'hidden-constraint-source-argument-tape-changed';
                break;
            }
        }
        foreach ($usageTransitions as $transition) {
            if ($transition['changed']) {
                $reasons[] = 'hidden-constraint-source-usage-tape-changed';
                break;
            }
        }
        if (count($current['rows']) !== count($next['rows'])) {
            $reasons[] = 'hidden-constraint-source-row-count-changed';
        }

        return array_values(array_unique($reasons));
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
            'rows' => $rows,
            'rowCount' => count($rows),
            'nullExtended' => $nullExtended,
            'idxStr' => $sourcePlan['idxStr'],
            'filterArguments' => $sourcePlan['filterArguments'],
            'constraintUsage' => $sourcePlan['constraintUsage'],
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
     * @param array<string,mixed> $hostPlan
     * @return array{hostKey:mixed,hostIndex:int,rowids:list<int>,firstRowid:int|null,lastRowid:int|null,rowCount:int,rowidResidualColumns:list<string>,nullExtended:bool,sourceKind:string,root:mixed,rows:list<array<string,mixed>>}
     */
    private static function lateralHiddenRowidHostSummary105(array $hostPlan): array
    {
        $rows = $hostPlan['rows'] ?? [];
        $rowids = self::rowidsFromRows94($rows);

        return [
            'hostKey' => $hostPlan['hostKey'] ?? null,
            'hostIndex' => (int) ($hostPlan['hostIndex'] ?? 0),
            'rowids' => $rowids,
            'firstRowid' => $rowids[0] ?? null,
            'lastRowid' => $rowids === [] ? null : $rowids[count($rowids) - 1],
            'rowCount' => count($rowids),
            'rowidResidualColumns' => array_column(self::hiddenRowidResidualConstraints94($hostPlan['constraintUsage'] ?? []), 'column'),
            'nullExtended' => (bool) ($hostPlan['nullExtended'] ?? false),
            'sourceKind' => (string) ($hostPlan['jsonInputKind'] ?? 'missing'),
            'root' => $hostPlan['rootValue'] ?? null,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string,mixed> $transition
     * @param array<string,mixed>|null $currentSummary
     * @param array<string,mixed>|null $nextSummary
     * @return array<string,mixed>
     */
    private static function lateralHiddenRowidTransition105(
        array $transition,
        ?array $currentSummary,
        ?array $nextSummary,
    ): array {
        $currentRowids = $currentSummary['rowids'] ?? [];
        $nextRowids = $nextSummary['rowids'] ?? [];
        $currentResiduals = $currentSummary['rowidResidualColumns'] ?? [];
        $nextResiduals = $nextSummary['rowidResidualColumns'] ?? [];
        $rowTransitions = self::sourceRowTransitions94($currentSummary['rows'] ?? [], $nextSummary['rows'] ?? []);

        return [
            'ordinal' => $transition['ordinal'],
            'hostKey' => $transition['hostKey'],
            'hostKeyToken' => $transition['hostKeyToken'],
            'currentOrdinal' => $transition['currentOrdinal'],
            'nextOrdinal' => $transition['nextOrdinal'],
            'ordinalChanged' => $transition['ordinalChanged'],
            'currentSummary' => $currentSummary,
            'nextSummary' => $nextSummary,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'rowidChanged' => $currentRowids !== $nextRowids,
            'rowCountChanged' => ($currentSummary['rowCount'] ?? 0) !== ($nextSummary['rowCount'] ?? 0),
            'currentRowidResidualColumns' => $currentResiduals,
            'nextRowidResidualColumns' => $nextResiduals,
            'rowidResidualChanged' => $currentResiduals !== $nextResiduals,
            'rowTransitions' => $rowTransitions,
            'changed' => $transition['changed'] || $currentRowids !== $nextRowids || $currentResiduals !== $nextResiduals,
            'reason' => self::lateralHiddenRowidTransitionReason105($transition, $currentRowids, $nextRowids, $currentResiduals, $nextResiduals),
        ];
    }

    /**
     * @param array<string,mixed> $transition
     * @param list<int> $currentRowids
     * @param list<int> $nextRowids
     * @param list<string> $currentResiduals
     * @param list<string> $nextResiduals
     */
    private static function lateralHiddenRowidTransitionReason105(
        array $transition,
        array $currentRowids,
        array $nextRowids,
        array $currentResiduals,
        array $nextResiduals,
    ): string {
        if ($transition['current'] === null) {
            return 'next-lateral-hidden-rowid-host-row-added';
        }
        if ($transition['next'] === null) {
            return 'current-lateral-hidden-rowid-host-row-removed';
        }
        if ($currentResiduals !== $nextResiduals) {
            return 'lateral-hidden-rowid-residual-usage-changed';
        }
        if ($currentRowids !== $nextRowids) {
            return 'lateral-hidden-rowid-tape-changed';
        }
        if ($transition['reason'] !== 'stable-lateral-hidden-json-plan') {
            return $transition['reason'];
        }

        return 'stable-lateral-hidden-rowid-source';
    }

    /**
     * @param list<array<string,mixed>> $hostRows
     * @param list<array{column:string,sourceColumn?:string,value?:mixed,operator?:string,usable?:bool}> $constraintSources
     * @return array{keys:list<string>,values:list<mixed>,rows:array<string,array{ordinal:int,row:array<string,mixed>,value:mixed}>}
     */
    private static function keyedHostRows118(
        array $hostRows,
        string $hostKeyColumn,
        array $constraintSources,
        string $side,
    ): array {
        $keys = [];
        $values = [];
        $rows = [];
        foreach ($hostRows as $ordinal => $row) {
            if (!array_key_exists($hostKeyColumn, $row)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral hidden current-source {$side} host row is missing {$hostKeyColumn}");
            }
            self::constraintsFromSource102($row, $constraintSources);

            $value = $row[$hostKeyColumn];
            $token = self::hostKeyToken103($value);
            if (isset($rows[$token])) {
                throw new \InvalidArgumentException("SQLite JSON table lateral hidden current-source {$side} host key column {$hostKeyColumn} must be unique");
            }

            $keys[] = $token;
            $values[] = $value;
            $rows[$token] = [
                'ordinal' => $ordinal,
                'row' => $row,
                'value' => $value,
            ];
        }

        return ['keys' => $keys, 'values' => $values, 'rows' => $rows];
    }

    /**
     * @param array<string,mixed> $hostRow
     * @param array<string,mixed> $pair
     * @return array<string,mixed>
     */
    private static function lateralConstraintHiddenHostPlan118(
        int $hostIndex,
        array $hostRow,
        array $pair,
        string $side,
        string $joinType,
    ): array {
        $rows = $side === 'current' ? $pair['currentRows'] : $pair['nextRows'];
        $sourcePlan = $side === 'current' ? $pair['current'] : $pair['next'];
        $nullExtended = $joinType === 'left' && $rows === [];

        return [
            'hostIndex' => $hostIndex,
            'hostRow' => $hostRow,
            'runnable' => $sourcePlan['runnable'],
            'rows' => $rows,
            'rowCount' => count($rows),
            'nullExtended' => $nullExtended,
            'idxStr' => $sourcePlan['idxStr'],
            'filterArguments' => $sourcePlan['filterArguments'],
            'constraintUsage' => $sourcePlan['constraintUsage'],
            'constraintSources' => $sourcePlan['constraintSources'],
            'filterCurrentNext' => $sourcePlan['filterCurrentNext'],
            'orderByConsumed' => $sourcePlan['orderByConsumed'],
            'estimatedRows' => $sourcePlan['estimatedRows'],
            'jsonInputKind' => $sourcePlan['jsonInputKind'],
            'jsonValid' => $sourcePlan['jsonValid'],
            'jsonError' => $sourcePlan['jsonError'],
        ];
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     * @param array<string,mixed>|null $pair
     */
    private static function lateralConstraintHiddenTransitionReason118(?array $current, ?array $next, ?array $pair): string
    {
        if ($current === null) {
            return 'next-lateral-hidden-current-source-host-row-added';
        }
        if ($next === null) {
            return 'current-lateral-hidden-current-source-host-row-removed';
        }
        if (($pair['replanReasons'] ?? []) !== []) {
            return 'lateral-hidden-current-source-plan-changed';
        }
        if (($current['rowCount'] ?? 0) !== ($next['rowCount'] ?? 0)) {
            return 'lateral-hidden-current-source-row-count-changed';
        }
        if (($current['nullExtended'] ?? false) !== ($next['nullExtended'] ?? false)) {
            return 'lateral-hidden-current-source-null-extension-changed';
        }

        return 'stable-lateral-hidden-current-source';
    }

    /**
     * @param list<array<string,mixed>> $hostRows
     * @return array{keys:list<string>,values:list<mixed>,rows:array<string,array{ordinal:int,row:array<string,mixed>,value:mixed}>}
     */
    private static function keyedHostRows103(
        array $hostRows,
        string $hostKeyColumn,
        string $jsonColumn,
        ?string $rootColumn,
        string $side,
    ): array {
        $keys = [];
        $values = [];
        $rows = [];
        foreach ($hostRows as $ordinal => $hostRow) {
            if (!array_key_exists($hostKeyColumn, $hostRow)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral hidden {$side} host row is missing {$hostKeyColumn}");
            }
            if (!array_key_exists($jsonColumn, $hostRow)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral hidden {$side} host row is missing {$jsonColumn}");
            }
            if ($rootColumn !== null && !array_key_exists($rootColumn, $hostRow)) {
                throw new \InvalidArgumentException("SQLite JSON table lateral hidden {$side} host row is missing {$rootColumn}");
            }

            $value = $hostRow[$hostKeyColumn];
            $key = self::hostKeyToken103($value);
            if (isset($rows[$key])) {
                throw new \InvalidArgumentException("SQLite JSON table lateral hidden {$side} host key column {$hostKeyColumn} must be unique");
            }

            $keys[] = $key;
            $values[] = $value;
            $rows[$key] = [
                'ordinal' => $ordinal,
                'row' => $hostRow,
                'value' => $value,
            ];
        }

        return [
            'keys' => $keys,
            'values' => $values,
            'rows' => $rows,
        ];
    }

    private static function hostKeyToken103(mixed $value): string
    {
        if ($value === null) {
            return 'null:';
        }
        if (is_bool($value)) {
            return 'bool:' . ($value ? '1' : '0');
        }
        if (is_int($value)) {
            return 'int:' . $value;
        }
        if (is_float($value)) {
            return 'float:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            return 'string:' . $value;
        }

        throw new \InvalidArgumentException('SQLite JSON table lateral hidden host key must be scalar or NULL');
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
     * @param list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}> $usage
     * @return list<array{constraintIndex:int,column:string,operator:string,usable:bool}>
     */
    private static function hiddenRowidResidualConstraints94(array $usage): array
    {
        $hidden = [];
        foreach ($usage as $entry) {
            if ($entry['kind'] !== 'residual' || !self::isRowIdAlias($entry['column'])) {
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
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return list<array{constraintIndex:int,column:string,operator:string,usable:bool}>
     */
    private static function sourceRowidResidualConstraints94(array $constraints): array
    {
        $rowid = [];
        foreach ($constraints as $index => $constraint) {
            $column = strtolower((string) $constraint['column']);
            if (!self::isRowIdAlias($column)) {
                continue;
            }

            $rowid[] = [
                'constraintIndex' => $index,
                'column' => $column,
                'operator' => strtoupper((string) $constraint['operator']),
                'usable' => (bool) ($constraint['usable'] ?? true),
            ];
        }

        return $rowid;
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{constraintIndex:int,column:string,operator:string,argvIndex:int|null,omit:bool,usable:bool,kind:string}> $usage
     * @return list<array{constraintIndex:int,originalColumn:string,normalizedColumn:string,operator:string,usable:bool,source:string}>
     */
    private static function rowidAliasConstraintProvenance99(array $constraints, array $usage): array
    {
        $aliases = [];
        foreach ($constraints as $index => $constraint) {
            $column = strtolower((string) ($constraint['column'] ?? ''));
            if (!self::isRowIdAlias($column)) {
                continue;
            }

            $aliases[] = [
                'constraintIndex' => $index,
                'originalColumn' => $column,
                'normalizedColumn' => 'id',
                'operator' => strtoupper((string) ($constraint['operator'] ?? '')),
                'usable' => (bool) ($constraint['usable'] ?? true),
                'source' => 'source-constraint',
            ];
        }

        foreach ($usage as $entry) {
            if (($entry['kind'] ?? null) !== 'residual' || !self::isRowIdAlias((string) ($entry['column'] ?? ''))) {
                continue;
            }

            $aliases[] = [
                'constraintIndex' => (int) $entry['constraintIndex'],
                'originalColumn' => (string) $entry['column'],
                'normalizedColumn' => 'id',
                'operator' => (string) $entry['operator'],
                'usable' => (bool) $entry['usable'],
                'source' => 'xbestindex-residual',
            ];
        }

        return $aliases;
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
     * @param list<array<string,mixed>> $used
     */
    private static function jsonTableOrderByConsumed(array $orderBy, array $used): bool
    {
        if ($orderBy === []) {
            return false;
        }

        foreach ($orderBy as $term) {
            $column = self::normalizeConstraintColumn((string) $term['column']);
            $direction = strtoupper($term['direction'] ?? 'ASC');
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite JSON table ORDER BY direction must be ASC or DESC');
            }
            if ($column === 'id' && $direction === 'ASC') {
                continue;
            }
            if (self::orderByTermIsConstantFromConstraint($column, $used)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $used
     */
    private static function orderByTermIsConstantFromConstraint(string $column, array $used): bool
    {
        foreach ($used as $constraint) {
            if (self::usedConstraintFixesOrderByColumn($constraint, $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $constraint
     */
    private static function usedConstraintFixesOrderByColumn(array $constraint, string $column): bool
    {
        if (($constraint['constraint'] ?? null) !== 'VISIBLE') {
            return false;
        }
        if (self::normalizeConstraintColumn((string) $constraint['column']) !== $column) {
            return false;
        }

        $operator = strtoupper((string) $constraint['operator']);
        if (in_array($operator, ['=', 'IS', 'IS NULL', 'IS NOT DISTINCT FROM'], true)) {
            return true;
        }
        if ($operator === 'IN' && is_array($constraint['value'] ?? null) && count($constraint['value']) === 1) {
            return true;
        }
        if ($operator === 'BETWEEN' && is_array($constraint['value'] ?? null) && count($constraint['value']) === 2) {
            $bounds = array_values($constraint['value']);

            return self::valuesAreNotDistinct($bounds[0], $bounds[1]);
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $used
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array{column:string,direction:string,consumed:bool,reason:string,constraintOperator:string|null,constraintValue:mixed}>
     */
    private static function orderByConstraintCoverage120(array $used, array $orderBy): array
    {
        $coverage = [];
        foreach (self::normalizeOrderByTerms113($orderBy) as $term) {
            $column = $term['column'];
            $reason = 'not-consumed';
            $constraintOperator = null;
            $constraintValue = null;
            if ($column === 'id' && $term['direction'] === 'ASC') {
                $reason = 'natural-json-rowid-order';
            } else {
                foreach ($used as $constraint) {
                    if (!self::usedConstraintFixesOrderByColumn($constraint, $column)) {
                        continue;
                    }

                    $reason = 'constant-visible-constraint';
                    $constraintOperator = strtoupper((string) $constraint['operator']);
                    $constraintValue = $constraint['value'] ?? null;
                    break;
                }
            }

            $coverage[] = [
                'column' => $column,
                'direction' => $term['direction'],
                'consumed' => $reason !== 'not-consumed',
                'reason' => $reason,
                'constraintOperator' => $constraintOperator,
                'constraintValue' => $constraintValue,
            ];
        }

        return $coverage;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $costOrder
     * @param list<array{column:string,direction:string,consumed:bool,reason:string,constraintOperator:string|null,constraintValue:mixed}> $coverage
     * @return array{orderBy:list<array{column:string,direction:string}>,consumedPrefix:list<array<string,mixed>>,suffixOrderBy:list<array{column:string,direction:string}>,consumedPrefixColumns:list<string>,suffixColumns:list<string>,prefixConsumedCount:int,suffixSortWidth:int,blockSortRequired:bool,baseSortPenalty:int,blockSortPenalty:int,sortSavings:int,baseEffectiveEstimatedCost:int,effectiveEstimatedCost:int,costClass:string,rowCount:int,rowOrder:list<int|null>,firstSuffixKey:mixed,lastSuffixKey:mixed}
     */
    private static function jsonTablePartialOrderCostProfile124(array $plan, array $costOrder, array $coverage): array
    {
        $orderBy = $costOrder['orderBy'];
        $consumedPrefix = [];
        foreach ($coverage as $term) {
            if (!($term['consumed'] ?? false)) {
                break;
            }

            $consumedPrefix[] = $term;
        }

        $prefixCount = count($consumedPrefix);
        $suffixOrderBy = array_slice($orderBy, $prefixCount);
        $rowCount = count($plan['rows']);
        $blockSortRequired = $suffixOrderBy !== [] && !$plan['orderByConsumed'] && $rowCount > 1;
        $blockSortPenalty = $blockSortRequired ? self::jsonTableSortPenalty113($rowCount, $suffixOrderBy) : 0;
        $baseCost = (int) $costOrder['baseEstimatedCost'];
        $effectiveCost = $baseCost >= 1000000 ? $baseCost : $baseCost + $blockSortPenalty;
        $baseSortPenalty = (int) $costOrder['sortPenalty'];

        return [
            'orderBy' => $orderBy,
            'consumedPrefix' => $consumedPrefix,
            'suffixOrderBy' => $suffixOrderBy,
            'consumedPrefixColumns' => array_column($consumedPrefix, 'column'),
            'suffixColumns' => array_column($suffixOrderBy, 'column'),
            'prefixConsumedCount' => $prefixCount,
            'suffixSortWidth' => count($suffixOrderBy),
            'blockSortRequired' => $blockSortRequired,
            'baseSortPenalty' => $baseSortPenalty,
            'blockSortPenalty' => $blockSortPenalty,
            'sortSavings' => max(0, $baseSortPenalty - $blockSortPenalty),
            'baseEffectiveEstimatedCost' => (int) $costOrder['effectiveEstimatedCost'],
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTablePartialOrderCostClass124($plan, $costOrder, $blockSortRequired, $prefixCount, $effectiveCost),
            'rowCount' => $rowCount,
            'rowOrder' => $costOrder['rowOrder'],
            'firstSuffixKey' => self::jsonTableOrderKey113($plan['rows'][0] ?? null, $suffixOrderBy),
            'lastSuffixKey' => self::jsonTableOrderKey113($plan['rows'][$rowCount - 1] ?? null, $suffixOrderBy),
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $costOrder
     */
    private static function jsonTablePartialOrderCostClass124(
        array $plan,
        array $costOrder,
        bool $blockSortRequired,
        int $prefixCount,
        int $effectiveCost,
    ): string {
        if (!$plan['runnable']) {
            return 'unrunnable-json-table';
        }
        if ($plan['orderByConsumed']) {
            return 'json-table-complete-order-consumed';
        }
        if ($blockSortRequired && $prefixCount > 0) {
            return 'json-table-partial-order-block-sort';
        }
        if ($blockSortRequired) {
            return 'json-table-full-order-sort';
        }
        if ($effectiveCost <= 10) {
            return 'json-table-partial-order-narrow-scan';
        }

        return (string) $costOrder['costClass'];
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTablePartialOrderCostTransitions124(array $current, array $next): array
    {
        return [
            [
                'field' => 'consumedPrefixColumns',
                'current' => $current['consumedPrefixColumns'],
                'next' => $next['consumedPrefixColumns'],
                'changed' => $current['consumedPrefixColumns'] !== $next['consumedPrefixColumns'],
            ],
            [
                'field' => 'suffixColumns',
                'current' => $current['suffixColumns'],
                'next' => $next['suffixColumns'],
                'changed' => $current['suffixColumns'] !== $next['suffixColumns'],
            ],
            [
                'field' => 'blockSortRequired',
                'current' => $current['blockSortRequired'],
                'next' => $next['blockSortRequired'],
                'changed' => $current['blockSortRequired'] !== $next['blockSortRequired'],
            ],
            [
                'field' => 'blockSortPenalty',
                'current' => $current['blockSortPenalty'],
                'next' => $next['blockSortPenalty'],
                'changed' => $current['blockSortPenalty'] !== $next['blockSortPenalty'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'rowOrder',
                'current' => $current['rowOrder'],
                'next' => $next['rowOrder'],
                'changed' => $current['rowOrder'] !== $next['rowOrder'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTablePartialOrderCostReplanReasons124(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'consumedPrefixColumns', 'suffixColumns' => 'json-table-partial-order-prefix-changed',
                'blockSortRequired' => 'json-table-partial-order-sorter-changed',
                'blockSortPenalty', 'effectiveEstimatedCost', 'costClass' => 'json-table-partial-order-cost-changed',
                'rowOrder' => 'json-table-partial-order-output-changed',
                default => 'json-table-partial-order-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $indexedCost
     * @param array<string,mixed> $pathCost
     * @return array{pathSignature:string|null,rowidSignature:string|null,compositeSignature:string|null,scanStrategy:string,pathEstimatedCost:int,rowidEstimatedCost:int,intersectedEstimatedRows:int,intersectedEstimatedCost:int,effectiveEstimatedCost:int,costClass:string,rowCount:int,rowids:list<int>,pathRowidTape:list<array{path:string|null,rowid:int|null}>,firstPathRowid:array{path:string|null,rowid:int|null}|null,lastPathRowid:array{path:string|null,rowid:int|null}|null}
     */
    private static function jsonTablePathHiddenRowidCostProfile126(array $plan, array $indexedCost, array $pathCost): array
    {
        $rowid = null;
        foreach ($indexedCost['indexedConstraints'] as $constraint) {
            if (($constraint['column'] ?? null) !== 'id') {
                continue;
            }

            $rowid = $constraint;
            break;
        }

        $path = $pathCost['selectedPath'];
        $pathSignature = $pathCost['selectedPathSignature'];
        $rowidSignature = $rowid === null ? null : self::jsonTableIndexedConstraintSignature119($rowid);
        $rowids = self::rowidsFromRows94($plan['rows']);
        $pathRowidTape = self::jsonTablePathRowidTape126($plan['rows']);

        if (!$plan['runnable']) {
            $scanStrategy = 'unrunnable-json-table';
            $intersectedRows = 0;
            $intersectedCost = 1000000;
            $effectiveCost = 1000000;
        } elseif ($path !== null && $rowid !== null) {
            $scanStrategy = 'path-rowid-intersection';
            $intersectedRows = min((int) $path['indexedEstimatedRows'], (int) $rowid['indexedEstimatedRows']);
            $intersectedRows = max(1, min($intersectedRows, max(1, count($pathRowidTape))));
            $intersectedCost = max(1, intdiv((int) $path['indexedEstimatedCost'] + (int) $rowid['indexedEstimatedCost'] + 1, 2));
            $effectiveCost = $intersectedCost + (int) $indexedCost['sortPenalty'];
        } elseif ($rowid !== null) {
            $scanStrategy = 'rowid-only-lookup';
            $intersectedRows = (int) $rowid['indexedEstimatedRows'];
            $intersectedCost = (int) $rowid['indexedEstimatedCost'];
            $effectiveCost = $intersectedCost + (int) $indexedCost['sortPenalty'];
        } elseif ($path !== null) {
            $scanStrategy = 'path-only-lookup';
            $intersectedRows = (int) $path['indexedEstimatedRows'];
            $intersectedCost = (int) $path['indexedEstimatedCost'];
            $effectiveCost = $intersectedCost + (int) $indexedCost['sortPenalty'];
        } else {
            $scanStrategy = 'full-json-table-scan';
            $intersectedRows = (int) $plan['estimatedRows'];
            $intersectedCost = (int) $plan['estimatedCost'];
            $effectiveCost = (int) $indexedCost['effectiveEstimatedCost'];
        }

        return [
            'pathSignature' => $pathSignature,
            'rowidSignature' => $rowidSignature,
            'compositeSignature' => $pathSignature !== null && $rowidSignature !== null ? $pathSignature . '&&' . $rowidSignature : null,
            'scanStrategy' => $scanStrategy,
            'pathEstimatedCost' => $path === null ? (int) $pathCost['pathEstimatedCost'] : (int) $path['indexedEstimatedCost'],
            'rowidEstimatedCost' => $rowid === null ? (int) $indexedCost['indexedEstimatedCost'] : (int) $rowid['indexedEstimatedCost'],
            'intersectedEstimatedRows' => $intersectedRows,
            'intersectedEstimatedCost' => $intersectedCost,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTablePathHiddenRowidCostClass126($scanStrategy, $effectiveCost),
            'rowCount' => count($pathRowidTape),
            'rowids' => $rowids,
            'pathRowidTape' => $pathRowidTape,
            'firstPathRowid' => $pathRowidTape[0] ?? null,
            'lastPathRowid' => $pathRowidTape === [] ? null : $pathRowidTape[array_key_last($pathRowidTape)],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{path:string|null,rowid:int|null}>
     */
    private static function jsonTablePathRowidTape126(array $rows): array
    {
        $tape = [];
        foreach ($rows as $row) {
            $tape[] = [
                'path' => isset($row['path']) && is_string($row['path']) ? $row['path'] : null,
                'rowid' => isset($row['id']) ? (int) $row['id'] : null,
            ];
        }

        return $tape;
    }

    private static function jsonTablePathHiddenRowidCostClass126(string $scanStrategy, int $effectiveCost): string
    {
        if ($scanStrategy === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($scanStrategy === 'path-rowid-intersection') {
            return $effectiveCost <= 2 ? 'json-table-path-rowid-point-intersection' : 'json-table-path-rowid-intersection';
        }
        if ($scanStrategy === 'rowid-only-lookup') {
            return 'json-table-rowid-point-lookup';
        }
        if ($scanStrategy === 'path-only-lookup') {
            return 'json-table-path-only-lookup';
        }

        return $effectiveCost <= 10 ? 'json-table-path-rowid-narrow-full-scan' : 'json-table-path-rowid-full-scan';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTablePathHiddenRowidCostTransitions126(array $current, array $next): array
    {
        return [
            [
                'field' => 'compositeSignature',
                'current' => $current['compositeSignature'],
                'next' => $next['compositeSignature'],
                'changed' => $current['compositeSignature'] !== $next['compositeSignature'],
            ],
            [
                'field' => 'scanStrategy',
                'current' => $current['scanStrategy'],
                'next' => $next['scanStrategy'],
                'changed' => $current['scanStrategy'] !== $next['scanStrategy'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'rowids',
                'current' => $current['rowids'],
                'next' => $next['rowids'],
                'changed' => $current['rowids'] !== $next['rowids'],
            ],
            [
                'field' => 'pathRowidTape',
                'current' => $current['pathRowidTape'],
                'next' => $next['pathRowidTape'],
                'changed' => $current['pathRowidTape'] !== $next['pathRowidTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTablePathHiddenRowidCostReplanReasons126(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'compositeSignature' => 'json-table-path-rowid-constraint-changed',
                'scanStrategy' => 'json-table-path-rowid-scan-strategy-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-path-rowid-cost-changed',
                'rowids' => 'json-table-path-rowid-rowset-changed',
                'pathRowidTape' => 'json-table-path-rowid-tape-changed',
                default => 'json-table-path-rowid-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $nestedRoot
     * @param array<string,mixed> $partialOrder
     * @param list<array{column:string,direction:string,consumed:bool,reason:string,constraintOperator:string|null,constraintValue:mixed}> $coverage
     * @return array{baseRoot:string,nestedPath:string,root:string,mode:string,consumedPrefixColumns:list<string>,suffixColumns:list<string>,prefixConsumedCount:int,blockSortRequired:bool,effectiveEstimatedCost:int,costClass:string,rowOrder:list<int|null>,coverage:list<array<string,mixed>>,rootOrderKey:list<mixed>,firstSuffixKey:mixed,lastSuffixKey:mixed}
     */
    private static function nestedConstraintOrderProfile127(array $nestedRoot, array $partialOrder, array $coverage): array
    {
        return [
            'baseRoot' => (string) $nestedRoot['baseRoot'],
            'nestedPath' => (string) $nestedRoot['nestedPath'],
            'root' => (string) $nestedRoot['root'],
            'mode' => (string) $nestedRoot['mode'],
            'consumedPrefixColumns' => $partialOrder['consumedPrefixColumns'],
            'suffixColumns' => $partialOrder['suffixColumns'],
            'prefixConsumedCount' => (int) $partialOrder['prefixConsumedCount'],
            'blockSortRequired' => (bool) $partialOrder['blockSortRequired'],
            'effectiveEstimatedCost' => (int) $partialOrder['effectiveEstimatedCost'],
            'costClass' => (string) $partialOrder['costClass'],
            'rowOrder' => $partialOrder['rowOrder'],
            'coverage' => $coverage,
            'rootOrderKey' => [$nestedRoot['root'], $partialOrder['rowOrder'][0] ?? null],
            'firstSuffixKey' => $partialOrder['firstSuffixKey'],
            'lastSuffixKey' => $partialOrder['lastSuffixKey'],
        ];
    }

    /**
     * @param array{baseRoot:string,nestedPath:string,root:string,mode:string} $nestedRoot
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $hiddenCost
     * @return array{baseRoot:string,nestedPath:string,root:string,mode:string,hiddenArguments:list<mixed>,hiddenArgumentCount:int,hiddenRootDepth:int,scanStrategy:string,compositeSignature:string|null,effectiveEstimatedCost:int,hiddenEstimatedCost:int,rowCount:int,rowids:list<int>,rootRowidTape:list<array{root:string,rowid:int|null,fullkey:string|null}>,firstRootRowid:array{root:string,rowid:int|null,fullkey:string|null}|null,lastRootRowid:array{root:string,rowid:int|null,fullkey:string|null}|null,costClass:string}
     */
    private static function nestedHiddenCostProfile129(array $nestedRoot, array $plan, array $hiddenCost): array
    {
        $hiddenArguments = array_slice($plan['filterArguments'] ?? [], 0, 2);
        $rowCount = count($plan['rows'] ?? []);
        $rootDepth = self::jsonPathDepth129((string) $nestedRoot['root']);
        $effectiveCost = (int) $hiddenCost['effectiveEstimatedCost'];
        $hiddenEstimatedCost = $effectiveCost >= 1000000
            ? 1000000
            : $effectiveCost + $rootDepth + self::nestedPathModePenalty129((string) $nestedRoot['mode']);
        $rootRowidTape = self::nestedRootRowidTape129((string) $nestedRoot['root'], $plan['rows'] ?? []);

        return [
            'baseRoot' => (string) $nestedRoot['baseRoot'],
            'nestedPath' => (string) $nestedRoot['nestedPath'],
            'root' => (string) $nestedRoot['root'],
            'mode' => (string) $nestedRoot['mode'],
            'hiddenArguments' => $hiddenArguments,
            'hiddenArgumentCount' => count($hiddenArguments),
            'hiddenRootDepth' => $rootDepth,
            'scanStrategy' => (string) $hiddenCost['scanStrategy'],
            'compositeSignature' => $hiddenCost['compositeSignature'],
            'effectiveEstimatedCost' => $effectiveCost,
            'hiddenEstimatedCost' => $hiddenEstimatedCost,
            'rowCount' => $rowCount,
            'rowids' => $hiddenCost['rowids'],
            'rootRowidTape' => $rootRowidTape,
            'firstRootRowid' => $rootRowidTape[0] ?? null,
            'lastRootRowid' => $rootRowidTape === [] ? null : $rootRowidTape[array_key_last($rootRowidTape)],
            'costClass' => self::nestedHiddenCostClass129((bool) $plan['runnable'], (string) $hiddenCost['scanStrategy'], $hiddenEstimatedCost, $rowCount),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{root:string,rowid:int|null,fullkey:string|null}>
     */
    private static function nestedRootRowidTape129(string $root, array $rows): array
    {
        $tape = [];
        foreach ($rows as $row) {
            $tape[] = [
                'root' => $root,
                'rowid' => isset($row['id']) ? (int) $row['id'] : null,
                'fullkey' => isset($row['fullkey']) && is_string($row['fullkey']) ? $row['fullkey'] : null,
            ];
        }

        return $tape;
    }

    private static function jsonPathDepth129(string $path): int
    {
        if ($path === '$') {
            return 0;
        }

        preg_match_all('/(?:\\.[A-Za-z_][A-Za-z0-9_]*|\\[[^\\]]+\\])/', $path, $matches);

        return count($matches[0]);
    }

    private static function nestedPathModePenalty129(string $mode): int
    {
        return match ($mode) {
            'absolute-path' => 0,
            'array-fragment', 'object-fragment' => 1,
            'bare-label-fragment' => 2,
            default => 3,
        };
    }

    private static function nestedHiddenCostClass129(bool $runnable, string $scanStrategy, int $hiddenEstimatedCost, int $rowCount): string
    {
        if (!$runnable || $scanStrategy === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($scanStrategy === 'path-rowid-intersection') {
            return 'json-table-nested-hidden-path-rowid-intersection';
        }
        if ($scanStrategy === 'rowid-only-lookup') {
            return 'json-table-nested-hidden-rowid-lookup';
        }
        if ($scanStrategy === 'path-only-lookup') {
            return 'json-table-nested-hidden-path-lookup';
        }
        if ($rowCount <= 1 || $hiddenEstimatedCost <= 10) {
            return 'json-table-nested-hidden-narrow-scan';
        }

        return 'json-table-nested-hidden-full-scan';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function nestedHiddenCostTransitions129(array $current, array $next): array
    {
        return [
            [
                'field' => 'root',
                'current' => $current['root'],
                'next' => $next['root'],
                'changed' => $current['root'] !== $next['root'],
            ],
            [
                'field' => 'mode',
                'current' => $current['mode'],
                'next' => $next['mode'],
                'changed' => $current['mode'] !== $next['mode'],
            ],
            [
                'field' => 'hiddenArguments',
                'current' => $current['hiddenArguments'],
                'next' => $next['hiddenArguments'],
                'changed' => $current['hiddenArguments'] !== $next['hiddenArguments'],
            ],
            [
                'field' => 'scanStrategy',
                'current' => $current['scanStrategy'],
                'next' => $next['scanStrategy'],
                'changed' => $current['scanStrategy'] !== $next['scanStrategy'],
            ],
            [
                'field' => 'hiddenEstimatedCost',
                'current' => $current['hiddenEstimatedCost'],
                'next' => $next['hiddenEstimatedCost'],
                'changed' => $current['hiddenEstimatedCost'] !== $next['hiddenEstimatedCost'],
            ],
            [
                'field' => 'rowCount',
                'current' => $current['rowCount'],
                'next' => $next['rowCount'],
                'changed' => $current['rowCount'] !== $next['rowCount'],
            ],
            [
                'field' => 'rootRowidTape',
                'current' => $current['rootRowidTape'],
                'next' => $next['rootRowidTape'],
                'changed' => $current['rootRowidTape'] !== $next['rootRowidTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function nestedHiddenCostReplanReasons129(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'root', 'hiddenArguments' => 'json-table-nested-hidden-root-changed',
                'mode' => 'json-table-nested-hidden-mode-changed',
                'scanStrategy' => 'json-table-nested-hidden-scan-strategy-changed',
                'hiddenEstimatedCost' => 'json-table-nested-hidden-cost-changed',
                'rowCount' => 'json-table-nested-hidden-row-count-changed',
                'rootRowidTape' => 'json-table-nested-hidden-output-changed',
                default => 'json-table-nested-hidden-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $pathCost
     * @param array<string,mixed> $partialOrder
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function jsonTablePathOrderByCostProfile131(array $pathCost, array $partialOrder, array $rows): array
    {
        $pathEstimatedCost = (int) $pathCost['pathEstimatedCost'];
        $sortPenalty = (int) $partialOrder['blockSortPenalty'];
        $effectiveCost = $pathEstimatedCost >= 1000000
            ? $pathEstimatedCost
            : $pathEstimatedCost + $sortPenalty;
        $orderedPathTape = self::jsonTablePathRowidTape126($rows);

        return [
            'selectedPathSignature' => $pathCost['selectedPathSignature'],
            'pathScanStrategy' => (string) $pathCost['pathScanStrategy'],
            'pathEstimatedCost' => $pathEstimatedCost,
            'pathRowCount' => (int) $pathCost['pathRowCount'],
            'orderBy' => $partialOrder['orderBy'],
            'consumedPrefixColumns' => $partialOrder['consumedPrefixColumns'],
            'suffixColumns' => $partialOrder['suffixColumns'],
            'prefixConsumedCount' => (int) $partialOrder['prefixConsumedCount'],
            'requiresSorter' => (bool) $partialOrder['blockSortRequired'],
            'sortPenalty' => $sortPenalty,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTablePathOrderByCostClass131((string) $pathCost['pathScanStrategy'], (bool) $partialOrder['blockSortRequired'], $effectiveCost),
            'orderedPathTape' => $orderedPathTape,
            'firstOrderedPath' => $orderedPathTape[0] ?? null,
            'lastOrderedPath' => $orderedPathTape === [] ? null : $orderedPathTape[array_key_last($orderedPathTape)],
        ];
    }

    private static function jsonTablePathOrderByCostClass131(string $pathScanStrategy, bool $requiresSorter, int $effectiveCost): string
    {
        if ($pathScanStrategy === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if (!$requiresSorter) {
            return 'json-table-path-order-stream';
        }
        if ($pathScanStrategy === 'path-constraint-pushdown') {
            return $effectiveCost <= 12
                ? 'json-table-path-order-block-sort'
                : 'json-table-path-order-sort';
        }

        return 'json-table-full-path-order-sort';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTablePathOrderByCostTransitions131(array $current, array $next): array
    {
        return [
            [
                'field' => 'selectedPathSignature',
                'current' => $current['selectedPathSignature'],
                'next' => $next['selectedPathSignature'],
                'changed' => $current['selectedPathSignature'] !== $next['selectedPathSignature'],
            ],
            [
                'field' => 'pathScanStrategy',
                'current' => $current['pathScanStrategy'],
                'next' => $next['pathScanStrategy'],
                'changed' => $current['pathScanStrategy'] !== $next['pathScanStrategy'],
            ],
            [
                'field' => 'consumedPrefixColumns',
                'current' => $current['consumedPrefixColumns'],
                'next' => $next['consumedPrefixColumns'],
                'changed' => $current['consumedPrefixColumns'] !== $next['consumedPrefixColumns'],
            ],
            [
                'field' => 'suffixColumns',
                'current' => $current['suffixColumns'],
                'next' => $next['suffixColumns'],
                'changed' => $current['suffixColumns'] !== $next['suffixColumns'],
            ],
            [
                'field' => 'requiresSorter',
                'current' => $current['requiresSorter'],
                'next' => $next['requiresSorter'],
                'changed' => $current['requiresSorter'] !== $next['requiresSorter'],
            ],
            [
                'field' => 'effectiveEstimatedCost',
                'current' => $current['effectiveEstimatedCost'],
                'next' => $next['effectiveEstimatedCost'],
                'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'orderedPathTape',
                'current' => $current['orderedPathTape'],
                'next' => $next['orderedPathTape'],
                'changed' => $current['orderedPathTape'] !== $next['orderedPathTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTablePathOrderByCostReplanReasons131(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'selectedPathSignature', 'pathScanStrategy' => 'json-table-path-orderby-path-changed',
                'consumedPrefixColumns', 'suffixColumns' => 'json-table-path-orderby-prefix-changed',
                'requiresSorter' => 'json-table-path-orderby-sorter-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-path-orderby-cost-changed',
                'orderedPathTape' => 'json-table-path-orderby-output-changed',
                default => 'json-table-path-orderby-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $nestedHidden
     * @param array<string,mixed> $plan
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{root:string,baseRoot:string,nestedPath:string,mode:string,rowidConstraintSignature:string|null,rowidConstraintValue:mixed,rowidConstraintOperator:string|null,rowidConstraintColumn:string|null,rowidConstraintUsable:bool,rowidScoped:bool,rootRowidTape:list<array{root:string,rowid:int|null,fullkey:string|null}>,scopedRowids:list<int>,relativeFullkeys:list<string>,firstScopedRowid:int|null,lastScopedRowid:int|null,rowCount:int,matchedRowCount:int,missingRowid:bool,scanStrategy:string,costClass:string,effectiveEstimatedCost:int}
     */
    private static function nestedPathRowidProfile133(array $nestedHidden, array $plan, array $constraints): array
    {
        $rowidConstraint = self::firstRowidConstraint133($constraints);
        $rowidValue = $rowidConstraint['value'] ?? null;
        $rowidOperator = isset($rowidConstraint['operator']) ? strtoupper((string) $rowidConstraint['operator']) : null;
        $root = (string) $nestedHidden['root'];
        $rootRowidTape = $nestedHidden['rootRowidTape'];
        $scopedTape = [];
        foreach ($rootRowidTape as $entry) {
            if ($rowidConstraint !== null && $rowidOperator === '=' && $entry['rowid'] !== self::rowidConstraintIntValue133($rowidValue)) {
                continue;
            }

            $scopedTape[] = $entry;
        }

        $relativeFullkeys = array_values(array_map(
            static fn (array $entry): string => self::relativeJsonFullkey133($root, $entry['fullkey']),
            $scopedTape,
        ));
        $scopedRowids = array_values(array_filter(
            array_map(static fn (array $entry): ?int => is_int($entry['rowid']) ? $entry['rowid'] : null, $scopedTape),
            static fn (?int $rowid): bool => $rowid !== null,
        ));

        $rowidScoped = $rowidConstraint !== null && $rowidOperator === '=' && self::rowidConstraintIntValue133($rowidValue) !== null;

        return [
            'root' => $root,
            'baseRoot' => (string) $nestedHidden['baseRoot'],
            'nestedPath' => (string) $nestedHidden['nestedPath'],
            'mode' => (string) $nestedHidden['mode'],
            'rowidConstraintSignature' => $rowidConstraint === null ? null : self::nestedPathRowidConstraintSignature133($rowidConstraint),
            'rowidConstraintValue' => $rowidValue,
            'rowidConstraintOperator' => $rowidOperator,
            'rowidConstraintColumn' => isset($rowidConstraint['column']) ? self::normalizeConstraintColumn((string) $rowidConstraint['column']) : null,
            'rowidConstraintUsable' => (bool) ($rowidConstraint['usable'] ?? true),
            'rowidScoped' => $rowidScoped,
            'rootRowidTape' => $rootRowidTape,
            'scopedRowids' => $scopedRowids,
            'relativeFullkeys' => $relativeFullkeys,
            'firstScopedRowid' => $scopedRowids[0] ?? null,
            'lastScopedRowid' => $scopedRowids === [] ? null : $scopedRowids[array_key_last($scopedRowids)],
            'rowCount' => (int) $nestedHidden['rowCount'],
            'matchedRowCount' => count($scopedTape),
            'missingRowid' => $rowidScoped && $scopedTape === [],
            'scanStrategy' => (string) $nestedHidden['scanStrategy'],
            'costClass' => self::nestedPathRowidCostClass133((bool) $plan['runnable'], $rowidScoped, $scopedTape !== [], (string) $nestedHidden['scanStrategy']),
            'effectiveEstimatedCost' => $rowidScoped && $scopedTape !== []
                ? max(1, min((int) $nestedHidden['hiddenEstimatedCost'], 2))
                : (int) $nestedHidden['hiddenEstimatedCost'],
        ];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{column:string,operator:string,value:mixed,usable?:bool}|null
     */
    private static function firstRowidConstraint133(array $constraints): ?array
    {
        foreach ($constraints as $constraint) {
            $column = self::normalizeConstraintColumn((string) ($constraint['column'] ?? ''));
            if ($column === 'id' && ($constraint['usable'] ?? true)) {
                return $constraint + ['column' => $column];
            }
        }

        return null;
    }

    /**
     * @param array{column:string,operator:string,value:mixed,usable?:bool} $constraint
     */
    private static function nestedPathRowidConstraintSignature133(array $constraint): string
    {
        $column = self::normalizeConstraintColumn((string) $constraint['column']);
        $operator = strtoupper((string) $constraint['operator']);

        return $column . ':' . $operator . ':' . json_encode($constraint['value']);
    }

    private static function rowidConstraintIntValue133(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private static function relativeJsonFullkey133(string $root, mixed $fullkey): string
    {
        if (!is_string($fullkey) || $fullkey === '') {
            return '';
        }
        if ($fullkey === $root) {
            return '$';
        }
        if (str_starts_with($fullkey, $root . '.')) {
            return '$.' . substr($fullkey, strlen($root) + 1);
        }
        if (str_starts_with($fullkey, $root . '[')) {
            return '$' . substr($fullkey, strlen($root));
        }

        return $fullkey;
    }

    private static function nestedPathRowidCostClass133(bool $runnable, bool $rowidScoped, bool $matchedRowid, string $scanStrategy): string
    {
        if (!$runnable || $scanStrategy === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($rowidScoped && $matchedRowid) {
            return 'json-table-nested-path-rowid-point';
        }
        if ($rowidScoped) {
            return 'json-table-nested-path-rowid-miss';
        }
        if ($scanStrategy === 'rowid-only-lookup' || $scanStrategy === 'path-rowid-intersection') {
            return 'json-table-nested-path-rowid-scan';
        }

        return 'json-table-nested-path-scan';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function nestedPathRowidTransitions133(array $current, array $next): array
    {
        return [
            [
                'field' => 'root',
                'current' => $current['root'],
                'next' => $next['root'],
                'changed' => $current['root'] !== $next['root'],
            ],
            [
                'field' => 'rowidConstraintSignature',
                'current' => $current['rowidConstraintSignature'],
                'next' => $next['rowidConstraintSignature'],
                'changed' => $current['rowidConstraintSignature'] !== $next['rowidConstraintSignature'],
            ],
            [
                'field' => 'scopedRowids',
                'current' => $current['scopedRowids'],
                'next' => $next['scopedRowids'],
                'changed' => $current['scopedRowids'] !== $next['scopedRowids'],
            ],
            [
                'field' => 'relativeFullkeys',
                'current' => $current['relativeFullkeys'],
                'next' => $next['relativeFullkeys'],
                'changed' => $current['relativeFullkeys'] !== $next['relativeFullkeys'],
            ],
            [
                'field' => 'missingRowid',
                'current' => $current['missingRowid'],
                'next' => $next['missingRowid'],
                'changed' => $current['missingRowid'] !== $next['missingRowid'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'matchedRowCount',
                'current' => $current['matchedRowCount'],
                'next' => $next['matchedRowCount'],
                'changed' => $current['matchedRowCount'] !== $next['matchedRowCount'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function nestedPathRowidReplanReasons133(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'root' => 'json-table-nested-path-rowid-root-changed',
                'rowidConstraintSignature' => 'json-table-nested-path-rowid-constraint-changed',
                'scopedRowids' => 'json-table-nested-path-rowid-rowset-changed',
                'relativeFullkeys' => 'json-table-nested-path-rowid-fullkey-changed',
                'missingRowid' => 'json-table-nested-path-rowid-miss-changed',
                'costClass' => 'json-table-nested-path-rowid-cost-class-changed',
                'matchedRowCount' => 'json-table-nested-path-rowid-count-changed',
                default => 'json-table-nested-path-rowid-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $pathOrderCost
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function jsonTableGeneratedPathCostProfile134(
        array $source,
        string $generatedPathColumn,
        array $pathOrderCost,
        array $rows,
    ): array {
        if (!array_key_exists($generatedPathColumn, $source)) {
            throw new \InvalidArgumentException('SQLite JSON table generated path source column is missing');
        }

        $generatedPath = $source[$generatedPathColumn];
        if ($generatedPath !== null && !is_string($generatedPath)) {
            throw new \InvalidArgumentException('SQLite JSON table generated path source column must be text or null');
        }
        if (is_string($generatedPath) && !SQLiteJsonPath::isWellFormed($generatedPath)) {
            throw new \InvalidArgumentException('SQLite JSON table generated path source column is not a well-formed path');
        }

        $selectedSignature = $pathOrderCost['selectedPathSignature'];
        $pathConstraint = self::jsonTableGeneratedPathConstraintFromSignature134($selectedSignature);
        $matched = $generatedPath !== null
            && $pathConstraint !== null
            && self::jsonTableGeneratedPathMatchesConstraint134($generatedPath, $pathConstraint);
        $coveredRows = $matched ? self::jsonTableGeneratedPathCoveredRows134($generatedPath, $rows) : [];
        $baseCost = (int) $pathOrderCost['effectiveEstimatedCost'];
        if (($pathOrderCost['costClass'] ?? null) === 'unrunnable-json-table') {
            $estimatedCost = 1000000;
            $estimatedRows = 0;
            $coverage = 'unrunnable-json-table';
        } elseif ($generatedPath === null) {
            $estimatedCost = $baseCost + 20;
            $estimatedRows = (int) $pathOrderCost['pathRowCount'];
            $coverage = 'missing-generated-path';
        } elseif ($pathConstraint === null) {
            $estimatedCost = $baseCost + 8;
            $estimatedRows = (int) $pathOrderCost['pathRowCount'];
            $coverage = 'generated-path-unconstrained';
        } elseif ($matched) {
            $estimatedRows = max(1, count($coveredRows));
            $estimatedCost = max(1, min($baseCost, $estimatedRows));
            $coverage = 'generated-path-covered';
        } else {
            $estimatedCost = $baseCost + 50;
            $estimatedRows = 0;
            $coverage = 'generated-path-mismatch';
        }

        return [
            'generatedPathColumn' => $generatedPathColumn,
            'generatedPath' => $generatedPath,
            'selectedPathSignature' => $selectedSignature,
            'pathConstraint' => $pathConstraint,
            'coverage' => $coverage,
            'generatedPathMatches' => $matched,
            'generatedEstimatedRows' => $estimatedRows,
            'generatedEstimatedCost' => $estimatedCost,
            'baseEffectiveCost' => $baseCost,
            'pathRowCount' => (int) $pathOrderCost['pathRowCount'],
            'coveredPathTape' => self::jsonTablePathRowidTape126($coveredRows),
            'firstCoveredPath' => $coveredRows === [] ? null : ($coveredRows[0]['path'] ?? null),
            'lastCoveredPath' => $coveredRows === [] ? null : ($coveredRows[array_key_last($coveredRows)]['path'] ?? null),
            'costClass' => self::jsonTableGeneratedPathCostClass134($coverage, $estimatedCost),
        ];
    }

    /**
     * @return array{operator:string,value:mixed}|null
     */
    private static function jsonTableGeneratedPathConstraintFromSignature134(mixed $signature): ?array
    {
        if (!is_string($signature)) {
            return null;
        }

        $parts = explode(':', $signature, 4);
        if (count($parts) !== 4 || $parts[1] !== 'path') {
            return null;
        }

        return [
            'operator' => strtoupper($parts[2]),
            'value' => json_decode($parts[3], true),
        ];
    }

    /**
     * @param array{operator:string,value:mixed} $constraint
     */
    private static function jsonTableGeneratedPathMatchesConstraint134(string $generatedPath, array $constraint): bool
    {
        $value = $constraint['value'];

        return match ($constraint['operator']) {
            '=', 'IS', 'IS NOT DISTINCT FROM' => is_string($value) && $generatedPath === $value,
            'LIKE' => is_string($value) && self::jsonTableGeneratedPathLike134($generatedPath, $value),
            'IN' => is_array($value) && in_array($generatedPath, $value, true),
            default => false,
        };
    }

    private static function jsonTableGeneratedPathLike134(string $generatedPath, string $pattern): bool
    {
        if ($pattern === '%') {
            return true;
        }
        if (str_ends_with($pattern, '%') && !str_contains(substr($pattern, 0, -1), '%') && !str_contains($pattern, '_')) {
            return str_starts_with($generatedPath, substr($pattern, 0, -1));
        }

        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], $quoted) . '$/u';

        return preg_match($regex, $generatedPath) === 1;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function jsonTableGeneratedPathCoveredRows134(string $generatedPath, array $rows): array
    {
        $covered = [];
        foreach ($rows as $row) {
            $path = $row['path'] ?? null;
            if (!is_string($path)) {
                continue;
            }
            if ($path === $generatedPath || str_starts_with($path, $generatedPath . '[') || str_starts_with($path, $generatedPath . '.')) {
                $covered[] = $row;
            }
        }

        return $covered;
    }

    private static function jsonTableGeneratedPathCostClass134(string $coverage, int $estimatedCost): string
    {
        if ($coverage === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($coverage === 'generated-path-covered') {
            return $estimatedCost <= 2 ? 'json-table-generated-path-point-cost' : 'json-table-generated-path-covered-cost';
        }
        if ($coverage === 'generated-path-mismatch') {
            return 'json-table-generated-path-empty-cost';
        }
        if ($coverage === 'missing-generated-path') {
            return 'json-table-generated-path-missing-cost';
        }

        return 'json-table-generated-path-unconstrained-cost';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableGeneratedPathCostTransitions134(array $current, array $next): array
    {
        return [
            [
                'field' => 'generatedPath',
                'current' => $current['generatedPath'],
                'next' => $next['generatedPath'],
                'changed' => $current['generatedPath'] !== $next['generatedPath'],
            ],
            [
                'field' => 'selectedPathSignature',
                'current' => $current['selectedPathSignature'],
                'next' => $next['selectedPathSignature'],
                'changed' => $current['selectedPathSignature'] !== $next['selectedPathSignature'],
            ],
            [
                'field' => 'coverage',
                'current' => $current['coverage'],
                'next' => $next['coverage'],
                'changed' => $current['coverage'] !== $next['coverage'],
            ],
            [
                'field' => 'generatedEstimatedCost',
                'current' => $current['generatedEstimatedCost'],
                'next' => $next['generatedEstimatedCost'],
                'changed' => $current['generatedEstimatedCost'] !== $next['generatedEstimatedCost'],
            ],
            [
                'field' => 'generatedEstimatedRows',
                'current' => $current['generatedEstimatedRows'],
                'next' => $next['generatedEstimatedRows'],
                'changed' => $current['generatedEstimatedRows'] !== $next['generatedEstimatedRows'],
            ],
            [
                'field' => 'costClass',
                'current' => $current['costClass'],
                'next' => $next['costClass'],
                'changed' => $current['costClass'] !== $next['costClass'],
            ],
            [
                'field' => 'coveredPathTape',
                'current' => $current['coveredPathTape'],
                'next' => $next['coveredPathTape'],
                'changed' => $current['coveredPathTape'] !== $next['coveredPathTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableGeneratedPathCostReplanReasons134(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'generatedPath' => 'json-table-generated-path-source-changed',
                'selectedPathSignature' => 'json-table-generated-path-constraint-changed',
                'coverage' => 'json-table-generated-path-coverage-changed',
                'generatedEstimatedCost', 'generatedEstimatedRows', 'costClass' => 'json-table-generated-path-cost-changed',
                'coveredPathTape' => 'json-table-generated-path-output-changed',
                default => 'json-table-generated-path-state-changed',
            };
        }

        return array_values(array_unique($reasons));
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function rowidsFromRows94(array $rows): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param array<string,mixed> $plan
     * @return array{rowids:list<int>,firstRowid:int|null,lastRowid:int|null,rowCount:int,rowidResidualColumns:list<string>,sourceKind:string,root:mixed}
     */
    private static function sourceRowidSummary94(array $plan): array
    {
        $rowids = self::rowidsFromRows94($plan['rows']);

        return [
            'rowids' => $rowids,
            'firstRowid' => $rowids[0] ?? null,
            'lastRowid' => $rowids === [] ? null : $rowids[count($rowids) - 1],
            'rowCount' => count($rowids),
            'rowidResidualColumns' => array_column(self::hiddenRowidResidualConstraints94($plan['constraintUsage']), 'column'),
            'sourceKind' => (string) $plan['jsonInputKind'],
            'root' => $plan['rootValue'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<array{index:int,current:array<string,mixed>|null,next:array<string,mixed>|null,currentRowid:int|null,nextRowid:int|null,changed:bool,reason:string}>
     */
    private static function sourceRowTransitions94(array $currentRows, array $nextRows): array
    {
        $count = max(count($currentRows), count($nextRows));
        $transitions = [];
        for ($index = 0; $index < $count; $index++) {
            $current = $currentRows[$index] ?? null;
            $next = $nextRows[$index] ?? null;
            $currentRowid = $current === null ? null : (int) $current['id'];
            $nextRowid = $next === null ? null : (int) $next['id'];
            $reason = self::sourceRowTransitionReason94($current, $next);
            $transitions[] = [
                'index' => $index,
                'current' => $current,
                'next' => $next,
                'currentRowid' => $currentRowid,
                'nextRowid' => $nextRowid,
                'changed' => $reason !== 'stable-hidden-rowid-source-row',
                'reason' => $reason,
            ];
        }

        return $transitions;
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function sourceRowTransitionReason94(?array $current, ?array $next): string
    {
        if ($current === null) {
            return 'next-hidden-rowid-source-row-added';
        }
        if ($next === null) {
            return 'current-hidden-rowid-source-row-removed';
        }
        if (($current['id'] ?? null) !== ($next['id'] ?? null)) {
            return 'hidden-rowid-source-rowid-changed';
        }
        if (($current['fullkey'] ?? null) !== ($next['fullkey'] ?? null)) {
            return 'hidden-rowid-source-fullkey-changed';
        }
        if (
            ($current['atom'] ?? null) !== ($next['atom'] ?? null)
            || ($current['type'] ?? null) !== ($next['type'] ?? null)
            || ($current['value'] ?? null) != ($next['value'] ?? null)
        ) {
            return 'hidden-rowid-source-payload-changed';
        }

        return 'stable-hidden-rowid-source-row';
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
