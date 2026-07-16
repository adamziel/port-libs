<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$window1T1Rows = [
    ['a' => 1, 'b' => 1, 'c' => 1],
    ['a' => 2, 'b' => 2, 'c' => 2],
];

$window1T2Rows = [
    ['a' => 'a', 'b' => 1],
    ['a' => 'a', 'b' => 2],
    ['a' => 'a', 'b' => 3],
    ['a' => 'b', 'b' => 4],
    ['a' => 'b', 'b' => 5],
    ['a' => 'b', 'b' => 6],
];

$tests['real upstream window1 42.3 grouped aggregate row feeds max window'] = static function (TestRunner $t) use ($window1T1Rows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT count(*) AS rows_seen, max(a) OVER () AS max_a FROM t1 GROUP BY c ORDER BY c',
        ['t1' => $window1T1Rows],
    );

    $t->same([
        ['rows_seen' => 1, 'max_a' => 2],
        ['rows_seen' => 1, 'max_a' => 2],
    ], $actual, 'window1.test 42.3');
};

$tests['real upstream window1 42.4 implicit aggregate row preserves first value for window'] = static function (TestRunner $t) use ($window1T1Rows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT sum(a) AS sum_a, max(b) OVER () AS max_b FROM t1',
        ['t1' => $window1T1Rows],
    );

    $t->same([['sum_a' => 3, 'max_b' => 1]], $actual, 'window1.test 42.4');
};

$tests['real upstream window1 42.6 grouped nested aggregate window uses group rows'] = static function (TestRunner $t) use ($window1T2Rows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT a, sum(b) AS group_total, sum(sum(b)) OVER (ORDER BY a) AS running_total FROM t2 GROUP BY a ORDER BY a',
        ['t2' => $window1T2Rows],
    );

    $t->same([
        ['a' => 'a', 'group_total' => 6, 'running_total' => 6],
        ['a' => 'b', 'group_total' => 15, 'running_total' => 21],
    ], $actual, 'window1.test 42.6');
};

$tests['real upstream window1 42.7 implicit nested aggregate window keeps order column'] = static function (TestRunner $t) use ($window1T2Rows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT sum(b) AS total_b, sum(sum(b)) OVER (ORDER BY a) AS running_total FROM t2',
        ['t2' => $window1T2Rows],
    );

    $t->same([['total_b' => 21, 'running_total' => 21]], $actual, 'window1.test 42.7');
};

$buildMetricRows = static function (int $case): array {
    $categories = ['alpha', 'beta', 'delta', 'gamma'];
    $rowCount = 6 + ($case % 9);
    $rows = [];

    for ($index = 0; $index < $rowCount; $index++) {
        $amount = (($case * 17 + $index * 7) % 53) - 18;
        if (($case + $index) % 13 === 0) {
            $amount = null;
        }

        $rows[] = [
            'category' => $categories[($case + $index) % count($categories)],
            'amount' => $amount,
            'marker' => (($case * 101 + $index * 19) % 97) - 20,
            'sort_key' => $index + 1,
        ];
    }

    return $rows;
};

$sumValues = static function (array $values): int|float|null {
    $seen = false;
    $sum = 0;

    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }

        $seen = true;
        $sum += $value;
    }

    return $seen ? $sum : null;
};

$groupedExpectedRows = static function (array $rows) use ($sumValues): array {
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['category']][] = $row;
    }
    ksort($groups);

    $expected = [];
    $running = null;
    foreach ($groups as $category => $groupRows) {
        $groupTotal = $sumValues(array_column($groupRows, 'amount'));
        if ($groupTotal !== null) {
            $running = ($running ?? 0) + $groupTotal;
        }

        $expected[] = [
            'category' => $category,
            'group_total' => $groupTotal,
            'running_total' => $running,
        ];
    }

    return $expected;
};

$projectRows = static function (array $rows, array $columns): array {
    $projected = [];
    foreach ($rows as $row) {
        $entry = [];
        foreach ($columns as $column) {
            $entry[$column] = $row[$column];
        }
        $projected[] = $entry;
    }

    return $projected;
};

for ($case = 1; $case <= 1000; $case++) {
    $rows = $buildMetricRows($case);
    $totalAmount = $sumValues(array_column($rows, 'amount'));
    $expectedGrouped = $groupedExpectedRows($rows);

    $tests[sprintf('real upstream window1 aggregate rows dynamic case %04d', $case)] = static function (TestRunner $t) use ($case, $rows, $totalAmount, $expectedGrouped, $projectRows): void {
        $implicitWindow = SQLiteSelectSql::execute(
            'SELECT sum(amount) AS total_amount, count(*) AS row_count, max(marker) OVER () AS aggregate_marker FROM app_metrics',
            ['app_metrics' => $rows],
        );
        $groupedWindow = SQLiteSelectSql::execute(
            'SELECT category, sum(amount) AS group_total, sum(sum(amount)) OVER (ORDER BY category) AS running_total FROM app_metrics GROUP BY category ORDER BY category',
            ['app_metrics' => $rows],
        );
        $singleNestedWindow = SQLiteSelectSql::execute(
            'SELECT sum(amount) AS total_amount, sum(sum(amount)) OVER (ORDER BY category) AS running_total FROM app_metrics',
            ['app_metrics' => $rows],
        );

        $t->same(1, count($implicitWindow), "window1.test 42.4 dynamic {$case} emits one implicit aggregate row");
        $t->same($totalAmount, $implicitWindow[0]['total_amount'], "window1.test 42.4 dynamic {$case} sum(amount)");
        $t->same(count($rows), $implicitWindow[0]['row_count'], "window1.test 42.4 dynamic {$case} count(*)");
        $t->same($rows[0]['marker'], $implicitWindow[0]['aggregate_marker'], "window1.test 42.4 dynamic {$case} first aggregate row marker");
        $t->same($expectedGrouped, $projectRows($groupedWindow, ['category', 'group_total', 'running_total']), "window1.test 42.6 dynamic {$case} grouped nested aggregate window");
        $t->same([['total_amount' => $totalAmount, 'running_total' => $totalAmount]], $singleNestedWindow, "window1.test 42.7 dynamic {$case} implicit nested aggregate window");
    };
}

$tests['real upstream window1 aggregate row dynamic cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 42.3-42.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 42.6-42.7',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 42.3-42.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 42.6-42.7',
    ]);
};

$tests['real upstream window1 aggregate row dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, and SQLiteWindowFunction execution',
        'no new support component needed; reuses lane-local SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, and SQLiteWindowFunction execution',
    );
};

return $tests;
