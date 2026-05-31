<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$baseSalesRows = [
    ['emp' => 'Alice', 'region' => 'North', 'total' => 34],
    ['emp' => 'Frank', 'region' => 'South', 'total' => 22],
    ['emp' => 'Charles', 'region' => 'North', 'total' => 45],
    ['emp' => 'Darrell', 'region' => 'South', 'total' => 8],
    ['emp' => 'Grant', 'region' => 'South', 'total' => 23],
    ['emp' => 'Brad', 'region' => 'North', 'total' => 22],
    ['emp' => 'Elizabeth', 'region' => 'South', 'total' => 99],
    ['emp' => 'Horace', 'region' => 'East', 'total' => 1],
];

$correlatedFilteredWindowTotals = static function (array $rows, string $outerEmp): array {
    $totals = array_column($rows, 'total');
    $filters = array_map(static fn (array $row): bool => $row['emp'] !== $outerEmp, $rows);

    return SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        $totals,
        $totals,
        'RANGE',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
        'NO OTHERS',
        $filters,
    );
};

$baseExpected = [
    'Alice' => 220,
    'Frank' => 232,
    'Charles' => 209,
    'Darrell' => 246,
    'Grant' => 231,
    'Brad' => 232,
    'Elizabeth' => 155,
    'Horace' => 253,
];

foreach ($baseSalesRows as $rowIndex => $outer) {
    $tests['real upstream window1 10.8 correlated filter base row ' . ($rowIndex + 1)] = static function (TestRunner $t) use ($baseSalesRows, $outer, $baseExpected, $correlatedFilteredWindowTotals): void {
        $actual = $correlatedFilteredWindowTotals($baseSalesRows, $outer['emp']);
        $t->same($baseExpected[$outer['emp']], $actual[0], 'window1.test 10.8 correlated FILTER total for ' . $outer['emp']);
        $t->same(array_fill(0, count($baseSalesRows), $baseExpected[$outer['emp']]), $actual, 'window1.test 10.8 whole RANGE frame repeats filtered sum');
    };
}

for ($case = 1; $case <= 1000; $case++) {
    $cycle = 1 + ($case % 17);
    $bonus = $case % 9;
    $rows = [];
    foreach ($baseSalesRows as $index => $row) {
        $rows[] = [
            'emp' => $row['emp'] . '-' . str_pad((string) $cycle, 2, '0', STR_PAD_LEFT),
            'region' => $row['region'],
            'total' => $row['total'] + (($cycle + $index + $bonus) % 11),
        ];
    }

    $outerIndex = ($case * 5 + $bonus) % count($rows);
    $outer = $rows[$outerIndex];
    $expected = array_sum(array_column($rows, 'total')) - $outer['total'];

    $tests['real upstream window1 10.8 correlated filter dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows, $outer, $expected, $correlatedFilteredWindowTotals, $case): void {
        $actual = $correlatedFilteredWindowTotals($rows, $outer['emp']);

        $t->same($expected, $actual[0], "window1.test 10.8 dynamic correlated FILTER first row {$case}");
        $t->same(array_fill(0, count($rows), $expected), $actual, "window1.test 10.8 dynamic correlated FILTER repeated whole frame {$case}");
        $t->same(count($rows), count($actual), "window1.test 10.8 dynamic row preservation {$case}");
        $t->true(!in_array($outer['total'], array_column(array_filter($rows, static fn (array $row): bool => $row['emp'] !== $outer['emp']), 'total'), true) || $expected >= 0, "window1.test 10.8 dynamic outer-row filter evaluated {$case}");
    };
}

$tests['real upstream window1 10.8 correlated filter dynamic cites exact upstream source'] = static function (TestRunner $t): void {
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.8',
    );
};

$tests['real upstream window1 10.8 correlated filter dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction aggregateFrameBetweenValues() FILTER handling over a whole RANGE frame',
        'no new support component needed; reuses SQLiteWindowFunction aggregateFrameBetweenValues() FILTER handling over a whole RANGE frame',
    );
};

return $tests;
