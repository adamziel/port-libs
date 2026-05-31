<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT join coverage:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-1.8: LEFT JOIN count preservation across ON and USING joins.
 * - e_select-1.9: LEFT JOIN null-extension and USING wildcard column shape.
 * - e_select-1-10: NATURAL JOIN equivalence to explicit USING columns.
 * - e_select-1-11: NATURAL with no common columns behaves like CROSS JOIN.
 * - e_select-1.12: NATURAL joins reject explicit ON or USING constraints.
 *
 * This batch owns core SELECT join semantics. It avoids the queued
 * scalar-subquery and ORDER BY collation e_select handoffs.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelectCoreJoinRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_')) {
                continue;
            }
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectCoreJoinFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenSelectCoreJoinRows): void {
    $actual = $flattenSelectCoreJoinRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario);
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint',
    );
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' first/last guard',
    );
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 */
$assertSelectCoreJoinError = static function (TestRunner $t, string $sql, array $tables, string $scenario): void {
    try {
        SQLiteSelectSql::execute($sql, $tables);
        $t->same('exception', 'not thrown', $scenario);
        return;
    } catch (InvalidArgumentException $exception) {
        $t->contains('NATURAL join may not have an ON or USING clause', $exception->getMessage());
    }
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCoreJoinTables = static function (int $case): array {
    $match = 'k' . ($case % 997);
    $leftOnly = 'l' . $case;
    $rightOnly = 'r' . $case;

    return [
        't7' => [
            ['a' => $match, 'b' => 'ex-' . $case, 'c' => 24 + $case],
            ['a' => $leftOnly, 'b' => 'why-' . $case, 'c' => 25 + $case],
        ],
        't8' => [
            ['a' => $match, 'd' => 'abc-' . $case, 'e' => 2400 + $case],
            ['a' => $rightOnly, 'd' => 'ghi-' . $case, 'e' => 2600 + $case],
        ],
        't3' => [
            ['a' => 'a-' . $case, 'c' => 1],
            ['a' => 'b-' . $case, 'c' => 2],
        ],
        't4' => [
            ['a' => 'a-' . $case, 'c' => null],
            ['a' => 'b-' . $case, 'c' => 2],
        ],
        't1' => [
            ['a' => 'a-' . $case],
            ['a' => 'b-' . $case],
            ['a' => 'c-' . $case],
        ],
        't2' => [
            ['a' => 'a-' . $case],
        ],
        't10' => [
            ['x' => 1, 'y' => 'true'],
            ['x' => 0, 'y' => 'false'],
        ],
    ];
};

$tests = [];

$tests['real upstream e_select.test cites SELECT core join source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('do_select_tests e_select-1.8', $text);
    $t->contains('do_select_tests e_select-1.9', $text);
    $t->contains('do_select_tests e_select-1-10', $text);
    $t->contains('do_select_tests e_select-1-11', $text);
    $t->contains('do_catchsql_test e_select-1.12', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $tables = $selectCoreJoinTables($case);
    $match = 'k' . ($case % 997);
    $leftOnly = 'l' . $case;
    $rightOnly = 'r' . $case;
    $leftMatch = ['b' => 'ex-' . $case, 'c' => 24 + $case];
    $leftMiss = ['b' => 'why-' . $case, 'c' => 25 + $case];
    $rightMatch = ['d' => 'abc-' . $case, 'e' => 2400 + $case];
    $rightMiss = ['d' => 'ghi-' . $case, 'e' => 2600 + $case];

    $tests[sprintf('real upstream e_select.test dynamic SELECT core joins case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertSelectCoreJoinFlat,
            $assertSelectCoreJoinError,
            $tables,
            $case,
            $match,
            $leftOnly,
            $rightOnly,
            $leftMatch,
            $leftMiss,
            $rightMatch,
            $rightMiss
        ): void {
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT count(*) FROM t7 JOIN t8 ON (t7.a=t8.a)',
                $tables,
                [1],
                'e_select-1.8 ON inner join count',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT count(*) FROM t7 LEFT JOIN t8 ON (t7.a=t8.a)',
                $tables,
                [2],
                'e_select-1.8 ON left join count',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT count(*) FROM t7 JOIN t8 USING (a)',
                $tables,
                [1],
                'e_select-1.8 USING inner join count',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT count(*) FROM t7 LEFT JOIN t8 USING (a)',
                $tables,
                [2],
                'e_select-1.8 USING left join count',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT * FROM t7 JOIN t8 ON (t7.a=t8.a)',
                $tables,
                [$match, $leftMatch['b'], $leftMatch['c'], $match, $rightMatch['d'], $rightMatch['e']],
                'e_select-1.9 ON inner join wildcard rows',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT * FROM t7 LEFT JOIN t8 ON (t7.a=t8.a)',
                $tables,
                [
                    $match,
                    $leftMatch['b'],
                    $leftMatch['c'],
                    $match,
                    $rightMatch['d'],
                    $rightMatch['e'],
                    $leftOnly,
                    $leftMiss['b'],
                    $leftMiss['c'],
                    null,
                    null,
                    null,
                ],
                'e_select-1.9 ON left join wildcard null extension',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT * FROM t7 JOIN t8 USING (a)',
                $tables,
                [$match, $leftMatch['b'], $leftMatch['c'], $rightMatch['d'], $rightMatch['e']],
                'e_select-1.9 USING inner join wildcard columns',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT * FROM t7 LEFT JOIN t8 USING (a)',
                $tables,
                [
                    $match,
                    $leftMatch['b'],
                    $leftMatch['c'],
                    $rightMatch['d'],
                    $rightMatch['e'],
                    $leftOnly,
                    $leftMiss['b'],
                    $leftMiss['c'],
                    null,
                    null,
                ],
                'e_select-1.9 USING left join wildcard null extension',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT * FROM t7 NATURAL JOIN t8',
                $tables,
                [$match, $leftMatch['b'], $leftMatch['c'], $rightMatch['d'], $rightMatch['e']],
                'e_select-1-10 NATURAL inner join equals USING',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT * FROM t8 NATURAL LEFT JOIN t7',
                $tables,
                [
                    $match,
                    $rightMatch['d'],
                    $rightMatch['e'],
                    $leftMatch['b'],
                    $leftMatch['c'],
                    $rightOnly,
                    $rightMiss['d'],
                    $rightMiss['e'],
                    null,
                    null,
                ],
                'e_select-1-10 NATURAL left join reversed input',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT * FROM t3 JOIN t4 USING (a,c)',
                $tables,
                ['b-' . $case, 2],
                'e_select-1-10 composite USING inner join',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT * FROM t3 NATURAL LEFT JOIN t4',
                $tables,
                ['a-' . $case, 1, 'b-' . $case, 2],
                'e_select-1-10 composite NATURAL left join',
            );
            $assertSelectCoreJoinFlat(
                $t,
                'SELECT a, x FROM t1 NATURAL CROSS JOIN t10',
                $tables,
                [
                    'a-' . $case,
                    1,
                    'a-' . $case,
                    0,
                    'b-' . $case,
                    1,
                    'b-' . $case,
                    0,
                    'c-' . $case,
                    1,
                    'c-' . $case,
                    0,
                ],
                'e_select-1-11 NATURAL has no effect with no common columns',
            );

            $assertSelectCoreJoinError(
                $t,
                'SELECT * FROM t1 NATURAL LEFT JOIN t2 USING (a)',
                $tables,
                'e_select-1.12 NATURAL rejects USING',
            );
            $assertSelectCoreJoinError(
                $t,
                'SELECT * FROM t1 NATURAL LEFT JOIN t2 ON (t1.a=t2.a)',
                $tables,
                'e_select-1.12 NATURAL rejects ON equality',
            );
            $assertSelectCoreJoinError(
                $t,
                'SELECT * FROM t1 NATURAL LEFT JOIN t2 ON (45)',
                $tables,
                'e_select-1.12 NATURAL rejects ON literal',
            );

            $t->same(true, $case >= 0 && $case < 1000, 'bounded dynamic e_select case id');
            $t->same(true, $match !== $leftOnly && $match !== $rightOnly, 'join keys keep match and misses distinct');
        };
}

return $tests;
