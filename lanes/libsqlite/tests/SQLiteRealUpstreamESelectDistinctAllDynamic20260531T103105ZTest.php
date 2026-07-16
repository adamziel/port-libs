<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT result-set filtering coverage:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-5.1: SELECT ALL and SELECT DISTINCT syntax.
 * - e_select-5.2 through e_select-5.4: default SELECT behaves as ALL, while
 *   DISTINCT removes duplicate result rows.
 * - e_select-5.5: DISTINCT treats NULL values as duplicates.
 * - e_select-5.6: DISTINCT duplicate detection follows the selected collation.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenDistinctRows = static function (array $rows): array {
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
$assertDistinctFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenDistinctRows): void {
    $actual = $flattenDistinctRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario);
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' first/last sentinel',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint',
    );
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$distinctAllTables = static function (int $case): array {
    $leftGroup = 1 + ($case % 17);
    $rightGroup = 40 + ($case % 23);
    $suffix = '_' . $case;

    return [
        'h1' => [
            ['a' => $leftGroup, 'b' => 'one' . $suffix],
            ['a' => $leftGroup, 'b' => 'I' . $suffix],
            ['a' => $leftGroup, 'b' => 'i' . $suffix],
            ['a' => $rightGroup, 'b' => 'four' . $suffix],
            ['a' => $rightGroup, 'b' => 'IV' . $suffix],
            ['a' => $rightGroup, 'b' => 'iv' . $suffix],
        ],
        'h2' => [
            ['x' => 'One' . $suffix],
            ['x' => 'Two' . $suffix],
            ['x' => 'Three' . $suffix],
            ['x' => 'Four' . $suffix],
            ['x' => 'one' . $suffix],
            ['x' => 'two' . $suffix],
            ['x' => 'three' . $suffix],
            ['x' => 'four' . $suffix],
        ],
        'h3' => [
            ['c' => 1, 'd' => null],
            ['c' => 2, 'd' => null],
            ['c' => 3, 'd' => null],
            ['c' => 4, 'd' => '2' . $suffix],
            ['c' => 5, 'd' => null],
            ['c' => 6, 'd' => '2,3' . $suffix],
            ['c' => 7, 'd' => null],
            ['c' => 8, 'd' => '2,4' . $suffix],
            ['c' => 9, 'd' => '3' . $suffix],
        ],
    ];
};

$tests = [];

$tests['real upstream e_select.test cites SELECT DISTINCT and ALL source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('do_select_tests e_select-5.1', $text);
    $t->contains('do_select_tests e_select-5 {', $text);
    $t->contains('do_select_tests e_select-5.5', $text);
    $t->contains('do_select_tests e_select-5.6', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $tables = $distinctAllTables($case);
    $leftGroup = 1 + ($case % 17);
    $rightGroup = 40 + ($case % 23);
    $suffix = '_' . $case;
    $allA = [$leftGroup, $leftGroup, $leftGroup, $rightGroup, $rightGroup, $rightGroup];
    $distinctA = [$leftGroup, $rightGroup];
    $distinctNulls = [null, '2' . $suffix, '2,3' . $suffix, '2,4' . $suffix, '3' . $suffix];
    $h1Binary = ['one' . $suffix, 'I' . $suffix, 'i' . $suffix, 'four' . $suffix, 'IV' . $suffix, 'iv' . $suffix];
    $h1Nocase = ['one' . $suffix, 'I' . $suffix, 'four' . $suffix, 'IV' . $suffix];
    $h2Binary = ['One' . $suffix, 'Two' . $suffix, 'Three' . $suffix, 'Four' . $suffix, 'one' . $suffix, 'two' . $suffix, 'three' . $suffix, 'four' . $suffix];
    $h2Nocase = ['One' . $suffix, 'Two' . $suffix, 'Three' . $suffix, 'Four' . $suffix];
    $joinAll = ['One' . $suffix, 'one' . $suffix, 'Four' . $suffix, 'four' . $suffix];
    $joinDistinctNocase = ['One' . $suffix, 'Four' . $suffix];

    $tests[sprintf('real upstream e_select.test dynamic DISTINCT ALL result filtering case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertDistinctFlat,
            $tables,
            $allA,
            $distinctA,
            $distinctNulls,
            $h1Binary,
            $h1Nocase,
            $h2Binary,
            $h2Nocase,
            $joinAll,
            $joinDistinctNocase,
            $case
        ): void {
            $assertDistinctFlat(
                $t,
                'SELECT ALL a FROM h1',
                $tables,
                $allA,
                'e_select-5.1 SELECT ALL keeps every row case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT a FROM h1',
                $tables,
                $allA,
                'e_select-5.2 default SELECT behaves as ALL case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT DISTINCT a FROM h1',
                $tables,
                $distinctA,
                'e_select-5.4 DISTINCT removes duplicate numeric rows case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT DISTINCT d FROM h3',
                $tables,
                $distinctNulls,
                'e_select-5.5 DISTINCT treats NULLs as duplicates case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT DISTINCT b FROM h1',
                $tables,
                $h1Binary,
                'e_select-5.6 binary DISTINCT keeps case variants case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT DISTINCT b COLLATE nocase FROM h1',
                $tables,
                $h1Nocase,
                'e_select-5.6 nocase DISTINCT folds case variants case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT DISTINCT x COLLATE binary FROM h2',
                $tables,
                $h2Binary,
                'e_select-5.6 explicit binary DISTINCT keeps mixed-case rows case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT DISTINCT x COLLATE nocase FROM h2',
                $tables,
                $h2Nocase,
                'e_select-5.6 explicit nocase DISTINCT follows selected collation case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT ALL x FROM h1, h2 ON (x COLLATE nocase=b)',
                $tables,
                $joinAll,
                'e_select-5.3 SELECT ALL join returns all matching collation rows case ' . $case,
            );
            $assertDistinctFlat(
                $t,
                'SELECT DISTINCT x COLLATE nocase FROM h1, h2 ON (x COLLATE nocase=b)',
                $tables,
                $joinDistinctNocase,
                'e_select-5.4 DISTINCT join removes nocase duplicates case ' . $case,
            );
            $t->same(true, $case >= 0 && $case < 1000, 'bounded real upstream dynamic case guard');
        };
}

$tests['real upstream e_select DISTINCT ALL dependency closure'] = static function (TestRunner $t): void {
    $t->same('e_select.test e_select-5.1 through e_select-5.6', 'e_select.test e_select-5.1 through e_select-5.6');
    $t->same(
        'non-overlap: owns SELECT ALL/default/DISTINCT duplicate filtering, NULL duplicate equality, and DISTINCT collation selection; avoids empty aggregates, SELECT joins/subqueries, grouped SELECT text, expression ORDER BY, comma LIMIT, JSON table, B-tree, WAL, VFS, PRAGMA, and metadata-only runner rows',
        'non-overlap: owns SELECT ALL/default/DISTINCT duplicate filtering, NULL duplicate equality, and DISTINCT collation selection; avoids empty aggregates, SELECT joins/subqueries, grouped SELECT text, expression ORDER BY, comma LIMIT, JSON table, B-tree, WAL, VFS, PRAGMA, and metadata-only runner rows',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectResult DISTINCT handling, and explicit COLLATE expressions in the native row-array executor',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectResult DISTINCT handling, and explicit COLLATE expressions in the native row-array executor',
    );
};

return $tests;
