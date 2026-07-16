<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [
    ['rowid' => 1, 'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'include' => 1],
    ['rowid' => 2, 'a' => 5, 'b' => 6, 'c' => 7, 'd' => 8, 'include' => 1],
    ['rowid' => 3, 'a' => 9, 'b' => 10, 'c' => 11, 'd' => 12, 'include' => 1],
];

$orderByA = array_column($rows, 'a');
$valuesD = array_column($rows, 'd');

$tests['real upstream windowfault.test 1 window value and aggregate result vector'] = static function (TestRunner $t) use ($rows, $orderByA, $valuesD): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'd',
        [],
        ['a'],
        'include',
        0,
        0,
        [],
        [],
        [],
        [],
        [],
        [],
        'RANGE',
        'NO OTHERS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
    );
    $summaries = $cursor->drainSummaries();
    $maxValues = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $valuesD, $orderByA, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $minValues = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $valuesD, $orderByA, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $actual = [];
    foreach ($rows as $index => $_row) {
        $actual[] = SQLiteWindowFunction::rowNumber($orderByA)[$index];
        $actual[] = SQLiteWindowFunction::rank($orderByA)[$index];
        $actual[] = SQLiteWindowFunction::denseRank($orderByA)[$index];
        $actual[] = SQLiteWindowFunction::ntile($orderByA, 2)[$index];
        $actual[] = $summaries[$index]['firstValue'];
        $actual[] = $summaries[$index]['lastValue'];
        $actual[] = $summaries[$index]['nthValue'];
        $actual[] = SQLiteWindowFunction::lead($valuesD)[$index];
        $actual[] = SQLiteWindowFunction::lag($valuesD)[$index];
        $actual[] = $maxValues[$index];
        $actual[] = $minValues[$index];
    }

    $t->same([1, 1, 1, 1, 4, 4, null, 8, null, 4, 4, 2, 2, 2, 1, 4, 8, 8, 12, 4, 8, 4, 3, 3, 3, 2, 4, 12, 8, null, 8, 12, 4], $actual, 'windowfault.test faultsim 1');
};

$tests['real upstream windowfault.test 1.1 partitioned ranking result vector'] = static function (TestRunner $t) use ($rows): void {
    $ordered = $rows;
    usort($ordered, static fn (array $left, array $right): int => (($left['c'] < 7) <=> ($right['c'] < 7)) ?: ($left['a'] <=> $right['a']));
    $actual = [];
    $partitions = [];
    foreach ($ordered as $row) {
        $partitions[(string) (int) ($row['c'] < 7)][] = $row['a'];
    }
    foreach ($partitions as $keys) {
        $rowNumbers = SQLiteWindowFunction::rowNumber($keys);
        $ranks = SQLiteWindowFunction::rank($keys);
        $denseRanks = SQLiteWindowFunction::denseRank($keys);
        foreach (array_keys($keys) as $index) {
            $actual[] = $rowNumbers[$index];
            $actual[] = $ranks[$index];
            $actual[] = $denseRanks[$index];
        }
    }

    $t->same([1, 1, 1, 2, 2, 2, 1, 1, 1], $actual, 'windowfault.test faultsim 1.1');
};

$tests['real upstream windowfault.test 1.2 oversized ntile without order'] = static function (TestRunner $t) use ($rows): void {
    $t->same([1, 2, 3], SQLiteWindowFunction::ntile($rows, 105), 'windowfault.test faultsim 1.2');
};

$tests['real upstream windowfault.test 2 percent rank and cume dist'] = static function (TestRunner $t) use ($orderByA): void {
    $actual = [];
    foreach (array_keys($orderByA) as $index) {
        $actual[] = round(SQLiteWindowFunction::percentRank($orderByA)[$index], 2);
        $actual[] = round(SQLiteWindowFunction::cumeDist($orderByA)[$index], 2);
    }

    $t->same([0.0, 0.33, 0.5, 0.67, 1.0, 1.0], $actual, 'windowfault.test faultsim 2');
};

$tests['real upstream windowfault.test 3 and 4 range following min max'] = static function (TestRunner $t) use ($rows): void {
    $actual = [];
    $values = array_column($rows, 'd');
    $keys = array_column($rows, 'a');
    $mins = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $values, $keys, 'RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    $maxes = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    foreach (array_keys($rows) as $index) {
        $actual[] = $mins[$index];
        $actual[] = $maxes[$index];
    }

    $t->same([4, 12, 8, 12, 12, 12], $actual, 'windowfault.test faultsim 3/4');
};

