<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test
 *
 * This ports select2.test select2-4.1 through select2-4.7: cross-join row
 * production with scalar min()/max(), numeric truthiness, NOT, and CASE
 * predicates in the WHERE clause.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select2BooleanJoinFlat = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @return array{left_rows:list<array{a:int}>,right_rows:list<array{b:int}>}
 */
$select2BooleanJoinTables = static function (int $seed): array {
    $leftRows = [];
    $rightRows = [];

    $leftStart = -2 + ($seed % 5);
    $rightStart = -1 + (($seed * 2) % 5);
    $leftCount = 4 + ($seed % 4);
    $rightCount = 3 + (($seed + 1) % 5);

    for ($index = 0; $index < $leftCount; $index++) {
        $leftRows[] = ['a' => $leftStart + $index];
    }
    for ($index = 0; $index < $rightCount; $index++) {
        $rightRows[] = ['b' => $rightStart + $index];
    }

    return [
        'left_rows' => $leftRows,
        'right_rows' => $rightRows,
    ];
};

/**
 * @param array{left_rows:list<array{a:int}>,right_rows:list<array{b:int}>} $tables
 * @return list<array{a:int,b:int}>
 */
$select2BooleanJoinPairs = static function (array $tables): array {
    $pairs = [];
    foreach ($tables['left_rows'] as $left) {
        foreach ($tables['right_rows'] as $right) {
            $pairs[] = ['a' => $left['a'], 'b' => $right['b']];
        }
    }

    return $pairs;
};

/**
 * @param array{left_rows:list<array{a:int}>,right_rows:list<array{b:int}>} $tables
 * @param callable(array{a:int,b:int}): bool $predicate
 * @return list<mixed>
 */
$select2BooleanJoinExpected = static function (array $tables, callable $predicate) use ($select2BooleanJoinPairs): array {
    $expected = [];
    foreach ($select2BooleanJoinPairs($tables) as $pair) {
        if ($predicate($pair)) {
            $expected[] = $pair['a'];
            $expected[] = $pair['b'];
        }
    }

    return $expected;
};

/**
 * @param array{left_rows:list<array{a:int}>,right_rows:list<array{b:int}>} $tables
 * @param list<mixed> $expected
 */
$select2BooleanJoinAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($select2BooleanJoinFlat): void {
    $actual = $select2BooleanJoinFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
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

$tests = [];

$tests['real upstream select2.test cites boolean cross join source sections'] = static function (TestRunner $t): void {
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test';

    $t->true(is_file($path), 'hydrated upstream select2.test exists');
    $source = file_get_contents($path);
    $t->true(is_string($source), 'hydrated upstream select2.test is readable');
    foreach (['select2-4.1', 'select2-4.2', 'select2-4.3', 'select2-4.4', 'select2-4.5', 'select2-4.6', 'select2-4.7'] as $scenario) {
        $t->contains('do_test ' . $scenario, $source);
    }
};

$cases = [
    'select2-4.1 max greater than two' => [
        'WHERE max(a,b)>2',
        static fn (array $pair): bool => max($pair['a'], $pair['b']) > 2,
    ],
    'select2-4.2 bare b truthy' => [
        'WHERE b',
        static fn (array $pair): bool => $pair['b'] != 0,
    ],
    'select2-4.3 not bare b' => [
        'WHERE NOT b',
        static fn (array $pair): bool => $pair['b'] == 0,
    ],
    'select2-4.4 min truthy' => [
        'WHERE min(a,b)',
        static fn (array $pair): bool => min($pair['a'], $pair['b']) != 0,
    ],
    'select2-4.5 not min' => [
        'WHERE NOT min(a,b)',
        static fn (array $pair): bool => min($pair['a'], $pair['b']) == 0,
    ],
    'select2-4.6 case implicit null false' => [
        'WHERE CASE WHEN a=b-1 THEN 1 END',
        static fn (array $pair): bool => $pair['a'] === $pair['b'] - 1,
    ],
    'select2-4.7 case explicit else truthy' => [
        'WHERE CASE WHEN a=b-1 THEN 0 ELSE 1 END',
        static fn (array $pair): bool => $pair['a'] !== $pair['b'] - 1,
    ],
];

for ($seed = 0; $seed < 150; $seed++) {
    $tables = $select2BooleanJoinTables($seed);
    foreach ($cases as $scenario => [$whereSql, $predicate]) {
        $expected = $select2BooleanJoinExpected($tables, $predicate);
        $testName = sprintf('real upstream select2.test %s dynamic seed %03d', $scenario, $seed);

        $tests[$testName] = static function (TestRunner $t) use (
            $select2BooleanJoinAssert,
            $tables,
            $whereSql,
            $expected,
            $scenario,
            $seed
        ): void {
            $sql = 'SELECT * FROM left_rows CROSS JOIN right_rows ' . $whereSql;
            $select2BooleanJoinAssert($t, $sql, $tables, $expected, $scenario . ' seed ' . $seed);
        };
    }
}

return $tests;
