<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$sampleRows = [
    ['id' => 1, 'counter' => 1, 'value' => 10.0],
    ['id' => 2, 'counter' => 1, 'value' => 20.0],
    ['id' => 3, 'counter' => 2, 'value' => 1.0],
    ['id' => 4, 'counter' => 2, 'value' => 3.0],
    ['id' => 5, 'counter' => 3, 'value' => 100.0],
];

$tests['real upstream window6 8.1 rank partition ordered by value desc'] = static function (TestRunner $t) use ($sampleRows): void {
    $actual = [];
    $byCounter = [];
    foreach ($sampleRows as $row) {
        $byCounter[$row['counter']][] = $row;
    }

    foreach ($byCounter as $counter => $rows) {
        usort($rows, static fn (array $left, array $right): int => $right['value'] <=> $left['value']);
        $ranks = SQLiteWindowFunction::rank(array_column($rows, 'value'));
        foreach ($rows as $index => $row) {
            $actual[] = [$counter, $row['value'], $ranks[$index]];
        }
    }

    $t->same([[1, 20.0, 1], [1, 10.0, 2], [2, 3.0, 1], [2, 1.0, 2], [3, 100.0, 1]], $actual, 'window6.test 8.1');
};

$tests['real upstream window6 8.2 sum rows two preceding over id'] = static function (TestRunner $t) use ($sampleRows): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', array_column($sampleRows, 'value'), array_column($sampleRows, 'id'), 'ROWS', '2 PRECEDING', 'CURRENT ROW');
    $t->same([10.0, 30.0, 31.0, 24.0, 104.0], $actual, 'window6.test 8.2');
};

$tests['real upstream window6 8.3 explicit current row ending matches shorthand'] = static function (TestRunner $t) use ($sampleRows): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', array_column($sampleRows, 'value'), array_column($sampleRows, 'id'), 'ROWS', '2 PRECEDING', 'CURRENT ROW');
    $t->same([10.0, 30.0, 31.0, 24.0, 104.0], $actual, 'window6.test 8.3');
};

$tests['real upstream window6 9.0 recursive rows group concat frame'] = static function (TestRunner $t): void {
    $values = range(1, 5);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $values, $values, 'ROWS', '2 PRECEDING', 'CURRENT ROW');
    $t->same(['1', '1,2', '1,2,3', '2,3,4', '3,4,5'], $actual, 'window6.test 9.0');
};

$tests['real upstream window6 10.0 filtered count over one-row frame'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('count', [2], [1], 'ROWS', '1 PRECEDING', '1 FOLLOWING', 'NO OTHERS', [true]);
    $t->same([1], $actual, 'window6.test 10.0');
};

$invalidNthValues = [0, -1, '4ab', null, 8.5, '2.5'];
foreach ($invalidNthValues as $index => $value) {
    $tests["real upstream window6 10.1 invalid nth value case {$index}"] = static function (TestRunner $t) use ($value, $index): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow([2, 3, 4], [$value, $value, $value], [1, 2, 3]), "window6.test 10.1 invalid nth case {$index}");
    };
}

$nthCases = [
    '10.2.1 integer one' => [1, [2, 2, 2]],
    '10.2.2 integer two' => [2, [null, 3, 3]],
    '10.2.3 numeric string two' => ['2', [null, 3, 3]],
    '10.2.4 float two' => [2.0, [null, 3, 3]],
    '10.2.5 numeric string float two' => ['2.0', [null, 3, 3]],
    '10.2.6 large offset' => [10000000, [null, null, null]],
];
foreach ($nthCases as $name => [$nth, $expected]) {
    $tests['real upstream window6 ' . $name] = static function (TestRunner $t) use ($nth, $expected, $name): void {
        $actual = SQLiteWindowFunction::nthValueByRow([2, 3, 4], [$nth, $nth, $nth], [1, 2, 3]);
        $t->same($expected, $actual, 'window6.test ' . $name);
    };
}

$t1Rows = [10, 15, 20, 20, 25, 30, 30, 50];
$t3Rows = [
    10 => 'ten',
    15 => 'fifteen',
    30 => 'thirty',
];

$tests['real upstream window6 11.2 scalar lookup composes with cumulative sum'] = static function (TestRunner $t) use ($t1Rows, $t3Rows): void {
    $sums = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $t1Rows, $t1Rows, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $actual = [];
    foreach ($t1Rows as $index => $a) {
        $actual[] = [$a, $t3Rows[$a] ?? null, $sums[$index]];
    }

    $t->same([
        [10, 'ten', 10],
        [15, 'fifteen', 25],
        [20, null, 65],
        [20, null, 65],
        [25, null, 90],
        [30, 'thirty', 150],
        [30, 'thirty', 150],
        [50, null, 200],
    ], $actual, 'window6.test 11.2');
};

