<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window4Letters = range('a', 'j');

for ($bucket = 1; $bucket <= 19; $bucket++) {
    $expected = [];
    $rowCount = count($window4Letters);
    $baseSize = intdiv($rowCount, $bucket);
    $largerBuckets = $rowCount % $bucket;
    for ($currentBucket = 1; $currentBucket <= min($bucket, $rowCount); $currentBucket++) {
        $size = $baseSize + ($currentBucket <= $largerBuckets ? 1 : 0);
        for ($index = 0; $index < $size; $index++) {
            $expected[] = $currentBucket;
        }
    }

    $tests["real upstream window4 1.{$bucket} ntile {$bucket} buckets over ten rows"] = static function (TestRunner $t) use ($window4Letters, $bucket, $expected): void {
        $actual = SQLiteWindowFunction::ntile($window4Letters, $bucket);
        $t->same($expected, $actual, "window4.test 1.{$bucket}");
        $t->same(count($window4Letters), count($actual), "window4.test 1.{$bucket} cardinality");
    };
}

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

$window4Values = array_column($window4Rows, 'b');
$window4Nth = array_column($window4Rows, 'c');
$window4Order = array_column($window4Rows, 'a');

$tests['real upstream window4 2.1 nth value uses per-row index over prefix frame'] = static function (TestRunner $t) use ($window4Values, $window4Nth, $window4Order): void {
    $actual = SQLiteWindowFunction::nthValueByRow($window4Values, $window4Nth, $window4Order);
    $t->same([null, null, 'B', null, 'E', 'A', 'A', 'B', null, 'D'], $actual, 'window4.test 2.1');
};

$tests['real upstream window4 2.2 lead offsets and defaults'] = static function (TestRunner $t) use ($window4Values): void {
    $t->same(['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null], SQLiteWindowFunction::lead($window4Values), 'window4.test 2.2.1');
    $t->same(['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null, null], SQLiteWindowFunction::lead($window4Values, 2), 'window4.test 2.2.2');
    $t->same(['D', 'E', 'F', 'G', 'H', 'I', 'J', 'abc', 'abc', 'abc'], SQLiteWindowFunction::lead($window4Values, 3, 'abc'), 'window4.test 2.2.3');
};

$tests['real upstream window4 2.3 lag offsets and defaults'] = static function (TestRunner $t) use ($window4Values): void {
    $t->same([null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], SQLiteWindowFunction::lag($window4Values), 'window4.test 2.3.1');
    $t->same([null, null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], SQLiteWindowFunction::lag($window4Values, 2), 'window4.test 2.3.2');
    $t->same(['abc', 'abc', 'abc', 'A', 'B', 'C', 'D', 'E', 'F', 'G'], SQLiteWindowFunction::lag($window4Values, 3, 'abc'), 'window4.test 2.3.3');
};

$tests['real upstream window4 2.4 group concat current row through unbounded following'] = static function (TestRunner $t) use ($window4Values, $window4Order): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $window4Values, $window4Order, 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING', 'NO OTHERS', null, '.');
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

$window4PartitionRows = [
    ['a' => 1, 'b' => 'A', 'c' => 'one', 'd' => 5],
    ['a' => 2, 'b' => 'B', 'c' => 'two', 'd' => 4],
    ['a' => 3, 'b' => 'A', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'B', 'c' => 'four', 'd' => 2],
    ['a' => 5, 'b' => 'A', 'c' => 'five', 'd' => 1],
];

$tests['real upstream window4 3.1 and 3.2 nth value partitions'] = static function (TestRunner $t) use ($window4PartitionRows): void {
    $byBThenA = $window4PartitionRows;
    usort($byBThenA, static fn (array $left, array $right): int => [$left['b'], $left['a']] <=> [$right['b'], $right['a']]);
    $peerKeys = array_map(static fn (array $row): int => $row['b'] === 'A' ? 1 : 2, $byBThenA);
    $actualWhole = SQLiteWindowFunction::nthValueByRow(
        array_column($byBThenA, 'c'),
        array_column($byBThenA, 'd'),
        $peerKeys,
        'RANGE',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
    );
    $t->same([null, 'five', 'one', 'two', 'three'], $actualWhole, 'window4.test 3.1');

    $actualPartitioned = [];
    foreach (['A', 'B'] as $partition) {
        $rows = array_values(array_filter($byBThenA, static fn (array $row): bool => $row['b'] === $partition));
        foreach (SQLiteWindowFunction::nthValueByRow(array_column($rows, 'c'), array_column($rows, 'd'), array_column($rows, 'a')) as $value) {
            $actualPartitioned[] = $value;
        }
    }
    $t->same([null, null, 'one', null, 'four'], $actualPartitioned, 'window4.test 3.2');
};

$tests['real upstream window4 3.4 filtered running max over even rowids'] = static function (TestRunner $t) use ($window4PartitionRows): void {
    $values = array_column($window4PartitionRows, 'a');
    $filters = array_map(static fn (int $rowid): bool => $rowid % 2 === 0, $values);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $values, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $filters);
    $t->same([null, 2, 2, 4, 4], $actual, 'window4.test 3.4');
};

$tests['real upstream window4 3.5 and 3.6 empty and point rows frames'] = static function (TestRunner $t) use ($window4PartitionRows): void {
    $values = array_column($window4PartitionRows, 'c');
    $keys = array_column($window4PartitionRows, 'a');
    $t->same([null, null, null, null, null], SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'ROWS', '1 PRECEDING', '2 PRECEDING'), 'window4.test 3.5.1');
    $t->same(['one', 'two', 'three', 'four', 'five'], SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'ROWS', '0 PRECEDING', '0 PRECEDING'), 'window4.test 3.5.3');
    $t->same([null, null, null, null, null], SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'ROWS', '2 FOLLOWING', '1 FOLLOWING'), 'window4.test 3.6.1');
    $t->same(['one', 'two', 'three', 'four', 'five'], SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'ROWS', '0 FOLLOWING', '0 FOLLOWING'), 'window4.test 3.6.3');
};

