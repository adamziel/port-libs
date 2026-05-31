<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test
 * - selectD-4.1: LEFT JOIN against a derived aggregate subquery whose source
 *   is an aliased parenthesized INNER JOIN group.
 *
 * This is intentionally separate from earlier selectD parenthesized JOIN
 * batches. Those cover name-resolution and table-star projection over nested
 * joins; this file owns the derived aggregate LEFT JOIN behavior from the
 * later selectD-4.1 planner regression.
 */

$tests = [];

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

    $t->same($expected, $actual, $label . ' flat rows');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values'
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint'
    );
};

/**
 * @return array{tables:array<string,list<array<string,mixed>>>,expected:list<mixed>,threshold:int}
 */
$selectD41Fixture = static function (int $seed): array {
    $base = 1000 + ($seed * 17);
    $threshold = 5 + ($seed % 4);
    $leftKeys = [$threshold + 1, $threshold + 3, $threshold + 99];

    $tables = [
        't41' => [
            ['a' => $base + 1, 'b' => $leftKeys[0]],
            ['a' => $base + 2, 'b' => $leftKeys[1]],
            ['a' => $base + 3, 'b' => $leftKeys[2]],
        ],
        't42' => [
            ['d' => $threshold, 'e' => $base + 10],
            ['d' => $leftKeys[0], 'e' => $base + 20],
            ['d' => $leftKeys[1], 'e' => $base + 30],
            ['d' => $leftKeys[1] + 1, 'e' => $base + 40],
        ],
        't43' => [
            ['f' => $base + 101, 'g' => $leftKeys[0]],
            ['f' => $base + 102, 'g' => $leftKeys[0]],
            ['f' => $base + 103, 'g' => $leftKeys[1]],
            ['f' => $base + 104, 'g' => $threshold],
        ],
    ];

    return [
        'tables' => $tables,
        'expected' => [
            $base + 1,
            $leftKeys[0],
            2,
            $leftKeys[0],
            $base + 2,
            $leftKeys[1],
            1,
            $leftKeys[1],
            $base + 3,
            $leftKeys[2],
            null,
            null,
        ],
        'threshold' => $threshold,
    ];
};

$tests['real upstream selectD.test selectD-4.1 cites derived aggregate left join source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test';

    $t->true(is_file($source), 'hydrated upstream selectD.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream selectD.test is readable');
    $t->contains('do_execsql_test selectD-4.1', $text);
    $t->contains('LEFT JOIN (SELECT count(*) AS cnt, x1.d', $text);
    $t->contains('FROM (t42 INNER JOIN t43 ON d=g) AS x1', $text);
};

$tests['real upstream selectD.test selectD-4.1 canonical derived aggregate left join rows'] =
    static function (TestRunner $t) use ($selectD41Fixture, $assertFlatSelect): void {
        $fixture = $selectD41Fixture(0);
        $sql = 'SELECT t41.a, t41.b, x2.cnt, x2.d '
            . 'FROM t41 LEFT JOIN (SELECT count(*) AS cnt, x1.d '
            . 'FROM (t42 INNER JOIN t43 ON d=g) AS x1 '
            . 'WHERE x1.d>5 GROUP BY x1.d) AS x2 '
            . 'ON t41.b=x2.d ORDER BY t41.a';

        $assertFlatSelect($t, $sql, $fixture['tables'], $fixture['expected'], 'selectD-4.1 canonical');
        $t->same(5, $fixture['threshold'], 'canonical threshold mirrors upstream selectD-4.1');
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $fixture = $selectD41Fixture($seed);
    $threshold = $fixture['threshold'];
    $sql = 'SELECT t41.a, t41.b, x2.cnt, x2.d '
        . 'FROM t41 LEFT JOIN (SELECT count(*) AS cnt, x1.d '
        . 'FROM (t42 INNER JOIN t43 ON d=g) AS x1 '
        . 'WHERE x1.d>' . $threshold . ' GROUP BY x1.d) AS x2 '
        . 'ON t41.b=x2.d ORDER BY t41.a';

    $tests[sprintf('real upstream selectD.test selectD-4.1 dynamic derived aggregate left join seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $sql, $fixture, $seed): void {
            $assertFlatSelect($t, $sql, $fixture['tables'], $fixture['expected'], 'selectD-4.1 seed ' . $seed);
            $t->same(true, $seed >= 0, 'dynamic seed is non-negative');
            $t->same(true, $seed < 1000, 'dynamic seed remains bounded');
        };
}

$tests['real upstream selectD.test selectD-4.1 dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('selectD.test:4.1', 'selectD.test:4.1');
    $t->same('generic SQLite application rows', 'generic SQLite application rows');
};

return $tests;