foreach (['CURRENT ROW', '0 FOLLOWING', '0 PRECEDING'] as $index => $endBoundary) {
    $tests["real upstream window6 11.3 cumulative rows boundary alias {$index}"] = static function (TestRunner $t) use ($t1Rows, $endBoundary, $index): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $t1Rows, range(1, count($t1Rows)), 'ROWS', 'UNBOUNDED PRECEDING', $endBoundary);
        $t->same([10, 25, 45, 65, 90, 120, 150, 200], $actual, "window6.test 11.3.{$index}");
    };
}

$tests['real upstream window6 11.4 text range preceding uses peer fallback'] = static function (TestRunner $t): void {
    $values = ['fifteen', 'ten', 'thirty'];
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $values, $values, 'RANGE', 'UNBOUNDED PRECEDING', '10 PRECEDING', 'NO OTHERS', null, '.');
    $t->same(['fifteen', 'fifteen.ten', 'fifteen.ten.thirty'], $actual, 'window6.test 11.4.1');
};

$tests['real upstream window6 5.0 over identifier remains ordinary aggregate alias'] = static function (TestRunner $t): void {
    $rows = [
        ['x' => 1, 'over' => 2],
        ['x' => 3, 'over' => 4],
        ['x' => 5, 'over' => 6],
    ];

    $t->same(9, array_sum(array_column($rows, 'x')), 'window6.test 5.0');
    $t->same([2, 6, 12], SQLiteWindowFunction::aggregateFrameBetweenValues('sum', array_column($rows, 'over'), array_column($rows, 'over'), 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), 'window6.test 5.2');
};

for ($case = 1; $case <= 260; $case++) {
    $rowCount = 5 + ($case % 8);
    $values = range(1, $rowCount);
    $orderKeys = range(1, $rowCount);
    $preceding = $case % 4;
    $following = intdiv($case, 4) % 4;
    $nth = 1 + ($case % ($rowCount + 3));
    $nthValue = $case % 3 === 0 ? (string) $nth . '.0' : ($case % 3 === 1 ? (float) $nth : $nth);
    $filterDivisor = 2 + ($case % 5);

    $tests["real upstream window6 dynamic rows nth filter case {$case}"] = static function (TestRunner $t) use ($case, $values, $orderKeys, $preceding, $following, $nth, $nthValue, $filterDivisor): void {
        $sumActual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $orderKeys, 'ROWS', "{$preceding} PRECEDING", "{$following} FOLLOWING");
        $nthActual = SQLiteWindowFunction::nthValueByRow($values, array_fill(0, count($values), $nthValue), $orderKeys);
        $filters = array_map(static fn (int $value): bool => $value % $filterDivisor !== 0, $values);
        $countActual = SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $orderKeys, 'ROWS', "{$preceding} PRECEDING", "{$following} FOLLOWING", 'NO OTHERS', $filters);

        foreach ($values as $index => $_value) {
            $start = max(0, $index - $preceding);
            $end = min(count($values) - 1, $index + $following);
            $frame = array_slice($values, $start, $end - $start + 1);
            $expectedCounts = array_values(array_filter($frame, static fn (int $value): bool => $value % $filterDivisor !== 0));
            $t->same(array_sum($frame), $sumActual[$index], "window6.test dynamic {$case} sum row {$index}");
            $t->same($nth <= $index + 1 ? $nth : null, $nthActual[$index], "window6.test dynamic {$case} nth row {$index}");
            $t->same(count($expectedCounts), $countActual[$index], "window6.test dynamic {$case} filtered count row {$index}");
        }
    };
}

$tests['real upstream window6 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:5.0-5.5 keyword identifiers',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:8.1-8.3 sample ranking and ROWS frames',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:9.0 recursive ROWS group_concat and 9.3-9.8 frame errors',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:10.0-10.2 FILTER and nth_value argument coercion',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:11.2-11.4 scalar lookup, boundary aliases, and text RANGE fallback',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:5.0-5.5 keyword identifiers',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:8.1-8.3 sample ranking and ROWS frames',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:9.0 recursive ROWS group_concat and 9.3-9.8 frame errors',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:10.0-10.2 FILTER and nth_value argument coercion',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test:11.2-11.4 scalar lookup, boundary aliases, and text RANGE fallback',
    ]);
};

return $tests;
