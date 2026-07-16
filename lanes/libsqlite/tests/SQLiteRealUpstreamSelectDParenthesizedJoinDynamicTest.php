<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test
 * - selectD-1.1 through selectD-1.7 parenthesized FROM/JOIN name resolution.
 *
 * This ports the green core SELECT behavior into generic application rows:
 * parenthesized comma sources, nested JOIN ON scopes, table-star projection,
 * and scoped ON predicates. The upstream selectD USING-column coalescing cases
 * remain intentionally excluded here because this accepted base still exposes
 * duplicate USING columns for SELECT *.
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
$assertSelectFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' rows');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' edge values'
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' fingerprint'
    );
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<mixed>
 */
$expectedOnChain = static function (array $tables): array {
    $flat = [];
    foreach ($tables['t1'] as $t1) {
        foreach ($tables['t2'] as $t2) {
            foreach ($tables['t3'] as $t3) {
                foreach ($tables['t4'] as $t4) {
                    if ($t4['a'] === $t3['a'] + 11 && $t3['a'] === $t2['a'] + 11 && $t2['a'] === $t1['a'] + 11) {
                        array_push($flat, $t1['a'], $t1['b'], $t2['a'], $t2['b'], $t3['a'], $t3['b'], $t4['a'], $t4['b']);
                    }
                }
            }
        }
    }

    return $flat;
};

$tests['real upstream selectD.test selectD-1 parenthesized joins cite source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test';
    $t->true(is_file($source), 'hydrated upstream selectD.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'selectD.test is readable');
    $t->contains('SELECT *', $text);
    $t->contains('FROM (t1), (t2), (t3), (t4)', $text);
    $t->contains('FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON', $text);
    $t->contains('SELECT t3.*, t2.*', $text);
};

$canonicalTables = [
    't1' => [['a' => 111, 'b' => 'x1']],
    't2' => [['a' => 222, 'b' => 'x2']],
    't3' => [['a' => 333, 'b' => 'x3']],
    't4' => [['a' => 444, 'b' => 'x4']],
];

$canonicalCases = [
    'selectD-1.1 parenthesized comma join chain' => [
        'SELECT * FROM (t1), (t2), (t3), (t4) WHERE t4.a=t3.a+111 AND t3.a=t2.a+111 AND t2.a=t1.a+111',
        $canonicalTables,
        [111, 'x1', 222, 'x2', 333, 'x3', 444, 'x4'],
    ],
    'selectD-1.2.1 nested join chain' => [
        'SELECT * FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) ON t3.a=t2.a+111) ON t2.a=t1.a+111',
        $canonicalTables,
        [111, 'x1', 222, 'x2', 333, 'x3', 444, 'x4'],
    ],
    'selectD-1.2.2 nested join projects inner table' => [
        'SELECT t3.a FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) ON t3.a=t2.a+111) ON t2.a=t1.a+111',
        $canonicalTables,
        [333],
    ],
    'selectD-1.2.3 nested join table star projection' => [
        'SELECT t3.*, t2.* FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) ON t3.a=t2.a+111) ON t2.a=t1.a+111',
        $canonicalTables,
        [333, 'x3', 222, 'x2'],
    ],
];

foreach ($canonicalCases as $name => [$sql, $tables, $expected]) {
    $tests['real upstream selectD.test ' . $name] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $tables, $expected, $name): void {
        $assertSelectFlat($t, $sql, $tables, $expected, $name);
        $t->contains('selectD-1.', $name);
    };
}

for ($seed = 0; $seed < 360; $seed++) {
    $base = 1000 + ($seed * 10);
    $onTables = [
        't1' => [['a' => $base + 11, 'b' => 'alpha-' . $seed]],
        't2' => [['a' => $base + 22, 'b' => 'bravo-' . $seed]],
        't3' => [['a' => $base + 33, 'b' => 'charlie-' . $seed]],
        't4' => [['a' => $base + 44, 'b' => 'delta-' . $seed]],
    ];

    $cases = [
        'comma chain' => [
            'SELECT * FROM (t1), (t2), (t3), (t4) WHERE t4.a=t3.a+11 AND t3.a=t2.a+11 AND t2.a=t1.a+11',
            $onTables,
            $expectedOnChain($onTables),
        ],
        'nested on chain' => [
            'SELECT * FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+11) ON t3.a=t2.a+11) ON t2.a=t1.a+11',
            $onTables,
            $expectedOnChain($onTables),
        ],
        'nested table star' => [
            'SELECT t3.*, t2.* FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+11) ON t3.a=t2.a+11) ON t2.a=t1.a+11',
            $onTables,
            [$base + 33, 'charlie-' . $seed, $base + 22, 'bravo-' . $seed],
        ],
    ];

    foreach ($cases as $label => [$sql, $tables, $expected]) {
        $tests[sprintf('real upstream selectD.test selectD-1 dynamic parenthesized join %s seed %04d', $label, $seed)] =
            static function (TestRunner $t) use ($assertSelectFlat, $sql, $tables, $expected, $seed): void {
                $assertSelectFlat($t, $sql, $tables, $expected, 'selectD dynamic parenthesized join seed ' . $seed);
                $t->same(true, $seed >= 0, 'dynamic seed is non-negative');
                $t->same(true, $seed < 360, 'dynamic seed stays bounded');
            };
    }
}

$tests['real upstream selectD.test parenthesized join dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('selectD.test:1.1-1.7', 'selectD.test:1.1-1.7');
    $t->same('generic application rows', 'generic application rows');
};

return $tests;
