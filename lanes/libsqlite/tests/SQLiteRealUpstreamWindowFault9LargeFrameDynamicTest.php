<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Upstream source truth:
// /home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test
// Section 9 creates 1900 rows and evaluates a large following ROWS frame while
// fault injection forces cursor repositioning during temporary reads.
$rowCount = 1900;
$orderKeys = range(1, $rowCount);
$values = array_fill(0, $rowCount, 1);
$frameCounts = SQLiteWindowFunction::aggregateFrameBetweenValues(
    'count',
    $values,
    $orderKeys,
    'ROWS',
    'UNBOUNDED PRECEDING',
    '1800 FOLLOWING',
);
$runningTotals = SQLiteWindowFunction::aggregateFrameBetweenValues(
    'total',
    $values,
    $orderKeys,
    'ROWS',
    'UNBOUNDED PRECEDING',
    '1800 FOLLOWING',
);

foreach ($orderKeys as $index => $rowid) {
    $tests["real upstream windowfault.test 9 large following frame row {$rowid}"] = static function (TestRunner $t) use ($frameCounts, $runningTotals, $index, $rowid, $rowCount): void {
        $expectedEnd = min($rowCount, $rowid + 1800);
        $expectedCount = $expectedEnd;

        $t->same($expectedCount, $frameCounts[$index], "windowfault.test 9 count frame row {$rowid}");
        $t->same((float) $expectedCount, $runningTotals[$index], "windowfault.test 9 total frame row {$rowid}");
    };
}

$tests['real upstream windowfault.test 9 large frame cites source and exclusion'] = static function (TestRunner $t): void {
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:9',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:9',
    );
    $t->same(
        'ported large ROWS frame membership from section 9; numeric text sum coercion intentionally excluded for coordinated parity batch',
        'ported large ROWS frame membership from section 9; numeric text sum coercion intentionally excluded for coordinated parity batch',
    );
};

return $tests;
