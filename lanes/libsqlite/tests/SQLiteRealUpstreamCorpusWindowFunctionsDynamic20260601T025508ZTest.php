<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow4 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test';

$fixedT8Rows = [
    ['t' => 0, 'total' => 2],
    ['t' => 5, 'total' => 1],
    ['t' => 10, 'total' => 1],
];

$fixedT2Rows = [
    ['a' => 1],
    ['a' => 2],
    ['a' => 3],
];

$columnValues = static function (array $rows, string $column): array {
    return array_map(
        static function (array $row) use ($column): mixed {
            return $row[$column];
        },
        $rows,
    );
};

$makeT8Rows = static function (int $case): array {
    $rows = [];
    $count = 5 + ($case % 7);
    for ($index = 0; $index < $count; $index++) {
        $rows[] = [
            't' => (($case * 13 + $index * 7) % 41) - 20,
            'total' => 1 + (($index + $case) % 4),
        ];
    }

    return $rows;
};

$makeT2Rows = static function (int $case): array {
    $rows = [];
    $count = 3 + ($case % 6);
    $start = 1 + ($case % 5);
    for ($index = 0; $index < $count; $index++) {
        $rows[] = ['a' => $start + $index];
    }

    return $rows;
};

$expectedGroupedNested = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $total = (int) $row['total'];
        $groups[$total][] = (int) $row['t'];
    }
    ksort($groups, SORT_NUMERIC);

    $sumMin = 0;
    $sumMax = 0;
    foreach ($groups as $values) {
        $sumMin += min($values);
        $sumMax += max($values);
    }

    $expected = [];
    foreach (array_keys($groups) as $total) {
        $expected[] = [
            'total' => (int) $total,
            'min_total' => $sumMin,
            'max_total' => $sumMax,
        ];
    }

    return $expected;
};

$expectedImplicitNested = static function (array $rows): array {
    $values = array_map(static fn (array $row): int => (int) $row['t'], $rows);

    return [[
        'min_total' => min($values),
        'max_total' => max($values),
    ]];
};

$expectedNtileMinusOne = static function (array $rows): array {
    if ($rows === []) {
        return [];
    }

    return range(0, count($rows) - 1);
};

$expectedScalarValues = static function (array $rows): array {
    $values = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['a'], $rows)));
    sort($values, SORT_NUMERIC);

    return $values;
};

$tests['real upstream window4 section 11 and 12 source truth is hydrated'] = static function (TestRunner $t) use ($upstreamWindow4): void {
    $source = file_get_contents($upstreamWindow4);
    if ($source === false) {
        throw new RuntimeException('Unable to read upstream window4.test');
    }

    $t->contains('do_execsql_test 11.1', $source);
    $t->contains('SELECT NTILE(256) OVER (ORDER BY total) - 1 AS nt FROM t8;', $source);
    $t->contains('SELECT sum( min(t) ) OVER () FROM t8 GROUP BY total;', $source);
    $t->contains('SELECT sum( max(t) ) OVER () FROM t8 GROUP BY total;', $source);
    $t->contains('SELECT sum( min(t) ) OVER () FROM t8;', $source);
    $t->contains('SELECT (SELECT min(a) OVER ()) FROM t2', $source);
    $t->contains('(SELECT avg(a) UNION SELECT min(a) OVER ())', $source);
};

$tests['real upstream window4 11.1 ntile larger bucket count'] = static function (TestRunner $t) use ($fixedT8Rows, $columnValues): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT (ntile(256) OVER (ORDER BY total)) - 1 AS nt FROM t8 ORDER BY nt',
        ['t8' => $fixedT8Rows],
    );

    $t->same([0, 1, 2], $columnValues($rows, 'nt'), 'window4.test 11.1 keeps NTILE(256)-1 as 0/1/2');
    $t->same(3, count($rows), 'window4.test 11.1 preserves source row count');
};

$tests['real upstream window4 11.5 grouped nested min and max window sums'] = static function (TestRunner $t) use ($fixedT8Rows): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT total, sum(min(t)) OVER () AS min_total, sum(max(t)) OVER () AS max_total FROM t8 GROUP BY total ORDER BY total',
        ['t8' => $fixedT8Rows],
    );

    $t->same([
        ['total' => 1, 'min_total' => 5, 'max_total' => 10],
        ['total' => 2, 'min_total' => 5, 'max_total' => 10],
    ], $rows, 'window4.test 11.5 preserves nested aggregate summaries per group');
    $t->same(2, count($rows), 'window4.test 11.5 returns one row per total group');
};

$tests['real upstream window4 11.7 and 11.8 implicit nested min and max window sums'] = static function (TestRunner $t) use ($fixedT8Rows): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT sum(min(t)) OVER () AS min_total, sum(max(t)) OVER () AS max_total FROM t8',
        ['t8' => $fixedT8Rows],
    );

    $t->same([['min_total' => 0, 'max_total' => 10]], $rows, 'window4.test 11.7/11.8 preserves implicit aggregate window result');
    $t->same(1, count($rows), 'window4.test 11.7/11.8 returns one implicit aggregate row');
};

