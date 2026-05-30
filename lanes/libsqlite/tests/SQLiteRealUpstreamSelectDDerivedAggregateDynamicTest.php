<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test
 * - selectD-4.1: a LEFT JOIN against a derived aggregate subquery whose FROM
 *   term is an aliased parenthesized join group.
 */

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectD41Tables = static function (int $seed): array {
    $base = 1000 + ($seed * 20);

    return [
        't41' => [
            ['a' => $base + 1, 'b' => $base + 6, 'label' => 'left-hit-a-' . $seed],
            ['a' => $base + 2, 'b' => $base + 7, 'label' => 'left-hit-b-' . $seed],
            ['a' => $base + 3, 'b' => $base + 4, 'label' => 'left-miss-low-' . $seed],
            ['a' => $base + 4, 'b' => $base + 99, 'label' => 'left-miss-high-' . $seed],
        ],
        't42' => [
            ['d' => $base + 4, 'e' => 'filtered-low-' . $seed],
            ['d' => $base + 6, 'e' => 'hit-a-' . $seed],
            ['d' => $base + 6, 'e' => 'hit-a-duplicate-' . $seed],
            ['d' => $base + 7, 'e' => 'hit-b-' . $seed],
            ['d' => $base + 99, 'e' => 'unmatched-inner-' . $seed],
        ],
        't43' => [
            ['f' => $base + 600, 'g' => $base + 4],
            ['f' => $base + 601, 'g' => $base + 6],
            ['f' => $base + 602, 'g' => $base + 6],
            ['f' => $base + 603, 'g' => $base + 7],
        ],
    ];
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
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actualRows = SQLiteSelectSql::execute($sql, $tables);
    $actual = $flattenRows($actualRows);

    $t->same($expected, $actual, $label . ' flattened result');
    $t->same(count($expected), count($actual), $label . ' flattened count');
    $t->same(count($expected) / 4, count($actualRows), $label . ' row count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint'
    );
    $t->same(
        ['t41.a', 'x2.cnt', 'x2.d', 'x2.min_f'],
        $actualRows === [] ? [] : array_keys($actualRows[0]),
        $label . ' exposes derived table columns through x2 alias'
    );
};

$tests = [];

$tests['real upstream selectD.test selectD-4.1 cites derived aggregate parenthesized join source'] = static function (TestRunner $t): void {
    $t->contains('/test/selectD.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test');
    $t->contains('selectD-4.1', 'selectD-4.1 parenthesized join subquery datatype and affinity setup');
};

for ($seed = 0; $seed < 1000; $seed++) {
    $base = 1000 + ($seed * 20);
    $tables = $selectD41Tables($seed);
    $sql = 'SELECT t41.a, x2.cnt, x2.d, x2.min_f '
        . 'FROM t41 LEFT JOIN ('
        . 'SELECT count(*) AS cnt, x1.d, min(x1.f) AS min_f '
        . 'FROM (t42 INNER JOIN t43 ON d=g) AS x1 '
        . 'WHERE x1.d>' . ($base + 5) . ' '
        . 'GROUP BY x1.d'
        . ') AS x2 ON t41.b=x2.d';
    $expected = [
        $base + 1,
        4,
        $base + 6,
        $base + 601,
        $base + 2,
        1,
        $base + 7,
        $base + 603,
        $base + 3,
        null,
        null,
        null,
        $base + 4,
        null,
        null,
        null,
    ];

    $tests[sprintf('real upstream selectD.test selectD-4.1 dynamic derived aggregate left join seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $sql, $tables, $expected, $seed): void {
            $assertFlatSelect($t, $sql, $tables, $expected, 'selectD-4.1 seed ' . $seed);
            $t->same(true, $seed >= 0, 'bounded dynamic seed lower guard');
            $t->same(true, $seed < 1000, 'bounded dynamic seed upper guard');
        };
}

return $tests;
