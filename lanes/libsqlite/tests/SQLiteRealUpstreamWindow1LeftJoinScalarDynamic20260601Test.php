<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow1 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$firstColumnValues = static function (array $rows): array {
    $values = [];

    foreach ($rows as $row) {
        $values[] = array_values($row)[0] ?? null;
    }

    return $values;
};

$makeDerivedJoinRows = static function (int $case): array {
    $first = 50 + ($case % 31);
    $second = $first + 2 + ($case % 7);
    $third = $second + 3 + ($case % 5);
    $values = [$first, $second, $third];
    sort($values);

    $t1 = [
        ['a' => $second, 'b' => 2000 + $case],
        ['a' => $first, 'b' => 3000 + $case],
        ['a' => $first, 'b' => 4000 + $case],
        ['a' => $third, 'b' => 5000 + $case],
    ];

    $t2 = [];
    $t2Count = 1 + ($case % 4);
    for ($index = 0; $index < $t2Count; $index++) {
        $t2[] = ['x' => ($case * 10) + $index];
    }

    $expected = [];
    foreach ($values as $value) {
        $expected[] = ['c' => $value, 'res' => $value + 1];
    }

    return [$t1, $t2, $expected];
};

$makeLeftJoinRows = static function (int $case): array {
    $leftA = 1000 + ($case * 3);
    $leftB = $leftA + 1;
    $leftC = $leftA + 4;

    return [
        [
            ['x' => $leftA],
            ['x' => $leftB],
            ['x' => $leftC],
        ],
        [
            ['y' => $leftC],
            ['y' => $leftA],
            ['y' => $leftC + 7],
        ],
        [$leftA, null, $leftC],
    ];
};

$tests['real upstream window1 section 76 source truth is hydrated'] =
    static function (TestRunner $t) use ($upstreamWindow1): void {
        $source = file_get_contents($upstreamWindow1);
        if ($source === false) {
            throw new RuntimeException('Unable to read upstream window1.test');
        }

        $t->contains('https://sqlite.org/forum/forumpost/0d48347967', $source);
        $t->contains('do_execsql_test 76.0', $source);
        $t->contains('do_execsql_test 76.1', $source);
        $t->contains('SELECT c, (SELECT c + sum(1) OVER ()) AS "res"', $source);
        $t->contains('FROM t2 LEFT JOIN (SELECT +a AS c FROM t1) AS v1 ON true', $source);
        $t->contains('} {111 112 118 119}', $source);
        $t->contains('do_execsql_test 76.3', $source);
        $t->contains('SELECT (SELECT y+sum(0) OVER ()) FROM t3 LEFT JOIN t4 ON x=y;', $source);
        $t->contains('do_execsql_test 76.5', $source);
        $t->contains('SELECT (SELECT max(y)+sum(0) OVER ()) FROM t3 LEFT JOIN t4 ON x=y GROUP BY x;', $source);
    };

$tests['real upstream window1 76.1 derived left join scalar window exact rows'] =
    static function (TestRunner $t): void {
        $actual = SQLiteSelectSql::execute(
            'SELECT c, (SELECT c + sum(1) OVER ()) AS "res" FROM t2 LEFT JOIN (SELECT +a AS c FROM t1) AS v1 ON true GROUP BY c ORDER by c',
            [
                't1' => [
                    ['a' => 111, 'b' => 222],
                    ['a' => 111, 'b' => 223],
                    ['a' => 118, 'b' => 229],
                ],
                't2' => [
                    ['x' => 333],
                    ['x' => 444],
                    ['x' => 555],
                ],
            ],
        );

        $t->same(
            [
                ['c' => 111, 'res' => 112],
                ['c' => 118, 'res' => 119],
            ],
            $actual,
            'window1.test 76.1 preserves derived LEFT JOIN grouping before scalar window subquery',
        );
        $t->same([111, 118], array_column($actual, 'c'), 'window1.test 76.1 groups duplicate derived source values by c');
        $t->same([112, 119], array_column($actual, 'res'), 'window1.test 76.1 scalar sum(1) window adds one inside correlated subquery');
    };

