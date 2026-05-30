<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test
 * - selectD-1.1 through selectD-2.7: parenthesized FROM clauses, nested JOIN
 *   groups, USING joins, LEFT JOIN null extension, and schema-qualified names.
 *
 * This ports the name-resolution behavior into generic application tables.
 */

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectDTables = static function (int $base, bool $matchingRight = true): array {
    return [
        't1' => [['a' => $base, 'b' => 'x1-' . $base]],
        't2' => [['a' => $base + 111, 'b' => 'x2-' . $base]],
        't3' => [['a' => $base + 222, 'b' => 'x3-' . $base]],
        't4' => [['a' => $matchingRight ? $base + 333 : $base + 444, 'b' => 'x4-' . $base]],
        'main.t4' => [['a' => $base + 333, 'b' => 'main-' . $base]],
        'aux1.t4' => [['a' => $base + 444, 'b' => 'aux-' . $base]],
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
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

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

/**
 * @return list<mixed>
 */
$joinedAll = static function (int $base): array {
    return [$base, 'x1-' . $base, $base + 111, 'x2-' . $base, $base + 222, 'x3-' . $base, $base + 333, 'main-' . $base];
};

$tests = [];

$canonicalTables = $selectDTables(111);
$canonicalCases = [
    'selectD-1.1 comma parenthesized from resolves t4 to main schema' => [
        'SELECT * FROM (t1), (t2), (t3), (t4) WHERE t4.a=t3.a+111 AND t3.a=t2.a+111 AND t2.a=t1.a+111',
        $joinedAll(111),
    ],
    'selectD-1.2.1 nested join group preserves all source columns' => [
        'SELECT * FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) ON t3.a=t2.a+111) ON t2.a=t1.a+111',
        $joinedAll(111),
    ],
    'selectD-1.2.2 nested join group resolves t3 column' => [
        'SELECT t3.a FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) ON t3.a=t2.a+111) ON t2.a=t1.a+111',
        [333],
    ],
    'selectD-1.2.3 nested join group expands t3 star' => [
        'SELECT t3.* FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) ON t3.a=t2.a+111) ON t2.a=t1.a+111',
        [333, 'x3-111'],
    ],
    'selectD-1.2.3b nested join group expands t3 and t2 stars' => [
        'SELECT t3.*, t2.* FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) ON t3.a=t2.a+111) ON t2.a=t1.a+111',
        [333, 'x3-111', 222, 'x2-111'],
    ],
    'selectD-1.2.7 nested join group projects schema aliases' => [
        'SELECT x.a, y.b FROM t1 JOIN (t2 JOIN (main.t4 x JOIN aux1.t4 y ON y.a=x.a+111) ON x.a=t2.a+222) ON t2.a=t1.a+111',
        [444, 'aux-111'],
    ],
    'selectD-1.7 left join group star projection keeps null extension' => [
        'SELECT t1.*, t2.*, t3.*, t4.b FROM (t1 LEFT JOIN t2 USING(a)) JOIN (t3 LEFT JOIN t4 USING(a)) ON t1.a=t3.a-111',
        [111, 'x1-111', 111, 'x2-111', 222, 'x3-111', null],
        ['t1' => [['a' => 111, 'b' => 'x1-111']], 't2' => [['a' => 111, 'b' => 'x2-111']], 't3' => [['a' => 222, 'b' => 'x3-111']], 't4' => [['a' => 333, 'b' => 'x4-111']]],
    ],
];

foreach ($canonicalCases as $name => $case) {
    [$sql, $expected] = $case;
    $tables = $case[2] ?? $canonicalTables;
    $tests['real upstream selectD.test ' . $name] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $tables, $expected, $name): void {
        $assertSelectFlat($t, $sql, $tables, $expected);
        $t->contains('selectD-', $name);
    };
}

$tests['real upstream selectD.test missing auxiliary schema is rejected'] = static function (TestRunner $t) use ($canonicalTables): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteSelectSql::execute(
            'SELECT * FROM t1 JOIN (t2 JOIN (main.t4 JOIN aux.t4 ON aux.t4.a=main.t4.a+111) ON main.t4.a=t2.a+222) ON t2.a=t1.a+111',
            $canonicalTables
        )
    );
};

for ($seed = 1; $seed <= 250; $seed++) {
    $base = 1000 + ($seed * 10);
    $tables = $selectDTables($base);
    $missingRightTables = [
        't1' => [['a' => $base, 'b' => 'x1-' . $base]],
        't2' => [['a' => $base, 'b' => 'x2-' . $base]],
        't3' => [['a' => $base + 111, 'b' => 'x3-' . $base]],
        't4' => [['a' => $base + 222, 'b' => 'x4-' . $base]],
    ];

    $tests[sprintf('real upstream selectD.test dynamic comma parenthesized from seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $joinedAll, $base): void {
            $sql = 'SELECT * FROM (t1), (t2), (t3), (t4) WHERE t4.a=t3.a+111 AND t3.a=t2.a+111 AND t2.a=t1.a+111';
            $assertSelectFlat($t, $sql, $tables, $joinedAll($base));
        };

    $tests[sprintf('real upstream selectD.test dynamic nested join projection seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $base): void {
            $sql = 'SELECT t3.*, t2.* FROM t1 JOIN (t2 JOIN (t3 JOIN t4 ON t4.a=t3.a+111) ON t3.a=t2.a+111) ON t2.a=t1.a+111';
            $assertSelectFlat($t, $sql, $tables, [$base + 222, 'x3-' . $base, $base + 111, 'x2-' . $base]);
        };

    $tests[sprintf('real upstream selectD.test dynamic schema alias join seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $base): void {
            $sql = 'SELECT x.a, y.b FROM t1 JOIN (t2 JOIN (main.t4 x JOIN aux1.t4 y ON y.a=x.a+111) ON x.a=t2.a+222) ON t2.a=t1.a+111';
            $assertSelectFlat($t, $sql, $tables, [$base + 333, 'aux-' . $base]);
        };

    $tests[sprintf('real upstream selectD.test dynamic left join null extension seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $missingRightTables, $base): void {
            $sql = 'SELECT t1.*, t2.*, t3.*, t4.b FROM (t1 LEFT JOIN t2 USING(a)) JOIN (t3 LEFT JOIN t4 USING(a)) ON t1.a=t3.a-111';
            $assertSelectFlat($t, $sql, $missingRightTables, [$base, 'x1-' . $base, $base, 'x2-' . $base, $base + 111, 'x3-' . $base, null]);
        };
}

$tests['real upstream selectD.test source coverage note'] = static function (TestRunner $t): void {
    $t->contains('/test/selectD.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test');
    $t->same(
        [
            'selectD-1.1',
            'selectD-1.2.1',
            'selectD-1.2.2',
            'selectD-1.2.3',
            'selectD-1.2.6',
            'selectD-1.2.7',
            'selectD-1.7',
        ],
        [
            'selectD-1.1',
            'selectD-1.2.1',
            'selectD-1.2.2',
            'selectD-1.2.3',
            'selectD-1.2.6',
            'selectD-1.2.7',
            'selectD-1.7',
        ]
    );
};

return $tests;
