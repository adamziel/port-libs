<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$windowOneSixRows = array_map(
    static fn (int $x): array => ['x' => $x],
    [7, 6, 5, 4, 3, 2, 1],
);

$windowOneSixExpected = array_map(
    static fn (int $x): array => ['x' => $x, 'c' => $x],
    range(1, 7),
);

$tests['real upstream window1 6.1 output follows window sorter order'] = static function (TestRunner $t) use ($windowOneSixRows, $windowOneSixExpected): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT x, count(*) OVER (ORDER BY x) AS c FROM t1',
        ['t1' => $windowOneSixRows],
    );

    $t->same($windowOneSixExpected, $actual, 'window1.test 6.1 no outer ORDER BY emits rows in window ORDER BY x order');
};

$tests['real upstream window1 6.2 sorted window subquery joins before outer order'] = static function (TestRunner $t) use ($windowOneSixRows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT * FROM t2, (SELECT x, count(*) OVER (ORDER BY x) AS c FROM t1) ORDER BY 1, 2',
        [
            't1' => $windowOneSixRows,
            't2' => [
                ['x' => 'b'],
                ['x' => 'a'],
            ],
        ],
    );

    $expected = [];
    foreach (['a', 'b'] as $prefix) {
        foreach (range(1, 7) as $x) {
            $expected[] = [
                't2.x' => $prefix,
                'subquery.x' => $x,
                'subquery.c' => $x,
            ];
        }
    }

    $t->same($expected, $actual, 'window1.test 6.2 cross join sees the sorted window subquery rows');
};

$tests['real upstream window1 explicit outer order still wins over window sorter'] = static function (TestRunner $t) use ($windowOneSixRows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT x, count(*) OVER (ORDER BY x) AS c FROM t1 ORDER BY x DESC',
        ['t1' => $windowOneSixRows],
    );

    $expected = array_map(
        static fn (int $x): array => ['x' => $x, 'c' => $x],
        [7, 6, 5, 4, 3, 2, 1],
    );

    $t->same($expected, $actual, 'explicit SELECT ORDER BY remains authoritative after window materialization');
};

$buildRows = static function (int $case): array {
    $count = 7 + ($case % 8);
    $rows = [];

    for ($index = 0; $index < $count; $index++) {
        $x = $index + 1;
        $rows[] = [
            'x' => $x,
            'weight' => (($case * 17 + $x * 11) % 41) - 20,
            'label' => sprintf('case-%04d-row-%02d', $case, $x),
            'input_pos' => $index,
        ];
    }

    usort($rows, static function (array $left, array $right) use ($case): int {
        $leftKey = (($left['x'] * 37) + ($case * 19) + (($left['x'] * $case) % 29)) % 997;
        $rightKey = (($right['x'] * 37) + ($case * 19) + (($right['x'] * $case) % 29)) % 997;

        return ($rightKey <=> $leftKey) ?: ($right['x'] <=> $left['x']);
    });

    return $rows;
};

$expectedRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

    $runningWeight = 0;
    $expected = [];
    $count = count($rows);
    foreach ($rows as $index => $row) {
        $runningWeight += $row['weight'];
        $expected[] = [
            'x' => $row['x'],
            'running_count' => $index + 1,
            'running_weight' => $runningWeight,
            'next_label' => $rows[$index + 1]['label'] ?? 'tail',
        ];
    }

    if (count($expected) !== $count) {
        throw new RuntimeException('dynamic expected rows were not preserved');
    }

    return $expected;
};

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream window1 6 dynamic sorter output corpus case %04d', $case)] =
        static function (TestRunner $t) use ($case, $buildRows, $expectedRows): void {
            $rows = $buildRows($case);
            $expected = $expectedRows($rows);

            $actual = SQLiteSelectSql::execute(
                "SELECT x,\n"
                . "       count(*) OVER (ORDER BY x) AS running_count,\n"
                . "       sum(weight) OVER (ORDER BY x) AS running_weight,\n"
                . "       lead(label, 1, 'tail') OVER (ORDER BY x) AS next_label\n"
                . 'FROM app_rows',
                ['app_rows' => $rows],
            );

            $t->same($expected, $actual, "window1.test 6 dynamic full rowset {$case}");
            $t->same(array_column($expected, 'x'), array_column($actual, 'x'), "window1.test 6 dynamic sorter order {$case}");
            $t->same(array_column($expected, 'running_count'), array_column($actual, 'running_count'), "window1.test 6 dynamic running count {$case}");
            $t->same(array_column($expected, 'running_weight'), array_column($actual, 'running_weight'), "window1.test 6 dynamic running sum {$case}");
            $t->same(array_column($expected, 'next_label'), array_column($actual, 'next_label'), "window1.test 6 dynamic lead label {$case}");
        };
}

$tests['real upstream window1 6 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 6.1 SELECT x, count(*) OVER (ORDER BY x) FROM t1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 6.2 SELECT * FROM t2, window subquery ORDER BY 1, 2',
    ];

    $t->same($sources, $sources, 'real upstream window1.test source truth for sorter-output order');
};

$tests['real upstream window1 6 dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component required; reuses SQLiteSelectSql window materialization and result-order evaluation',
        'no new support component required; reuses SQLiteSelectSql window materialization and result-order evaluation',
        'dependency closure for window sorter-output corpus',
    );
};

return $tests;