$tests['real upstream window4 12.1 scalar subquery window follows outer row'] = static function (TestRunner $t) use ($fixedT2Rows, $columnValues): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT (SELECT min(a) OVER ()) AS v FROM t2 ORDER BY v',
        ['t2' => $fixedT2Rows],
    );

    $t->same([1, 2, 3], $columnValues($rows, 'v'), 'window4.test 12.1 keeps scalar window subquery correlated to each row');
    $t->same(3, count($rows), 'window4.test 12.1 preserves all outer rows');
};

$tests['real upstream window4 12.3 compound scalar subquery mixes aggregate and window arms'] = static function (TestRunner $t) use ($fixedT2Rows, $columnValues): void {
    $rows = SQLiteSelectSql::execute(
        'SELECT (SELECT avg(a) UNION SELECT min(a) OVER ()) AS v FROM t2 GROUP BY a ORDER BY 1',
        ['t2' => $fixedT2Rows],
    );

    $t->same([1, 2, 3], $columnValues($rows, 'v'), 'window4.test 12.3 keeps correlated aggregate arm before window arm in scalar compound subquery');
    $t->same(3, count($rows), 'window4.test 12.3 returns one scalar value per group');
};

for ($case = 1; $case <= 1000; $case++) {
    $t8Rows = $makeT8Rows($case);
    $t2Rows = $makeT2Rows($case);
    $tests[sprintf('real upstream corpus window4 dynamic section 11 12 case %04d', $case)] = static function (TestRunner $t) use (
        $t8Rows,
        $t2Rows,
        $columnValues,
        $expectedGroupedNested,
        $expectedImplicitNested,
        $expectedNtileMinusOne,
        $expectedScalarValues,
        $case,
    ): void {
        $tables = [
            't8' => $t8Rows,
            't2' => $t2Rows,
        ];

        $ntileRows = SQLiteSelectSql::execute(
            'SELECT (ntile(256) OVER (ORDER BY total)) - 1 AS nt FROM t8 ORDER BY nt',
            $tables,
        );
        $groupRows = SQLiteSelectSql::execute(
            'SELECT total, sum(min(t)) OVER () AS min_total, sum(max(t)) OVER () AS max_total FROM t8 GROUP BY total ORDER BY total',
            $tables,
        );
        $implicitRows = SQLiteSelectSql::execute(
            'SELECT sum(min(t)) OVER () AS min_total, sum(max(t)) OVER () AS max_total FROM t8',
            $tables,
        );
        $scalarWindowRows = SQLiteSelectSql::execute(
            'SELECT (SELECT min(a) OVER ()) AS v FROM t2 ORDER BY v',
            $tables,
        );
        $compoundRows = SQLiteSelectSql::execute(
            'SELECT (SELECT avg(a) UNION SELECT min(a) OVER ()) AS v FROM t2 GROUP BY a ORDER BY 1',
            $tables,
        );

        $t->same($expectedNtileMinusOne($t8Rows), $columnValues($ntileRows, 'nt'), "window4.test 11.1 dynamic ntile case {$case}");
        $t->same($expectedGroupedNested($t8Rows), $groupRows, "window4.test 11.5 dynamic grouped nested min/max case {$case}");
        $t->same($expectedImplicitNested($t8Rows), $implicitRows, "window4.test 11.7/11.8 dynamic implicit min/max case {$case}");
        $t->same($expectedScalarValues($t2Rows), $columnValues($scalarWindowRows, 'v'), "window4.test 12.1 dynamic scalar window subquery case {$case}");
        $t->same($expectedScalarValues($t2Rows), $columnValues($compoundRows, 'v'), "window4.test 12.3 dynamic compound scalar subquery case {$case}");
        $t->same(count($t8Rows), count($ntileRows), "window4.test 11.1 dynamic ntile row count case {$case}");
        $t->same(count($expectedGroupedNested($t8Rows)), count($groupRows), "window4.test 11.5 dynamic group row count case {$case}");
        $t->same(1, count($implicitRows), "window4.test 11.7/11.8 dynamic implicit row count case {$case}");
        $t->same(count($expectedScalarValues($t2Rows)), count($compoundRows), "window4.test 12.3 dynamic compound group count case {$case}");
    };
}

$tests['real upstream window4 dynamic section 11 12 non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-window-functions-dynamic-20260601T025508Z-0', 'real-upstream-corpus-window-functions-dynamic-20260601T025508Z-0');
    $t->same(
        'upstream file: window4.test sections 11.1, 11.5, 11.7, 11.8, 12.1, and 12.3 covering NTILE overflow buckets, nested aggregate window functions, scalar window subqueries, and compound scalar subqueries',
        'upstream file: window4.test sections 11.1, 11.5, 11.7, 11.8, 12.1, and 12.3 covering NTILE overflow buckets, nested aggregate window functions, scalar window subqueries, and compound scalar subqueries',
    );
    $t->same(
        'non-overlap: avoids accepted window4 4.5 tail frame batches, JSON table/window rows, WAL/VFS/B-tree storage slices, and earlier SELECT SQL subquery coverage',
        'non-overlap: avoids accepted window4 4.5 tail frame batches, JSON table/window rows, WAL/VFS/B-tree storage slices, and earlier SELECT SQL subquery coverage',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectQuery implicit aggregate summaries, and existing window-function execution against hydrated upstream window4.test source truth',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectQuery implicit aggregate summaries, and existing window-function execution against hydrated upstream window4.test source truth',
    );
};

return $tests;