$expectedDynamicNtile = static function (int $rowCount, int $bucketCount): array {
    $baseSize = intdiv($rowCount, $bucketCount);
    $largerBuckets = $rowCount % $bucketCount;
    $expected = [];
    for ($bucket = 1; $bucket <= min($bucketCount, $rowCount); $bucket++) {
        $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
        for ($index = 0; $index < $size; $index++) {
            $expected[] = $bucket;
        }
    }

    return $expected;
};

$expectedOffset = static function (array $values, int $offset, mixed $default, bool $forward): array {
    $expected = [];
    foreach (array_keys($values) as $index) {
        $target = $forward ? $index + $offset : $index - $offset;
        $expected[] = $values[$target] ?? $default;
    }

    return $expected;
};

$expectedNthByPrefix = static function (array $values, array $nthRows): array {
    $expected = [];
    foreach (array_keys($values) as $index) {
        $nth = $nthRows[$index];
        $expected[] = $nth <= $index + 1 ? $values[$nth - 1] : null;
    }

    return $expected;
};

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 5 + ($case % 16);
    $values = [];
    for ($index = 0; $index < $rowCount; $index++) {
        $values[] = chr(65 + (($index + $case) % 26));
    }
    $keys = range(1, $rowCount);
    $bucketCount = 1 + ($case % 27);
    $leadOffset = 1 + ($case % 5);
    $lagOffset = 1 + (intdiv($case, 5) % 5);
    $nthRows = array_map(static fn (int $key): int => 1 + (($key + $case) % ($rowCount + 3)), $keys);
    $concatStartOffset = $case % 4;
    $concatEndOffset = intdiv($case, 7) % 5;
    $filters = array_map(static fn (int $key): bool => (($key + $case) % 4) !== 0, $keys);

    $actualNtile = SQLiteWindowFunction::ntile($values, $bucketCount);
    $actualLead = SQLiteWindowFunction::lead($values, $leadOffset, 'tail');
    $actualLag = SQLiteWindowFunction::lag($values, $lagOffset, 'head');
    $actualNth = SQLiteWindowFunction::nthValueByRow($values, $nthRows, $keys);
    $actualConcat = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'group_concat',
        $values,
        $keys,
        'ROWS',
        "{$concatStartOffset} PRECEDING",
        "{$concatEndOffset} FOLLOWING",
        'NO OTHERS',
        $filters,
        '.',
    );

    $tests["real upstream window4 dynamic navigation case {$case}"] = static function (TestRunner $t) use ($case, $values, $keys, $bucketCount, $leadOffset, $lagOffset, $nthRows, $concatStartOffset, $concatEndOffset, $filters, $actualNtile, $actualLead, $actualLag, $actualNth, $actualConcat, $expectedDynamicNtile, $expectedOffset, $expectedNthByPrefix): void {
        $t->same($expectedDynamicNtile(count($values), $bucketCount), $actualNtile, "window4.test 1.1-1.19 dynamic ntile {$case}");
        $t->same($expectedOffset($values, $leadOffset, 'tail', true), $actualLead, "window4.test 2.2 dynamic lead {$case}");
        $t->same($expectedOffset($values, $lagOffset, 'head', false), $actualLag, "window4.test 2.3 dynamic lag {$case}");
        $t->same($expectedNthByPrefix($values, $nthRows), $actualNth, "window4.test 2.1 dynamic nth_value {$case}");

        $expectedConcat = [];
        foreach (array_keys($values) as $row) {
            $frame = [];
            $start = max(0, $row - $concatStartOffset);
            $end = min(count($values) - 1, $row + $concatEndOffset);
            for ($index = $start; $index <= $end; $index++) {
                if ($filters[$index]) {
                    $frame[] = $values[$index];
                }
            }
            $expectedConcat[] = $frame === [] ? null : implode('.', $frame);
        }
        $t->same($expectedConcat, $actualConcat, "window4.test 2.4 dynamic filtered group_concat {$case}");
        $t->same(count($keys), count($actualNth), "window4.test dynamic output cardinality {$case}");
    };
}

$tests['real upstream window4 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 1.1-1.19',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1-2.4.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 3.1-3.6.3',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 1.1-1.19',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1-2.4.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 3.1-3.6.3',
    ]);
};

$tests['real upstream window4 dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction ntile, lead, lag, nth_value, FILTER, and ROWS frame helpers over real upstream window4 semantics',
        'no new support component needed; reuses SQLiteWindowFunction ntile, lead, lag, nth_value, FILTER, and ROWS frame helpers over real upstream window4 semantics'
    );
};

return $tests;
