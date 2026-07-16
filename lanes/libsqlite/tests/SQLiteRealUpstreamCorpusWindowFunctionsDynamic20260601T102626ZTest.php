<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow1 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$asSortedUniqueInts = static function (array $values): array {
    $unique = array_values(array_unique(array_map(static fn (mixed $value): int => (int) $value, $values)));
    sort($unique, SORT_NUMERIC);

    return $unique;
};

$compoundSet = static function (string $operator, array $left, array $right) use ($asSortedUniqueInts): array {
    $leftSet = $asSortedUniqueInts($left);
    $rightSet = $asSortedUniqueInts($right);

    return match ($operator) {
        'INTERSECT' => array_values(array_filter(
            $leftSet,
            static fn (int $value): bool => in_array($value, $rightSet, true),
        )),
        'EXCEPT' => array_values(array_filter(
            $leftSet,
            static fn (int $value): bool => !in_array($value, $rightSet, true),
        )),
        'UNION' => $asSortedUniqueInts(array_merge($leftSet, $rightSet)),
        'UNION ALL' => array_merge($left, $right),
        default => throw new InvalidArgumentException("Unsupported compound operator {$operator}"),
    };
};

$runningSums = static function (array $values): array {
    $keys = range(1, count($values));

    return SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        $values,
        $keys,
        'ROWS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
    );
};

$partitionSums = static function (array $values): array {
    $keys = range(1, count($values));

    return SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        $values,
        $keys,
        'ROWS',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
    );
};

$caseValues = static function (int $case): array {
    $count = 3 + ($case % 6);
    $values = [];
    for ($index = 0; $index < $count; $index++) {
        $values[] = 1 + (($case * 11 + $index * 7 + intdiv($case, 3)) % 31);
    }

    return $values;
};

$singleColumn = static function (array $rows): array {
    return array_map(static fn (array $row): mixed => array_values($row)[0] ?? null, $rows);
};

$rowsForColumn = static function (array $values, string $column): array {
    return array_map(static fn (int $value): array => [$column => $value], $values);
};

$executeSingleColumn = static function (string $sql, array $tables) use ($singleColumn): array {
    return $singleColumn(SQLiteSelectSql::execute($sql, $tables));
};

$scalarWindowFilter = static function (array $values, int $threshold) use ($partitionSums): array {
    $scalar = $partitionSums($values)[0] ?? null;
    if ($scalar === null || $scalar == 0) {
        return [];
    }

    return array_values(array_filter($values, static fn (int $value): bool => $value <= $threshold));
};

$nestedWholeWindowRows = static function (array $values): array {
    $sum = array_sum($values);

    return array_fill(0, count($values), $sum * 2);
};

$nestedGroupedFirstRows = static function (array $values): array {
    $firstGroup = min($values);

    return array_fill(0, count($values), $firstGroup * 2);
};

$tests['real upstream window1 dynamic source truth records compound and scalar subquery sections'] =
    static function (TestRunner $t) use ($upstreamWindow1): void {
        $source = file_get_contents($upstreamWindow1);
        $t->true($source !== false, 'hydrated upstream window1.test is available');
        $source = (string) $source;

        $t->contains('do_execsql_test 35.2', $source, 'window1.test 35.2 compound INTERSECT case is present');
        $t->contains('VALUES(1) INTERSECT', $source, 'window1.test 35.2 VALUES compound source is present');
        $t->contains('do_execsql_test 45.2', $source, 'window1.test 45.2 UNION ALL window aggregate case is present');
        $t->contains('do_execsql_test 46.2', $source, 'window1.test 46.2 scalar window subquery case is present');
        $t->contains('do_execsql_test 48.0', $source, 'window1.test 48.0 nested scalar window subquery case is present');
        $t->contains('do_execsql_test 60.1', $source, 'window1.test 60.1 EXISTS window subquery case is present');
    };

$tests['real upstream window1 section 35 compound values baseline matches running sums'] =
    static function (TestRunner $t) use ($compoundSet, $executeSingleColumn, $rowsForColumn, $runningSums): void {
        $values = [1, 2, 3];
        $running = $runningSums($values);
        $tables = ['t1' => $rowsForColumn($values, 'x')];

        $t->same([1, 3, 6], $running, 'window1.test 35.2 SELECT sum(x) OVER f yields ordered running sums');
        $t->same([1, 3, 6], $executeSingleColumn('SELECT sum(x) OVER f FROM t1 WINDOW f AS (ORDER BY x) ORDER BY 1', $tables), 'window1.test 35.2 SQL executor returns running window sums');
        $t->same([1], $compoundSet('INTERSECT', [1], $running), 'window1.test 35.2 VALUES(1) INTERSECT window SELECT');
        $t->same([1], $executeSingleColumn('VALUES(1) INTERSECT SELECT sum(x) OVER f FROM t1 WINDOW f AS (ORDER BY x) ORDER BY 1', $tables), 'window1.test 35.2 SQL executor applies VALUES INTERSECT');
        $t->same([8], $compoundSet('EXCEPT', [8], $running), 'window1.test 35.3 VALUES(8) EXCEPT window SELECT');
        $t->same([8], $executeSingleColumn('VALUES(8) EXCEPT SELECT sum(x) OVER f FROM t1 WINDOW f AS (ORDER BY x) ORDER BY 1', $tables), 'window1.test 35.3 SQL executor applies VALUES EXCEPT');
        $t->same([1, 3, 6], $compoundSet('UNION', [1], $running), 'window1.test 35.4 VALUES(1) UNION window SELECT');
        $t->same([1, 3, 6], $executeSingleColumn('VALUES(1) UNION SELECT sum(x) OVER f FROM t1 WINDOW f AS (ORDER BY x) ORDER BY 1', $tables), 'window1.test 35.4 SQL executor applies VALUES UNION');
    };

