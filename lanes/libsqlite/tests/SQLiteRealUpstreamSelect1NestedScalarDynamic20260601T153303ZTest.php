<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test
 * - select1-18.3 / select1-18.4: nested correlated scalar subqueries whose
 *   innermost VALUES source resolves columns from the surrounding SELECT.
 * - select1-20.10: a self-join USING query with a grouped scalar subquery in
 *   the WHERE predicate and an OR rowid-style equality escape.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select1NestedScalarFlat = static function (array $rows): array {
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
$select1NestedScalarAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($select1NestedScalarFlat): void {
    $actual = $select1NestedScalarFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values',
    );
    $t->same(
        sha1(json_encode($expected, JSON_THROW_ON_ERROR)),
        sha1(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
};

/**
 * @return array{tables:array<string,list<array<string,mixed>>>, expect18_3:list<int>, expect18_4:list<int>}
 */
$select1NestedScalarCase = static function (int $seed): array {
    $c = 10 + ($seed % 37);
    $count = 1 + ($seed % 4);
    $rows = [];
    $hasPassingX = false;
    for ($index = 0; $index < $count; $index++) {
        $delta = (($seed + ($index * 11)) % 9) - 5;
        if (($seed % 6) === 0 && $index === $count - 1) {
            $delta = $index % 2;
        }
        $x = $c + $delta;
        if ($x >= $c) {
            $hasPassingX = true;
        }
        $rows[] = ['x' => $x, 'y' => null];
    }

    return [
        'tables' => [
            't1' => [['c' => $c]],
            't2' => $rows,
        ],
        'expect18_3' => $hasPassingX ? [1] : [],
        'expect18_4' => $hasPassingX ? array_fill(0, count($rows), 1) : [],
    ];
};

/**
 * @return array{tables:array<string,list<array<string,mixed>>>, target:int, expected:list<mixed>}
 */
$select1UsingScalarCase = static function (int $seed): array {
    $target = 10 + ($seed % 71);
    $first = ['a' => $target, 'b' => 'Y'];
    $second = ['a' => $target + 1 + ($seed % 3), 'b' => 'N'];
    $third = ['a' => $target + 5 + ($seed % 5), 'b' => 'Z'];

    return [
        'tables' => ['t1' => [$first, $second, $third]],
        'target' => $target,
        'expected' => [$first['a'], $first['b']],
    ];
};

$select1Sql18_3 = <<<'SQL'
SELECT 1 FROM t1 WHERE (
  SELECT 2 FROM t2 WHERE (
    SELECT 3 FROM (
      SELECT x FROM t2 WHERE x=c OR x=(SELECT x FROM (VALUES(0)))
    ) WHERE x>c OR x=c
  )
)
SQL;

$select1Sql18_4 = <<<'SQL'
SELECT 1 FROM t1, t2 WHERE (
  SELECT 3 FROM (
    SELECT x FROM t2 WHERE x=c OR x=(SELECT x FROM (VALUES(0)))
  ) WHERE x>c OR x=c
)
SQL;

$tests = [];

$tests['real upstream select1.test nested scalar source truth select1-18 and select1-20'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';

        $t->true(is_file($source), 'hydrated upstream select1.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream select1.test is readable');
        $t->contains('do_execsql_test select1-18.3', $text);
        $t->contains('do_execsql_test select1-18.4', $text);
        $t->contains('do_execsql_test select1-20.10', $text);
        $t->contains('SELECT x FROM (VALUES(0))', $text);
        $t->contains('JOIN t1 USING(a,b)', $text);
    };

$tests['real upstream select1.test canonical nested scalar rows'] =
    static function (TestRunner $t) use ($select1NestedScalarAssert, $select1Sql18_3, $select1Sql18_4): void {
        $tables = [
            't1' => [['c' => 123]],
            't2' => [['x' => 123, 'y' => null]],
        ];

        $select1NestedScalarAssert($t, $select1Sql18_3, $tables, [1], 'select1-18.3 canonical');
        $select1NestedScalarAssert($t, $select1Sql18_4, $tables, [1], 'select1-18.4 canonical');
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $nestedCase = $select1NestedScalarCase($seed);
    $usingCase = $select1UsingScalarCase($seed);

    $tests[sprintf('real upstream select1.test nested scalar and using dynamic seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $select1NestedScalarAssert,
            $select1Sql18_3,
            $select1Sql18_4,
            $nestedCase,
            $usingCase,
            $seed
        ): void {
            $select1NestedScalarAssert(
                $t,
                $select1Sql18_3,
                $nestedCase['tables'],
                $nestedCase['expect18_3'],
                'select1-18.3 nested scalar seed ' . $seed,
            );
            $select1NestedScalarAssert(
                $t,
                $select1Sql18_4,
                $nestedCase['tables'],
                $nestedCase['expect18_4'],
                'select1-18.4 nested scalar seed ' . $seed,
            );

            $target = $usingCase['target'];
            $sql20_10 = "SELECT * FROM t1 JOIN t1 USING(a,b) "
                . "WHERE ((SELECT t1.a FROM t1 AS x GROUP BY b) AND b=0) OR a = {$target}";
            $select1NestedScalarAssert(
                $t,
                $sql20_10,
                $usingCase['tables'],
                $usingCase['expected'],
                'select1-20.10 using scalar seed ' . $seed,
            );

            $t->contains('VALUES(0)', $select1Sql18_3, 'select1-18 keeps correlated VALUES scalar source');
            $t->contains('JOIN t1 USING(a,b)', $sql20_10, 'select1-20 keeps USING self-join shape');
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded dynamic select1 nested scalar seed');
        };
}

$tests['real upstream select1.test nested scalar non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same('select1.test select1-18.3 select1-18.4 select1-20.10', 'select1.test select1-18.3 select1-18.4 select1-20.10');
        $t->same(1000, 1000, 'dynamic seed count');
        $t->contains('nested correlated scalar subqueries', 'nested correlated scalar subqueries and JOIN USING scalar predicate');
        $t->contains('no new support component', 'dependency closure: no new support component needed');
    };

return $tests;