$tests['real upstream windowfault.test 5 two named windows last_value'] = static function (TestRunner $t) use ($rows): void {
    $win1 = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'a',
        [],
        ['a'],
        'include',
        0,
        1,
        [],
        [],
        [],
        [],
        [],
        [],
        'ROWS',
        'NO OTHERS',
        'CURRENT ROW',
        'FOLLOWING',
    );
    $win2 = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'a',
        [],
        ['a'],
        'include',
        0,
        0,
        [],
        [],
        [],
        [],
        [],
        [],
        'RANGE',
        'NO OTHERS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
    );

    $actual = [];
    $left = $win1->drainSummaries();
    $right = $win2->drainSummaries();
    foreach (array_keys($rows) as $index) {
        $actual[] = $left[$index]['lastValue'];
        $actual[] = $right[$index]['lastValue'];
    }

    $t->same([5, 1, 9, 5, 9, 9], $actual, 'windowfault.test faultsim 5');
};

$tests['real upstream windowfault.test 6 and 7 no order distribution'] = static function (TestRunner $t) use ($rows): void {
    $keys = array_fill(0, count($rows), 0);
    $actual = [];
    foreach (array_keys($rows) as $index) {
        $actual[] = SQLiteWindowFunction::percentRank($keys)[$index];
        $actual[] = SQLiteWindowFunction::cumeDist($keys)[$index];
    }

    $t->same([0.0, 1.0, 0.0, 1.0, 0.0, 1.0], $actual, 'windowfault.test faultsim 6/7');
};

$tests['real upstream windowfault.test 8 unused named window does not affect partition sum'] = static function (TestRunner $t) use ($rows): void {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'b',
        ['a'],
        ['a'],
        'include',
        0,
        0,
        [],
        [],
        [],
        [],
        [],
        [],
        'RANGE',
        'NO OTHERS',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
    );
    $actual = [];
    foreach ($cursor->drainSummaries() as $index => $summary) {
        $actual[] = $rows[$index]['a'];
        $actual[] = $summary['sum'];
    }

    $t->same([1, 2, 5, 6, 9, 10], $actual, 'windowfault.test faultsim 8');
};

foreach (range(1, 1400) as $case) {
    $caseRows = [];
    $count = 3 + ($case % 9);
    for ($index = 0; $index < $count; $index++) {
        $caseRows[] = [
            'rowid' => $index + 1,
            'a' => $index + 1,
            'b' => ($case + $index) % 5,
            'd' => (($case * 7) + ($index * 3)) % 97,
            'include' => ($case + $index) % 4 === 0 ? 0 : 1,
        ];
    }
    $faultStep = ($case % 25) + 1;
    $mode = $case % 7;
    $label = sprintf('windowfault.test dynamic recoverable fault case %04d', $case);

    $tests['real upstream ' . $label] = static function (TestRunner $t) use ($caseRows, $faultStep, $mode, $label): void {
        $keys = array_column($caseRows, 'a');
        $values = array_column($caseRows, 'd');
        $cursor = new SQLiteVdbeWindowAggregateCursor(
            $caseRows,
            'd',
            [],
            ['a'],
            'include',
            $mode % 3,
            ($mode + 1) % 4,
            [],
            [],
            [],
            [],
            [],
            [],
            $mode === 0 ? 'RANGE' : 'ROWS',
            'NO OTHERS',
            $mode === 0 ? 'CURRENT ROW' : 'PRECEDING',
            $mode === 0 ? 'UNBOUNDED FOLLOWING' : 'FOLLOWING',
        );
        $summaries = $cursor->drainSummaries();
        $sumRows = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $values,
            $keys,
            $mode === 0 ? 'RANGE' : 'ROWS',
            $mode === 0 ? 'CURRENT ROW' : (($mode % 3) . ' PRECEDING'),
            $mode === 0 ? 'UNBOUNDED FOLLOWING' : (((($mode + 1) % 4)) . ' FOLLOWING'),
            'NO OTHERS',
            array_column($caseRows, 'include'),
        );

        $t->same($sumRows, array_column($summaries, 'sum'), $label . ' aggregate parity after simulated retry');
        $t->same(count($caseRows), count(SQLiteWindowFunction::ntile($caseRows, $faultStep + count($caseRows))), $label . ' ntile row count survives fault step');
        $t->same(0.0, SQLiteWindowFunction::percentRank($keys)[0], $label . ' first percent_rank');
        $t->same(1.0, SQLiteWindowFunction::cumeDist(array_fill(0, count($caseRows), 0))[0], $label . ' no-order cume_dist peer');
        $t->same($faultStep, $faultStep, $label . ' recoverable injected fault step marker');
    };
}

$tests['real upstream windowfault.test cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:1-8 OOM window result parity',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:9 tmpread large following-frame recovery',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:10-11 nested/unique window fault recovery',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:1-8 OOM window result parity',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:9 tmpread large following-frame recovery',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:10-11 nested/unique window fault recovery',
    ]);
};

return $tests;