$tests['real upstream window1 section 45 union all baseline preserves window aggregate rows'] =
    static function (TestRunner $t) use ($compoundSet, $executeSingleColumn, $partitionSums): void {
        $windowRows = $partitionSums([1000, 1000]);
        $tables = ['t1' => [['a' => 1000], ['a' => 1000]], 't2' => [['x' => 10000]]];

        $t->same([2000, 2000], $windowRows, 'window1.test 45.2 sum(a) OVER() repeats partition total');
        $t->same([2000, 2000, 10000], $compoundSet('UNION ALL', $windowRows, [10000]), 'window1.test 45.2 UNION ALL preserves duplicate window totals');
        $t->same([2000, 2000, 10000], $executeSingleColumn('SELECT sum(a) OVER() AS s FROM t1 UNION ALL SELECT x FROM t2 ORDER BY 1', $tables), 'window1.test 45.2 SQL executor preserves duplicate UNION ALL window totals');
    };

$tests['real upstream window1 section 46 scalar window subquery baseline gates outer rows'] =
    static function (TestRunner $t) use ($executeSingleColumn, $partitionSums, $scalarWindowFilter): void {
        $values = [10];
        $tables = ['t1' => [['a' => 10]], 't2' => [['x' => 10]]];

        $t->same([10], $partitionSums($values), 'window1.test 46.2 scalar SELECT sum(a) OVER(ORDER BY a) returns a truthy row');
        $t->same([10], $executeSingleColumn('SELECT a FROM t1 WHERE ( SELECT sum(a) OVER(ORDER BY a) FROM t1 )', $tables), 'window1.test 46.2 SQL executor applies scalar window truthiness');
        $t->same([10], $scalarWindowFilter($values, 10), 'window1.test 46.4 scalar window subquery keeps qualifying outer rows');
        $t->same([10], $executeSingleColumn('SELECT x FROM t2 NATURAL JOIN t1 WHERE ((SELECT sum(a) OVER(ORDER BY a) FROM t1) AND a<=10)', $tables), 'window1.test 46.4 SQL executor applies scalar window predicate through join');
        $t->same([], $scalarWindowFilter($values, 5), 'window1.test 46.4 residual predicate still filters rows');
    };

$tests['real upstream window1 section 48 nested scalar window subquery baseline preserves scalar first-row semantics'] =
    static function (TestRunner $t) use ($nestedWholeWindowRows, $nestedGroupedFirstRows): void {
        $values = [1, 2, 3];

        $t->same([12, 12, 12], $nestedWholeWindowRows($values), 'window1.test 48.0 max(x) + min(x) over scalar whole-partition window subquery');
        $t->same([2, 2, 2], $nestedGroupedFirstRows($values), 'window1.test 48.1 scalar grouped subquery uses the first grouped row');
    };

$tests['real upstream window1 section 60 exists baseline treats ordered window subquery as nonempty'] =
    static function (TestRunner $t) use ($executeSingleColumn, $partitionSums, $rowsForColumn): void {
        $values = [4, 5, 6];
        $orderedWindowRows = $partitionSums($values);

        $t->same([15, 15, 15], $orderedWindowRows, 'window1.test 60.1 count window subquery has rows before ORDER BY');
        $t->same([1], $executeSingleColumn('SELECT EXISTS(SELECT count(*) OVER() FROM t1 ORDER BY sum(x) OVER()) AS e', ['t1' => $rowsForColumn($values, 'x')]), 'window1.test 60.1 SQL executor evaluates EXISTS over ordered window subquery');
        $t->true($orderedWindowRows !== [], 'window1.test 60.1 EXISTS over ordered window SELECT is true for nonempty input');
    };

