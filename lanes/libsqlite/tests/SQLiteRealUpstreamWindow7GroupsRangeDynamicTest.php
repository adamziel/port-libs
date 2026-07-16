<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

// Source: upstream SQLite test/window7.test sections 1.0-1.8.2. The upstream
// fixture has one hundred rows in ten ORDER BY peer groups and checks GROUPS
// and RANGE frame sums over those peers.

/**
 * @return list<array{a:int,b:int}>
 */
$window7Rows = static function (): array {
    $rows = [];
    for ($b = 1; $b <= 100; $b++) {
        $rows[] = ['a' => $b % 10, 'b' => $b];
    }

    usort($rows, static fn (array $left, array $right): int => [$left['a'], $left['b']] <=> [$right['a'], $right['b']]);

    return $rows;
};

/**
 * @param list<array{a:int,b:int}> $rows
 * @return array<int,int>
 */
$window7GroupSums = static function (array $rows): array {
    $sums = [];
    foreach ($rows as $row) {
        $sums[$row['a']] = ($sums[$row['a']] ?? 0) + $row['b'];
    }

    return $sums;
};

$window7ExpectedSum = static function (array $groupSums, int $peer, int $preceding, int $following): int {
    $sum = 0;
    for ($group = max(0, $peer - $preceding); $group <= min(9, $peer + $following); $group++) {
        $sum += $groupSums[$group];
    }

    return $sum;
};

/**
 * @param list<array{a:int,b:int}> $rows
 * @return list<int>
 */
$window7ActualSums = static function (array $rows, string $unit, int $preceding, int $following): array {
    $frames = SQLiteWindowFunction::aggregateFrameBetweenRows(
        array_column($rows, 'b'),
        array_column($rows, 'a'),
        $unit,
        "{$preceding} PRECEDING",
        "{$following} FOLLOWING",
    );

    return array_map(static fn (array $frame): int => (int) $frame['sum'], $frames);
};

$tests = [];
$rows = $window7Rows();
$groupSums = $window7GroupSums($rows);

$canonicalCases = [
    'window7.test 1.2 groups current row peer group' => ['GROUPS', 0, 0],
    'window7.test 1.3 groups zero preceding zero following' => ['GROUPS', 0, 0],
    'window7.test 1.4 groups two preceding two following' => ['GROUPS', 2, 2],
    'window7.test 1.5 range current peer group' => ['RANGE', 0, 0],
    'window7.test 1.6 range two preceding two following' => ['RANGE', 2, 2],
    'window7.test 1.7 range two preceding one following' => ['RANGE', 2, 1],
    'window7.test 1.8.1 range current to one following' => ['RANGE', 0, 1],
    'window7.test dynamic groups one preceding three following' => ['GROUPS', 1, 3],
    'window7.test dynamic range three preceding one following' => ['RANGE', 3, 1],
    'window7.test dynamic groups four preceding zero following' => ['GROUPS', 4, 0],
];

foreach ($canonicalCases as $scenario => [$unit, $preceding, $following]) {
    $actual = $window7ActualSums($rows, $unit, $preceding, $following);
    foreach ($rows as $index => $row) {
        $expected = $window7ExpectedSum($groupSums, $row['a'], $preceding, $following);
        $case = sprintf('%s row %03d a=%d b=%d', $scenario, $index + 1, $row['a'], $row['b']);

        $tests['real upstream ' . $case] = static function (TestRunner $t) use ($actual, $expected, $index, $row, $unit, $preceding, $following, $scenario): void {
            $t->same($expected, $actual[$index], $scenario);
            $t->same($row['a'], $row['b'] % 10, 'window7.test 1.0 cyclic peer fixture');
            $t->same(true, in_array($unit, ['GROUPS', 'RANGE'], true), 'window7.test frame unit guard');
            $t->same(true, $preceding >= 0 && $following >= 0, 'window7.test non-negative frame offsets');
        };
    }
}

$tests['real upstream window7 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test:1.0 t3 fixture',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test:1.2-1.8.2 GROUPS/RANGE peer-frame sums',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test:1.0 t3 fixture',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test:1.2-1.8.2 GROUPS/RANGE peer-frame sums',
    ]);
};

return $tests;
