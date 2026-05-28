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
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return array<string,mixed>
     */
    public static function currentSourcePathGeneratedOrderNext137(
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
            throw new \InvalidArgumentException('SQLite JSON table path generated order requires generated order terms');
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

        $currentProfile = self::jsonTablePathGeneratedOrderProfile137($plan['currentPathOrderByCost'], $plan['currentRows'], $generatedOrder);
        $nextProfile = self::jsonTablePathGeneratedOrderProfile137($plan['nextPathOrderByCost'], $plan['nextRows'], $generatedOrder);
        $transitions = self::jsonTablePathGeneratedOrderTransitions137($currentProfile, $nextProfile);
        $reasons = self::jsonTablePathGeneratedOrderReplanReasons137($transitions);

        $plan['currentPathGeneratedOrder'] = $currentProfile;
        $plan['nextPathGeneratedOrder'] = $nextProfile;
        $plan['pathGeneratedOrderTransitions'] = $transitions;
        $plan['next137ReplanReasons'] = array_values(array_unique(array_merge($plan['next131ReplanReasons'], $reasons)));
        $plan['replanRequired'] = $plan['next137ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-path-generated-order-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next137ReplanReasons'] === []
            ? 'reuse-current-json-table-path-generated-order-source-plan'
            : 'prepare-next-json-table-path-generated-order-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-path-generated-order-current-source-next137'],
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
    public static function currentSourceRowidHiddenPathNext138(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceNestedPathRowidNext133(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
        );

        $currentProfile = self::rowidHiddenPathProfile138($plan['currentNestedPathRowid'], $constraints);
        $nextProfile = self::rowidHiddenPathProfile138($plan['nextNestedPathRowid'], $constraints);
        $transitions = self::rowidHiddenPathTransitions138($currentProfile, $nextProfile);
        $reasons = self::rowidHiddenPathReplanReasons138($transitions);

        $plan['currentRowidHiddenPath'] = $currentProfile;
        $plan['nextRowidHiddenPath'] = $nextProfile;
        $plan['rowidHiddenPathTransitions'] = $transitions;
        $plan['next138ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next133ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next138ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-rowid-hidden-path-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next138ReplanReasons'] === []
            ? 'reuse-current-json-table-rowid-hidden-path-source-plan'
            : 'prepare-next-json-table-rowid-hidden-path-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-rowid-hidden-path-current-source-next138'],
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
    public static function currentSourceHiddenRowidPathNext146(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceRowidHiddenPathNext138(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
        );

        $currentProfile = self::hiddenRowidPathProfile146($currentSource, $plan['currentRowidHiddenPath'], $constraints);
        $nextProfile = self::hiddenRowidPathProfile146($nextSource, $plan['nextRowidHiddenPath'], $constraints);
        $transitions = self::hiddenRowidPathTransitions146($currentProfile, $nextProfile);
        $reasons = self::hiddenRowidPathReplanReasons146($transitions);

        $plan['currentHiddenRowidPathSource'] = $currentProfile;
        $plan['nextHiddenRowidPathSource'] = $nextProfile;
        $plan['hiddenRowidPathSourceTransitions'] = $transitions;
        $plan['next146ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next138ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next146ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-rowid-path-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next146ReplanReasons'] === []
            ? 'reuse-current-json-table-hidden-rowid-path-source-plan'
            : 'prepare-next-json-table-hidden-rowid-path-source-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-rowid-path-current-source-next146'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return array<string,mixed>
     */
    public static function currentSourceGeneratedOrderCostNext139(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
        array $generatedConstraints = [],
        array $generatedOrder = [],
    ): array {
        if ($generatedOrder === []) {
            throw new \InvalidArgumentException('SQLite JSON table generated order cost planner requires generated order terms');
        }

        $plan = self::currentSourceGeneratedHiddenCostNext136(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
            $generatedConstraints,
        );

        $currentProfile = self::jsonTableGeneratedOrderCostProfile139(
            $plan['currentGeneratedHiddenCost'],
            $plan['current'],
            $generatedOrder,
        );
        $nextProfile = self::jsonTableGeneratedOrderCostProfile139(
            $plan['nextGeneratedHiddenCost'],
            $plan['next'],
            $generatedOrder,
        );
        $transitions = self::jsonTableGeneratedOrderCostTransitions139($currentProfile, $nextProfile);
        $reasons = self::jsonTableGeneratedOrderCostReplanReasons139($transitions);

        $plan['currentGeneratedOrderCost'] = $currentProfile;
        $plan['nextGeneratedOrderCost'] = $nextProfile;
        $plan['generatedOrderCostTransitions'] = $transitions;
        $plan['next139ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next136ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next139ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-generated-order-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next139ReplanReasons'] === []
            ? 'reuse-current-json-table-generated-order-cost-plan'
            : 'prepare-next-json-table-generated-order-cost-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-generated-order-cost-current-source-next139'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array<string,mixed>
     */
    public static function currentSourceGeneratedHiddenResidualCostNext141(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
        array $generatedConstraints = [],
    ): array {
        if ($generatedConstraints === []) {
            throw new \InvalidArgumentException('SQLite JSON table generated hidden residual cost planner requires generated constraints');
        }

        $plan = self::currentSourceGeneratedHiddenCostNext136(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
            $generatedConstraints,
        );

        $currentProfile = self::jsonTableGeneratedHiddenResidualCostProfile141($plan['currentGeneratedHiddenCost']);
        $nextProfile = self::jsonTableGeneratedHiddenResidualCostProfile141($plan['nextGeneratedHiddenCost']);
        $transitions = self::jsonTableGeneratedHiddenResidualCostTransitions141($currentProfile, $nextProfile);
        $reasons = self::jsonTableGeneratedHiddenResidualCostReplanReasons141($transitions);

        $plan['currentGeneratedHiddenResidualCost'] = $currentProfile;
        $plan['nextGeneratedHiddenResidualCost'] = $nextProfile;
        $plan['generatedHiddenResidualCostTransitions'] = $transitions;
        $plan['next141ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next136ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next141ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-generated-hidden-residual-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next141ReplanReasons'] === []
            ? 'reuse-current-json-table-generated-hidden-residual-cost-plan'
            : 'prepare-next-json-table-generated-hidden-residual-cost-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-generated-hidden-residual-cost-current-source-next141'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array<string,mixed>
     */
    public static function currentSourceGeneratedHiddenPathNext144(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $generatedPathColumn,
        array $constraints = [],
        array $orderBy = [],
        array $generatedConstraints = [],
    ): array {
        if ($generatedPathColumn === '') {
            throw new \InvalidArgumentException('SQLite JSON table generated hidden path current-source planner requires a generated path column');
        }

        $plan = self::currentSourceGeneratedHiddenResidualCostNext141(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $generatedPathColumn,
            $constraints,
            $orderBy,
            $generatedConstraints,
        );

        $currentProfile = self::jsonTableGeneratedHiddenPathProfile144(
            $currentSource,
            $jsonColumn,
            $baseRootColumn,
            $generatedPathColumn,
            $plan['currentNestedHiddenCost'],
            $plan['currentGeneratedHiddenResidualCost'],
        );
        $nextProfile = self::jsonTableGeneratedHiddenPathProfile144(
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $generatedPathColumn,
            $plan['nextNestedHiddenCost'],
            $plan['nextGeneratedHiddenResidualCost'],
        );
        $transitions = self::jsonTableGeneratedHiddenPathTransitions144($currentProfile, $nextProfile);
        $reasons = self::jsonTableGeneratedHiddenPathReplanReasons144($transitions);

        $plan['currentGeneratedHiddenPath'] = $currentProfile;
        $plan['nextGeneratedHiddenPath'] = $nextProfile;
        $plan['generatedHiddenPathTransitions'] = $transitions;
        $plan['next144ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next141ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next144ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-generated-hidden-path-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next144ReplanReasons'] === []
            ? 'reuse-current-json-table-generated-hidden-path-plan'
            : 'prepare-next-json-table-generated-hidden-path-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-generated-hidden-path-current-source-next144'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return array<string,mixed>
     */
    public static function currentSourceGeneratedRowidOrderNext147(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
        array $generatedConstraints = [],
        array $generatedOrder = [],
    ): array {
        if ($generatedOrder === []) {
            throw new \InvalidArgumentException('SQLite JSON table generated rowid order planner requires generated order terms');
        }

        $plan = self::currentSourceGeneratedHiddenRowidCostNext142(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
            $generatedConstraints,
        );

        $currentProfile = self::jsonTableGeneratedRowidOrderProfile147(
            $plan['currentGeneratedHiddenRowidCost'],
            $generatedOrder,
        );
        $nextProfile = self::jsonTableGeneratedRowidOrderProfile147(
            $plan['nextGeneratedHiddenRowidCost'],
            $generatedOrder,
        );
        $transitions = self::jsonTableGeneratedRowidOrderTransitions147($currentProfile, $nextProfile);
        $reasons = self::jsonTableGeneratedRowidOrderReplanReasons147($transitions);

        $plan['currentGeneratedRowidOrder'] = $currentProfile;
        $plan['nextGeneratedRowidOrder'] = $nextProfile;
        $plan['generatedRowidOrderTransitions'] = $transitions;
        $plan['next147ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next142ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next147ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-generated-rowid-order-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next147ReplanReasons'] === []
            ? 'reuse-current-json-table-generated-rowid-order-plan'
            : 'prepare-next-json-table-generated-rowid-order-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-generated-rowid-order-current-source-next147'],
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
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @param list<string> $generatedOutputColumns
     * @return array<string,mixed>
     */
    public static function currentSourceRowidHiddenGeneratedNext149(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
        array $generatedConstraints = [],
        array $generatedOutputColumns = [],
    ): array {
        $plan = self::currentSourceGeneratedHiddenRowidCostNext142(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
            $generatedConstraints,
        );

        $currentProfile = self::jsonTableRowidHiddenGeneratedProfile149($plan['currentGeneratedHiddenRowidCost'], $generatedOutputColumns);
        $nextProfile = self::jsonTableRowidHiddenGeneratedProfile149($plan['nextGeneratedHiddenRowidCost'], $generatedOutputColumns);
        $transitions = self::jsonTableRowidHiddenGeneratedTransitions149($currentProfile, $nextProfile);
        $reasons = self::jsonTableRowidHiddenGeneratedReplanReasons149($transitions);

        $plan['currentRowidHiddenGenerated'] = $currentProfile;
        $plan['nextRowidHiddenGenerated'] = $nextProfile;
        $plan['rowidHiddenGeneratedTransitions'] = $transitions;
        $plan['next149ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next142ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next149ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-rowid-hidden-generated-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next149ReplanReasons'] === []
            ? 'reuse-current-json-table-rowid-hidden-generated-plan'
            : 'prepare-next-json-table-rowid-hidden-generated-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-rowid-hidden-generated-current-source-next149'],
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
    public static function currentSourceGeneratedPathRowidCostNext145(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $generatedPathColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
    ): array {
        $plan = self::currentSourceGeneratedPathCostNext134(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $generatedPathColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTableGeneratedPathRowidCostProfile145($plan['currentGeneratedPathCost'], $constraints);
        $nextProfile = self::jsonTableGeneratedPathRowidCostProfile145($plan['nextGeneratedPathCost'], $constraints);
        $transitions = self::jsonTableGeneratedPathRowidCostTransitions145($currentProfile, $nextProfile);
        $reasons = self::jsonTableGeneratedPathRowidCostReplanReasons145($transitions);

        $plan['currentGeneratedPathRowidCost'] = $currentProfile;
        $plan['nextGeneratedPathRowidCost'] = $nextProfile;
        $plan['generatedPathRowidCostTransitions'] = $transitions;
        $plan['next145ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next134ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next145ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-generated-path-rowid-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next145ReplanReasons'] === []
            ? 'reuse-current-json-table-generated-path-rowid-cost-plan'
            : 'prepare-next-json-table-generated-path-rowid-cost-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-generated-path-rowid-cost-current-source-next145'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array<string,mixed>
     */
    public static function currentSourceGeneratedHiddenRowidCostNext142(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
        array $generatedConstraints = [],
    ): array {
        $plan = self::currentSourceGeneratedHiddenCostNext136(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $baseRootColumn,
            $nestedPathColumn,
            $constraints,
            $orderBy,
            $generatedConstraints,
        );

        $currentProfile = self::jsonTableGeneratedHiddenRowidCostProfile142($plan['currentGeneratedHiddenCost'], $constraints);
        $nextProfile = self::jsonTableGeneratedHiddenRowidCostProfile142($plan['nextGeneratedHiddenCost'], $constraints);
        $transitions = self::jsonTableGeneratedHiddenRowidCostTransitions142($currentProfile, $nextProfile);
        $reasons = self::jsonTableGeneratedHiddenRowidCostReplanReasons142($transitions);

        $plan['currentGeneratedHiddenRowidCost'] = $currentProfile;
        $plan['nextGeneratedHiddenRowidCost'] = $nextProfile;
        $plan['generatedHiddenRowidCostTransitions'] = $transitions;
        $plan['next142ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next136ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next142ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-generated-hidden-rowid-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next142ReplanReasons'] === []
            ? 'reuse-current-json-table-generated-hidden-rowid-cost-plan'
            : 'prepare-next-json-table-generated-hidden-rowid-cost-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-generated-hidden-rowid-cost-current-source-next142'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array<string,mixed>
     */
    public static function currentSourceGeneratedHiddenCostNext136(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        string $baseRootColumn,
        string $nestedPathColumn,
        array $constraints = [],
        array $orderBy = [],
        array $generatedConstraints = [],
    ): array {
        if ($generatedConstraints === []) {
            throw new \InvalidArgumentException('SQLite JSON table generated hidden cost planner requires generated constraints');
        }

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

        $currentProfile = self::jsonTableGeneratedHiddenCostProfile136($plan['currentNestedHiddenCost'], $plan['current'], $generatedConstraints);
        $nextProfile = self::jsonTableGeneratedHiddenCostProfile136($plan['nextNestedHiddenCost'], $plan['next'], $generatedConstraints);
        $transitions = self::jsonTableGeneratedHiddenCostTransitions136($currentProfile, $nextProfile);
        $reasons = self::jsonTableGeneratedHiddenCostReplanReasons136($transitions);

        $plan['currentGeneratedHiddenCost'] = $currentProfile;
        $plan['nextGeneratedHiddenCost'] = $nextProfile;
        $plan['generatedHiddenCostTransitions'] = $transitions;
        $plan['next136ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next129ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next136ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-generated-hidden-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next136ReplanReasons'] === []
            ? 'reuse-current-json-table-generated-hidden-cost-plan'
            : 'prepare-next-json-table-generated-hidden-cost-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-generated-hidden-cost-current-source-next136'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array<string,mixed>
     */
    public static function currentSourceHiddenGeneratedCostNext148(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
        array $generatedConstraints = [],
    ): array {
        if ($generatedConstraints === []) {
            throw new \InvalidArgumentException('SQLite JSON table hidden generated cost planner requires generated constraints');
        }

        $plan = self::currentSourceHiddenPathGeneratedNext143(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
            $generatedConstraints,
        );

        $currentProfile = self::jsonTableHiddenGeneratedCostProfile148(
            $plan['currentHiddenPathGeneratedSource'],
            $constraints,
        );
        $nextProfile = self::jsonTableHiddenGeneratedCostProfile148(
            $plan['nextHiddenPathGeneratedSource'],
            $constraints,
        );
        $transitions = self::jsonTableHiddenGeneratedCostTransitions148($currentProfile, $nextProfile);
        $reasons = self::jsonTableHiddenGeneratedCostReplanReasons148($transitions);

        $plan['currentHiddenGeneratedCost'] = $currentProfile;
        $plan['nextHiddenGeneratedCost'] = $nextProfile;
        $plan['hiddenGeneratedCostTransitions'] = $transitions;
        $plan['next148ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next143ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next148ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-generated-cost-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next148ReplanReasons'] === []
            ? 'reuse-current-json-table-hidden-generated-cost-plan'
            : 'prepare-next-json-table-hidden-generated-cost-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-generated-cost-current-source-next148'],
        )));

        return $plan;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @param list<array{column:string,direction?:string}> $orderBy
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array<string,mixed>
     */
    public static function currentSourceHiddenGeneratedRowidNext157(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
        array $generatedConstraints = [],
    ): array {
        $plan = self::currentSourceHiddenGeneratedCostNext148(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
            $generatedConstraints,
        );

        $currentProfile = self::jsonTableHiddenGeneratedRowidProfile157($plan['currentHiddenGeneratedCost'], $constraints);
        $nextProfile = self::jsonTableHiddenGeneratedRowidProfile157($plan['nextHiddenGeneratedCost'], $constraints);
        $transitions = self::jsonTableHiddenGeneratedRowidTransitions157($currentProfile, $nextProfile);
        $reasons = self::jsonTableHiddenGeneratedRowidReplanReasons157($transitions);

        $plan['currentHiddenGeneratedRowid'] = $currentProfile;
        $plan['nextHiddenGeneratedRowid'] = $nextProfile;
        $plan['hiddenGeneratedRowidTransitions'] = $transitions;
        $plan['next157ReplanReasons'] = array_values(array_unique(array_merge(
            $plan['next148ReplanReasons'],
            $reasons,
        )));
        $plan['replanRequired'] = $plan['next157ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-generated-rowid-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next157ReplanReasons'] === []
            ? 'reuse-current-json-table-hidden-generated-rowid-plan'
            : 'prepare-next-json-table-hidden-generated-rowid-plan';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-generated-rowid-current-source-next157'],
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
    public static function currentSourceHiddenRowidOrderNext135(
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

        $currentProfile = self::jsonTableHiddenRowidOrderProfile135($plan['current'], $orderBy);
        $nextProfile = self::jsonTableHiddenRowidOrderProfile135($plan['next'], $orderBy);
        $transitions = self::jsonTableHiddenRowidOrderTransitions135($currentProfile, $nextProfile);
        $reasons = self::jsonTableHiddenRowidOrderReplanReasons135($transitions);

        $plan['currentHiddenRowidOrder'] = $currentProfile;
        $plan['nextHiddenRowidOrder'] = $nextProfile;
        $plan['hiddenRowidOrderTransitions'] = $transitions;
        $plan['next135ReplanReasons'] = array_values(array_unique(array_merge($plan['next94ReplanReasons'], $reasons)));
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-rowid-order-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next135ReplanReasons'] === []
            || $plan['next135ReplanReasons'] === ['hidden-rowid-residual-constraint-present']
            ? 'reuse-current-json-table-hidden-rowid-order-source'
            : 'prepare-next-json-table-hidden-rowid-order-source';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-rowid-order-current-source-next135'],
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
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array<string,mixed>
     */
    public static function currentSourceHiddenPathGeneratedNext143(
        string $function,
        array $currentSource,
        array $nextSource,
        string $jsonColumn,
        array $constraints = [],
        ?string $rootColumn = null,
        array $orderBy = [],
        array $generatedConstraints = [],
    ): array {
        if ($generatedConstraints === []) {
            throw new \InvalidArgumentException('SQLite JSON table hidden path generated current-source planner requires generated constraints');
        }

        $plan = self::currentSourceHiddenPathRowidNext140(
            $function,
            $currentSource,
            $nextSource,
            $jsonColumn,
            $constraints,
            $rootColumn,
            $orderBy,
        );

        $currentProfile = self::jsonTableHiddenPathGeneratedCurrentSourceProfile143(
            $plan['current'],
            $plan['currentHiddenPathRowidSource'],
            $generatedConstraints,
        );
        $nextProfile = self::jsonTableHiddenPathGeneratedCurrentSourceProfile143(
            $plan['next'],
            $plan['nextHiddenPathRowidSource'],
            $generatedConstraints,
        );
        $transitions = self::jsonTableHiddenPathGeneratedCurrentSourceTransitions143($currentProfile, $nextProfile);
        $reasons = self::jsonTableHiddenPathGeneratedCurrentSourceReplanReasons143($transitions);

        $plan['currentHiddenPathGeneratedSource'] = $currentProfile;
        $plan['nextHiddenPathGeneratedSource'] = $nextProfile;
        $plan['hiddenPathGeneratedSourceTransitions'] = $transitions;
        $plan['next143ReplanReasons'] = array_values(array_unique(array_merge($plan['next140ReplanReasons'], $reasons)));
        $plan['replanRequired'] = $plan['next143ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-path-generated-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next143ReplanReasons'] === []
            ? 'reuse-current-json-table-hidden-path-generated-source'
            : 'prepare-next-json-table-hidden-path-generated-source';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-path-generated-current-source-next143'],
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
    public static function currentSourceHiddenPathRowidNext140(
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

        $currentProfile = self::jsonTableHiddenPathRowidCurrentSourceProfile140(
            $plan['current'],
            $plan['currentPathHiddenRowidCost'],
        );
        $nextProfile = self::jsonTableHiddenPathRowidCurrentSourceProfile140(
            $plan['next'],
            $plan['nextPathHiddenRowidCost'],
        );
        $transitions = self::jsonTableHiddenPathRowidCurrentSourceTransitions140($currentProfile, $nextProfile);
        $reasons = self::jsonTableHiddenPathRowidCurrentSourceReplanReasons140($transitions);

        $plan['currentHiddenPathRowidSource'] = $currentProfile;
        $plan['nextHiddenPathRowidSource'] = $nextProfile;
        $plan['hiddenPathRowidSourceTransitions'] = $transitions;
        $plan['next140ReplanReasons'] = array_values(array_unique(array_merge($plan['next126ReplanReasons'], $reasons)));
        $plan['replanRequired'] = $plan['next140ReplanReasons'] !== [];
        $plan['currentReaderPolicy'] = 'pin-current-json-table-hidden-path-rowid-source-until-cursor-reset';
        $plan['nextReaderPolicy'] = $plan['next140ReplanReasons'] === []
            ? 'reuse-current-json-table-hidden-path-rowid-source'
            : 'prepare-next-json-table-hidden-path-rowid-source';
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-json-table-hidden-path-rowid-current-source-next140'],
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
     * @param array<string,mixed> $pathOrder
     * @param list<array<string,mixed>> $rows
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return array{generatedOrderBy:list<array{name:string,source:string,path:string,direction:string}>,pathScanStrategy:string,selectedPathSignature:string|null,pathOrderCostClass:string,pathOrderEffectiveCost:int,hasGeneratedOrder:bool,requiresGeneratedSorter:bool,generatedSortPenalty:int,rowGeneratedKeys:list<list<mixed>>,orderedRowids:list<int|null>,firstGeneratedKey:list<mixed>,lastGeneratedKey:list<mixed>,generatedOutputTape:list<array{rowid:int|null,key:list<mixed>,path:mixed,fullkey:mixed}>,effectiveEstimatedCost:int,costClass:string,rowCount:int}
     */
    private static function jsonTablePathGeneratedOrderProfile137(array $pathOrder, array $rows, array $generatedOrder): array
    {
        $terms = self::normalizeGeneratedOrderTerms132($generatedOrder);
        $rowCount = count($rows);
        $rowEntries = [];
        foreach ($rows as $row) {
            $key = [];
            foreach ($terms as $term) {
                $key[] = self::generatedOrderValue132($row, $term);
            }
            $rowEntries[] = [
                'rowid' => isset($row['id']) ? (int) $row['id'] : null,
                'key' => $key,
                'path' => $row['path'] ?? null,
                'fullkey' => $row['fullkey'] ?? null,
            ];
        }

        if ($terms !== []) {
            usort(
                $rowEntries,
                static fn (array $left, array $right): int => self::compareGeneratedOrderEntries132($left, $right, $terms),
            );
        }

        $pathCost = (int) ($pathOrder['effectiveEstimatedCost'] ?? 1000000);
        $generatedSortPenalty = $rowCount > 1 ? self::jsonTableSortPenalty113($rowCount, $terms) : 0;
        $effectiveCost = $pathCost >= 1000000 ? $pathCost : $pathCost + $generatedSortPenalty;

        return [
            'generatedOrderBy' => $terms,
            'pathScanStrategy' => (string) ($pathOrder['pathScanStrategy'] ?? 'full-json-table-scan'),
            'selectedPathSignature' => $pathOrder['selectedPathSignature'] ?? null,
            'pathOrderCostClass' => (string) ($pathOrder['costClass'] ?? 'json-table-path-generated-order'),
            'pathOrderEffectiveCost' => $pathCost,
            'hasGeneratedOrder' => $terms !== [],
            'requiresGeneratedSorter' => $terms !== [] && $rowCount > 1,
            'generatedSortPenalty' => $generatedSortPenalty,
            'rowGeneratedKeys' => array_values(array_map(static fn (array $entry): array => $entry['key'], $rowEntries)),
            'orderedRowids' => array_values(array_map(static fn (array $entry): ?int => $entry['rowid'], $rowEntries)),
            'firstGeneratedKey' => $rowEntries[0]['key'] ?? [],
            'lastGeneratedKey' => $rowEntries[$rowCount - 1]['key'] ?? [],
            'generatedOutputTape' => $rowEntries,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTablePathGeneratedOrderCostClass137($pathOrder, $terms, $terms !== [] && $rowCount > 1, $effectiveCost),
            'rowCount' => $rowCount,
        ];
    }

    /**
     * @param array<string,mixed> $pathOrder
     * @param list<array{name:string,source:string,path:string,direction:string}> $terms
     */
    private static function jsonTablePathGeneratedOrderCostClass137(
        array $pathOrder,
        array $terms,
        bool $requiresSorter,
        int $effectiveCost,
    ): string {
        if (($pathOrder['costClass'] ?? null) === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($requiresSorter) {
            return ($pathOrder['pathScanStrategy'] ?? null) === 'path-constraint-pushdown'
                ? 'json-table-path-generated-order-block-sort'
                : 'json-table-generated-order-sort-required';
        }
        if ($terms !== []) {
            return $effectiveCost <= 6 ? 'json-table-path-generated-order-narrow' : 'json-table-path-generated-order';
        }

        return (string) ($pathOrder['costClass'] ?? 'json-table-path-generated-order');
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTablePathGeneratedOrderTransitions137(array $current, array $next): array
    {
        return [
            [
                'field' => 'generatedOrderBy',
                'current' => $current['generatedOrderBy'],
                'next' => $next['generatedOrderBy'],
                'changed' => $current['generatedOrderBy'] !== $next['generatedOrderBy'],
            ],
            [
                'field' => 'selectedPathSignature',
                'current' => $current['selectedPathSignature'],
                'next' => $next['selectedPathSignature'],
                'changed' => $current['selectedPathSignature'] !== $next['selectedPathSignature'],
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
    private static function jsonTablePathGeneratedOrderReplanReasons137(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'generatedOrderBy' => 'json-table-path-generated-order-terms-changed',
                'selectedPathSignature' => 'json-table-path-generated-path-constraint-changed',
                'rowGeneratedKeys' => 'json-table-path-generated-keys-changed',
                'orderedRowids' => 'json-table-path-generated-output-order-changed',
                'requiresGeneratedSorter' => 'json-table-path-generated-sorter-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-path-generated-cost-changed',
                default => 'json-table-path-generated-state-changed',
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
     * @param array<string,mixed> $nestedHidden
     * @param array<string,mixed> $plan
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array{root:string,baseRoot:string,nestedPath:string,mode:string,generatedConstraints:list<array{name:string,source:string,path:string,operator:string,value:mixed,usable:bool}>,generatedConstraintSignatures:list<string>,hiddenEstimatedCost:int,generatedEstimatedCost:int,effectiveEstimatedCost:int,rowCount:int,matchedRowCount:int,filteredRowids:list<int|null>,filteredFullkeys:list<mixed>,generatedValueTape:list<array{rowid:int|null,fullkey:mixed,values:array<string,mixed>,matched:bool}>,firstFilteredRowid:int|null,lastFilteredRowid:int|null,costClass:string,scanStrategy:string}
     */
    private static function jsonTableGeneratedHiddenCostProfile136(array $nestedHidden, array $plan, array $generatedConstraints): array
    {
        $constraints = self::normalizeGeneratedHiddenConstraints136($generatedConstraints);
        $rows = $plan['rows'];
        $tape = [];
        $filteredRowids = [];
        $filteredFullkeys = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($constraints as $constraint) {
                $values[$constraint['name']] = self::generatedOrderValue132($row, $constraint);
            }

            $matched = self::generatedHiddenValuesMatch136($values, $constraints);
            $rowid = isset($row['id']) ? (int) $row['id'] : null;
            $fullkey = $row['fullkey'] ?? null;
            if ($matched) {
                $filteredRowids[] = $rowid;
                $filteredFullkeys[] = $fullkey;
            }

            $tape[] = [
                'rowid' => $rowid,
                'fullkey' => $fullkey,
                'values' => $values,
                'matched' => $matched,
            ];
        }

        $hiddenCost = (int) $nestedHidden['hiddenEstimatedCost'];
        $rowCount = count($rows);
        $matchedCount = count($filteredRowids);
        if (!(bool) $plan['runnable']) {
            $generatedCost = 1000000;
            $effectiveCost = 1000000;
        } elseif ($matchedCount === 0) {
            $generatedCost = 1;
            $effectiveCost = 1;
        } else {
            $selectivity = max(1, $rowCount === 0 ? 1 : (int) ceil($rowCount / $matchedCount));
            $generatedCost = max(1, intdiv($hiddenCost + $selectivity - 1, $selectivity));
            $effectiveCost = min($hiddenCost, $generatedCost + count($constraints));
        }

        return [
            'root' => (string) $nestedHidden['root'],
            'baseRoot' => (string) $nestedHidden['baseRoot'],
            'nestedPath' => (string) $nestedHidden['nestedPath'],
            'mode' => (string) $nestedHidden['mode'],
            'generatedConstraints' => $constraints,
            'generatedConstraintSignatures' => array_map(
                static fn (array $constraint): string => $constraint['name'] . ':' . $constraint['source'] . ':' . $constraint['path'] . ':' . $constraint['operator'] . ':' . json_encode($constraint['value']),
                $constraints,
            ),
            'hiddenEstimatedCost' => $hiddenCost,
            'generatedEstimatedCost' => $generatedCost,
            'effectiveEstimatedCost' => $effectiveCost,
            'rowCount' => $rowCount,
            'matchedRowCount' => $matchedCount,
            'filteredRowids' => $filteredRowids,
            'filteredFullkeys' => $filteredFullkeys,
            'generatedValueTape' => $tape,
            'firstFilteredRowid' => $filteredRowids[0] ?? null,
            'lastFilteredRowid' => $filteredRowids === [] ? null : $filteredRowids[array_key_last($filteredRowids)],
            'costClass' => self::jsonTableGeneratedHiddenCostClass136((bool) $plan['runnable'], count($constraints), $rowCount, $matchedCount, $effectiveCost),
            'scanStrategy' => (string) $nestedHidden['scanStrategy'],
        ];
    }

    /**
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $constraints
     * @return list<array{name:string,source:string,path:string,operator:string,value:mixed,usable:bool}>
     */
    private static function normalizeGeneratedHiddenConstraints136(array $constraints): array
    {
        $normalized = [];
        foreach ($constraints as $constraint) {
            $name = (string) ($constraint['name'] ?? '');
            $source = (string) ($constraint['source'] ?? 'value');
            $path = (string) ($constraint['path'] ?? '');
            $operator = strtoupper((string) ($constraint['operator'] ?? '='));
            if ($name === '' || $path === '') {
                throw new \InvalidArgumentException('SQLite JSON table generated hidden cost constraints require a name and path');
            }
            if (!in_array($source, ['value', 'json', 'atom'], true)) {
                throw new \InvalidArgumentException('SQLite JSON table generated hidden cost source must be value, json, or atom');
            }
            if (!in_array($operator, ['=', 'IS', 'IS NOT', 'IS NULL', 'IS NOT NULL', 'IN', 'BETWEEN'], true)) {
                throw new \InvalidArgumentException('SQLite JSON table generated hidden cost operator is not supported');
            }
            if (!SQLiteJsonPath::isWellFormed($path)) {
                throw new \InvalidArgumentException('SQLite JSON table generated hidden cost path is not well-formed');
            }

            $normalized[] = [
                'name' => $name,
                'source' => $source,
                'path' => $path,
                'operator' => $operator,
                'value' => $constraint['value'] ?? null,
                'usable' => (bool) ($constraint['usable'] ?? true),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $values
     * @param list<array{name:string,source:string,path:string,operator:string,value:mixed,usable:bool}> $constraints
     */
    private static function generatedHiddenValuesMatch136(array $values, array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if (!$constraint['usable']) {
                continue;
            }

            $actual = $values[$constraint['name']] ?? null;
            $expected = $constraint['value'];
            $matched = match ($constraint['operator']) {
                '=', 'IS' => self::valuesAreNotDistinct($actual, $expected),
                'IS NOT' => !self::valuesAreNotDistinct($actual, $expected),
                'IS NULL' => $actual === null,
                'IS NOT NULL' => $actual !== null,
                'IN' => is_array($expected) && self::generatedHiddenInList136($actual, $expected),
                'BETWEEN' => is_array($expected)
                    && count($expected) === 2
                    && self::compareSQLiteValues($actual, array_values($expected)[0]) >= 0
                    && self::compareSQLiteValues($actual, array_values($expected)[1]) <= 0,
                default => false,
            };
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $values
     */
    private static function generatedHiddenInList136(mixed $actual, array $values): bool
    {
        foreach ($values as $value) {
            if (self::valuesAreNotDistinct($actual, $value)) {
                return true;
            }
        }

        return false;
    }

    private static function jsonTableGeneratedHiddenCostClass136(bool $runnable, int $constraintCount, int $rowCount, int $matchedCount, int $effectiveCost): string
    {
        if (!$runnable) {
            return 'unrunnable-json-table';
        }
        if ($matchedCount === 0) {
            return 'json-table-generated-hidden-empty';
        }
        if ($matchedCount === 1) {
            return 'json-table-generated-hidden-point';
        }
        if ($constraintCount > 0 && $matchedCount < $rowCount) {
            return $effectiveCost <= 8
                ? 'json-table-generated-hidden-narrow-filter'
                : 'json-table-generated-hidden-filter';
        }

        return 'json-table-generated-hidden-full-scan';
    }

    /**
     * @param array<string,mixed> $generatedHidden
     * @param array<string,mixed> $plan
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return array{root:string,generatedOrderBy:list<array{name:string,source:string,path:string,direction:string}>,filteredRowCount:int,orderedRowids:list<int|null>,orderedFullkeys:list<mixed>,orderedGeneratedKeys:list<list<mixed>>,generatedOrderTape:list<array{rowid:int|null,fullkey:mixed,key:list<mixed>,matched:bool}>,firstOrderedRowid:int|null,lastOrderedRowid:int|null,filterEffectiveCost:int,generatedSortPenalty:int,effectiveEstimatedCost:int,requiresGeneratedSorter:bool,costClass:string}
     */
    private static function jsonTableGeneratedOrderCostProfile139(array $generatedHidden, array $plan, array $generatedOrder): array
    {
        $terms = self::normalizeGeneratedOrderTerms132($generatedOrder);
        $matchedByRowid = [];
        foreach ($generatedHidden['generatedValueTape'] as $entry) {
            if (($entry['matched'] ?? false) !== true) {
                continue;
            }

            $matchedByRowid[(string) ($entry['rowid'] ?? '')] = true;
        }

        $entries = [];
        foreach ($plan['rows'] as $row) {
            $rowid = isset($row['id']) ? (int) $row['id'] : null;
            if (!isset($matchedByRowid[(string) $rowid])) {
                continue;
            }

            $key = [];
            foreach ($terms as $term) {
                $key[] = self::generatedOrderValue132($row, $term);
            }

            $entries[] = [
                'rowid' => $rowid,
                'fullkey' => $row['fullkey'] ?? null,
                'key' => $key,
                'matched' => true,
            ];
        }

        usort($entries, static fn (array $left, array $right): int => self::compareGeneratedOrderEntries132($left, $right, $terms));

        $filteredCount = count($entries);
        $requiresSorter = $filteredCount > 1;
        $sortPenalty = $requiresSorter ? self::jsonTableSortPenalty113($filteredCount, $terms) : 0;
        $filterCost = (int) $generatedHidden['effectiveEstimatedCost'];
        $effectiveCost = $filterCost >= 1000000 ? $filterCost : $filterCost + $sortPenalty;

        return [
            'root' => (string) $generatedHidden['root'],
            'generatedOrderBy' => $terms,
            'filteredRowCount' => $filteredCount,
            'orderedRowids' => array_values(array_map(static fn (array $entry): ?int => $entry['rowid'], $entries)),
            'orderedFullkeys' => array_values(array_map(static fn (array $entry): mixed => $entry['fullkey'], $entries)),
            'orderedGeneratedKeys' => array_values(array_map(static fn (array $entry): array => $entry['key'], $entries)),
            'generatedOrderTape' => $entries,
            'firstOrderedRowid' => $entries[0]['rowid'] ?? null,
            'lastOrderedRowid' => $entries === [] ? null : $entries[array_key_last($entries)]['rowid'],
            'filterEffectiveCost' => $filterCost,
            'generatedSortPenalty' => $sortPenalty,
            'effectiveEstimatedCost' => $effectiveCost,
            'requiresGeneratedSorter' => $requiresSorter,
            'costClass' => self::jsonTableGeneratedOrderCostClass139((string) $generatedHidden['costClass'], $filteredCount, $requiresSorter, $effectiveCost),
        ];
    }

    private static function jsonTableGeneratedOrderCostClass139(string $filterCostClass, int $filteredCount, bool $requiresSorter, int $effectiveCost): string
    {
        if ($filterCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($filteredCount === 0) {
            return 'json-table-generated-order-empty';
        }
        if (!$requiresSorter) {
            return 'json-table-generated-order-point';
        }

        return $effectiveCost <= 16
            ? 'json-table-generated-order-narrow-sort'
            : 'json-table-generated-order-sort';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableGeneratedOrderCostTransitions139(array $current, array $next): array
    {
        return [
            [
                'field' => 'root',
                'current' => $current['root'],
                'next' => $next['root'],
                'changed' => $current['root'] !== $next['root'],
            ],
            [
                'field' => 'generatedOrderBy',
                'current' => $current['generatedOrderBy'],
                'next' => $next['generatedOrderBy'],
                'changed' => $current['generatedOrderBy'] !== $next['generatedOrderBy'],
            ],
            [
                'field' => 'filteredRowCount',
                'current' => $current['filteredRowCount'],
                'next' => $next['filteredRowCount'],
                'changed' => $current['filteredRowCount'] !== $next['filteredRowCount'],
            ],
            [
                'field' => 'orderedRowids',
                'current' => $current['orderedRowids'],
                'next' => $next['orderedRowids'],
                'changed' => $current['orderedRowids'] !== $next['orderedRowids'],
            ],
            [
                'field' => 'orderedGeneratedKeys',
                'current' => $current['orderedGeneratedKeys'],
                'next' => $next['orderedGeneratedKeys'],
                'changed' => $current['orderedGeneratedKeys'] !== $next['orderedGeneratedKeys'],
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
    private static function jsonTableGeneratedOrderCostReplanReasons139(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'root' => 'json-table-generated-order-root-changed',
                'generatedOrderBy' => 'json-table-generated-order-terms-changed',
                'filteredRowCount' => 'json-table-generated-order-row-count-changed',
                'orderedRowids' => 'json-table-generated-order-output-changed',
                'orderedGeneratedKeys' => 'json-table-generated-order-keys-changed',
                'requiresGeneratedSorter' => 'json-table-generated-order-sorter-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-generated-order-cost-changed',
                default => 'json-table-generated-order-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableGeneratedHiddenCostTransitions136(array $current, array $next): array
    {
        return [
            [
                'field' => 'root',
                'current' => $current['root'],
                'next' => $next['root'],
                'changed' => $current['root'] !== $next['root'],
            ],
            [
                'field' => 'generatedConstraintSignatures',
                'current' => $current['generatedConstraintSignatures'],
                'next' => $next['generatedConstraintSignatures'],
                'changed' => $current['generatedConstraintSignatures'] !== $next['generatedConstraintSignatures'],
            ],
            [
                'field' => 'matchedRowCount',
                'current' => $current['matchedRowCount'],
                'next' => $next['matchedRowCount'],
                'changed' => $current['matchedRowCount'] !== $next['matchedRowCount'],
            ],
            [
                'field' => 'filteredRowids',
                'current' => $current['filteredRowids'],
                'next' => $next['filteredRowids'],
                'changed' => $current['filteredRowids'] !== $next['filteredRowids'],
            ],
            [
                'field' => 'filteredFullkeys',
                'current' => $current['filteredFullkeys'],
                'next' => $next['filteredFullkeys'],
                'changed' => $current['filteredFullkeys'] !== $next['filteredFullkeys'],
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
                'field' => 'generatedValueTape',
                'current' => $current['generatedValueTape'],
                'next' => $next['generatedValueTape'],
                'changed' => $current['generatedValueTape'] !== $next['generatedValueTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableGeneratedHiddenCostReplanReasons136(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'root' => 'json-table-generated-hidden-root-changed',
                'generatedConstraintSignatures' => 'json-table-generated-hidden-constraint-changed',
                'matchedRowCount' => 'json-table-generated-hidden-row-count-changed',
                'filteredRowids', 'filteredFullkeys' => 'json-table-generated-hidden-rowset-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-generated-hidden-cost-changed',
                'generatedValueTape' => 'json-table-generated-hidden-values-changed',
                default => 'json-table-generated-hidden-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $generatedHidden
     * @return array{generatedConstraintSignatures:list<string>,usableGeneratedConstraintSignatures:list<string>,residualGeneratedConstraintSignatures:list<string>,residualGeneratedColumns:list<string>,residualGeneratedConstraintCount:int,matchedRowCount:int,rowCount:int,baseEffectiveEstimatedCost:int,residualEvaluationPenalty:int,effectiveEstimatedCost:int,costClass:string,residualValueTape:list<array{rowid:int|null,fullkey:mixed,values:array<string,mixed>,matched:bool,residualValues:array<string,mixed>}>}
     */
    private static function jsonTableGeneratedHiddenResidualCostProfile141(array $generatedHidden): array
    {
        $constraints = $generatedHidden['generatedConstraints'];
        $usableSignatures = [];
        $residualSignatures = [];
        $residualNames = [];
        foreach ($constraints as $constraint) {
            $signature = $constraint['name'] . ':' . $constraint['source'] . ':' . $constraint['path'] . ':' . $constraint['operator'] . ':' . json_encode($constraint['value']);
            if ((bool) $constraint['usable']) {
                $usableSignatures[] = $signature;
            } else {
                $residualSignatures[] = $signature;
                $residualNames[] = $constraint['name'];
            }
        }

        $residualTape = [];
        foreach ($generatedHidden['generatedValueTape'] as $entry) {
            $residualValues = [];
            foreach ($residualNames as $name) {
                $residualValues[$name] = $entry['values'][$name] ?? null;
            }

            $residualTape[] = $entry + ['residualValues' => $residualValues];
        }

        $rowCount = (int) $generatedHidden['rowCount'];
        $matchedCount = (int) $generatedHidden['matchedRowCount'];
        $residualCount = count($residualSignatures);
        $baseCost = (int) $generatedHidden['effectiveEstimatedCost'];
        $residualPenalty = $residualCount === 0 || $baseCost >= 1000000
            ? 0
            : max(1, $matchedCount) * $residualCount;
        $effectiveCost = $baseCost >= 1000000 ? 1000000 : $baseCost + $residualPenalty;

        return [
            'generatedConstraintSignatures' => (array) $generatedHidden['generatedConstraintSignatures'],
            'usableGeneratedConstraintSignatures' => $usableSignatures,
            'residualGeneratedConstraintSignatures' => $residualSignatures,
            'residualGeneratedColumns' => $residualNames,
            'residualGeneratedConstraintCount' => $residualCount,
            'matchedRowCount' => $matchedCount,
            'rowCount' => $rowCount,
            'baseEffectiveEstimatedCost' => $baseCost,
            'residualEvaluationPenalty' => $residualPenalty,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableGeneratedHiddenResidualCostClass141(
                (string) $generatedHidden['costClass'],
                $residualCount,
                $matchedCount,
                $effectiveCost,
            ),
            'residualValueTape' => $residualTape,
        ];
    }

    private static function jsonTableGeneratedHiddenResidualCostClass141(string $baseCostClass, int $residualCount, int $matchedCount, int $effectiveCost): string
    {
        if ($baseCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($residualCount === 0) {
            return $baseCostClass;
        }
        if ($matchedCount === 0) {
            return 'json-table-generated-hidden-residual-empty';
        }
        if ($matchedCount === 1) {
            return 'json-table-generated-hidden-residual-point';
        }

        return $effectiveCost <= 12
            ? 'json-table-generated-hidden-residual-narrow-filter'
            : 'json-table-generated-hidden-residual-filter';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableGeneratedHiddenResidualCostTransitions141(array $current, array $next): array
    {
        return [
            [
                'field' => 'usableGeneratedConstraintSignatures',
                'current' => $current['usableGeneratedConstraintSignatures'],
                'next' => $next['usableGeneratedConstraintSignatures'],
                'changed' => $current['usableGeneratedConstraintSignatures'] !== $next['usableGeneratedConstraintSignatures'],
            ],
            [
                'field' => 'residualGeneratedConstraintSignatures',
                'current' => $current['residualGeneratedConstraintSignatures'],
                'next' => $next['residualGeneratedConstraintSignatures'],
                'changed' => $current['residualGeneratedConstraintSignatures'] !== $next['residualGeneratedConstraintSignatures'],
            ],
            [
                'field' => 'matchedRowCount',
                'current' => $current['matchedRowCount'],
                'next' => $next['matchedRowCount'],
                'changed' => $current['matchedRowCount'] !== $next['matchedRowCount'],
            ],
            [
                'field' => 'residualEvaluationPenalty',
                'current' => $current['residualEvaluationPenalty'],
                'next' => $next['residualEvaluationPenalty'],
                'changed' => $current['residualEvaluationPenalty'] !== $next['residualEvaluationPenalty'],
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
                'field' => 'residualValueTape',
                'current' => $current['residualValueTape'],
                'next' => $next['residualValueTape'],
                'changed' => $current['residualValueTape'] !== $next['residualValueTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableGeneratedHiddenResidualCostReplanReasons141(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'usableGeneratedConstraintSignatures' => 'json-table-generated-hidden-usable-constraint-changed',
                'residualGeneratedConstraintSignatures' => 'json-table-generated-hidden-residual-constraint-changed',
                'matchedRowCount' => 'json-table-generated-hidden-residual-row-count-changed',
                'residualEvaluationPenalty', 'effectiveEstimatedCost', 'costClass' => 'json-table-generated-hidden-residual-cost-changed',
                'residualValueTape' => 'json-table-generated-hidden-residual-values-changed',
                default => 'json-table-generated-hidden-residual-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $generatedHidden
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{root:string,rowidConstraintSignature:string|null,rowidConstraintColumn:string|null,rowidConstraintOperator:string|null,rowidConstraintValue:mixed,rowidScoped:bool,generatedMatchedRowCount:int,rowidMatchedRowCount:int,intersectedRowCount:int,intersectedRowids:list<int|null>,intersectedFullkeys:list<mixed>,firstIntersectedRowid:int|null,lastIntersectedRowid:int|null,generatedRowidTape:list<array{rowid:int|null,fullkey:mixed,generatedMatched:bool,rowidMatched:bool,matched:bool,values:array<string,mixed>}>,baseGeneratedCost:int,effectiveEstimatedCost:int,costClass:string}
     */
    private static function jsonTableGeneratedHiddenRowidCostProfile142(array $generatedHidden, array $constraints): array
    {
        $rowidConstraint = self::firstRowidConstraint133($constraints);
        $rowidOperator = isset($rowidConstraint['operator']) ? strtoupper((string) $rowidConstraint['operator']) : null;
        $rowidValue = $rowidConstraint['value'] ?? null;
        $rowidScoped = $rowidConstraint !== null
            && $rowidOperator === '='
            && self::rowidConstraintIntValue133($rowidValue) !== null;

        $tape = [];
        $rowidMatched = 0;
        $intersectedRowids = [];
        $intersectedFullkeys = [];
        foreach ($generatedHidden['generatedValueTape'] as $entry) {
            $rowid = is_int($entry['rowid'] ?? null) ? $entry['rowid'] : null;
            $row = [
                'id' => $rowid,
                'rowid' => $rowid,
                '_rowid_' => $rowid,
                'oid' => $rowid,
                'fullkey' => $entry['fullkey'] ?? null,
            ];
            $generatedMatched = ($entry['matched'] ?? false) === true;
            $matchedRowid = $rowidConstraint === null || self::rowMatchesResidualConstraints($row, [$rowidConstraint]);
            if ($matchedRowid) {
                $rowidMatched++;
            }
            $matched = $generatedMatched && $matchedRowid;
            if ($matched) {
                $intersectedRowids[] = $rowid;
                $intersectedFullkeys[] = $entry['fullkey'] ?? null;
            }

            $tape[] = [
                'rowid' => $rowid,
                'fullkey' => $entry['fullkey'] ?? null,
                'generatedMatched' => $generatedMatched,
                'rowidMatched' => $matchedRowid,
                'matched' => $matched,
                'values' => $entry['values'] ?? [],
            ];
        }

        $baseCost = (int) $generatedHidden['effectiveEstimatedCost'];
        $matchedCount = count($intersectedRowids);
        if ((string) $generatedHidden['costClass'] === 'unrunnable-json-table') {
            $effectiveCost = 1000000;
        } elseif ($matchedCount === 0) {
            $effectiveCost = 1;
        } elseif ($rowidScoped) {
            $effectiveCost = 1;
        } elseif ($rowidConstraint !== null) {
            $effectiveCost = min($baseCost, max(1, $matchedCount));
        } else {
            $effectiveCost = $baseCost;
        }

        return [
            'root' => (string) $generatedHidden['root'],
            'rowidConstraintSignature' => $rowidConstraint === null ? null : self::nestedPathRowidConstraintSignature133($rowidConstraint),
            'rowidConstraintColumn' => isset($rowidConstraint['column']) ? self::normalizeConstraintColumn((string) $rowidConstraint['column']) : null,
            'rowidConstraintOperator' => $rowidOperator,
            'rowidConstraintValue' => $rowidValue,
            'rowidScoped' => $rowidScoped,
            'generatedMatchedRowCount' => (int) $generatedHidden['matchedRowCount'],
            'rowidMatchedRowCount' => $rowidMatched,
            'intersectedRowCount' => $matchedCount,
            'intersectedRowids' => $intersectedRowids,
            'intersectedFullkeys' => $intersectedFullkeys,
            'firstIntersectedRowid' => $intersectedRowids[0] ?? null,
            'lastIntersectedRowid' => $intersectedRowids === [] ? null : $intersectedRowids[array_key_last($intersectedRowids)],
            'generatedRowidTape' => $tape,
            'baseGeneratedCost' => $baseCost,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableGeneratedHiddenRowidCostClass142((string) $generatedHidden['costClass'], $rowidConstraint !== null, $rowidScoped, $matchedCount, $effectiveCost),
        ];
    }

    private static function jsonTableGeneratedHiddenRowidCostClass142(
        string $generatedCostClass,
        bool $hasRowidConstraint,
        bool $rowidScoped,
        int $matchedCount,
        int $effectiveCost,
    ): string {
        if ($generatedCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if (!$hasRowidConstraint) {
            return 'json-table-generated-hidden-rowid-unconstrained';
        }
        if ($matchedCount === 0) {
            return 'json-table-generated-hidden-rowid-empty';
        }
        if ($rowidScoped && $matchedCount === 1) {
            return 'json-table-generated-hidden-rowid-point';
        }

        return $effectiveCost <= 4
            ? 'json-table-generated-hidden-rowid-narrow-intersection'
            : 'json-table-generated-hidden-rowid-intersection';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableGeneratedHiddenRowidCostTransitions142(array $current, array $next): array
    {
        return [
            ['field' => 'root', 'current' => $current['root'], 'next' => $next['root'], 'changed' => $current['root'] !== $next['root']],
            ['field' => 'rowidConstraintSignature', 'current' => $current['rowidConstraintSignature'], 'next' => $next['rowidConstraintSignature'], 'changed' => $current['rowidConstraintSignature'] !== $next['rowidConstraintSignature']],
            ['field' => 'generatedMatchedRowCount', 'current' => $current['generatedMatchedRowCount'], 'next' => $next['generatedMatchedRowCount'], 'changed' => $current['generatedMatchedRowCount'] !== $next['generatedMatchedRowCount']],
            ['field' => 'rowidMatchedRowCount', 'current' => $current['rowidMatchedRowCount'], 'next' => $next['rowidMatchedRowCount'], 'changed' => $current['rowidMatchedRowCount'] !== $next['rowidMatchedRowCount']],
            ['field' => 'intersectedRowids', 'current' => $current['intersectedRowids'], 'next' => $next['intersectedRowids'], 'changed' => $current['intersectedRowids'] !== $next['intersectedRowids']],
            ['field' => 'intersectedFullkeys', 'current' => $current['intersectedFullkeys'], 'next' => $next['intersectedFullkeys'], 'changed' => $current['intersectedFullkeys'] !== $next['intersectedFullkeys']],
            ['field' => 'effectiveEstimatedCost', 'current' => $current['effectiveEstimatedCost'], 'next' => $next['effectiveEstimatedCost'], 'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost']],
            ['field' => 'costClass', 'current' => $current['costClass'], 'next' => $next['costClass'], 'changed' => $current['costClass'] !== $next['costClass']],
            ['field' => 'generatedRowidTape', 'current' => $current['generatedRowidTape'], 'next' => $next['generatedRowidTape'], 'changed' => $current['generatedRowidTape'] !== $next['generatedRowidTape']],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableGeneratedHiddenRowidCostReplanReasons142(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'root' => 'json-table-generated-hidden-rowid-root-changed',
                'rowidConstraintSignature' => 'json-table-generated-hidden-rowid-constraint-changed',
                'generatedMatchedRowCount' => 'json-table-generated-hidden-rowid-generated-count-changed',
                'rowidMatchedRowCount' => 'json-table-generated-hidden-rowid-rowid-count-changed',
                'intersectedRowids', 'intersectedFullkeys' => 'json-table-generated-hidden-rowid-rowset-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-generated-hidden-rowid-cost-changed',
                'generatedRowidTape' => 'json-table-generated-hidden-rowid-tape-changed',
                default => 'json-table-generated-hidden-rowid-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $generatedHiddenRowid
     * @param list<string> $generatedOutputColumns
     * @return array{root:string,rowidConstraintSignature:string|null,rowidScoped:bool,generatedOutputColumns:list<string>,outputColumnCount:int,matchedRowCount:int,rowids:list<int|null>,fullkeys:list<mixed>,generatedRows:list<array{rowid:int|null,fullkey:mixed,matched:bool,values:array<string,mixed>,generatedFingerprint:string}>,firstRowid:int|null,lastRowid:int|null,generatedFingerprints:list<string>,combinedGeneratedFingerprint:string,effectiveEstimatedCost:int,costClass:string}
     */
    private static function jsonTableRowidHiddenGeneratedProfile149(array $generatedHiddenRowid, array $generatedOutputColumns): array
    {
        $availableColumns = [];
        foreach ($generatedHiddenRowid['generatedRowidTape'] as $entry) {
            foreach (array_keys(is_array($entry['values'] ?? null) ? $entry['values'] : []) as $column) {
                $availableColumns[(string) $column] = true;
            }
        }

        $columns = self::normalizeGeneratedOutputColumns149($generatedOutputColumns, array_keys($availableColumns));
        $rows = [];
        $rowids = [];
        $fullkeys = [];
        $fingerprints = [];
        foreach ($generatedHiddenRowid['generatedRowidTape'] as $entry) {
            if (($entry['matched'] ?? false) !== true) {
                continue;
            }

            $values = [];
            foreach ($columns as $column) {
                $values[$column] = is_array($entry['values'] ?? null) && array_key_exists($column, $entry['values'])
                    ? $entry['values'][$column]
                    : null;
            }

            $fingerprint = self::jsonTableGeneratedOutputFingerprint149($values);
            $rowid = is_int($entry['rowid'] ?? null) ? $entry['rowid'] : null;
            $rowids[] = $rowid;
            $fullkeys[] = $entry['fullkey'] ?? null;
            $fingerprints[] = $fingerprint;
            $rows[] = [
                'rowid' => $rowid,
                'fullkey' => $entry['fullkey'] ?? null,
                'matched' => true,
                'values' => $values,
                'generatedFingerprint' => $fingerprint,
            ];
        }

        $matchedCount = count($rows);
        $effectiveCost = (int) $generatedHiddenRowid['effectiveEstimatedCost'];

        return [
            'root' => (string) $generatedHiddenRowid['root'],
            'rowidConstraintSignature' => $generatedHiddenRowid['rowidConstraintSignature'],
            'rowidScoped' => (bool) $generatedHiddenRowid['rowidScoped'],
            'generatedOutputColumns' => $columns,
            'outputColumnCount' => count($columns),
            'matchedRowCount' => $matchedCount,
            'rowids' => $rowids,
            'fullkeys' => $fullkeys,
            'generatedRows' => $rows,
            'firstRowid' => $rowids[0] ?? null,
            'lastRowid' => $rowids === [] ? null : $rowids[array_key_last($rowids)],
            'generatedFingerprints' => $fingerprints,
            'combinedGeneratedFingerprint' => hash('sha256', json_encode($fingerprints, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableRowidHiddenGeneratedCostClass149(
                (string) $generatedHiddenRowid['costClass'],
                (bool) $generatedHiddenRowid['rowidScoped'],
                $matchedCount,
                count($columns),
            ),
        ];
    }

    /**
     * @param list<string> $requested
     * @param list<string> $available
     * @return list<string>
     */
    private static function normalizeGeneratedOutputColumns149(array $requested, array $available): array
    {
        $columns = $requested === [] ? $available : $requested;
        $normalized = [];
        foreach ($columns as $column) {
            $column = trim((string) $column);
            if ($column === '') {
                throw new \InvalidArgumentException('SQLite JSON table generated output column must not be empty');
            }
            $normalized[$column] = $column;
        }

        ksort($normalized);

        return array_values($normalized);
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function jsonTableGeneratedOutputFingerprint149(array $values): string
    {
        return hash('sha256', json_encode($values, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function jsonTableRowidHiddenGeneratedCostClass149(
        string $baseCostClass,
        bool $rowidScoped,
        int $matchedCount,
        int $outputColumnCount,
    ): string {
        if ($baseCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($matchedCount === 0) {
            return 'json-table-rowid-hidden-generated-empty';
        }
        if ($rowidScoped && $matchedCount === 1) {
            return $outputColumnCount <= 1
                ? 'json-table-rowid-hidden-generated-point'
                : 'json-table-rowid-hidden-generated-covering-point';
        }

        return 'json-table-rowid-hidden-generated-scan';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableRowidHiddenGeneratedTransitions149(array $current, array $next): array
    {
        return [
            ['field' => 'root', 'current' => $current['root'], 'next' => $next['root'], 'changed' => $current['root'] !== $next['root']],
            ['field' => 'rowidConstraintSignature', 'current' => $current['rowidConstraintSignature'], 'next' => $next['rowidConstraintSignature'], 'changed' => $current['rowidConstraintSignature'] !== $next['rowidConstraintSignature']],
            ['field' => 'generatedOutputColumns', 'current' => $current['generatedOutputColumns'], 'next' => $next['generatedOutputColumns'], 'changed' => $current['generatedOutputColumns'] !== $next['generatedOutputColumns']],
            ['field' => 'matchedRowCount', 'current' => $current['matchedRowCount'], 'next' => $next['matchedRowCount'], 'changed' => $current['matchedRowCount'] !== $next['matchedRowCount']],
            ['field' => 'rowids', 'current' => $current['rowids'], 'next' => $next['rowids'], 'changed' => $current['rowids'] !== $next['rowids']],
            ['field' => 'fullkeys', 'current' => $current['fullkeys'], 'next' => $next['fullkeys'], 'changed' => $current['fullkeys'] !== $next['fullkeys']],
            ['field' => 'generatedFingerprints', 'current' => $current['generatedFingerprints'], 'next' => $next['generatedFingerprints'], 'changed' => $current['generatedFingerprints'] !== $next['generatedFingerprints']],
            ['field' => 'effectiveEstimatedCost', 'current' => $current['effectiveEstimatedCost'], 'next' => $next['effectiveEstimatedCost'], 'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost']],
            ['field' => 'costClass', 'current' => $current['costClass'], 'next' => $next['costClass'], 'changed' => $current['costClass'] !== $next['costClass']],
            ['field' => 'generatedRows', 'current' => $current['generatedRows'], 'next' => $next['generatedRows'], 'changed' => $current['generatedRows'] !== $next['generatedRows']],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableRowidHiddenGeneratedReplanReasons149(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'root' => 'json-table-rowid-hidden-generated-root-changed',
                'rowidConstraintSignature' => 'json-table-rowid-hidden-generated-rowid-constraint-changed',
                'generatedOutputColumns' => 'json-table-rowid-hidden-generated-output-columns-changed',
                'matchedRowCount' => 'json-table-rowid-hidden-generated-row-count-changed',
                'rowids', 'fullkeys' => 'json-table-rowid-hidden-generated-rowset-changed',
                'generatedFingerprints', 'generatedRows' => 'json-table-rowid-hidden-generated-values-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-rowid-hidden-generated-cost-changed',
                default => 'json-table-rowid-hidden-generated-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $nestedPath
     * @param array<string,mixed> $residualCost
     * @return array{baseRoot:string,generatedPath:string,composedRoot:string,mode:string,jsonSourceKind:string,jsonSourceFingerprint:string,rootFingerprint:string,generatedPathFingerprint:string,matchedRowCount:int,rowCount:int,matchedFullkeys:list<mixed>,residualColumns:list<string>,residualValueTape:list<array{rowid:int|null,fullkey:mixed,matched:bool,residualValues:array<string,mixed>}>,effectiveEstimatedCost:int,costClass:string,pathStableKey:string}
     */
    private static function jsonTableGeneratedHiddenPathProfile144(
        array $source,
        string $jsonColumn,
        string $baseRootColumn,
        string $generatedPathColumn,
        array $nestedPath,
        array $residualCost,
    ): array {
        if (!array_key_exists($jsonColumn, $source)) {
            throw new \InvalidArgumentException("SQLite JSON table generated hidden path source is missing {$jsonColumn}");
        }
        if (!array_key_exists($baseRootColumn, $source)) {
            throw new \InvalidArgumentException("SQLite JSON table generated hidden path source is missing {$baseRootColumn}");
        }
        if (!array_key_exists($generatedPathColumn, $source)) {
            throw new \InvalidArgumentException("SQLite JSON table generated hidden path source is missing {$generatedPathColumn}");
        }

        $baseRoot = (string) $nestedPath['baseRoot'];
        $generatedPath = (string) $nestedPath['nestedPath'];
        $composedRoot = (string) $nestedPath['root'];
        $jsonValue = $source[$jsonColumn];
        $jsonKind = self::validateJsonInput($jsonValue)['jsonInputKind'];
        $matchedFullkeys = [];
        $residualTape = [];
        foreach ($residualCost['residualValueTape'] as $entry) {
            if (($entry['matched'] ?? false) === true) {
                $matchedFullkeys[] = $entry['fullkey'] ?? null;
            }

            $residualTape[] = [
                'rowid' => isset($entry['rowid']) ? (int) $entry['rowid'] : null,
                'fullkey' => $entry['fullkey'] ?? null,
                'matched' => (bool) ($entry['matched'] ?? false),
                'residualValues' => $entry['residualValues'] ?? [],
            ];
        }

        return [
            'baseRoot' => $baseRoot,
            'generatedPath' => $generatedPath,
            'composedRoot' => $composedRoot,
            'mode' => (string) $nestedPath['mode'],
            'jsonSourceKind' => $jsonKind,
            'jsonSourceFingerprint' => self::jsonTableSourceValueFingerprint144($jsonValue),
            'rootFingerprint' => hash('sha256', $composedRoot),
            'generatedPathFingerprint' => hash('sha256', $generatedPath),
            'matchedRowCount' => (int) $residualCost['matchedRowCount'],
            'rowCount' => (int) $residualCost['rowCount'],
            'matchedFullkeys' => $matchedFullkeys,
            'residualColumns' => (array) $residualCost['residualGeneratedColumns'],
            'residualValueTape' => $residualTape,
            'effectiveEstimatedCost' => (int) $residualCost['effectiveEstimatedCost'],
            'costClass' => self::jsonTableGeneratedHiddenPathCostClass144(
                (string) $residualCost['costClass'],
                $composedRoot,
                (int) $residualCost['matchedRowCount'],
            ),
            'pathStableKey' => $baseRoot . '|' . $generatedPath . '|' . $composedRoot,
        ];
    }

    private static function jsonTableSourceValueFingerprint144(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . hash('sha256', $value->bytes);
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return 'json-subtype:' . hash('sha256', $value->json);
        }
        if ($value === null) {
            return 'sql-null';
        }
        if (is_scalar($value)) {
            return get_debug_type($value) . ':' . hash('sha256', (string) $value);
        }

        return get_debug_type($value) . ':' . hash('sha256', json_encode($value));
    }

    private static function jsonTableGeneratedHiddenPathCostClass144(string $baseCostClass, string $composedRoot, int $matchedCount): string
    {
        if ($baseCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($matchedCount === 0) {
            return 'json-table-generated-hidden-path-empty';
        }
        if ($composedRoot !== '$') {
            return $matchedCount === 1
                ? 'json-table-generated-hidden-path-point'
                : 'json-table-generated-hidden-path-subtree';
        }

        return 'json-table-generated-hidden-path-root-scan';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableGeneratedHiddenPathTransitions144(array $current, array $next): array
    {
        return [
            [
                'field' => 'pathStableKey',
                'current' => $current['pathStableKey'],
                'next' => $next['pathStableKey'],
                'changed' => $current['pathStableKey'] !== $next['pathStableKey'],
            ],
            [
                'field' => 'jsonSourceKind',
                'current' => $current['jsonSourceKind'],
                'next' => $next['jsonSourceKind'],
                'changed' => $current['jsonSourceKind'] !== $next['jsonSourceKind'],
            ],
            [
                'field' => 'jsonSourceFingerprint',
                'current' => $current['jsonSourceFingerprint'],
                'next' => $next['jsonSourceFingerprint'],
                'changed' => $current['jsonSourceFingerprint'] !== $next['jsonSourceFingerprint'],
            ],
            [
                'field' => 'matchedFullkeys',
                'current' => $current['matchedFullkeys'],
                'next' => $next['matchedFullkeys'],
                'changed' => $current['matchedFullkeys'] !== $next['matchedFullkeys'],
            ],
            [
                'field' => 'residualValueTape',
                'current' => $current['residualValueTape'],
                'next' => $next['residualValueTape'],
                'changed' => $current['residualValueTape'] !== $next['residualValueTape'],
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
    private static function jsonTableGeneratedHiddenPathReplanReasons144(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'pathStableKey' => 'json-table-generated-hidden-path-root-changed',
                'jsonSourceKind' => 'json-table-generated-hidden-path-source-kind-changed',
                'jsonSourceFingerprint' => 'json-table-generated-hidden-path-source-changed',
                'matchedFullkeys' => 'json-table-generated-hidden-path-rowset-changed',
                'residualValueTape' => 'json-table-generated-hidden-path-values-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-generated-hidden-path-cost-changed',
                default => 'json-table-generated-hidden-path-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $generatedPath
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{generatedPath:string|null,rowidConstraintSignature:string|null,rowidConstraintColumn:string|null,rowidConstraintOperator:string|null,rowidConstraintValue:mixed,rowidScoped:bool,pathMatchedRowCount:int,rowidMatchedRowCount:int,intersectedRowCount:int,intersectedRowids:list<int|null>,intersectedPaths:list<string|null>,firstIntersectedRowid:int|null,lastIntersectedRowid:int|null,generatedPathRowidTape:list<array{path:string|null,rowid:int|null,pathMatched:bool,rowidMatched:bool,matched:bool}>,baseGeneratedPathCost:int,effectiveEstimatedCost:int,costClass:string}
     */
    private static function jsonTableGeneratedPathRowidCostProfile145(array $generatedPath, array $constraints): array
    {
        $rowidConstraint = self::firstRowidConstraint133($constraints);
        $rowidOperator = isset($rowidConstraint['operator']) ? strtoupper((string) $rowidConstraint['operator']) : null;
        $rowidValue = $rowidConstraint['value'] ?? null;
        $rowidScoped = $rowidConstraint !== null
            && $rowidOperator === '='
            && self::rowidConstraintIntValue133($rowidValue) !== null;

        $tape = [];
        $rowidMatched = 0;
        $intersectedRowids = [];
        $intersectedPaths = [];
        foreach ($generatedPath['coveredPathTape'] as $entry) {
            $rowid = is_int($entry['rowid'] ?? null) ? $entry['rowid'] : null;
            $path = is_string($entry['path'] ?? null) ? $entry['path'] : null;
            $row = [
                'id' => $rowid,
                'rowid' => $rowid,
                '_rowid_' => $rowid,
                'oid' => $rowid,
                'path' => $path,
            ];
            $matchedRowid = $rowidConstraint === null || self::rowMatchesResidualConstraints($row, [$rowidConstraint]);
            if ($matchedRowid) {
                $rowidMatched++;
            }
            $matched = $matchedRowid;
            if ($matched) {
                $intersectedRowids[] = $rowid;
                $intersectedPaths[] = $path;
            }

            $tape[] = [
                'path' => $path,
                'rowid' => $rowid,
                'pathMatched' => true,
                'rowidMatched' => $matchedRowid,
                'matched' => $matched,
            ];
        }

        $baseCost = (int) $generatedPath['generatedEstimatedCost'];
        $matchedCount = count($intersectedRowids);
        if ((string) $generatedPath['costClass'] === 'unrunnable-json-table') {
            $effectiveCost = 1000000;
        } elseif ($rowidConstraint !== null && $matchedCount === 0) {
            $effectiveCost = 1;
        } elseif ($rowidScoped && $matchedCount === 1) {
            $effectiveCost = 1;
        } elseif ($rowidConstraint !== null) {
            $effectiveCost = min($baseCost, max(1, $matchedCount));
        } else {
            $effectiveCost = $baseCost;
        }

        return [
            'generatedPath' => $generatedPath['generatedPath'],
            'rowidConstraintSignature' => $rowidConstraint === null ? null : self::nestedPathRowidConstraintSignature133($rowidConstraint),
            'rowidConstraintColumn' => isset($rowidConstraint['column']) ? self::normalizeConstraintColumn((string) $rowidConstraint['column']) : null,
            'rowidConstraintOperator' => $rowidOperator,
            'rowidConstraintValue' => $rowidValue,
            'rowidScoped' => $rowidScoped,
            'pathMatchedRowCount' => (int) $generatedPath['generatedEstimatedRows'],
            'rowidMatchedRowCount' => $rowidMatched,
            'intersectedRowCount' => $matchedCount,
            'intersectedRowids' => $intersectedRowids,
            'intersectedPaths' => $intersectedPaths,
            'firstIntersectedRowid' => $intersectedRowids[0] ?? null,
            'lastIntersectedRowid' => $intersectedRowids === [] ? null : $intersectedRowids[array_key_last($intersectedRowids)],
            'generatedPathRowidTape' => $tape,
            'baseGeneratedPathCost' => $baseCost,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableGeneratedPathRowidCostClass145((string) $generatedPath['costClass'], $rowidConstraint !== null, $rowidScoped, $matchedCount, $effectiveCost),
        ];
    }

    private static function jsonTableGeneratedPathRowidCostClass145(
        string $generatedPathCostClass,
        bool $hasRowidConstraint,
        bool $rowidScoped,
        int $matchedCount,
        int $effectiveCost,
    ): string {
        if ($generatedPathCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if (!$hasRowidConstraint) {
            return 'json-table-generated-path-rowid-unconstrained';
        }
        if ($matchedCount === 0) {
            return 'json-table-generated-path-rowid-empty';
        }
        if ($rowidScoped && $matchedCount === 1) {
            return 'json-table-generated-path-rowid-point';
        }

        return $effectiveCost <= 4
            ? 'json-table-generated-path-rowid-narrow-intersection'
            : 'json-table-generated-path-rowid-intersection';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableGeneratedPathRowidCostTransitions145(array $current, array $next): array
    {
        return [
            ['field' => 'generatedPath', 'current' => $current['generatedPath'], 'next' => $next['generatedPath'], 'changed' => $current['generatedPath'] !== $next['generatedPath']],
            ['field' => 'rowidConstraintSignature', 'current' => $current['rowidConstraintSignature'], 'next' => $next['rowidConstraintSignature'], 'changed' => $current['rowidConstraintSignature'] !== $next['rowidConstraintSignature']],
            ['field' => 'pathMatchedRowCount', 'current' => $current['pathMatchedRowCount'], 'next' => $next['pathMatchedRowCount'], 'changed' => $current['pathMatchedRowCount'] !== $next['pathMatchedRowCount']],
            ['field' => 'rowidMatchedRowCount', 'current' => $current['rowidMatchedRowCount'], 'next' => $next['rowidMatchedRowCount'], 'changed' => $current['rowidMatchedRowCount'] !== $next['rowidMatchedRowCount']],
            ['field' => 'intersectedRowids', 'current' => $current['intersectedRowids'], 'next' => $next['intersectedRowids'], 'changed' => $current['intersectedRowids'] !== $next['intersectedRowids']],
            ['field' => 'intersectedPaths', 'current' => $current['intersectedPaths'], 'next' => $next['intersectedPaths'], 'changed' => $current['intersectedPaths'] !== $next['intersectedPaths']],
            ['field' => 'effectiveEstimatedCost', 'current' => $current['effectiveEstimatedCost'], 'next' => $next['effectiveEstimatedCost'], 'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost']],
            ['field' => 'costClass', 'current' => $current['costClass'], 'next' => $next['costClass'], 'changed' => $current['costClass'] !== $next['costClass']],
            ['field' => 'generatedPathRowidTape', 'current' => $current['generatedPathRowidTape'], 'next' => $next['generatedPathRowidTape'], 'changed' => $current['generatedPathRowidTape'] !== $next['generatedPathRowidTape']],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableGeneratedPathRowidCostReplanReasons145(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'generatedPath' => 'json-table-generated-path-rowid-source-changed',
                'rowidConstraintSignature' => 'json-table-generated-path-rowid-constraint-changed',
                'pathMatchedRowCount' => 'json-table-generated-path-rowid-path-count-changed',
                'rowidMatchedRowCount' => 'json-table-generated-path-rowid-rowid-count-changed',
                'intersectedRowids', 'intersectedPaths' => 'json-table-generated-path-rowid-rowset-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-generated-path-rowid-cost-changed',
                'generatedPathRowidTape' => 'json-table-generated-path-rowid-tape-changed',
                default => 'json-table-generated-path-rowid-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $generatedRowid
     * @param list<array{name:string,source?:string,path:string,direction?:string}> $generatedOrder
     * @return array{root:string,rowidConstraintSignature:string|null,generatedOrderBy:list<array{name:string,source:string,path:string,direction:string}>,intersectedRowCount:int,orderedRowids:list<int|null>,orderedFullkeys:list<mixed>,orderedGeneratedKeys:list<list<mixed>>,generatedRowidOrderTape:list<array{rowid:int|null,fullkey:mixed,key:list<mixed>,matched:bool}>,firstOrderedRowid:int|null,lastOrderedRowid:int|null,rowidEffectiveCost:int,generatedSortPenalty:int,effectiveEstimatedCost:int,requiresGeneratedSorter:bool,costClass:string}
     */
    private static function jsonTableGeneratedRowidOrderProfile147(array $generatedRowid, array $generatedOrder): array
    {
        $terms = self::normalizeGeneratedOrderTerms132($generatedOrder);
        $entries = [];
        foreach ($generatedRowid['generatedRowidTape'] as $entry) {
            if (($entry['matched'] ?? false) !== true) {
                continue;
            }

            $key = [];
            foreach ($terms as $term) {
                $key[] = $entry['values'][$term['name']] ?? null;
            }

            $entries[] = [
                'rowid' => is_int($entry['rowid'] ?? null) ? $entry['rowid'] : null,
                'fullkey' => $entry['fullkey'] ?? null,
                'key' => $key,
                'matched' => true,
            ];
        }

        usort($entries, static fn (array $left, array $right): int => self::compareGeneratedOrderEntries132($left, $right, $terms));

        $rowCount = count($entries);
        $requiresSorter = $rowCount > 1;
        $sortPenalty = $requiresSorter ? self::jsonTableSortPenalty113($rowCount, $terms) : 0;
        $rowidCost = (int) $generatedRowid['effectiveEstimatedCost'];
        $effectiveCost = $rowidCost >= 1000000 ? 1000000 : $rowidCost + $sortPenalty;

        return [
            'root' => (string) $generatedRowid['root'],
            'rowidConstraintSignature' => $generatedRowid['rowidConstraintSignature'],
            'generatedOrderBy' => $terms,
            'intersectedRowCount' => $rowCount,
            'orderedRowids' => array_values(array_map(static fn (array $entry): ?int => $entry['rowid'], $entries)),
            'orderedFullkeys' => array_values(array_map(static fn (array $entry): mixed => $entry['fullkey'], $entries)),
            'orderedGeneratedKeys' => array_values(array_map(static fn (array $entry): array => $entry['key'], $entries)),
            'generatedRowidOrderTape' => $entries,
            'firstOrderedRowid' => $entries[0]['rowid'] ?? null,
            'lastOrderedRowid' => $entries === [] ? null : $entries[array_key_last($entries)]['rowid'],
            'rowidEffectiveCost' => $rowidCost,
            'generatedSortPenalty' => $sortPenalty,
            'effectiveEstimatedCost' => $effectiveCost,
            'requiresGeneratedSorter' => $requiresSorter,
            'costClass' => self::jsonTableGeneratedRowidOrderCostClass147(
                (string) $generatedRowid['costClass'],
                $rowCount,
                $requiresSorter,
                $effectiveCost,
            ),
        ];
    }

    private static function jsonTableGeneratedRowidOrderCostClass147(
        string $rowidCostClass,
        int $rowCount,
        bool $requiresSorter,
        int $effectiveCost,
    ): string {
        if ($rowidCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($rowCount === 0) {
            return 'json-table-generated-rowid-order-empty';
        }
        if (!$requiresSorter) {
            return str_contains($rowidCostClass, 'point')
                ? 'json-table-generated-rowid-order-point'
                : 'json-table-generated-rowid-order-single';
        }

        return $effectiveCost <= 12
            ? 'json-table-generated-rowid-order-narrow-sort'
            : 'json-table-generated-rowid-order-sort';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableGeneratedRowidOrderTransitions147(array $current, array $next): array
    {
        return [
            ['field' => 'root', 'current' => $current['root'], 'next' => $next['root'], 'changed' => $current['root'] !== $next['root']],
            ['field' => 'rowidConstraintSignature', 'current' => $current['rowidConstraintSignature'], 'next' => $next['rowidConstraintSignature'], 'changed' => $current['rowidConstraintSignature'] !== $next['rowidConstraintSignature']],
            ['field' => 'generatedOrderBy', 'current' => $current['generatedOrderBy'], 'next' => $next['generatedOrderBy'], 'changed' => $current['generatedOrderBy'] !== $next['generatedOrderBy']],
            ['field' => 'intersectedRowCount', 'current' => $current['intersectedRowCount'], 'next' => $next['intersectedRowCount'], 'changed' => $current['intersectedRowCount'] !== $next['intersectedRowCount']],
            ['field' => 'orderedRowids', 'current' => $current['orderedRowids'], 'next' => $next['orderedRowids'], 'changed' => $current['orderedRowids'] !== $next['orderedRowids']],
            ['field' => 'orderedGeneratedKeys', 'current' => $current['orderedGeneratedKeys'], 'next' => $next['orderedGeneratedKeys'], 'changed' => $current['orderedGeneratedKeys'] !== $next['orderedGeneratedKeys']],
            ['field' => 'requiresGeneratedSorter', 'current' => $current['requiresGeneratedSorter'], 'next' => $next['requiresGeneratedSorter'], 'changed' => $current['requiresGeneratedSorter'] !== $next['requiresGeneratedSorter']],
            ['field' => 'effectiveEstimatedCost', 'current' => $current['effectiveEstimatedCost'], 'next' => $next['effectiveEstimatedCost'], 'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost']],
            ['field' => 'costClass', 'current' => $current['costClass'], 'next' => $next['costClass'], 'changed' => $current['costClass'] !== $next['costClass']],
            ['field' => 'generatedRowidOrderTape', 'current' => $current['generatedRowidOrderTape'], 'next' => $next['generatedRowidOrderTape'], 'changed' => $current['generatedRowidOrderTape'] !== $next['generatedRowidOrderTape']],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableGeneratedRowidOrderReplanReasons147(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'root' => 'json-table-generated-rowid-order-root-changed',
                'rowidConstraintSignature' => 'json-table-generated-rowid-order-rowid-constraint-changed',
                'generatedOrderBy' => 'json-table-generated-rowid-order-terms-changed',
                'intersectedRowCount' => 'json-table-generated-rowid-order-row-count-changed',
                'orderedRowids' => 'json-table-generated-rowid-order-output-changed',
                'orderedGeneratedKeys' => 'json-table-generated-rowid-order-keys-changed',
                'requiresGeneratedSorter' => 'json-table-generated-rowid-order-sorter-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-generated-rowid-order-cost-changed',
                'generatedRowidOrderTape' => 'json-table-generated-rowid-order-tape-changed',
                default => 'json-table-generated-rowid-order-state-changed',
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
     * @param array<string,mixed> $plan
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return array{orderBy:list<array{column:string,direction:string}>,rowidTieBreakColumns:list<string>,rowidTieBreakConsumed:bool,orderByConsumed:bool,requiresSorter:bool,baseEstimatedCost:int,sortPenalty:int,effectiveEstimatedCost:int,costClass:string,orderedRowids:list<int>,orderKeyTape:list<array{rowid:int,orderKey:list<mixed>,fullkey:string}>,firstOrderKey:list<mixed>|null,lastOrderKey:list<mixed>|null}
     */
    private static function jsonTableHiddenRowidOrderProfile135(array $plan, array $orderBy): array
    {
        $normalizedOrderBy = self::normalizeOrderByTerms113($orderBy);
        $rows = $plan['rows'];
        if ($normalizedOrderBy !== []) {
            usort($rows, static fn (array $left, array $right): int => self::compareRowsForOrderBy($left, $right, $normalizedOrderBy));
        }

        $rowCount = count($rows);
        $rowidTieBreakColumns = self::rowidTieBreakColumns135($normalizedOrderBy);
        $rowidTieBreakConsumed = $rowidTieBreakColumns !== [] && self::rowidTieBreakIsStreaming135($normalizedOrderBy);
        $orderByConsumed = (bool) $plan['orderByConsumed'];
        $requiresSorter = $normalizedOrderBy !== [] && !$orderByConsumed && $rowCount > 1;
        $sortPenalty = $requiresSorter ? self::jsonTableSortPenalty113($rowCount, $normalizedOrderBy) : 0;
        $baseCost = (int) $plan['estimatedCost'];
        $effectiveCost = $baseCost >= 1000000 ? $baseCost : $baseCost + $sortPenalty;
        $orderKeyTape = self::hiddenRowidOrderKeyTape135($rows, $normalizedOrderBy);

        return [
            'orderBy' => $normalizedOrderBy,
            'rowidTieBreakColumns' => $rowidTieBreakColumns,
            'rowidTieBreakConsumed' => $rowidTieBreakConsumed,
            'orderByConsumed' => $orderByConsumed,
            'requiresSorter' => $requiresSorter,
            'baseEstimatedCost' => $baseCost,
            'sortPenalty' => $sortPenalty,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableHiddenRowidOrderCostClass135($plan, $normalizedOrderBy, $rowidTieBreakConsumed, $requiresSorter),
            'orderedRowids' => array_map(static fn (array $row): int => (int) $row['id'], $rows),
            'orderKeyTape' => $orderKeyTape,
            'firstOrderKey' => $orderKeyTape[0]['orderKey'] ?? null,
            'lastOrderKey' => $orderKeyTape === [] ? null : $orderKeyTape[count($orderKeyTape) - 1]['orderKey'],
        ];
    }

    /**
     * @param list<array{column:string,direction:string}> $orderBy
     * @return list<string>
     */
    private static function rowidTieBreakColumns135(array $orderBy): array
    {
        $columns = [];
        foreach ($orderBy as $term) {
            if ((string) $term['column'] === 'id' || self::isRowIdAlias((string) $term['column'])) {
                $columns[] = (string) $term['column'];
            }
        }

        return $columns;
    }

    /**
     * @param list<array{column:string,direction:string}> $orderBy
     */
    private static function rowidTieBreakIsStreaming135(array $orderBy): bool
    {
        $count = count($orderBy);
        for ($index = 0; $index < $count; $index++) {
            $term = $orderBy[$index];
            if ((string) $term['column'] !== 'id' && !self::isRowIdAlias((string) $term['column'])) {
                continue;
            }

            return $term['direction'] === 'ASC' && $index === $count - 1;
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{column:string,direction:string}> $orderBy
     * @return list<array{rowid:int,orderKey:list<mixed>,fullkey:string}>
     */
    private static function hiddenRowidOrderKeyTape135(array $rows, array $orderBy): array
    {
        $tape = [];
        foreach ($rows as $row) {
            $key = [];
            foreach ($orderBy as $term) {
                $key[] = self::rowColumnValue($row, (string) $term['column']);
            }
            $tape[] = [
                'rowid' => (int) $row['id'],
                'orderKey' => $key,
                'fullkey' => (string) ($row['fullkey'] ?? ''),
            ];
        }

        return $tape;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array{column:string,direction:string}> $orderBy
     */
    private static function jsonTableHiddenRowidOrderCostClass135(
        array $plan,
        array $orderBy,
        bool $rowidTieBreakConsumed,
        bool $requiresSorter,
    ): string {
        if (!$plan['runnable']) {
            return 'unrunnable-json-table';
        }
        if ($orderBy === []) {
            return 'json-table-hidden-rowid-natural-order';
        }
        if ((bool) $plan['orderByConsumed']) {
            return 'json-table-hidden-rowid-order-consumed';
        }
        if ($rowidTieBreakConsumed && $requiresSorter) {
            return 'json-table-hidden-rowid-tiebreak-sort';
        }
        if ($rowidTieBreakConsumed) {
            return 'json-table-hidden-rowid-tiebreak-streaming';
        }

        return $requiresSorter
            ? 'json-table-hidden-rowid-order-sort'
            : 'json-table-hidden-rowid-narrow-order';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableHiddenRowidOrderTransitions135(array $current, array $next): array
    {
        return [
            [
                'field' => 'orderBy',
                'current' => $current['orderBy'],
                'next' => $next['orderBy'],
                'changed' => $current['orderBy'] !== $next['orderBy'],
            ],
            [
                'field' => 'rowidTieBreakColumns',
                'current' => $current['rowidTieBreakColumns'],
                'next' => $next['rowidTieBreakColumns'],
                'changed' => $current['rowidTieBreakColumns'] !== $next['rowidTieBreakColumns'],
            ],
            [
                'field' => 'rowidTieBreakConsumed',
                'current' => $current['rowidTieBreakConsumed'],
                'next' => $next['rowidTieBreakConsumed'],
                'changed' => $current['rowidTieBreakConsumed'] !== $next['rowidTieBreakConsumed'],
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
                'field' => 'orderedRowids',
                'current' => $current['orderedRowids'],
                'next' => $next['orderedRowids'],
                'changed' => $current['orderedRowids'] !== $next['orderedRowids'],
            ],
            [
                'field' => 'orderKeyTape',
                'current' => $current['orderKeyTape'],
                'next' => $next['orderKeyTape'],
                'changed' => $current['orderKeyTape'] !== $next['orderKeyTape'],
            ],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableHiddenRowidOrderReplanReasons135(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'orderBy' => 'json-table-hidden-rowid-orderby-changed',
                'rowidTieBreakColumns', 'rowidTieBreakConsumed' => 'json-table-hidden-rowid-tiebreak-changed',
                'requiresSorter' => 'json-table-hidden-rowid-sorter-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-hidden-rowid-order-cost-changed',
                'orderedRowids' => 'json-table-hidden-rowid-output-order-changed',
                'orderKeyTape' => 'json-table-hidden-rowid-order-key-tape-changed',
                default => 'json-table-hidden-rowid-order-state-changed',
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
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $pathRowid
     * @return array{seekSignature:string|null,pathValue:mixed,rowidValue:mixed,pointSeekable:bool,matched:bool,missingRowid:bool,sourceKind:string,rowCount:int,matchedRowid:int|null,matchedPath:string|null,matchedFullkey:string|null,matchedKey:mixed,matchedType:string|null,matchedAtom:mixed,matchedValue:mixed,matchedValueFingerprint:string|null,seekTape:list<array{path:string|null,rowid:int|null,fullkey:string|null,type:string|null,key:mixed,matched:bool}>,effectiveEstimatedCost:int,costClass:string}
     */
    private static function jsonTableHiddenPathRowidCurrentSourceProfile140(array $plan, array $pathRowid): array
    {
        $pathConstraint = self::pointConstraintValue140($plan['used'] ?? [], 'path');
        $rowidConstraint = self::pointConstraintValue140($plan['used'] ?? [], 'id');
        $pointSeekable = $pathConstraint['usable'] && $rowidConstraint['usable'];
        $rowidValue = $rowidConstraint['value'];
        $pathValue = $pathConstraint['value'];
        $seekTape = [];
        $matchedRow = null;

        foreach ($plan['rows'] ?? [] as $row) {
            $rowPath = isset($row['path']) && is_string($row['path']) ? $row['path'] : null;
            $rowid = isset($row['id']) ? (int) $row['id'] : null;
            $matched = $pointSeekable && $rowPath === $pathValue && $rowid === $rowidValue;
            if ($matched && $matchedRow === null) {
                $matchedRow = $row;
            }
            $seekTape[] = [
                'path' => $rowPath,
                'rowid' => $rowid,
                'fullkey' => isset($row['fullkey']) && is_string($row['fullkey']) ? $row['fullkey'] : null,
                'type' => isset($row['type']) && is_string($row['type']) ? $row['type'] : null,
                'key' => $row['key'] ?? null,
                'matched' => $matched,
            ];
        }

        $matched = $matchedRow !== null;
        $effectiveCost = $pointSeekable
            ? ($matched ? max(1, min(2, (int) ($pathRowid['effectiveEstimatedCost'] ?? 1000000))) : 3)
            : (int) ($pathRowid['effectiveEstimatedCost'] ?? 1000000);

        return [
            'seekSignature' => $pointSeekable ? (string) ($pathRowid['compositeSignature'] ?? null) : null,
            'pathValue' => $pathValue,
            'rowidValue' => $rowidValue,
            'pointSeekable' => $pointSeekable,
            'matched' => $matched,
            'missingRowid' => $pointSeekable && !$matched,
            'sourceKind' => (string) ($plan['jsonInputKind'] ?? 'missing'),
            'rowCount' => count($seekTape),
            'matchedRowid' => $matchedRow !== null && isset($matchedRow['id']) ? (int) $matchedRow['id'] : null,
            'matchedPath' => $matchedRow !== null && isset($matchedRow['path']) && is_string($matchedRow['path']) ? $matchedRow['path'] : null,
            'matchedFullkey' => $matchedRow !== null && isset($matchedRow['fullkey']) && is_string($matchedRow['fullkey']) ? $matchedRow['fullkey'] : null,
            'matchedKey' => $matchedRow['key'] ?? null,
            'matchedType' => $matchedRow !== null && isset($matchedRow['type']) && is_string($matchedRow['type']) ? $matchedRow['type'] : null,
            'matchedAtom' => $matchedRow['atom'] ?? null,
            'matchedValue' => $matchedRow['value'] ?? null,
            'matchedValueFingerprint' => $matchedRow === null ? null : self::jsonTableStableValueToken140($matchedRow['value'] ?? null),
            'seekTape' => $seekTape,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableHiddenPathRowidCurrentSourceCostClass140(
                (bool) ($plan['runnable'] ?? false),
                $pointSeekable,
                $matched,
                (string) ($pathRowid['scanStrategy'] ?? 'full-json-table-scan'),
            ),
        ];
    }

    /**
     * @param list<array<string,mixed>> $constraints
     * @return array{usable:bool,value:mixed}
     */
    private static function pointConstraintValue140(array $constraints, string $column): array
    {
        foreach ($constraints as $constraint) {
            if (($constraint['column'] ?? null) !== $column) {
                continue;
            }
            if (($constraint['usable'] ?? true) !== true || strtoupper((string) ($constraint['operator'] ?? '')) !== '=') {
                return ['usable' => false, 'value' => $constraint['value'] ?? null];
            }

            return [
                'usable' => true,
                'value' => $column === 'id' ? self::rowidConstraintIntValue133($constraint['value'] ?? null) : ($constraint['value'] ?? null),
            ];
        }

        return ['usable' => false, 'value' => null];
    }

    private static function jsonTableStableValueToken140(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return get_debug_type($value) . ':' . json_encode($value, JSON_THROW_ON_ERROR);
        }

        return get_debug_type($value) . ':' . json_encode($value, JSON_THROW_ON_ERROR);
    }

    private static function jsonTableHiddenPathRowidCurrentSourceCostClass140(
        bool $runnable,
        bool $pointSeekable,
        bool $matched,
        string $scanStrategy,
    ): string {
        if (!$runnable || $scanStrategy === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($pointSeekable && $matched) {
            return 'json-table-hidden-path-rowid-current-source-point';
        }
        if ($pointSeekable) {
            return 'json-table-hidden-path-rowid-current-source-miss';
        }
        if ($scanStrategy === 'path-rowid-intersection') {
            return 'json-table-hidden-path-rowid-current-source-intersection';
        }

        return 'json-table-hidden-path-rowid-current-source-scan';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableHiddenPathRowidCurrentSourceTransitions140(array $current, array $next): array
    {
        return [
            [
                'field' => 'seekSignature',
                'current' => $current['seekSignature'],
                'next' => $next['seekSignature'],
                'changed' => $current['seekSignature'] !== $next['seekSignature'],
            ],
            [
                'field' => 'sourceKind',
                'current' => $current['sourceKind'],
                'next' => $next['sourceKind'],
                'changed' => $current['sourceKind'] !== $next['sourceKind'],
            ],
            [
                'field' => 'matched',
                'current' => $current['matched'],
                'next' => $next['matched'],
                'changed' => $current['matched'] !== $next['matched'],
            ],
            [
                'field' => 'matchedFullkey',
                'current' => $current['matchedFullkey'],
                'next' => $next['matchedFullkey'],
                'changed' => $current['matchedFullkey'] !== $next['matchedFullkey'],
            ],
            [
                'field' => 'matchedValueFingerprint',
                'current' => $current['matchedValueFingerprint'],
                'next' => $next['matchedValueFingerprint'],
                'changed' => $current['matchedValueFingerprint'] !== $next['matchedValueFingerprint'],
            ],
            [
                'field' => 'seekTape',
                'current' => $current['seekTape'],
                'next' => $next['seekTape'],
                'changed' => $current['seekTape'] !== $next['seekTape'],
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
    private static function jsonTableHiddenPathRowidCurrentSourceReplanReasons140(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'seekSignature' => 'json-table-hidden-path-rowid-seek-signature-changed',
                'sourceKind' => 'json-table-hidden-path-rowid-source-kind-changed',
                'matched', 'matchedFullkey' => 'json-table-hidden-path-rowid-current-source-match-changed',
                'matchedValueFingerprint' => 'json-table-hidden-path-rowid-current-source-value-changed',
                'seekTape' => 'json-table-hidden-path-rowid-current-source-tape-changed',
                'costClass' => 'json-table-hidden-path-rowid-current-source-cost-changed',
                default => 'json-table-hidden-path-rowid-current-source-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $hiddenPathRowid
     * @param list<array{name:string,source?:string,path:string,operator?:string,value?:mixed,usable?:bool}> $generatedConstraints
     * @return array{seekSignature:string|null,sourceKind:string,matched:bool,generatedMatched:bool,generatedConstraints:list<array{name:string,source:string,path:string,operator:string,value:mixed,usable:bool}>,generatedConstraintSignatures:list<string>,generatedValues:array<string,mixed>,generatedTape:list<array{name:string,source:string,path:string,operator:string,expected:mixed,actual:mixed,matched:bool,usable:bool}>,matchedRowid:int|null,matchedPath:string|null,matchedFullkey:string|null,matchedValueFingerprint:string|null,effectiveEstimatedCost:int,costClass:string}
     */
    private static function jsonTableHiddenPathGeneratedCurrentSourceProfile143(
        array $plan,
        array $hiddenPathRowid,
        array $generatedConstraints,
    ): array {
        $constraints = self::normalizeGeneratedHiddenConstraints136($generatedConstraints);
        $matched = (bool) ($hiddenPathRowid['matched'] ?? false);
        $fakeRow = [
            'json' => $plan['source']['jsonValue'] ?? $plan['source']['option_value'] ?? null,
            'value' => $hiddenPathRowid['matchedValue'] ?? null,
            'atom' => $hiddenPathRowid['matchedAtom'] ?? null,
        ];

        $values = [];
        $tape = [];
        foreach ($constraints as $constraint) {
            $actual = $matched ? self::generatedOrderValue132($fakeRow, $constraint) : null;
            $singleMatch = $matched && self::generatedHiddenValuesMatch136(
                [$constraint['name'] => $actual],
                [$constraint],
            );
            $values[$constraint['name']] = $actual;
            $tape[] = [
                'name' => $constraint['name'],
                'source' => $constraint['source'],
                'path' => $constraint['path'],
                'operator' => $constraint['operator'],
                'expected' => $constraint['value'],
                'actual' => $actual,
                'matched' => $singleMatch,
                'usable' => $constraint['usable'],
            ];
        }

        $generatedMatched = $matched && self::generatedHiddenValuesMatch136($values, $constraints);
        $baseCost = (int) ($hiddenPathRowid['effectiveEstimatedCost'] ?? 1000000);
        if (!(bool) ($plan['runnable'] ?? false) || ($hiddenPathRowid['costClass'] ?? null) === 'unrunnable-json-table') {
            $effectiveCost = 1000000;
        } elseif (!$matched) {
            $effectiveCost = min(8, max(3, $baseCost + count($constraints)));
        } elseif (!$generatedMatched) {
            $effectiveCost = min(12, max(4, $baseCost + count($constraints) + 1));
        } else {
            $effectiveCost = max(1, min($baseCost + count($constraints), 6));
        }

        return [
            'seekSignature' => $hiddenPathRowid['seekSignature'] ?? null,
            'sourceKind' => (string) ($hiddenPathRowid['sourceKind'] ?? 'missing'),
            'matched' => $matched,
            'generatedMatched' => $generatedMatched,
            'generatedConstraints' => $constraints,
            'generatedConstraintSignatures' => array_map(
                static fn (array $constraint): string => $constraint['name'] . ':' . $constraint['source'] . ':' . $constraint['path'] . ':' . $constraint['operator'] . ':' . json_encode($constraint['value']),
                $constraints,
            ),
            'generatedValues' => $values,
            'generatedTape' => $tape,
            'matchedRowid' => $hiddenPathRowid['matchedRowid'] ?? null,
            'matchedPath' => $hiddenPathRowid['matchedPath'] ?? null,
            'matchedFullkey' => $hiddenPathRowid['matchedFullkey'] ?? null,
            'matchedValueFingerprint' => $hiddenPathRowid['matchedValueFingerprint'] ?? null,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableHiddenPathGeneratedCurrentSourceCostClass143(
                (bool) ($plan['runnable'] ?? false),
                $matched,
                $generatedMatched,
                (string) ($hiddenPathRowid['costClass'] ?? 'json-table-hidden-path-rowid-current-source-scan'),
            ),
        ];
    }

    private static function jsonTableHiddenPathGeneratedCurrentSourceCostClass143(
        bool $runnable,
        bool $matched,
        bool $generatedMatched,
        string $baseCostClass,
    ): string {
        if (!$runnable || $baseCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if (!$matched) {
            return 'json-table-hidden-path-generated-current-source-miss';
        }
        if (!$generatedMatched) {
            return 'json-table-hidden-path-generated-current-source-filtered';
        }

        return 'json-table-hidden-path-generated-current-source-point';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableHiddenPathGeneratedCurrentSourceTransitions143(array $current, array $next): array
    {
        return [
            [
                'field' => 'seekSignature',
                'current' => $current['seekSignature'],
                'next' => $next['seekSignature'],
                'changed' => $current['seekSignature'] !== $next['seekSignature'],
            ],
            [
                'field' => 'sourceKind',
                'current' => $current['sourceKind'],
                'next' => $next['sourceKind'],
                'changed' => $current['sourceKind'] !== $next['sourceKind'],
            ],
            [
                'field' => 'generatedConstraintSignatures',
                'current' => $current['generatedConstraintSignatures'],
                'next' => $next['generatedConstraintSignatures'],
                'changed' => $current['generatedConstraintSignatures'] !== $next['generatedConstraintSignatures'],
            ],
            [
                'field' => 'generatedMatched',
                'current' => $current['generatedMatched'],
                'next' => $next['generatedMatched'],
                'changed' => $current['generatedMatched'] !== $next['generatedMatched'],
            ],
            [
                'field' => 'generatedValues',
                'current' => $current['generatedValues'],
                'next' => $next['generatedValues'],
                'changed' => $current['generatedValues'] !== $next['generatedValues'],
            ],
            [
                'field' => 'matchedValueFingerprint',
                'current' => $current['matchedValueFingerprint'],
                'next' => $next['matchedValueFingerprint'],
                'changed' => $current['matchedValueFingerprint'] !== $next['matchedValueFingerprint'],
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
    private static function jsonTableHiddenPathGeneratedCurrentSourceReplanReasons143(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'seekSignature' => 'json-table-hidden-path-generated-seek-signature-changed',
                'sourceKind' => 'json-table-hidden-path-generated-source-kind-changed',
                'generatedConstraintSignatures' => 'json-table-hidden-path-generated-constraint-changed',
                'generatedMatched' => 'json-table-hidden-path-generated-match-changed',
                'generatedValues', 'matchedValueFingerprint' => 'json-table-hidden-path-generated-value-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-hidden-path-generated-cost-changed',
                default => 'json-table-hidden-path-generated-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $generated
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{seekSignature:string|null,sourceKind:string,matched:bool,generatedMatched:bool,usableGeneratedConstraintCount:int,residualGeneratedConstraintCount:int,hiddenConstraintColumns:list<string>,constraintUsage:list<array<string,mixed>>,argvBindings:list<array{argvIndex:int,column:string,operator:string,value:mixed,omit:bool,kind:string}>,omitColumns:list<string>,residualColumns:list<string>,estimatedRows:int,estimatedCost:int,costClass:string,matchedRowid:int|null,matchedFullkey:string|null,generatedValues:array<string,mixed>,planFingerprint:string}
     */
    private static function jsonTableHiddenGeneratedCostProfile148(array $generated, array $constraints): array
    {
        $constraintUsage = [];
        $argvBindings = [];
        $hiddenColumns = [];
        $omitColumns = [];
        $residualColumns = [];
        $argvIndex = 1;

        foreach ($constraints as $constraint) {
            $column = self::normalizeConstraintColumn((string) ($constraint['column'] ?? ''));
            if ($column === '') {
                continue;
            }
            $operator = strtoupper((string) ($constraint['operator'] ?? '='));
            $usable = (bool) ($constraint['usable'] ?? true);
            $hidden = in_array($column, ['json', 'root', 'path', 'id'], true);
            if ($hidden) {
                $hiddenColumns[] = $column;
            }
            $omit = $usable && $hidden && in_array($operator, ['=', 'IS', 'IS NOT DISTINCT FROM'], true);
            if ($omit) {
                $omitColumns[] = $column;
            } elseif ($usable) {
                $residualColumns[] = $column;
            }

            $usage = [
                'column' => $column,
                'operator' => $operator,
                'usable' => $usable,
                'hidden' => $hidden,
                'argvIndex' => $usable ? $argvIndex : null,
                'omit' => $omit,
            ];
            $constraintUsage[] = $usage;
            if ($usable) {
                $argvBindings[] = [
                    'argvIndex' => $argvIndex,
                    'column' => $column,
                    'operator' => $operator,
                    'value' => $constraint['value'] ?? null,
                    'omit' => $omit,
                    'kind' => $hidden ? 'hidden' : 'visible',
                ];
                $argvIndex++;
            }
        }

        $generatedTape = $generated['generatedTape'] ?? [];
        $usableGenerated = array_values(array_filter(
            $generatedTape,
            static fn (array $entry): bool => (bool) ($entry['usable'] ?? true),
        ));
        $residualGenerated = array_values(array_filter(
            $generatedTape,
            static fn (array $entry): bool => !($entry['usable'] ?? true),
        ));
        $matched = (bool) ($generated['matched'] ?? false);
        $generatedMatched = (bool) ($generated['generatedMatched'] ?? false);
        $baseCost = (int) ($generated['effectiveEstimatedCost'] ?? 1000000);
        $sourceKind = (string) ($generated['sourceKind'] ?? 'missing');
        $estimatedRows = self::jsonTableHiddenGeneratedEstimatedRows148($matched, $generatedMatched, count($usableGenerated), count($residualGenerated));
        $estimatedCost = self::jsonTableHiddenGeneratedEstimatedCost148($baseCost, $estimatedRows, count($usableGenerated), count($residualGenerated), $sourceKind);

        return [
            'seekSignature' => $generated['seekSignature'] ?? null,
            'sourceKind' => $sourceKind,
            'matched' => $matched,
            'generatedMatched' => $generatedMatched,
            'usableGeneratedConstraintCount' => count($usableGenerated),
            'residualGeneratedConstraintCount' => count($residualGenerated),
            'hiddenConstraintColumns' => array_values(array_unique($hiddenColumns)),
            'constraintUsage' => $constraintUsage,
            'argvBindings' => $argvBindings,
            'omitColumns' => array_values(array_unique($omitColumns)),
            'residualColumns' => array_values(array_unique($residualColumns)),
            'estimatedRows' => $estimatedRows,
            'estimatedCost' => $estimatedCost,
            'costClass' => self::jsonTableHiddenGeneratedCostClass148($sourceKind, $matched, $generatedMatched, count($usableGenerated), count($residualGenerated)),
            'matchedRowid' => $generated['matchedRowid'] ?? null,
            'matchedFullkey' => $generated['matchedFullkey'] ?? null,
            'generatedValues' => $generated['generatedValues'] ?? [],
            'planFingerprint' => self::jsonTableHiddenGeneratedPlanFingerprint148($generated, $argvBindings, $estimatedRows, $estimatedCost),
        ];
    }

    private static function jsonTableHiddenGeneratedEstimatedRows148(bool $matched, bool $generatedMatched, int $usableGenerated, int $residualGenerated): int
    {
        if (!$matched || !$generatedMatched) {
            return 0;
        }

        return max(1, 4 - min(3, $usableGenerated) + $residualGenerated);
    }

    private static function jsonTableHiddenGeneratedEstimatedCost148(int $baseCost, int $estimatedRows, int $usableGenerated, int $residualGenerated, string $sourceKind): int
    {
        if ($sourceKind === 'sql-null' || $baseCost >= 1000000) {
            return 1000000;
        }
        if ($estimatedRows === 0) {
            return min(12, max(3, $baseCost + $usableGenerated + $residualGenerated));
        }

        return max(1, $baseCost + $estimatedRows + $residualGenerated);
    }

    private static function jsonTableHiddenGeneratedCostClass148(string $sourceKind, bool $matched, bool $generatedMatched, int $usableGenerated, int $residualGenerated): string
    {
        if ($sourceKind === 'sql-null') {
            return 'unrunnable-json-table';
        }
        if (!$matched) {
            return 'json-table-hidden-generated-cost-miss';
        }
        if (!$generatedMatched) {
            return 'json-table-hidden-generated-cost-filtered';
        }
        if ($usableGenerated >= 2 && $residualGenerated === 0) {
            return 'json-table-hidden-generated-cost-covering-point';
        }
        if ($usableGenerated >= 1) {
            return 'json-table-hidden-generated-cost-generated-filter';
        }

        return 'json-table-hidden-generated-cost-hidden-seek';
    }

    /**
     * @param array<string,mixed> $generated
     * @param list<array{argvIndex:int,column:string,operator:string,value:mixed,omit:bool,kind:string}> $argvBindings
     */
    private static function jsonTableHiddenGeneratedPlanFingerprint148(array $generated, array $argvBindings, int $estimatedRows, int $estimatedCost): string
    {
        return hash('sha256', json_encode([
            $generated['seekSignature'] ?? null,
            $generated['sourceKind'] ?? null,
            $generated['generatedValues'] ?? [],
            $argvBindings,
            $estimatedRows,
            $estimatedCost,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableHiddenGeneratedCostTransitions148(array $current, array $next): array
    {
        return [
            ['field' => 'seekSignature', 'current' => $current['seekSignature'], 'next' => $next['seekSignature'], 'changed' => $current['seekSignature'] !== $next['seekSignature']],
            ['field' => 'sourceKind', 'current' => $current['sourceKind'], 'next' => $next['sourceKind'], 'changed' => $current['sourceKind'] !== $next['sourceKind']],
            ['field' => 'generatedMatched', 'current' => $current['generatedMatched'], 'next' => $next['generatedMatched'], 'changed' => $current['generatedMatched'] !== $next['generatedMatched']],
            ['field' => 'generatedValues', 'current' => $current['generatedValues'], 'next' => $next['generatedValues'], 'changed' => $current['generatedValues'] !== $next['generatedValues']],
            ['field' => 'argvBindings', 'current' => $current['argvBindings'], 'next' => $next['argvBindings'], 'changed' => $current['argvBindings'] !== $next['argvBindings']],
            ['field' => 'estimatedRows', 'current' => $current['estimatedRows'], 'next' => $next['estimatedRows'], 'changed' => $current['estimatedRows'] !== $next['estimatedRows']],
            ['field' => 'estimatedCost', 'current' => $current['estimatedCost'], 'next' => $next['estimatedCost'], 'changed' => $current['estimatedCost'] !== $next['estimatedCost']],
            ['field' => 'costClass', 'current' => $current['costClass'], 'next' => $next['costClass'], 'changed' => $current['costClass'] !== $next['costClass']],
            ['field' => 'planFingerprint', 'current' => $current['planFingerprint'], 'next' => $next['planFingerprint'], 'changed' => $current['planFingerprint'] !== $next['planFingerprint']],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableHiddenGeneratedCostReplanReasons148(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'seekSignature' => 'json-table-hidden-generated-cost-seek-changed',
                'sourceKind' => 'json-table-hidden-generated-cost-source-kind-changed',
                'generatedMatched', 'generatedValues' => 'json-table-hidden-generated-cost-values-changed',
                'argvBindings' => 'json-table-hidden-generated-cost-argv-changed',
                'estimatedRows' => 'json-table-hidden-generated-cost-row-estimate-changed',
                'estimatedCost', 'costClass' => 'json-table-hidden-generated-cost-estimate-changed',
                'planFingerprint' => 'json-table-hidden-generated-cost-fingerprint-changed',
                default => 'json-table-hidden-generated-cost-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $hiddenGenerated
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{seekSignature:string|null,rowidConstraintSignature:string|null,rowidConstraintColumn:string|null,rowidConstraintOperator:string|null,rowidConstraintValue:mixed,rowidScoped:bool,matched:bool,generatedMatched:bool,rowidMatched:bool,intersected:bool,intersectedRowids:list<int>,intersectedFullkeys:list<string>,generatedValues:array<string,mixed>,rowidGeneratedTape:list<array{rowid:int|null,fullkey:string|null,generatedMatched:bool,rowidMatched:bool,matched:bool,values:array<string,mixed>}>,baseEstimatedRows:int,baseEstimatedCost:int,effectiveEstimatedRows:int,effectiveEstimatedCost:int,costClass:string,planFingerprint:string}
     */
    private static function jsonTableHiddenGeneratedRowidProfile157(array $hiddenGenerated, array $constraints): array
    {
        $rowidConstraint = self::firstRowidConstraint133($constraints);
        $rowidOperator = isset($rowidConstraint['operator']) ? strtoupper((string) $rowidConstraint['operator']) : null;
        $rowidValue = $rowidConstraint['value'] ?? null;
        $rowidScoped = $rowidConstraint !== null
            && $rowidOperator === '='
            && self::rowidConstraintIntValue133($rowidValue) !== null;
        $matchedRowid = is_int($hiddenGenerated['matchedRowid'] ?? null) ? $hiddenGenerated['matchedRowid'] : null;
        $matchedFullkey = is_string($hiddenGenerated['matchedFullkey'] ?? null) ? $hiddenGenerated['matchedFullkey'] : null;
        $row = [
            'id' => $matchedRowid,
            'rowid' => $matchedRowid,
            '_rowid_' => $matchedRowid,
            'oid' => $matchedRowid,
            'fullkey' => $matchedFullkey,
        ];
        $matched = (bool) ($hiddenGenerated['matched'] ?? false);
        $generatedMatched = (bool) ($hiddenGenerated['generatedMatched'] ?? false);
        $rowidMatched = $rowidConstraint === null
            ? $matched
            : ($matched && self::rowMatchesResidualConstraints($row, [$rowidConstraint]));
        $intersected = $matched && $generatedMatched && $rowidMatched;
        $baseRows = (int) ($hiddenGenerated['estimatedRows'] ?? 0);
        $baseCost = (int) ($hiddenGenerated['estimatedCost'] ?? 1000000);

        if ((string) ($hiddenGenerated['costClass'] ?? '') === 'unrunnable-json-table') {
            $effectiveRows = 0;
            $effectiveCost = 1000000;
        } elseif (!$intersected) {
            $effectiveRows = 0;
            $effectiveCost = min(12, max(1, $baseCost));
        } elseif ($rowidScoped) {
            $effectiveRows = 1;
            $effectiveCost = 1;
        } elseif ($rowidConstraint !== null) {
            $effectiveRows = 1;
            $effectiveCost = min($baseCost, 2);
        } else {
            $effectiveRows = $baseRows;
            $effectiveCost = $baseCost;
        }

        $tape = [];
        if ($matched || $matchedRowid !== null || $matchedFullkey !== null) {
            $tape[] = [
                'rowid' => $matchedRowid,
                'fullkey' => $matchedFullkey,
                'generatedMatched' => $generatedMatched,
                'rowidMatched' => $rowidMatched,
                'matched' => $intersected,
                'values' => $hiddenGenerated['generatedValues'] ?? [],
            ];
        }

        $fingerprint = hash('sha256', json_encode([
            $hiddenGenerated['seekSignature'] ?? null,
            $rowidConstraint === null ? null : self::nestedPathRowidConstraintSignature133($rowidConstraint),
            $intersected,
            $hiddenGenerated['generatedValues'] ?? [],
            $effectiveRows,
            $effectiveCost,
        ], JSON_THROW_ON_ERROR));

        return [
            'seekSignature' => $hiddenGenerated['seekSignature'] ?? null,
            'rowidConstraintSignature' => $rowidConstraint === null ? null : self::nestedPathRowidConstraintSignature133($rowidConstraint),
            'rowidConstraintColumn' => isset($rowidConstraint['column']) ? self::normalizeConstraintColumn((string) $rowidConstraint['column']) : null,
            'rowidConstraintOperator' => $rowidOperator,
            'rowidConstraintValue' => $rowidValue,
            'rowidScoped' => $rowidScoped,
            'matched' => $matched,
            'generatedMatched' => $generatedMatched,
            'rowidMatched' => $rowidMatched,
            'intersected' => $intersected,
            'intersectedRowids' => $intersected && $matchedRowid !== null ? [$matchedRowid] : [],
            'intersectedFullkeys' => $intersected && $matchedFullkey !== null ? [$matchedFullkey] : [],
            'generatedValues' => $hiddenGenerated['generatedValues'] ?? [],
            'rowidGeneratedTape' => $tape,
            'baseEstimatedRows' => $baseRows,
            'baseEstimatedCost' => $baseCost,
            'effectiveEstimatedRows' => $effectiveRows,
            'effectiveEstimatedCost' => $effectiveCost,
            'costClass' => self::jsonTableHiddenGeneratedRowidCostClass157(
                (string) ($hiddenGenerated['costClass'] ?? ''),
                $rowidConstraint !== null,
                $rowidScoped,
                $intersected,
                $effectiveCost,
            ),
            'planFingerprint' => $fingerprint,
        ];
    }

    private static function jsonTableHiddenGeneratedRowidCostClass157(
        string $baseCostClass,
        bool $hasRowidConstraint,
        bool $rowidScoped,
        bool $intersected,
        int $effectiveCost,
    ): string {
        if ($baseCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if (!$hasRowidConstraint) {
            return $intersected
                ? 'json-table-hidden-generated-rowid-unconstrained-current-source'
                : 'json-table-hidden-generated-rowid-empty-current-source';
        }
        if (!$intersected) {
            return 'json-table-hidden-generated-rowid-empty-current-source';
        }
        if ($rowidScoped) {
            return 'json-table-hidden-generated-rowid-point-current-source';
        }

        return $effectiveCost <= 2
            ? 'json-table-hidden-generated-rowid-narrow-current-source'
            : 'json-table-hidden-generated-rowid-intersection-current-source';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function jsonTableHiddenGeneratedRowidTransitions157(array $current, array $next): array
    {
        return [
            ['field' => 'seekSignature', 'current' => $current['seekSignature'], 'next' => $next['seekSignature'], 'changed' => $current['seekSignature'] !== $next['seekSignature']],
            ['field' => 'rowidConstraintSignature', 'current' => $current['rowidConstraintSignature'], 'next' => $next['rowidConstraintSignature'], 'changed' => $current['rowidConstraintSignature'] !== $next['rowidConstraintSignature']],
            ['field' => 'generatedMatched', 'current' => $current['generatedMatched'], 'next' => $next['generatedMatched'], 'changed' => $current['generatedMatched'] !== $next['generatedMatched']],
            ['field' => 'rowidMatched', 'current' => $current['rowidMatched'], 'next' => $next['rowidMatched'], 'changed' => $current['rowidMatched'] !== $next['rowidMatched']],
            ['field' => 'intersectedRowids', 'current' => $current['intersectedRowids'], 'next' => $next['intersectedRowids'], 'changed' => $current['intersectedRowids'] !== $next['intersectedRowids']],
            ['field' => 'intersectedFullkeys', 'current' => $current['intersectedFullkeys'], 'next' => $next['intersectedFullkeys'], 'changed' => $current['intersectedFullkeys'] !== $next['intersectedFullkeys']],
            ['field' => 'generatedValues', 'current' => $current['generatedValues'], 'next' => $next['generatedValues'], 'changed' => $current['generatedValues'] !== $next['generatedValues']],
            ['field' => 'effectiveEstimatedRows', 'current' => $current['effectiveEstimatedRows'], 'next' => $next['effectiveEstimatedRows'], 'changed' => $current['effectiveEstimatedRows'] !== $next['effectiveEstimatedRows']],
            ['field' => 'effectiveEstimatedCost', 'current' => $current['effectiveEstimatedCost'], 'next' => $next['effectiveEstimatedCost'], 'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost']],
            ['field' => 'costClass', 'current' => $current['costClass'], 'next' => $next['costClass'], 'changed' => $current['costClass'] !== $next['costClass']],
            ['field' => 'rowidGeneratedTape', 'current' => $current['rowidGeneratedTape'], 'next' => $next['rowidGeneratedTape'], 'changed' => $current['rowidGeneratedTape'] !== $next['rowidGeneratedTape']],
            ['field' => 'planFingerprint', 'current' => $current['planFingerprint'], 'next' => $next['planFingerprint'], 'changed' => $current['planFingerprint'] !== $next['planFingerprint']],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function jsonTableHiddenGeneratedRowidReplanReasons157(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }

            $reasons[] = match ($transition['field']) {
                'seekSignature' => 'json-table-hidden-generated-rowid-seek-changed',
                'rowidConstraintSignature' => 'json-table-hidden-generated-rowid-constraint-changed',
                'generatedMatched', 'generatedValues' => 'json-table-hidden-generated-rowid-values-changed',
                'rowidMatched' => 'json-table-hidden-generated-rowid-rowid-match-changed',
                'intersectedRowids', 'intersectedFullkeys' => 'json-table-hidden-generated-rowid-rowset-changed',
                'effectiveEstimatedRows' => 'json-table-hidden-generated-rowid-row-estimate-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-hidden-generated-rowid-cost-changed',
                'rowidGeneratedTape' => 'json-table-hidden-generated-rowid-tape-changed',
                'planFingerprint' => 'json-table-hidden-generated-rowid-fingerprint-changed',
                default => 'json-table-hidden-generated-rowid-state-changed',
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
     * @param array<string,mixed> $nestedPathRowid
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{root:string,baseRoot:string,nestedPath:string,pathConstraintSignature:string|null,rowidConstraintSignature:string|null,compositeSignature:string|null,pathScoped:bool,rowidScoped:bool,matchedRowCount:int,pathMatchedRowids:list<int>,rowidMatchedRowids:list<int>,intersectedRowids:list<int>,relativeFullkeys:list<string>,hiddenPathTape:list<array{root:string,rowid:int|null,fullkey:string|null,relativeFullkey:string,pathMatched:bool,rowidMatched:bool,matched:bool}>,firstIntersectedRowid:int|null,lastIntersectedRowid:int|null,costClass:string,effectiveEstimatedCost:int}
     */
    private static function rowidHiddenPathProfile138(array $nestedPathRowid, array $constraints): array
    {
        $pathConstraint = self::firstHiddenPathConstraint138($constraints);
        $rowidConstraint = self::firstRowidConstraint133($constraints);
        $rowidOperator = isset($rowidConstraint['operator']) ? strtoupper((string) $rowidConstraint['operator']) : null;
        $rowidValue = $rowidConstraint['value'] ?? null;
        $root = (string) $nestedPathRowid['root'];
        $tape = [];
        $pathMatchedRowids = [];
        $rowidMatchedRowids = [];
        $intersectedRowids = [];
        $relativeFullkeys = [];

        foreach ($nestedPathRowid['rootRowidTape'] as $entry) {
            $rowid = is_int($entry['rowid'] ?? null) ? $entry['rowid'] : null;
            $fullkey = is_string($entry['fullkey'] ?? null) ? $entry['fullkey'] : null;
            $relativeFullkey = self::relativeJsonFullkey133($root, $fullkey);
            $row = [
                'id' => $rowid,
                'rowid' => $rowid,
                '_rowid_' => $rowid,
                'oid' => $rowid,
                'fullkey' => $fullkey,
                'path' => self::hiddenPathFromFullkey138($fullkey),
                'relative_fullkey' => $relativeFullkey,
                'relativefullkey' => $relativeFullkey,
            ];
            $pathMatched = $pathConstraint === null || self::rowMatchesResidualConstraints($row, [$pathConstraint]);
            $rowidMatched = $rowidConstraint === null || self::rowMatchesResidualConstraints($row, [$rowidConstraint]);
            $matched = $pathMatched && $rowidMatched;

            if ($pathMatched && $rowid !== null) {
                $pathMatchedRowids[] = $rowid;
            }
            if ($rowidMatched && $rowid !== null) {
                $rowidMatchedRowids[] = $rowid;
            }
            if ($matched && $rowid !== null) {
                $intersectedRowids[] = $rowid;
                $relativeFullkeys[] = $relativeFullkey;
            }

            $tape[] = [
                'root' => $root,
                'rowid' => $rowid,
                'fullkey' => $fullkey,
                'relativeFullkey' => $relativeFullkey,
                'pathMatched' => $pathMatched,
                'rowidMatched' => $rowidMatched,
                'matched' => $matched,
            ];
        }

        $pathScoped = $pathConstraint !== null;
        $rowidScoped = $rowidConstraint !== null && $rowidOperator === '=' && self::rowidConstraintIntValue133($rowidValue) !== null;
        $baseCost = (int) $nestedPathRowid['effectiveEstimatedCost'];
        $effectiveCost = $baseCost >= 1000000 ? $baseCost : self::rowidHiddenPathEffectiveCost138($baseCost, $pathScoped, $rowidScoped, count($intersectedRowids));

        return [
            'root' => $root,
            'baseRoot' => (string) $nestedPathRowid['baseRoot'],
            'nestedPath' => (string) $nestedPathRowid['nestedPath'],
            'pathConstraintSignature' => $pathConstraint === null ? null : self::rowidHiddenPathConstraintSignature138($pathConstraint),
            'rowidConstraintSignature' => $rowidConstraint === null ? null : self::nestedPathRowidConstraintSignature133($rowidConstraint),
            'compositeSignature' => $pathConstraint !== null && $rowidConstraint !== null
                ? self::rowidHiddenPathConstraintSignature138($pathConstraint) . '&&' . self::nestedPathRowidConstraintSignature133($rowidConstraint)
                : null,
            'pathScoped' => $pathScoped,
            'rowidScoped' => $rowidScoped,
            'matchedRowCount' => count($intersectedRowids),
            'pathMatchedRowids' => $pathMatchedRowids,
            'rowidMatchedRowids' => $rowidMatchedRowids,
            'intersectedRowids' => $intersectedRowids,
            'relativeFullkeys' => $relativeFullkeys,
            'hiddenPathTape' => $tape,
            'firstIntersectedRowid' => $intersectedRowids[0] ?? null,
            'lastIntersectedRowid' => $intersectedRowids === [] ? null : $intersectedRowids[array_key_last($intersectedRowids)],
            'costClass' => self::rowidHiddenPathCostClass138((string) $nestedPathRowid['costClass'], $pathScoped, $rowidScoped, count($intersectedRowids)),
            'effectiveEstimatedCost' => $effectiveCost,
        ];
    }

    /**
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{column:string,operator:string,value:mixed,usable?:bool}|null
     */
    private static function firstHiddenPathConstraint138(array $constraints): ?array
    {
        foreach ($constraints as $constraint) {
            if (!($constraint['usable'] ?? true)) {
                continue;
            }
            $column = strtolower((string) ($constraint['column'] ?? ''));
            if ($column === 'fullkey' || $column === 'path' || $column === 'relative_fullkey' || $column === 'relativefullkey') {
                return $constraint + ['column' => $column];
            }
        }

        return null;
    }

    private static function hiddenPathFromFullkey138(?string $fullkey): ?string
    {
        if ($fullkey === null || $fullkey === '' || $fullkey === '$') {
            return null;
        }
        $dot = strrpos($fullkey, '.');
        $bracket = strrpos($fullkey, '[');
        $offset = max($dot === false ? -1 : $dot, $bracket === false ? -1 : $bracket);

        return $offset <= 0 ? '$' : substr($fullkey, 0, $offset);
    }

    /**
     * @param array{column:string,operator:string,value:mixed,usable?:bool} $constraint
     */
    private static function rowidHiddenPathConstraintSignature138(array $constraint): string
    {
        return strtolower((string) $constraint['column']) . ':' . strtoupper((string) $constraint['operator']) . ':' . json_encode($constraint['value']);
    }

    private static function rowidHiddenPathEffectiveCost138(int $baseCost, bool $pathScoped, bool $rowidScoped, int $matchedRows): int
    {
        if ($matchedRows === 0) {
            return 1;
        }
        if ($pathScoped && $rowidScoped) {
            return 1;
        }
        if ($rowidScoped) {
            return min($baseCost, 2);
        }
        if ($pathScoped) {
            return min($baseCost, max(2, $matchedRows));
        }

        return $baseCost;
    }

    private static function rowidHiddenPathCostClass138(string $baseCostClass, bool $pathScoped, bool $rowidScoped, int $matchedRows): string
    {
        if ($baseCostClass === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ($matchedRows === 0) {
            return 'json-table-rowid-hidden-path-empty';
        }
        if ($pathScoped && $rowidScoped) {
            return 'json-table-rowid-hidden-path-point';
        }
        if ($pathScoped) {
            return 'json-table-hidden-path-scan';
        }
        if ($rowidScoped) {
            return 'json-table-hidden-rowid-scan';
        }

        return 'json-table-hidden-path-full-scan';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function rowidHiddenPathTransitions138(array $current, array $next): array
    {
        return [
            ['field' => 'root', 'current' => $current['root'], 'next' => $next['root'], 'changed' => $current['root'] !== $next['root']],
            ['field' => 'compositeSignature', 'current' => $current['compositeSignature'], 'next' => $next['compositeSignature'], 'changed' => $current['compositeSignature'] !== $next['compositeSignature']],
            ['field' => 'matchedRowCount', 'current' => $current['matchedRowCount'], 'next' => $next['matchedRowCount'], 'changed' => $current['matchedRowCount'] !== $next['matchedRowCount']],
            ['field' => 'intersectedRowids', 'current' => $current['intersectedRowids'], 'next' => $next['intersectedRowids'], 'changed' => $current['intersectedRowids'] !== $next['intersectedRowids']],
            ['field' => 'relativeFullkeys', 'current' => $current['relativeFullkeys'], 'next' => $next['relativeFullkeys'], 'changed' => $current['relativeFullkeys'] !== $next['relativeFullkeys']],
            ['field' => 'effectiveEstimatedCost', 'current' => $current['effectiveEstimatedCost'], 'next' => $next['effectiveEstimatedCost'], 'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost']],
            ['field' => 'costClass', 'current' => $current['costClass'], 'next' => $next['costClass'], 'changed' => $current['costClass'] !== $next['costClass']],
            ['field' => 'hiddenPathTape', 'current' => $current['hiddenPathTape'], 'next' => $next['hiddenPathTape'], 'changed' => $current['hiddenPathTape'] !== $next['hiddenPathTape']],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function rowidHiddenPathReplanReasons138(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }
            $reasons[] = match ($transition['field']) {
                'root' => 'json-table-rowid-hidden-path-root-changed',
                'compositeSignature' => 'json-table-rowid-hidden-path-constraint-changed',
                'matchedRowCount' => 'json-table-rowid-hidden-path-count-changed',
                'intersectedRowids', 'relativeFullkeys' => 'json-table-rowid-hidden-path-rowset-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-rowid-hidden-path-cost-changed',
                'hiddenPathTape' => 'json-table-rowid-hidden-path-tape-changed',
                default => 'json-table-rowid-hidden-path-state-changed',
            };
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $rowidHiddenPath
     * @param list<array{column:string,operator:string,value:mixed,usable?:bool}> $constraints
     * @return array{sourceToken:array{option_id:mixed,option_name:mixed,root:string,nestedPath:string},root:string,baseRoot:string,nestedPath:string,pathConstraintSignature:string|null,rowidConstraintSignature:string|null,aliasColumns:list<string>,rowidAlias:string|null,pathAlias:string|null,pointSeekable:bool,matchedRowCount:int,pinnedRowids:list<int>,pinnedRelativeFullkeys:list<string>,rowidPathTape:list<array{rowid:int|null,relativeFullkey:string,pathMatched:bool,rowidMatched:bool,matched:bool}>,firstPinned:array{rowid:int,relativeFullkey:string}|null,lastPinned:array{rowid:int,relativeFullkey:string}|null,effectiveEstimatedCost:int,costClass:string}
     */
    private static function hiddenRowidPathProfile146(array $source, array $rowidHiddenPath, array $constraints): array
    {
        $rowidConstraint = self::firstRowidConstraint133($constraints);
        $pathConstraint = self::firstHiddenPathConstraint138($constraints);
        $rowidAlias = $rowidConstraint === null ? null : self::normalizeConstraintColumn((string) $rowidConstraint['column']);
        $pathAlias = $pathConstraint === null ? null : strtolower((string) $pathConstraint['column']);
        $aliasColumns = array_values(array_unique(array_filter([$rowidAlias, $pathAlias], static fn (?string $column): bool => $column !== null)));
        $pinnedRowids = array_values(array_map('intval', $rowidHiddenPath['intersectedRowids'] ?? []));
        $pinnedRelativeFullkeys = array_values(array_map('strval', $rowidHiddenPath['relativeFullkeys'] ?? []));
        $tape = [];

        foreach ($rowidHiddenPath['hiddenPathTape'] ?? [] as $entry) {
            $tape[] = [
                'rowid' => is_int($entry['rowid'] ?? null) ? $entry['rowid'] : null,
                'relativeFullkey' => (string) ($entry['relativeFullkey'] ?? ''),
                'pathMatched' => (bool) ($entry['pathMatched'] ?? false),
                'rowidMatched' => (bool) ($entry['rowidMatched'] ?? false),
                'matched' => (bool) ($entry['matched'] ?? false),
            ];
        }

        $pointSeekable = (bool) ($rowidHiddenPath['pathScoped'] ?? false)
            && (bool) ($rowidHiddenPath['rowidScoped'] ?? false)
            && count($pinnedRowids) <= 1;

        return [
            'sourceToken' => [
                'option_id' => $source['option_id'] ?? null,
                'option_name' => $source['option_name'] ?? null,
                'root' => (string) ($rowidHiddenPath['root'] ?? '$'),
                'nestedPath' => (string) ($rowidHiddenPath['nestedPath'] ?? ''),
            ],
            'root' => (string) ($rowidHiddenPath['root'] ?? '$'),
            'baseRoot' => (string) ($rowidHiddenPath['baseRoot'] ?? '$'),
            'nestedPath' => (string) ($rowidHiddenPath['nestedPath'] ?? ''),
            'pathConstraintSignature' => $rowidHiddenPath['pathConstraintSignature'] ?? null,
            'rowidConstraintSignature' => $rowidHiddenPath['rowidConstraintSignature'] ?? null,
            'aliasColumns' => $aliasColumns,
            'rowidAlias' => $rowidAlias,
            'pathAlias' => $pathAlias,
            'pointSeekable' => $pointSeekable,
            'matchedRowCount' => (int) ($rowidHiddenPath['matchedRowCount'] ?? 0),
            'pinnedRowids' => $pinnedRowids,
            'pinnedRelativeFullkeys' => $pinnedRelativeFullkeys,
            'rowidPathTape' => $tape,
            'firstPinned' => isset($pinnedRowids[0], $pinnedRelativeFullkeys[0])
                ? ['rowid' => $pinnedRowids[0], 'relativeFullkey' => $pinnedRelativeFullkeys[0]]
                : null,
            'lastPinned' => $pinnedRowids === [] || $pinnedRelativeFullkeys === []
                ? null
                : ['rowid' => $pinnedRowids[array_key_last($pinnedRowids)], 'relativeFullkey' => $pinnedRelativeFullkeys[array_key_last($pinnedRelativeFullkeys)]],
            'effectiveEstimatedCost' => (int) ($rowidHiddenPath['effectiveEstimatedCost'] ?? 1000000),
            'costClass' => self::hiddenRowidPathCostClass146($rowidHiddenPath, $pointSeekable),
        ];
    }

    private static function hiddenRowidPathCostClass146(array $rowidHiddenPath, bool $pointSeekable): string
    {
        $base = (string) ($rowidHiddenPath['costClass'] ?? 'unrunnable-json-table');
        if ($base === 'unrunnable-json-table') {
            return 'unrunnable-json-table';
        }
        if ((int) ($rowidHiddenPath['matchedRowCount'] ?? 0) === 0) {
            return 'json-table-hidden-rowid-path-empty';
        }
        if ($pointSeekable) {
            return 'json-table-hidden-rowid-path-point-current-source';
        }
        if (($rowidHiddenPath['pathScoped'] ?? false) && ($rowidHiddenPath['rowidScoped'] ?? false)) {
            return 'json-table-hidden-rowid-path-intersection-current-source';
        }
        if ($rowidHiddenPath['rowidScoped'] ?? false) {
            return 'json-table-hidden-rowid-path-rowid-current-source';
        }
        if ($rowidHiddenPath['pathScoped'] ?? false) {
            return 'json-table-hidden-rowid-path-path-current-source';
        }

        return 'json-table-hidden-rowid-path-scan-current-source';
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<array{field:string,current:mixed,next:mixed,changed:bool}>
     */
    private static function hiddenRowidPathTransitions146(array $current, array $next): array
    {
        return [
            ['field' => 'sourceToken', 'current' => $current['sourceToken'], 'next' => $next['sourceToken'], 'changed' => $current['sourceToken'] !== $next['sourceToken']],
            ['field' => 'aliasColumns', 'current' => $current['aliasColumns'], 'next' => $next['aliasColumns'], 'changed' => $current['aliasColumns'] !== $next['aliasColumns']],
            ['field' => 'pointSeekable', 'current' => $current['pointSeekable'], 'next' => $next['pointSeekable'], 'changed' => $current['pointSeekable'] !== $next['pointSeekable']],
            ['field' => 'matchedRowCount', 'current' => $current['matchedRowCount'], 'next' => $next['matchedRowCount'], 'changed' => $current['matchedRowCount'] !== $next['matchedRowCount']],
            ['field' => 'pinnedRowids', 'current' => $current['pinnedRowids'], 'next' => $next['pinnedRowids'], 'changed' => $current['pinnedRowids'] !== $next['pinnedRowids']],
            ['field' => 'pinnedRelativeFullkeys', 'current' => $current['pinnedRelativeFullkeys'], 'next' => $next['pinnedRelativeFullkeys'], 'changed' => $current['pinnedRelativeFullkeys'] !== $next['pinnedRelativeFullkeys']],
            ['field' => 'rowidPathTape', 'current' => $current['rowidPathTape'], 'next' => $next['rowidPathTape'], 'changed' => $current['rowidPathTape'] !== $next['rowidPathTape']],
            ['field' => 'effectiveEstimatedCost', 'current' => $current['effectiveEstimatedCost'], 'next' => $next['effectiveEstimatedCost'], 'changed' => $current['effectiveEstimatedCost'] !== $next['effectiveEstimatedCost']],
            ['field' => 'costClass', 'current' => $current['costClass'], 'next' => $next['costClass'], 'changed' => $current['costClass'] !== $next['costClass']],
        ];
    }

    /**
     * @param list<array{field:string,current:mixed,next:mixed,changed:bool}> $transitions
     * @return list<string>
     */
    private static function hiddenRowidPathReplanReasons146(array $transitions): array
    {
        $reasons = [];
        foreach ($transitions as $transition) {
            if (!$transition['changed']) {
                continue;
            }
            $reasons[] = match ($transition['field']) {
                'sourceToken' => 'json-table-hidden-rowid-path-source-token-changed',
                'aliasColumns' => 'json-table-hidden-rowid-path-alias-columns-changed',
                'pointSeekable' => 'json-table-hidden-rowid-path-seekability-changed',
                'matchedRowCount' => 'json-table-hidden-rowid-path-count-changed',
                'pinnedRowids', 'pinnedRelativeFullkeys' => 'json-table-hidden-rowid-path-rowset-changed',
                'rowidPathTape' => 'json-table-hidden-rowid-path-tape-changed',
                'effectiveEstimatedCost', 'costClass' => 'json-table-hidden-rowid-path-cost-changed',
                default => 'json-table-hidden-rowid-path-state-changed',
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

    private static function compareSQLiteValues(mixed $left, mixed $right): int
    {
        if ($left === null || $right === null) {
            return $left <=> $right;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return (float) $left <=> (float) $right;
        }
        if (is_string($left) && is_string($right)) {
            return strcmp($left, $right);
        }

        return self::sqliteValueSortRank($left) <=> self::sqliteValueSortRank($right);
    }

    private static function sqliteValueSortRank(mixed $value): int
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

        return 3;
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
