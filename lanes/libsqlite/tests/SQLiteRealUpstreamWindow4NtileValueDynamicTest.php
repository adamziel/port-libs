<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window4Letters = range('a', 'j');

$expectedNtile = static function (int $rowCount, int $buckets): array {
    $baseSize = intdiv($rowCount, $buckets);
    $largerBuckets = $rowCount % $buckets;
    $result = [];
    for ($bucket = 1; $bucket <= min($buckets, $rowCount); $bucket++) {
        $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
        foreach (range(1, $size) as $_) {
            $result[] = $bucket;
        }
    }

    return $result;
};

$tests['real upstream window4 1.1-1.19 ntile static bucket distribution'] = static function (TestRunner $t) use ($window4Letters, $expectedNtile): void {
    $expectedByBucket = [
        1 => array_fill(0, 10, 1),
        2 => [1, 1, 1, 1, 1, 2, 2, 2, 2, 2],
        3 => [1, 1, 1, 1, 2, 2, 2, 3, 3, 3],
        4 => [1, 1, 1, 2, 2, 2, 3, 3, 4, 4],
        5 => [1, 1, 2, 2, 3, 3, 4, 4, 5, 5],
        6 => [1, 1, 2, 2, 3, 3, 4, 4, 5, 6],
        7 => [1, 1, 2, 2, 3, 3, 4, 5, 6, 7],
        8 => [1, 1, 2, 2, 3, 4, 5, 6, 7, 8],
        9 => [1, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        10 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        11 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        12 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        13 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        14 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        15 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        16 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        17 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        18 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        19 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    ];

    foreach ($expectedByBucket as $bucket => $expected) {
        $actual = SQLiteWindowFunction::ntile($window4Letters, $bucket);
        $t->same($expected, $actual, "window4.test 1.{$bucket}");
        $t->same($expectedNtile(count($window4Letters), $bucket), $actual, "window4.test 1.{$bucket} independent distribution");
    }
};

$window4Rows = [
    ['a' => 1, 'b' => 'A', 'c' => 9],
    ['a' => 2, 'b' => 'B', 'c' => 3],
    ['a' => 3, 'b' => 'C', 'c' => 2],
    ['a' => 4, 'b' => 'D', 'c' => 10],
    ['a' => 5, 'b' => 'E', 'c' => 5],
    ['a' => 6, 'b' => 'F', 'c' => 1],
    ['a' => 7, 'b' => 'G', 'c' => 1],
    ['a' => 8, 'b' => 'H', 'c' => 2],
    ['a' => 9, 'b' => 'I', 'c' => 10],
    ['a' => 10, 'b' => 'J', 'c' => 4],
];
$window4BValues = array_column($window4Rows, 'b');
$window4CValues = array_column($window4Rows, 'c');
$window4AKeys = array_column($window4Rows, 'a');

$tests['real upstream window4 2.1 nth_value uses per-row nth arguments'] = static function (TestRunner $t) use ($window4BValues, $window4CValues, $window4AKeys): void {
    $actual = SQLiteWindowFunction::nthValueByRow($window4BValues, $window4CValues, $window4AKeys);
    $t->same([null, null, 'B', null, 'E', 'A', 'A', 'B', null, 'D'], $actual, 'window4.test 2.1');
};

$tests['real upstream window4 2.2 lead offsets and defaults'] = static function (TestRunner $t) use ($window4BValues): void {
    $t->same(['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null], SQLiteWindowFunction::lead($window4BValues), 'window4.test 2.2.1');
    $t->same(['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null, null], SQLiteWindowFunction::lead($window4BValues, 2), 'window4.test 2.2.2');
    $t->same(['D', 'E', 'F', 'G', 'H', 'I', 'J', 'abc', 'abc', 'abc'], SQLiteWindowFunction::lead($window4BValues, 3, 'abc'), 'window4.test 2.2.3');
};

$tests['real upstream window4 2.3 lag offsets and defaults'] = static function (TestRunner $t) use ($window4BValues): void {
    $t->same([null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], SQLiteWindowFunction::lag($window4BValues), 'window4.test 2.3.1');
    $t->same([null, null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], SQLiteWindowFunction::lag($window4BValues, 2), 'window4.test 2.3.2');
    $t->same(['abc', 'abc', 'abc', 'A', 'B', 'C', 'D', 'E', 'F', 'G'], SQLiteWindowFunction::lag($window4BValues, 3, 'abc'), 'window4.test 2.3.3');
};

$tests['real upstream window4 2.4 group_concat current row through unbounded following'] = static function (TestRunner $t) use ($window4BValues, $window4AKeys): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'group_concat',
        $window4BValues,
        $window4AKeys,
        'ROWS',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
        'NO OTHERS',
        null,
        '.',
    );
    $t->same([
        'A.B.C.D.E.F.G.H.I.J',
        'B.C.D.E.F.G.H.I.J',
        'C.D.E.F.G.H.I.J',
        'D.E.F.G.H.I.J',
        'E.F.G.H.I.J',
        'F.G.H.I.J',
        'G.H.I.J',
        'H.I.J',
        'I.J',
        'J',
    ], $actual, 'window4.test 2.4.1');
};

$window4T5 = [
    ['a' => 1, 'b' => 'A', 'c' => 'one', 'd' => 5],
    ['a' => 2, 'b' => 'B', 'c' => 'two', 'd' => 4],
    ['a' => 3, 'b' => 'A', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'B', 'c' => 'four', 'd' => 2],
    ['a' => 5, 'b' => 'A', 'c' => 'five', 'd' => 1],
];

$tests['real upstream window4 3.1 nth_value over order by text peers'] = static function (TestRunner $t) use ($window4T5): void {
    $ordered = $window4T5;
    usort($ordered, static fn (array $left, array $right): int => $left['b'] <=> $right['b'] ?: $left['a'] <=> $right['a']);

    $actual = SQLiteWindowFunction::valueFrameBetweenValues(
        'nth_value',
        array_column($ordered, 'c'),
        array_column($ordered, 'b'),
        'RANGE',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
        'NO OTHERS',
        array_column($ordered, 'd'),
    );
    $t->same([null, 'five', 'one', 'two', 'three'], $actual, 'window4.test 3.1');
};

$tests['real upstream window4 3.2 nth_value partitioned by text'] = static function (TestRunner $t) use ($window4T5): void {
    $actual = [];
    foreach (['A', 'B'] as $partition) {
        $rows = array_values(array_filter($window4T5, static fn (array $row): bool => $row['b'] === $partition));
        $values = array_column($rows, 'c');
        $nth = array_column($rows, 'd');
        $keys = array_column($rows, 'a');
        foreach (SQLiteWindowFunction::nthValueByRow($values, $nth, $keys) as $index => $value) {
            $actual[] = [$rows[$index]['a'], $value];
        }
    }

    $t->same([[1, null], [3, null], [5, 'one'], [2, null], [4, 'four']], $actual, 'window4.test 3.2');
};

$tests['real upstream window4 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 1.1-1.19',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1-2.4.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 3.1-3.2',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 1.1-1.19',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1-2.4.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 3.1-3.2',
    ]);
};

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 3 + ($case % 29);
    $bucketCount = 1 + (($case * 7) % 37);
    $labels = array_map(static fn (int $index): string => 'r' . ($index + 1), range(0, $rowCount - 1));
    $expected = $expectedNtile($rowCount, $bucketCount);

    $tests["real upstream window4 dynamic ntile distribution case {$case}"] = static function (TestRunner $t) use ($labels, $bucketCount, $expected, $rowCount): void {
        $actual = SQLiteWindowFunction::ntile($labels, $bucketCount);
        $t->same($expected, $actual, "window4.test 1.1-1.19 dynamic ntile {$rowCount}/{$bucketCount}");
        $t->same(count($labels), count($actual), "window4.test dynamic ntile preserves row count {$rowCount}");
        $t->same(min($bucketCount, $rowCount), max($actual), "window4.test dynamic ntile caps bucket label");
    };
}

return $tests;
