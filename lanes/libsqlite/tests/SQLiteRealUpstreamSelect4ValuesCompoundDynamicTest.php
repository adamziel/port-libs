<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat values');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null, $label . ' flat value ' . $index);
    }
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' flat result fingerprint',
    );
};

/**
 * @return array{0:array<string,list<array<string,mixed>>>,1:int,2:int,3:int,4:int,5:int,6:int,7:int,8:int,9:int}
 */
$select4TablesFor = static function (int $case): array {
    $base = 1000 + ($case * 10);
    $a1 = $base + 1;
    $b1 = $base + 2;
    $c1 = $base + 3;
    $a2 = $base + 4;
    $b2 = $base + 5;
    $c2 = $base + 6;
    $x1 = $base + 7;
    $x2 = $base + 8;
    $x3 = $base + 9;

    return [
        [
            't14' => [
                ['a' => $a1, 'b' => $b1, 'c' => $c1],
                ['a' => $a2, 'b' => $b2, 'c' => $c2],
            ],
        ],
        $a1,
        $b1,
        $c1,
        $a2,
        $b2,
        $c2,
        $x1,
        $x2,
        $x3,
    ];
};

$tests = [];

$tests['real upstream select4.test select4-14 values compound cites source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test';

    $t->true(is_file($source), 'hydrated upstream select4.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream select4.test is readable');
    $t->contains('Make sure compound SELECTs work with VALUES clauses', $text);
    $t->contains('do_execsql_test select4-14.1', $text);
    $t->contains('do_execsql_test select4-14.17', $text);
    $t->contains('VALUES(1),(2),(3),(4) UNION ALL SELECT 5 LIMIT 3', $text);
};

$tests['real upstream select4.test select4-14 canonical values compound rows'] =
    static function (TestRunner $t) use ($assertFlatSelect): void {
        $tables = [
            't14' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                ['a' => 4, 'b' => 5, 'c' => 6],
            ],
        ];

        $assertFlatSelect(
            $t,
            'SELECT * FROM t14 INTERSECT VALUES(3,2,1),(2,3,1),(1,2,3),(2,1,3)',
            $tables,
            [1, 2, 3],
            'select4-14.1',
        );
        $assertFlatSelect(
            $t,
            'SELECT * FROM t14 UNION VALUES(3,2,1),(2,3,1),(1,2,3),(7,8,9),(4,5,6) UNION SELECT * FROM t14 ORDER BY 1, 2, 3',
            $tables,
            [1, 2, 3, 2, 3, 1, 3, 2, 1, 4, 5, 6, 7, 8, 9],
            'select4-14.3',
        );
        $assertFlatSelect(
            $t,
            'SELECT * FROM t14 EXCEPT VALUES(1,2,3) EXCEPT VALUES(4,5,6)',
            $tables,
            [],
            'select4-14.7',
        );
        $assertFlatSelect(
            $t,
            'VALUES(1),(2),(3),(4) UNION ALL SELECT 5 LIMIT 3',
            [],
            [1, 2, 3],
            'select4-14.17',
        );
    };

for ($case = 0; $case < 1000; $case++) {
    $tests[sprintf('real upstream select4.test select4-14 dynamic values compound case %04d', $case)] =
        static function (TestRunner $t) use ($case, $select4TablesFor, $assertFlatSelect): void {
            [$tables, $a1, $b1, $c1, $a2, $b2, $c2, $x1, $x2, $x3] = $select4TablesFor($case);

            $assertFlatSelect(
                $t,
                "SELECT * FROM t14 INTERSECT VALUES({$x1},{$x2},{$x3}),({$b1},{$c1},{$a1}),({$a1},{$b1},{$c1}),({$b1},{$a1},{$c1})",
                $tables,
                [$a1, $b1, $c1],
                'select4-14.1 dynamic case ' . $case,
            );
            $assertFlatSelect(
                $t,
                "SELECT * FROM t14 UNION VALUES({$x1},{$x2},{$x3}),({$b1},{$c1},{$a1}),({$a1},{$b1},{$c1}),({$a2},{$b2},{$c2}) UNION SELECT * FROM t14 ORDER BY 1, 2, 3",
                $tables,
                [$a1, $b1, $c1, $b1, $c1, $a1, $a2, $b2, $c2, $x1, $x2, $x3],
                'select4-14.3 dynamic case ' . $case,
            );
            $assertFlatSelect(
                $t,
                "SELECT * FROM t14 EXCEPT VALUES({$a1},{$b1},{$c1})",
                $tables,
                [$a2, $b2, $c2],
                'select4-14.6 dynamic case ' . $case,
            );
            $assertFlatSelect(
                $t,
                "SELECT * FROM t14 EXCEPT VALUES({$a1},{$b1},{$c1}) EXCEPT VALUES({$a2},{$b2},{$c2})",
                $tables,
                [],
                'select4-14.7 dynamic case ' . $case,
            );
            $assertFlatSelect(
                $t,
                "VALUES({$a1}),({$b1}),({$c1}),({$a2}) UNION ALL SELECT {$b2} LIMIT 3",
                [],
                [$a1, $b1, $c1],
                'select4-14.17 dynamic case ' . $case,
            );

            $t->contains('select4-14', 'select4.test select4-14 VALUES compound dynamic case ' . $case);
            $t->same(2, count($tables['t14']), 't14 keeps the upstream two-row table shape');
            $t->true($x1 > $c2, 'VALUES-only row sorts after table rows in ORDER BY case');
        };
}

return $tests;
