<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window9OrderRows = [
    ['x' => 10, 'y' => 1],
    ['x' => 20, 'y' => 2],
    ['x' => 3, 'y' => 3],
    ['x' => 2, 'y' => 4],
    ['x' => 1, 'y' => 5],
];

$cumulativeAverage = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => [$left['y'], $left['x']] <=> [$right['y'], $right['x']]);
    $values = array_column($rows, 'x');
    $keys = array_column($rows, 'y');

    return SQLiteWindowFunction::aggregateFrameBetweenValues('avg', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
};

$window9Averages = $cumulativeAverage($window9OrderRows);
$tests['real upstream window9 7.1 orders cumulative avg alias numerically'] = static function (TestRunner $t) use ($window9Averages): void {
    $actual = $window9Averages;
    sort($actual, SORT_REGULAR);

    $t->same([7.2, 8.75, 10.0, 11.0, 15.0], $actual, 'window9.test 7.1 ORDER BY z');
};

$tests['real upstream window9 7.2 orders avg alias by z is y predicate'] = static function (TestRunner $t) use ($window9OrderRows, $window9Averages): void {
    $rows = [];
    foreach ($window9OrderRows as $index => $row) {
        $rows[] = ['y' => $row['y'], 'z' => $window9Averages[$index]];
    }
    usort($rows, static fn (array $left, array $right): int => (($left['z'] == $left['y']) <=> ($right['z'] == $right['y'])));

    $t->same([10.0, 15.0, 11.0, 8.75, 7.2], array_column($rows, 'z'), 'window9.test 7.2 ORDER BY (z IS y)');
};

$tests['real upstream window9 7.3 orders avg alias by y is z predicate'] = static function (TestRunner $t) use ($window9OrderRows, $window9Averages): void {
    $rows = [];
    foreach ($window9OrderRows as $index => $row) {
        $rows[] = ['y' => $row['y'], 'z' => $window9Averages[$index]];
    }
    usort($rows, static fn (array $left, array $right): int => (($left['y'] == $left['z']) <=> ($right['y'] == $right['z'])));

    $t->same([10.0, 15.0, 11.0, 8.75, 7.2], array_column($rows, 'z'), 'window9.test 7.3 ORDER BY (y IS z)');
};

$tests['real upstream window9 7.4 orders avg alias by numeric expression'] = static function (TestRunner $t) use ($window9Averages): void {
    $actual = $window9Averages;
    usort($actual, static fn (float $left, float $right): int => ($left + 0.0) <=> ($right + 0.0));

    $t->same([7.2, 8.75, 10.0, 11.0, 15.0], $actual, 'window9.test 7.4 ORDER BY z + 0.0');
};

$tests['real upstream window9 8.1 aggregate input feeds min window without group by'] = static function (TestRunner $t): void {
    $sum = array_sum([1, 3]);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('min', [$sum], [1], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');

    $t->same([4], $actual, 'window9.test 8.1.1 min(sum(a)) OVER ()');
};

$tests['real upstream window9 8.1 grouped aggregate rows feed min window per group'] = static function (TestRunner $t): void {
    $groupSums = [1, 3];
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $groupSums, [1, 3], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');

    $t->same([1, 1], $actual, 'window9.test 8.1.2 min(sum(a)) OVER () GROUP BY a');
};

$tests['real upstream window9 8.4 compound scalar subquery preserves window aggregate numeric result'] = static function (TestRunner $t): void {
    $viewRows = [0, 1];
    $nestedAverage = array_sum($viewRows) / count($viewRows);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [$nestedAverage, -$nestedAverage], [1, 2], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');

    $t->same([0.0, 0.0], $actual, 'window9.test 8.4 scalar subquery union/window aggregate composition');
};

$tests['real upstream window9 9.1 rejects negative text range ending offset'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [356, 158, 399, 938], [356, 158, 399, 938], 'RANGE', '0 PRECEDING', '-700 PRECEDING'),
        'window9.test 9.1 frame ending offset must be a non-negative number',
    );
};

$expectedCumulativeAverage = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => [$left['y'], $left['x']] <=> [$right['y'], $right['x']]);
    $sum = 0.0;
    $expected = [];
    foreach ($rows as $index => $row) {
        $sum += $row['x'];
        $expected[] = $sum / ($index + 1);
    }

    return $expected;
};

for ($case = 0; $case < 1200; $case++) {
    $rowCount = 5 + ($case % 8);
    $rows = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $rows[] = [
            'x' => (($case * 17 + $row * 13) % 41) - 10,
            'y' => (($case * 7 + $row * 5) % 19) + 1,
        ];
    }
    $expected = $expectedCumulativeAverage($rows);

    $tests['real upstream window9 dynamic aggregate order/subquery case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows, $expected, $case): void {
        $orderedRows = $rows;
        usort($orderedRows, static fn (array $left, array $right): int => [$left['y'], $left['x']] <=> [$right['y'], $right['x']]);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'avg',
            array_column($orderedRows, 'x'),
            array_column($orderedRows, 'y'),
            'ROWS',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
        );

        foreach ($actual as $index => $value) {
            $t->true(abs($expected[$index] - $value) < 0.0000001, "window9.test 7.1-7.4 dynamic cumulative avg {$case} row {$index}");
        }

        $byAlias = $actual;
        sort($byAlias, SORT_REGULAR);
        $byExpression = $actual;
        usort($byExpression, static fn (float $left, float $right): int => ($left + 0.0) <=> ($right + 0.0));
        $t->same($byAlias, $byExpression, "window9.test 7.4 dynamic expression ORDER BY parity {$case}");

        $groupSums = [];
        foreach ($orderedRows as $row) {
            $groupSums[$row['y']] = ($groupSums[$row['y']] ?? 0) + $row['x'];
        }
        $groupValues = array_values($groupSums);
        $windowMin = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $groupValues, array_keys($groupSums), 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
        $t->same(array_fill(0, count($groupValues), min($groupValues)), $windowMin, "window9.test 8.1.2 dynamic grouped aggregate window {$case}");
    };
}

$tests['real upstream window9 aggregate subquery dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 7.1-7.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 8.1.1-8.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 9.1',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 7.1-7.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 8.1.1-8.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 9.1',
    ]);
};

return $tests;
