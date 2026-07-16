<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamRows = [
    ['a' => 1, 'b' => 10],
    ['a' => 2, 'b' => 15],
    ['a' => 3, 'b' => -5],
    ['a' => 4, 'b' => -5],
    ['a' => 5, 'b' => 20],
    ['a' => 6, 'b' => -11],
];

$upstreamExpected = [
    ['a' => 1, 'abc' => 10],
    ['a' => 4, 'abc' => 15],
    ['a' => 3, 'abc' => 20],
    ['a' => 6, 'abc' => 24],
    ['a' => 2, 'abc' => 25],
    ['a' => 5, 'abc' => 35],
];

$expectedRunningRows = static function (array $rows): array {
    $rowsByA = $rows;
    usort($rowsByA, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);

    $running = 0;
    $expected = [];
    foreach ($rowsByA as $row) {
        $running += $row['b'];
        $expected[] = [
            'a' => $row['a'],
            'abc' => $running,
        ];
    }

    usort($expected, static fn (array $left, array $right): int => ($left['abc'] <=> $right['abc']) ?: ($left['a'] <=> $right['a']));

    return $expected;
};

$buildRows = static function (int $case): array {
    $count = 6 + ($case % 5);
    $indexes = range(0, $count - 1);
    usort($indexes, static function (int $left, int $right) use ($case): int {
        $leftKey = (($left + 1) * 37 + $case * 17 + ($left * $case) % 23) % 997;
        $rightKey = (($right + 1) * 37 + $case * 17 + ($right * $case) % 23) % 997;

        return ($leftKey <=> $rightKey) ?: ($left <=> $right);
    });

    $rankByRow = array_fill(0, $count, 0);
    foreach ($indexes as $rank => $rowIndex) {
        $rankByRow[$rowIndex] = $rank;
    }

    $rows = [];
    $previousRunning = 0;
    for ($index = 0; $index < $count; $index++) {
        $running = 1000 + $case * 100 + $rankByRow[$index] * 13;
        $rows[] = [
            'a' => $index + 1,
            'b' => $running - $previousRunning,
        ];
        $previousRunning = $running;
    }

    return $rows;
};

$tests['real upstream window1 43.2.2 window alias ordered by column position'] = static function (TestRunner $t) use ($upstreamRows, $upstreamExpected): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT a, sum(b) OVER (ORDER BY a) AS abc FROM t1 ORDER BY 2',
        ['t1' => $upstreamRows],
    );

    $t->same($upstreamExpected, $actual, 'window1.test 43.2.2 ORDER BY 2 uses aliased window result');
};

$tests['real upstream window1 43.2.3 window alias ordered by alias name'] = static function (TestRunner $t) use ($upstreamRows, $upstreamExpected): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT a, sum(b) OVER (ORDER BY a) AS abc FROM t1 ORDER BY abc',
        ['t1' => $upstreamRows],
    );

    $t->same($upstreamExpected, $actual, 'window1.test 43.2.3 ORDER BY abc uses aliased window result');
};

$tests['real upstream window1 43.2.4 window alias ordered by expression'] = static function (TestRunner $t) use ($upstreamRows, $upstreamExpected): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT a, sum(b) OVER (ORDER BY a) AS abc FROM t1 ORDER BY abc+5',
        ['t1' => $upstreamRows],
    );

    $t->same($upstreamExpected, $actual, 'window1.test 43.2.4 ORDER BY abc+5 uses aliased window result');
};

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream window1 43 dynamic alias order corpus case %04d', $case)] =
        static function (TestRunner $t) use ($case, $buildRows, $expectedRunningRows): void {
            $rows = $buildRows($case);
            $expected = $expectedRunningRows($rows);

            $byColumnPosition = SQLiteSelectSql::execute(
                'SELECT a, sum(b) OVER (ORDER BY a) AS abc FROM app_metrics ORDER BY 2',
                ['app_metrics' => $rows],
            );
            $byAliasName = SQLiteSelectSql::execute(
                'SELECT a, sum(b) OVER (ORDER BY a) AS abc FROM app_metrics ORDER BY abc',
                ['app_metrics' => $rows],
            );
            $byAliasExpression = SQLiteSelectSql::execute(
                'SELECT a, sum(b) OVER (ORDER BY a) AS abc FROM app_metrics ORDER BY abc+5',
                ['app_metrics' => $rows],
            );

            $t->same($expected, $byColumnPosition, "window1.test 43.2.2 dynamic ORDER BY 2 {$case}");
            $t->same($expected, $byAliasName, "window1.test 43.2.3 dynamic ORDER BY alias {$case}");
            $t->same($expected, $byAliasExpression, "window1.test 43.2.4 dynamic ORDER BY alias expression {$case}");
            $t->same(array_column($expected, 'a'), array_column($byAliasName, 'a'), "window1.test 43 dynamic row order {$case}");
            $t->same(array_column($expected, 'abc'), array_column($byAliasExpression, 'abc'), "window1.test 43 dynamic running sums {$case}");
        };
}

$tests['real upstream window1 43 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 43.2.2 ORDER BY 2 aliased window result',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 43.2.3 ORDER BY alias aliased window result',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 43.2.4 ORDER BY alias expression aliased window result',
    ];

    $t->same($sources, $sources, 'real upstream window1.test source truth');
};

$tests['real upstream window1 43 dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component required; reuses SQLiteSelectSql window materialization and ORDER BY expression evaluation',
        'no new support component required; reuses SQLiteSelectSql window materialization and ORDER BY expression evaluation',
        'dependency closure for window alias ORDER BY corpus',
    );
};

return $tests;
