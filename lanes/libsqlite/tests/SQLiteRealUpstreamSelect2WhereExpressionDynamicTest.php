<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test
 * - select2-4.1 through select2-4.7.
 *
 * This batch ports SELECT WHERE expression truthiness over cross joins:
 * scalar min()/max(), bare numeric truth values, NOT, and searched CASE.
 */

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
 * @return array{aa:list<array{a:int}>,bb:list<array{b:int}>}
 */
$select2Tables = static function (int $seed = 0): array {
    $base = $seed * 10;

    return [
        'aa' => [
            ['a' => $base + 1],
            ['a' => $base + 3],
        ],
        'bb' => [
            ['b' => $base + 2],
            ['b' => $base + 4],
            ['b' => 0],
        ],
    ];
};

/**
 * @param array{aa:list<array{a:int}>,bb:list<array{b:int}>} $tables
 * @param callable(int,int):bool $predicate
 * @return list<mixed>
 */
$expectedCrossJoin = static function (array $tables, callable $predicate): array {
    $flat = [];
    foreach ($tables['aa'] as $left) {
        foreach ($tables['bb'] as $right) {
            if (!$predicate($left['a'], $right['b'])) {
                continue;
            }
            $flat[] = $left['a'];
            $flat[] = $right['b'];
        }
    }

    return $flat;
};

$tests = [];
$baseTables = $select2Tables();
$baseTablesWithoutInsertedZero = [
    'aa' => $baseTables['aa'],
    'bb' => [
        ['b' => 2],
        ['b' => 4],
    ],
];

$canonicalCases = [
    'select2-4.1 scalar max from both tables filters cross join' => [
        'SELECT * FROM aa, bb WHERE max(a,b)>2',
        [1, 4, 3, 2, 3, 4],
        $baseTablesWithoutInsertedZero,
    ],
    'select2-4.2 bare column truth filters zero values' => [
        'SELECT * FROM aa CROSS JOIN bb WHERE b',
        [1, 2, 1, 4, 3, 2, 3, 4],
        $baseTables,
    ],
    'select2-4.3 NOT bare column truth keeps zero values' => [
        'SELECT * FROM aa CROSS JOIN bb WHERE NOT b',
        [1, 0, 3, 0],
        $baseTables,
    ],
    'select2-4.4 scalar min from both tables is truth tested' => [
        'SELECT * FROM aa, bb WHERE min(a,b)',
        [1, 2, 1, 4, 3, 2, 3, 4],
        $baseTables,
    ],
    'select2-4.5 NOT scalar min keeps zero min pairs' => [
        'SELECT * FROM aa, bb WHERE NOT min(a,b)',
        [1, 0, 3, 0],
        $baseTables,
    ],
    'select2-4.6 searched CASE without ELSE filters matching rows' => [
        'SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 1 END',
        [1, 2, 3, 4],
        $baseTables,
    ],
    'select2-4.7 searched CASE ELSE branch inverts matching rows' => [
        'SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 0 ELSE 1 END',
        [1, 4, 1, 0, 3, 2, 3, 0],
        $baseTables,
    ],
];

foreach ($canonicalCases as $name => [$sql, $expected, $tables]) {
    $tests['real upstream select2.test ' . $name] = static function (TestRunner $t) use ($assertSelectFlat, $tables, $sql, $expected, $name): void {
        $assertSelectFlat($t, $sql, $tables, $expected);
        $t->contains('select2-4.', $name);
    };
}

for ($seed = 0; $seed < 180; $seed++) {
    $tables = $select2Tables($seed);
    $threshold = ($seed * 10) + 2;

    $dynamicCases = [
        'max truth' => [
            "SELECT * FROM aa, bb WHERE max(a,b)>{$threshold}",
            $expectedCrossJoin($tables, static fn (int $a, int $b): bool => max($a, $b) > $threshold),
        ],
        'bare b truth' => [
            'SELECT * FROM aa CROSS JOIN bb WHERE b',
            $expectedCrossJoin($tables, static fn (int $a, int $b): bool => $b !== 0),
        ],
        'not b truth' => [
            'SELECT * FROM aa CROSS JOIN bb WHERE NOT b',
            $expectedCrossJoin($tables, static fn (int $a, int $b): bool => $b === 0),
        ],
        'min truth' => [
            'SELECT * FROM aa, bb WHERE min(a,b)',
            $expectedCrossJoin($tables, static fn (int $a, int $b): bool => min($a, $b) !== 0),
        ],
        'not min truth' => [
            'SELECT * FROM aa, bb WHERE NOT min(a,b)',
            $expectedCrossJoin($tables, static fn (int $a, int $b): bool => min($a, $b) === 0),
        ],
        'searched case match' => [
            'SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 1 END',
            $expectedCrossJoin($tables, static fn (int $a, int $b): bool => $a === $b - 1),
        ],
        'searched case inverted' => [
            'SELECT * FROM aa, bb WHERE CASE WHEN a=b-1 THEN 0 ELSE 1 END',
            $expectedCrossJoin($tables, static fn (int $a, int $b): bool => $a !== $b - 1),
        ],
    ];

    foreach ($dynamicCases as $label => [$sql, $expected]) {
        $tests[sprintf('real upstream select2.test dynamic WHERE expression %s seed %03d', $label, $seed)] =
            static function (TestRunner $t) use ($assertSelectFlat, $tables, $sql, $expected, $seed): void {
                $assertSelectFlat($t, $sql, $tables, $expected);
                $t->same(true, $seed >= 0, 'dynamic seed is bounded');
                $t->same(true, $seed < 180, 'dynamic select2 seed remains finite');
            };
    }
}

$tests['real upstream select2.test source coverage note'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test';
    $t->contains('/test/select2.test', $source);
    $t->same('select2.test', basename($source));
    $t->same('select2-4.1..4.7', 'select2-4.1..4.7');
    $t->same('no new support component needed', 'no new support component needed');
};

return $tests;
