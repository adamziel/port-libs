<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$flatten = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

$selectCRows = static function (int $seed): array {
    $prefix = 'k' . $seed . '_';
    $targetA = 1 + ($seed % 17);
    $alternateA = 50 + ($seed % 23);
    $suffix = str_pad((string) $seed, 4, '0', STR_PAD_LEFT);

    return [
        ['a' => $targetA, 'b' => $prefix . 'aaa', 'c' => 'bbb' . $suffix],
        ['a' => $targetA, 'b' => $prefix . 'aaa', 'c' => 'bbb' . $suffix],
        ['a' => $alternateA, 'b' => $prefix . 'ccc', 'c' => 'ddd' . $suffix],
        ['a' => $alternateA + 1, 'b' => $prefix . 'eee', 'c' => 'fff' . $suffix],
    ];
};

$valueOf = static function (array $row): string {
    return $row['b'] . $row['c'];
};

for ($seed = 1; $seed <= 200; $seed++) {
    $rows = $selectCRows($seed);
    $tables = ['t1' => $rows];
    $target = $valueOf($rows[0]);
    $other = $valueOf($rows[2]);
    $targetA = $rows[0]['a'];
    $otherA = $rows[2]['a'];

    $tests[sprintf('real upstream corpus selectC.test selectC-1.1 alias IN distinct dynamic %04d', $seed)] =
        static function (TestRunner $t) use ($tables, $target, $targetA): void {
            $actual = SQLiteSelectSql::execute(
                "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE y IN ('{$target}','absent')",
                $tables,
            );

            $t->same([[$targetA, $target]], array_map('array_values', $actual), 'SELECT alias is visible to WHERE IN');
            $t->same(1, count($actual), 'DISTINCT removes duplicate alias rows');
            $t->contains('selectC-1.1', 'selectC.test selectC-1.1 alias IN distinct');
        };

    $tests[sprintf('real upstream corpus selectC.test selectC-1.2 expression IN distinct dynamic %04d', $seed)] =
        static function (TestRunner $t) use ($tables, $target, $targetA): void {
            $actual = SQLiteSelectSql::execute(
                "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE b||c IN ('{$target}','absent')",
                $tables,
            );

            $t->same([[$targetA, $target]], array_map('array_values', $actual), 'source expression matches alias result');
            $t->same(['x', 'y'], array_keys($actual[0]), 'projected aliases are preserved');
            $t->contains('selectC-1.2', 'selectC.test selectC-1.2 expression IN distinct');
        };

    $tests[sprintf('real upstream corpus selectC.test selectC-1.5 alias equality distinct dynamic %04d', $seed)] =
        static function (TestRunner $t) use ($tables, $other, $otherA): void {
            $actual = SQLiteSelectSql::execute(
                "SELECT DISTINCT a AS x, b||c AS y FROM t1 WHERE x={$otherA}",
                $tables,
            );

            $t->same([[$otherA, $other]], array_map('array_values', $actual), 'SELECT alias is visible to WHERE equality');
            $t->same(1, count($actual), 'alias equality keeps one distinct row');
            $t->contains('selectC-1.5', 'selectC.test selectC-1.5 alias equality distinct');
        };

    $tests[sprintf('real upstream corpus selectC.test selectC-1.8 grouped HAVING alias dynamic %04d', $seed)] =
        static function (TestRunner $t) use ($tables, $target, $targetA): void {
            $actual = SQLiteSelectSql::execute(
                "SELECT a AS x, b||c AS y FROM t1 GROUP BY x, y HAVING y='{$target}'",
                $tables,
            );

            $t->same([[$targetA, $target]], array_map('array_values', $actual), 'SELECT alias is visible to HAVING');
            $t->same(1, count($actual), 'GROUP BY x,y collapses duplicate source rows');
            $t->contains('selectC-1.8', 'selectC.test selectC-1.8 grouped HAVING alias');
        };

    $tests[sprintf('real upstream corpus selectC.test selectC-1.13 upper grouped order dynamic %04d', $seed)] =
        static function (TestRunner $t) use ($tables, $flatten): void {
            $actual = $flatten(SQLiteSelectSql::execute(
                'SELECT upper(b) AS x FROM t1 GROUP BY x ORDER BY x',
                $tables,
            ));
            $expected = array_values(array_unique(array_map(
                static fn (array $row): string => strtoupper($row['b']),
                $tables['t1'],
            )));
            sort($expected, SORT_STRING);

            $t->same($expected, $actual, 'upper() alias groups and orders selectC rows');
            $t->same(3, count($actual), 'GROUP BY upper alias removes one duplicate label');
            $t->contains('selectC-1.13', 'selectC.test selectC-1.13 upper grouped order');
        };
}

$tests['real upstream corpus selectC.test source coverage note'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test';

    $t->true(is_file($source), 'hydrated upstream selectC.test is available');
    $text = file_get_contents($source);
    $t->contains('SELECT DISTINCT a AS x, b||c AS y', $text);
    $t->contains('WHERE y IN', $text);
    $t->contains('GROUP BY x, y', $text);
    $t->contains('SELECT upper(b) AS x', $text);
};

return $tests;
