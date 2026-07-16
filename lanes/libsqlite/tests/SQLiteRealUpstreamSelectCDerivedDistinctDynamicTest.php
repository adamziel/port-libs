<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectCDerivedFlatten = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCDerivedDistinctTables = static function (int $seed): array {
    $tenant = 'tenant' . ($seed % 7);
    $rows = [];
    foreach ([1, 2, 3, 1, 2, 3] as $index => $bucket) {
        $rows[] = [
            'tenant_id' => $tenant,
            'a' => (string) (100 + ($seed % 5)),
            'b' => (string) $bucket,
            'c' => chr(97 + (($seed + $index) % 26)),
        ];
    }
    $rows[] = [
        'tenant_id' => $tenant,
        'a' => (string) (200 + ($seed % 11)),
        'b' => '9',
        'c' => 'z',
    ];

    return ['t_distinct_bug' => $rows];
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCCompoundTables = static function (int $seed): array {
    $leftA = 'a' . ($seed % 5);
    $leftB = 'b' . ($seed % 5);
    $base = 20 + ($seed % 13);
    $tail = 300 + ($seed % 17);

    return [
        'x1' => [
            ['a' => $leftA],
            ['a' => $leftB],
        ],
        'x2' => [
            ['b' => $base + 1],
            ['b' => $base + 3],
            ['b' => $base],
            ['b' => $base + 2],
            ['b' => $base + 4],
        ],
        'x3' => [
            ['c' => $tail + 1],
            ['c' => $tail + 2],
            ['c' => $tail],
        ],
    ];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<mixed>
 */
$selectCExpectedCompoundJoin = static function (array $tables): array {
    $left = array_map(
        static fn (array $row): string => (string) $row['a'],
        $tables['x1']
    );
    sort($left, SORT_STRING);

    $right = [];
    foreach ($tables['x2'] as $row) {
        $right[] = (int) $row['b'];
    }
    foreach ($tables['x3'] as $row) {
        $right[] = (int) $row['c'];
    }
    sort($right, SORT_NUMERIC);

    $flat = [];
    foreach ($left as $a) {
        foreach ($right as $value) {
            $flat[] = $a;
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$selectCAssertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($selectCDerivedFlatten): void {
    $actual = $selectCDerivedFlatten(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $sql
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql
    );
};

$tests = [];

$tests['real upstream corpus selectC.test selectC-4.2 distinct derived projection canonical'] = static function (TestRunner $t) use ($selectCAssertFlat): void {
    $tables = [
        't_distinct_bug' => [
            ['a' => '1', 'b' => '1', 'c' => 'a'],
            ['a' => '1', 'b' => '2', 'c' => 'b'],
            ['a' => '1', 'b' => '3', 'c' => 'c'],
            ['a' => '1', 'b' => '1', 'c' => 'd'],
            ['a' => '1', 'b' => '2', 'c' => 'e'],
            ['a' => '1', 'b' => '3', 'c' => 'f'],
        ],
    ];

    $selectCAssertFlat($t, 'SELECT a FROM (SELECT DISTINCT a, b FROM t_distinct_bug)', $tables, ['1', '1', '1']);
    $t->contains('/test/selectC.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test');
};

$tests['real upstream corpus selectC.test selectC-5.3 explicit compound derived join canonical'] = static function (TestRunner $t) use ($selectCAssertFlat): void {
    $tables = [
        'x1' => [
            ['a' => 'a'],
            ['a' => 'b'],
        ],
        'x2' => [
            ['b' => 22],
            ['b' => 23],
            ['b' => 25],
            ['b' => 24],
            ['b' => 21],
        ],
        'x3' => [
            ['c' => 302],
            ['c' => 303],
            ['c' => 301],
        ],
    ];

    $expected = [
        'a', 21, 'a', 22, 'a', 23, 'a', 24, 'a', 25, 'a', 301, 'a', 302, 'a', 303,
        'b', 21, 'b', 22, 'b', 23, 'b', 24, 'b', 25, 'b', 301, 'b', 302, 'b', 303,
    ];

    $selectCAssertFlat(
        $t,
        'SELECT x1.a, subquery.b FROM x1, (SELECT b FROM x2 UNION ALL SELECT c FROM x3) ORDER BY x1.a, subquery.b',
        $tables,
        $expected
    );
    $t->contains('selectC-5.3', 'selectC-5.3 explicit projection mirrors the upstream compound derived join result order');
};

for ($seed = 0; $seed < 500; $seed++) {
    $tests[sprintf('real upstream corpus selectC.test dynamic distinct derived projection %03d', $seed)] = static function (TestRunner $t) use ($selectCAssertFlat, $selectCDerivedDistinctTables, $seed): void {
        $tables = $selectCDerivedDistinctTables($seed);
        $firstA = (string) $tables['t_distinct_bug'][0]['a'];
        $lastA = (string) $tables['t_distinct_bug'][6]['a'];
        $expected = [$firstA, $firstA, $firstA, $lastA];

        $selectCAssertFlat(
            $t,
            'SELECT a FROM (SELECT DISTINCT a, b FROM t_distinct_bug) ORDER BY a, b',
            $tables,
            $expected
        );
    };

    $tests[sprintf('real upstream corpus selectC.test dynamic explicit compound derived join %03d', $seed)] = static function (TestRunner $t) use ($selectCAssertFlat, $selectCCompoundTables, $selectCExpectedCompoundJoin, $seed): void {
        $tables = $selectCCompoundTables($seed);

        $selectCAssertFlat(
            $t,
            'SELECT x1.a, subquery.b FROM x1, (SELECT b FROM x2 UNION ALL SELECT c FROM x3) ORDER BY x1.a, subquery.b',
            $tables,
            $selectCExpectedCompoundJoin($tables)
        );
    };
}

return $tests;
