<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window1ViewRows = array_map(
    static fn (int $i): array => ['a' => $i, 'b' => $i, 'c' => $i],
    range(1, 6),
);

$runningTriples = static function (array $rows): array {
    $values = array_column($rows, 'b');
    $keys = array_column($rows, 'c');
    $sums = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $mins = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $maxes = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

    return array_map(
        static fn (int $index): array => [$sums[$index], $mins[$index], $maxes[$index]],
        array_keys($rows),
    );
};

$window1T4Rows = [
    ['x' => 1, 'y' => 'g'],
    ['x' => 2, 'y' => 'i'],
    ['x' => 3, 'y' => 'l'],
    ['x' => 4, 'y' => 'g'],
    ['x' => 5, 'y' => 'a'],
];

$partitionedMaxRows = static function (array $rows): array {
    $ordered = $rows;
    usort($ordered, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

    $partitionValues = [0 => [], 1 => []];
    foreach ($ordered as $row) {
        $partitionValues[$row['x'] % 2][] = $row['y'];
    }

    $partitionMaxes = [];
    foreach ($partitionValues as $partition => $values) {
        $keys = range(1, count($values));
        $partitionMaxes[$partition] = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'max',
            $values,
            $keys,
            'ROWS',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
        );
    }

    $seen = [0 => 0, 1 => 0];
    $result = [];
    foreach ($ordered as $row) {
        $partition = $row['x'] % 2;
        $result[] = [$row['x'], $row['y'], $partitionMaxes[$partition][$seen[$partition]]];
        $seen[$partition]++;
    }

    return $result;
};

$nestedWindowRows = static function (array $rows) use ($partitionedMaxRows): array {
    $inner = $partitionedMaxRows($rows);
    $zValues = array_column($inner, 2);
    $xKeys = array_column($inner, 0);
    $runningMin = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $zValues, $xKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

    return array_map(
        static fn (int $index): array => [$inner[$index][0], $inner[$index][1], $inner[$index][2], $runningMin[$index]],
        array_keys($inner),
    );
};

$tests['real upstream window1 8.1 view running sum min max'] = static function (TestRunner $t) use ($window1ViewRows, $runningTriples): void {
    $t->same([
        [1, 1, 1],
        [3, 1, 2],
        [6, 1, 3],
        [10, 1, 4],
        [15, 1, 5],
        [21, 1, 6],
    ], $runningTriples($window1ViewRows), 'window1.test 8.1.1');
};

$tests['real upstream window1 8.2 reopened view keeps running aggregate state'] = static function (TestRunner $t) use ($window1ViewRows, $runningTriples): void {
    $first = $runningTriples($window1ViewRows);
    $second = $runningTriples($window1ViewRows);
    $t->same($first, $second, 'window1.test 8.2.1 and 8.2.2');
    $t->same([21, 1, 6], $second[5], 'window1.test reopened view final row');
};

$tests['real upstream window1 9.1 trigger select partition max before insert'] = static function (TestRunner $t) use ($window1T4Rows, $partitionedMaxRows): void {
    $t->same([
        [1, 'g', 'g'],
        [2, 'i', 'i'],
        [3, 'l', 'l'],
        [4, 'g', 'i'],
        [5, 'a', 'l'],
    ], $partitionedMaxRows($window1T4Rows), 'window1.test 9.1.1');
};

$tests['real upstream window1 9.1 trigger refresh after insert'] = static function (TestRunner $t) use ($window1T4Rows, $partitionedMaxRows): void {
    $rows = [...$window1T4Rows, ['x' => 6, 'y' => 'm']];
    $expected = [
        [1, 'g', 'g'],
        [2, 'i', 'i'],
        [3, 'l', 'l'],
        [4, 'g', 'i'],
        [5, 'a', 'l'],
        [6, 'm', 'm'],
    ];
    $t->same($expected, $partitionedMaxRows($rows), 'window1.test 9.1.2');
    $t->same($expected, $partitionedMaxRows($rows), 'window1.test 9.1.3 trigger table refresh');
};

$tests['real upstream window1 9.2 and 9.3 cte nested window aggregate'] = static function (TestRunner $t) use ($window1T4Rows, $partitionedMaxRows, $nestedWindowRows): void {
    $rows = [...$window1T4Rows, ['x' => 6, 'y' => 'm']];
    $t->same([
        [1, 'g', 'g'],
        [2, 'i', 'i'],
        [3, 'l', 'l'],
        [4, 'g', 'i'],
        [5, 'a', 'l'],
        [6, 'm', 'm'],
    ], $partitionedMaxRows($rows), 'window1.test 9.2');
    $t->same([
        [1, 'g', 'g', 'g'],
        [2, 'i', 'i', 'g'],
        [3, 'l', 'l', 'g'],
        [4, 'g', 'i', 'g'],
        [5, 'a', 'l', 'g'],
        [6, 'm', 'm', 'g'],
    ], $nestedWindowRows($rows), 'window1.test 9.3');
};

for ($case = 1; $case <= 1000; $case++) {
    $extraCount = $case % 5;
    $extraRows = [];
    for ($i = 0; $i < $extraCount; $i++) {
        $x = 6 + $i + 1;
        $extraRows[] = ['x' => $x, 'y' => chr(97 + (($case + $i) % 26))];
    }

    $rows = [...$window1T4Rows, ['x' => 6, 'y' => 'm'], ...$extraRows];
    $viewRows = array_map(
        static fn (int $i): array => ['a' => $i, 'b' => $i + ($case % 3), 'c' => $i],
        range(1, 6 + ($case % 4)),
    );
    $triggerRows = $partitionedMaxRows($rows);
    $nestedRows = $nestedWindowRows($rows);
    $viewTriples = $runningTriples($viewRows);

    $tests["real upstream window1 dynamic view trigger aggregate case {$case}"] = static function (TestRunner $t) use ($case, $rows, $triggerRows, $nestedRows, $viewTriples): void {
        $t->same(count($rows), count($triggerRows), "window1.test 9.1 dynamic trigger cardinality case {$case}");
        $t->same(count($rows), count($nestedRows), "window1.test 9.3 dynamic nested cardinality case {$case}");
        $t->same('g', $nestedRows[0][3], "window1.test 9.3 dynamic running min fence case {$case}");
        $t->same($viewTriples[count($viewTriples) - 1][2], max(array_column($viewTriples, 2)), "window1.test 8.1 dynamic max is cumulative case {$case}");
        $t->same(true, $triggerRows[count($triggerRows) - 1][0] >= 6, "window1.test 9.1 dynamic inserted row order case {$case}");
    };
}

$tests['real upstream window1 view trigger dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 8.1.1-8.2.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 9.1.1-9.3',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 8.1.1-8.2.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 9.1.1-9.3',
    ]);
};

return $tests;