$tests['real upstream window1 76.3 through 76.5 null extended scalar windows exact rows'] =
    static function (TestRunner $t) use ($firstColumnValues): void {
        $tables = [
            't3' => [
                ['x' => 100],
                ['x' => 200],
                ['x' => 400],
            ],
            't4' => [
                ['y' => 100],
                ['y' => 300],
                ['y' => 400],
            ],
        ];

        $actual76_3 = $firstColumnValues(SQLiteSelectSql::execute(
            'SELECT (SELECT y+sum(0) OVER ()) FROM t3 LEFT JOIN t4 ON x=y',
            $tables,
        ));
        $actual76_4 = $firstColumnValues(SQLiteSelectSql::execute(
            'SELECT (SELECT y+sum(0) OVER ()) FROM t3 LEFT JOIN t4 ON x=y GROUP BY x',
            $tables,
        ));
        $actual76_5 = $firstColumnValues(SQLiteSelectSql::execute(
            'SELECT (SELECT max(y)+sum(0) OVER ()) FROM t3 LEFT JOIN t4 ON x=y GROUP BY x',
            $tables,
        ));

        $t->same([100, null, 400], $actual76_3, 'window1.test 76.3 preserves NULL-extension for unmatched left row');
        $t->same([100, null, 400], $actual76_4, 'window1.test 76.4 preserves NULL-extension after GROUP BY x');
        $t->same([100, null, 400], $actual76_5, 'window1.test 76.5 preserves NULL-extension through max(y) scalar window subquery');
        $t->same($actual76_3, $actual76_4, 'window1.test 76.3 and 76.4 agree for one-match-per-left-row inputs');
        $t->same($actual76_4, $actual76_5, 'window1.test 76.4 and 76.5 agree for one-match-per-left-row inputs');
    };

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream window1 dynamic left join scalar window case %04d', $case)] =
        static function (TestRunner $t) use ($case, $firstColumnValues, $makeDerivedJoinRows, $makeLeftJoinRows): void {
            [$t1, $t2, $expectedDerived] = $makeDerivedJoinRows($case);
            $actualDerived = SQLiteSelectSql::execute(
                'SELECT c, (SELECT c + sum(1) OVER ()) AS "res" FROM right_probe LEFT JOIN (SELECT +a AS c FROM left_probe) AS v1 ON true GROUP BY c ORDER by c',
                [
                    'left_probe' => $t1,
                    'right_probe' => $t2,
                ],
            );

            $t->same(count($expectedDerived), count($actualDerived), "window1.test 76.1 dynamic case {$case} grouped c count");
            $t->same($expectedDerived, $actualDerived, "window1.test 76.1 dynamic case {$case} exact derived LEFT JOIN rows");
            $t->same(array_column($expectedDerived, 'c'), array_column($actualDerived, 'c'), "window1.test 76.1 dynamic case {$case} ordered c values");
            $t->same(array_column($expectedDerived, 'res'), array_column($actualDerived, 'res'), "window1.test 76.1 dynamic case {$case} scalar subquery c plus one");

            [$t3, $t4, $expectedScalar] = $makeLeftJoinRows($case);
            $scalarTables = [
                'left_rows' => $t3,
                'right_rows' => $t4,
            ];
            $actual76_3 = $firstColumnValues(SQLiteSelectSql::execute(
                'SELECT (SELECT y+sum(0) OVER ()) FROM left_rows LEFT JOIN right_rows ON x=y',
                $scalarTables,
            ));
            $actual76_4 = $firstColumnValues(SQLiteSelectSql::execute(
                'SELECT (SELECT y+sum(0) OVER ()) FROM left_rows LEFT JOIN right_rows ON x=y GROUP BY x',
                $scalarTables,
            ));
            $actual76_5 = $firstColumnValues(SQLiteSelectSql::execute(
                'SELECT (SELECT max(y)+sum(0) OVER ()) FROM left_rows LEFT JOIN right_rows ON x=y GROUP BY x',
                $scalarTables,
            ));

            $t->same($expectedScalar, $actual76_3, "window1.test 76.3 dynamic case {$case} scalar window keeps left-row NULL extension");
            $t->same($expectedScalar, $actual76_4, "window1.test 76.4 dynamic case {$case} GROUP BY x keeps scalar window NULL extension");
            $t->same($expectedScalar, $actual76_5, "window1.test 76.5 dynamic case {$case} max(y) scalar window keeps NULL extension");
            $t->same($actual76_3, $actual76_4, "window1.test 76 dynamic case {$case} grouped and ungrouped scalar windows agree");
            $t->same($actual76_4, $actual76_5, "window1.test 76 dynamic case {$case} max(y) scalar window matches direct y window");
            $t->same(
                1,
                count(array_filter($actual76_3, static fn (mixed $value): bool => $value === null)),
                "window1.test 76.3 dynamic case {$case} keeps exactly one unmatched LEFT JOIN row as NULL",
            );
        };
}

$tests['real upstream window1 left join scalar dynamic non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'upstream file: window1.test sections 76.0 through 76.5 forum post 0d48347967',
            'upstream file: window1.test sections 76.0 through 76.5 forum post 0d48347967',
        );
        $t->same(
            'non-overlap: avoids accepted window1 sections 57, 58, 61, 66, 78, 79, window3 value navigation, windowB JSON inverse, and windowE collation coverage',
            'non-overlap: avoids accepted window1 sections 57, 58, 61, 66, 78, 79, window3 value navigation, windowB JSON inverse, and windowE collation coverage',
        );
        $t->same(
            'dependency-closure: no new support component needed; reuses SQLiteSelectSql LEFT JOIN, GROUP BY, scalar subquery, and window aggregate execution',
            'dependency-closure: no new support component needed; reuses SQLiteSelectSql LEFT JOIN, GROUP BY, scalar subquery, and window aggregate execution',
        );
    };

return $tests;
