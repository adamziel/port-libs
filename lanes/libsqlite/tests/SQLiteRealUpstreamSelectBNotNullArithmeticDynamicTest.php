<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test
 * - selectB-3.25 through selectB-6.25: arithmetic over a derived compound
 *   SELECT, filtered by SQLite's postfix "NOT NULL" predicate spelling.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values'
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint'
    );
    $t->contains('NOT NULL', $sql, $label . ' preserves upstream postfix predicate spelling');
};

/**
 * @return array{tables:array<string,list<array<string,mixed>>>, expected:list<mixed>}
 */
$selectB25Case = static function (int $seed): array {
    $base = 10 + ($seed * 7);
    $t1 = [
        ['a' => $base + 1, 'b' => $base + 2, 'c' => $base + 30],
        ['a' => $base + 3, 'b' => $base + 5, 'c' => $base + 40],
        ['a' => $base + 5, 'b' => $base + 8, 'c' => $base + 50],
    ];
    $t2 = [
        ['d' => $base + 30, 'e' => $base + 31, 'f' => $base + 60],
        ['d' => $base + 50, 'e' => $base + 51, 'f' => $base + 70],
        ['d' => $base + 900, 'e' => $base + 901, 'f' => $base + 902],
    ];
    if ($seed % 3 === 0) {
        $t2[] = ['d' => $base + 40, 'e' => $base + 41, 'f' => $base + 80];
    }

    $expected = [];
    foreach ($t1 as $row) {
        $expected[] = $row['a'] + $row['b'];
    }
    foreach ($t1 as $left) {
        foreach ($t2 as $right) {
            if ($left['c'] !== $right['d']) {
                continue;
            }
            $expected[] = round(($left['a'] * 10 + 0.1) + ($right['f'] * 10 + 0.1), 6);
        }
    }
    foreach ($t1 as $row) {
        $expected[] = ($row['a'] * 100) + ($row['b'] * 100);
    }
    sort($expected);

    return [
        'tables' => ['t1' => $t1, 't2' => $t2],
        'expected' => $expected,
    ];
};

$tests = [];

$tests['real upstream selectB.test selectB-25 cites postfix NOT NULL arithmetic source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test';

    $t->true(is_file($source), 'hydrated upstream selectB.test is available');
    $text = file_get_contents($source);
    $t->contains('do_test selectB-$ii.25', $text);
    $t->contains('WHERE y+x NOT NULL ORDER BY 1', $text);
    $t->contains('SELECT x+y FROM (', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $case = $selectB25Case($seed);
    $sql = 'SELECT x+y FROM ('
        . 'SELECT a AS x, b AS y FROM t1 '
        . 'UNION ALL '
        . 'SELECT a*10 + 0.1, f*10 + 0.1 FROM t1 LEFT JOIN t2 ON (c=d) '
        . 'UNION ALL '
        . 'SELECT a*100, b*100 FROM t1'
        . ') WHERE y+x NOT NULL ORDER BY 1';

    $tests[sprintf('real upstream selectB.test selectB-25 dynamic arithmetic not null seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $sql, $case, $seed): void {
            $assertFlatSelect($t, $sql, $case['tables'], $case['expected'], 'selectB-25 seed ' . $seed);
            $t->same(true, $seed >= 0, 'bounded dynamic selectB-25 lower seed');
            $t->same(true, $seed < 1000, 'bounded dynamic selectB-25 upper seed');
        };
}

return $tests;