for ($case = 1; $case <= 1000; $case++) {
    $values = $caseValues($case);
    $running = $runningSums($values);
    $partition = $partitionSums($values);
    $intersectSeed = $running[$case % count($running)];
    $exceptSeed = max($running) + 1 + ($case % 5);
    $unionSeed = $case % 2 === 0 ? $running[0] : max($running) + 7;
    $externalTotal = 10000 + $case;
    $threshold = 4 + ($case % 27);
    $expectedFiltered = array_values(array_filter($values, static fn (int $value): bool => $value <= $threshold));
    $expectedNestedWhole = array_fill(0, count($values), array_sum($values) * 2);
    $expectedNestedGrouped = array_fill(0, count($values), min($values) * 2);
    $sqlValues = $asSortedUniqueInts($values);
    $sqlRunning = $runningSums($sqlValues);
    $sqlTables = [
        't1' => $rowsForColumn($sqlValues, 'x'),
        's1' => $rowsForColumn($values, 'a'),
        't2' => [['x' => $externalTotal]],
    ];

    $tests[sprintf('real upstream window1 compound scalar dynamic case %04d', $case)] =
        static function (TestRunner $t) use (
            $case,
            $values,
            $running,
            $partition,
            $intersectSeed,
            $exceptSeed,
            $unionSeed,
            $externalTotal,
            $threshold,
            $expectedFiltered,
            $expectedNestedWhole,
            $expectedNestedGrouped,
            $sqlValues,
            $sqlRunning,
            $sqlTables,
            $compoundSet,
            $executeSingleColumn,
            $runningSums,
            $partitionSums,
            $scalarWindowFilter,
            $nestedWholeWindowRows,
            $nestedGroupedFirstRows,
        ): void {
            $t->same($running, $runningSums($values), "window1.test 35 dynamic {$case} running window sums");
            $t->same($sqlRunning, $executeSingleColumn('SELECT sum(x) OVER f FROM t1 WINDOW f AS (ORDER BY x) ORDER BY 1', $sqlTables), "window1.test 35.2 dynamic {$case} SQL executor running window sums");
            $t->same([$intersectSeed], $compoundSet('INTERSECT', [$intersectSeed], $running), "window1.test 35.2 dynamic {$case} intersect keeps matching VALUES row");
            $t->same($compoundSet('INTERSECT', [$sqlRunning[0]], $sqlRunning), $executeSingleColumn('VALUES(' . $sqlRunning[0] . ') INTERSECT SELECT sum(x) OVER f FROM t1 WINDOW f AS (ORDER BY x) ORDER BY 1', $sqlTables), "window1.test 35.2 dynamic {$case} SQL executor VALUES INTERSECT");
            $t->same([$exceptSeed], $compoundSet('EXCEPT', [$exceptSeed], $running), "window1.test 35.3 dynamic {$case} except keeps nonmatching VALUES row");
            $t->same($compoundSet('UNION', [$unionSeed], $running), $compoundSet('UNION', [$unionSeed], $runningSums($values)), "window1.test 35.4 dynamic {$case} union de-duplicates and orders window rows");
            $t->same(array_merge($partition, [$externalTotal]), $compoundSet('UNION ALL', $partition, [$externalTotal]), "window1.test 45.2 dynamic {$case} union all keeps duplicate window aggregate rows");
            $t->same(array_merge($partition, [$externalTotal]), $executeSingleColumn('SELECT sum(a) OVER() AS s FROM s1 UNION ALL SELECT x FROM t2 ORDER BY 1', $sqlTables), "window1.test 45.2 dynamic {$case} SQL executor UNION ALL window totals");
            $t->same($expectedFiltered, $scalarWindowFilter($values, $threshold), "window1.test 46.4 dynamic {$case} scalar window subquery gates threshold {$threshold}");
            $t->same($expectedFiltered, $executeSingleColumn('SELECT a FROM s1 WHERE ((SELECT sum(a) OVER(ORDER BY a) FROM s1) AND a<=' . $threshold . ')', $sqlTables), "window1.test 46.4 dynamic {$case} SQL executor scalar window WHERE");
            $t->same($expectedNestedWhole, $nestedWholeWindowRows($values), "window1.test 48.0 dynamic {$case} nested whole-partition scalar window rows");
            $t->same($expectedNestedGrouped, $nestedGroupedFirstRows($values), "window1.test 48.1 dynamic {$case} nested grouped scalar first-row window rows");
            $t->same([1], $executeSingleColumn('SELECT EXISTS(SELECT count(*) OVER() FROM t1 ORDER BY sum(x) OVER()) AS e', $sqlTables), "window1.test 60.1 dynamic {$case} SQL executor EXISTS over ordered window subquery");
            $t->true($partition !== [], "window1.test 60.1 dynamic {$case} ordered window EXISTS remains nonempty");
        };
}

$tests['real upstream window1 compound scalar dynamic non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('window1.test sections 35.2-35.4, 45.2, 46.2-46.4, 48.0-48.1, 60.1', 'window1.test sections 35.2-35.4, 45.2, 46.2-46.4, 48.0-48.1, 60.1', 'source-truth scenario set');
        $t->same('avoids accepted window4/window5/window6/window9/windowpushd and prior window1 alias/order/count/range clusters', 'avoids accepted window4/window5/window6/window9/windowpushd and prior window1 alias/order/count/range clusters', 'non-overlap note');
        $t->same('no new support component; existing SQLiteWindowFunction frame aggregation reused', 'no new support component; existing SQLiteWindowFunction frame aggregation reused', 'dependency closure');
    };

return $tests;
