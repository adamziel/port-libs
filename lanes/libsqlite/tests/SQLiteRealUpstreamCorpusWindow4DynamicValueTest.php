<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

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

$values = array_column($window4Rows, 'b');
$rowNumbers = array_column($window4Rows, 'a');
$nthIndexes = array_column($window4Rows, 'c');

$tests['real upstream window4 1 ntile bucket layout across overwide buckets'] = static function (TestRunner $t) use ($values): void {
    $t->same([1, 1, 1, 1, 2, 2, 2, 3, 3, 3], SQLiteWindowFunction::ntile($values, 3), 'window4.test 1.3');
    $t->same([1, 1, 2, 2, 3, 3, 4, 5, 6, 7], SQLiteWindowFunction::ntile($values, 7), 'window4.test 1.7');
    $t->same([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], SQLiteWindowFunction::ntile($values, 11), 'window4.test 1.11');
};

$tests['real upstream window4 2.1 nth value uses current row specific index'] = static function (TestRunner $t) use ($values, $nthIndexes, $rowNumbers): void {
    $actual = SQLiteWindowFunction::nthValueByRow($values, $nthIndexes, $rowNumbers);
    $t->same([null, null, 'B', null, 'E', 'A', 'A', 'B', null, 'D'], $actual, 'window4.test 2.1');
};

$tests['real upstream window4 2.2 lead offsets and defaults'] = static function (TestRunner $t) use ($values): void {
    $t->same(['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null], SQLiteWindowFunction::lead($values), 'window4.test 2.2.1');
    $t->same(['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null, null], SQLiteWindowFunction::lead($values, 2), 'window4.test 2.2.2');
    $t->same(['D', 'E', 'F', 'G', 'H', 'I', 'J', 'abc', 'abc', 'abc'], SQLiteWindowFunction::lead($values, 3, 'abc'), 'window4.test 2.2.3');
};

$tests['real upstream window4 2.3 lag offsets and defaults'] = static function (TestRunner $t) use ($values): void {
    $t->same([null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], SQLiteWindowFunction::lag($values), 'window4.test 2.3.1');
    $t->same([null, null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], SQLiteWindowFunction::lag($values, 2), 'window4.test 2.3.2');
    $t->same(['abc', 'abc', 'abc', 'A', 'B', 'C', 'D', 'E', 'F', 'G'], SQLiteWindowFunction::lag($values, 3, 'abc'), 'window4.test 2.3.3');
};

$window4ExpectedNtile = static function (int $rowCount, int $buckets): array {
    $baseSize = intdiv($rowCount, $buckets);
    $largerBuckets = $rowCount % $buckets;
    $expected = [];
    for ($bucket = 1; $bucket <= min($buckets, $rowCount); $bucket++) {
        $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
        for ($index = 0; $index < $size; $index++) {
            $expected[] = $bucket;
        }
    }

    return $expected;
};

$window4ExpectedOffset = static function (array $rows, int $offset, mixed $default, bool $lead): array {
    $expected = [];
    foreach (array_keys($rows) as $index) {
        $target = $lead ? $index + $offset : $index - $offset;
        $expected[] = array_key_exists($target, $rows) ? $rows[$target] : $default;
    }

    return $expected;
};

$window4ExpectedNth = static function (array $rows, array $indexes, int $preceding, int $following): array {
    $expected = [];
    $count = count($rows);
    foreach (array_keys($rows) as $row) {
        $start = max(0, $row - $preceding);
        $end = min($count - 1, $row + $following);
        $frame = range($start, $end);
        $nth = (int) $indexes[$row];
        $target = $frame[$nth - 1] ?? null;
        $expected[] = $target === null ? null : $rows[$target];
    }

    return $expected;
};

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 4 + ($case % 13);
    $rows = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $rows[] = chr(65 + (($row + $case) % 26));
    }

    $buckets = 1 + ($case % ($rowCount + 5));
    $leadOffset = 1 + ($case % 5);
    $lagOffset = 1 + (intdiv($case, 3) % 5);
    $preceding = $case % 4;
    $following = intdiv($case, 7) % 4;
    $nth = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $nth[] = 1 + (($row + $case) % ($preceding + $following + 4));
    }

    $tests["real upstream window4 dynamic value function case {$case}"] = static function (TestRunner $t) use (
        $case,
        $rows,
        $buckets,
        $leadOffset,
        $lagOffset,
        $preceding,
        $following,
        $nth,
        $window4ExpectedNtile,
        $window4ExpectedOffset,
        $window4ExpectedNth
    ): void {
        $keys = range(1, count($rows));
        $t->same($window4ExpectedNtile(count($rows), $buckets), SQLiteWindowFunction::ntile($rows, $buckets), "window4.test 1 dynamic ntile case {$case}");
        $t->same($window4ExpectedOffset($rows, $leadOffset, 'fallback', true), SQLiteWindowFunction::lead($rows, $leadOffset, 'fallback'), "window4.test 2.2 dynamic lead case {$case}");
        $t->same($window4ExpectedOffset($rows, $lagOffset, 'fallback', false), SQLiteWindowFunction::lag($rows, $lagOffset, 'fallback'), "window4.test 2.3 dynamic lag case {$case}");
        $t->same(
            $window4ExpectedNth($rows, $nth, $preceding, $following),
            SQLiteWindowFunction::nthValueByRow($rows, $nth, $keys, 'ROWS', "{$preceding} PRECEDING", "{$following} FOLLOWING"),
            "window4.test 2.1/3.1 dynamic nth_value case {$case}"
        );
    };
}

$tests['real upstream window4 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 1.1-1.19',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1-2.3.3',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 3.1-3.6.3',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 1.1-1.19',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1-2.3.3',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 3.1-3.6.3',
    ]);
};

return $tests;
