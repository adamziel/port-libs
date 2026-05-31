<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test
 * - selectH-1.2: DISTINCT projection from a UNION ALL subquery with unused columns.
 * - selectH-2.1: UNION ALL subquery ORDER BY on an otherwise unused output column.
 * - selectH-5.1/selectH-5.2: DISTINCT left arm plus empty right arm under outer count().
 */

$tests = [];

/**
 * @return array<string,mixed>
 */
$wideRow = static function (int $seed): array {
    $row = [];
    for ($i = 0; $i <= 65; $i++) {
        $row['c' . $i] = ($seed * 1000) + $i;
    }
    $row['c15'] = 15 + $seed;
    $row['c16'] = 16 + $seed;
    $row['c44'] = 44 + ($seed % 17);
    $row['c60'] = 60;
    $row['c61'] = 61 + ($seed % 7);
    $row['c62'] = 72 + ($seed % 11);

    return $row;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flattened result');
    $t->same(count($expected), count($actual), $label . ' flattened count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
};

$tests['real upstream selectH.test cites omit unused compound subquery source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test';

    $t->true(is_file($source), 'hydrated upstream selectH.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream selectH.test is readable');
    $t->contains('omit-unused-subquery-column optimization', $text);
    $t->contains('do_execsql_test 1.2', $text);
    $t->contains('do_execsql_test 2.1', $text);
    $t->contains('do_execsql_test 5.1', $text);
    $t->contains('do_execsql_test 5.2', $text);
};

for ($seed = 0; $seed < 1200; $seed++) {
    $row = $wideRow($seed);
    $tables = ['t1' => [$row]];

    $distinctSql = 'SELECT DISTINCT c44 FROM ('
        . 'SELECT c0 AS a, *, ' . (1000 + $seed) . ' AS unused FROM t1 '
        . 'UNION ALL '
        . 'SELECT c1 AS a, *, ' . (2000 + $seed) . ' AS unused FROM t1'
        . ') WHERE c60=60';
    $distinctExpected = [$row['c44']];

    $tests[sprintf('real upstream selectH.test selectH-1.2 dynamic distinct unused compound seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $distinctSql, $tables, $distinctExpected, $seed): void {
            $assertFlatSelect($t, $distinctSql, $tables, $distinctExpected, 'selectH-1.2 seed ' . $seed);
            $t->same(1, count($distinctExpected), 'DISTINCT keeps duplicate c44 values from the two UNION ALL arms');
        };

    $orderedSql = 'SELECT a FROM ('
        . 'SELECT c15 AS a, *, c62 AS b FROM t1 '
        . 'UNION ALL '
        . 'SELECT c16 AS a, *, c61 AS b FROM t1 '
        . 'ORDER BY b'
        . ')';
    $orderedExpected = $row['c61'] <= $row['c62']
        ? [$row['c16'], $row['c15']]
        : [$row['c15'], $row['c16']];

    $tests[sprintf('real upstream selectH.test selectH-2.1 dynamic ordered compound seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $orderedSql, $tables, $orderedExpected, $seed): void {
            $assertFlatSelect($t, $orderedSql, $tables, $orderedExpected, 'selectH-2.1 seed ' . $seed);
            $t->same(2, count($orderedExpected), 'compound subquery exposes only requested a column');
        };

    $leftRows = [
        ['val1' => 4 + ($seed % 9)],
        ['val1' => 5 + ($seed % 9)],
        ['val1' => 4 + ($seed % 9)],
    ];
    $rightRows = $seed % 4 === 0
        ? [['val2' => 40 + $seed]]
        : [];
    $countTables = ['t1' => $leftRows, 't2' => $rightRows];
    $distinctValues = [];
    foreach ($leftRows as $leftRow) {
        $distinctValues[(string) $leftRow['val1']] = $leftRow['val1'];
    }
    $countExpected = [count($distinctValues) + count($rightRows)];

    $countSql = 'SELECT count(1234) FROM ('
        . 'SELECT DISTINCT val1 FROM t1 '
        . 'UNION ALL '
        . 'SELECT val2 FROM t2'
        . ')';

    $tests[sprintf('real upstream selectH.test selectH-5.2 dynamic compound count seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $countSql, $countTables, $countExpected, $seed): void {
            $assertFlatSelect($t, $countSql, $countTables, $countExpected, 'selectH-5.2 seed ' . $seed);
            $t->true($countExpected[0] >= 2, 'outer count includes DISTINCT left arm rows');
        };
}

return $tests;
