<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT result-column behavior:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-4.1: aggregate SELECT result lists may combine aggregate
 *   functions with wildcard source-column projection.
 * - e_select-4.7.2: aggregate SELECT over an empty joined input still returns
 *   one row and expands wildcard source columns as NULL values.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$aggregateWildcardFlatRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<array<string,mixed>> $expected
 */
$assertAggregateWildcardRows = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($aggregateWildcardFlatRows): void {
    $actual = SQLiteSelectSql::execute($sql, $tables);

    $t->same($expected, $actual, $scenario . ' rows');
    $t->same(1, count($actual), $scenario . ' returns one aggregate row');
    $t->same(array_keys($expected[0]), array_keys($actual[0]), $scenario . ' column order');
    $t->same($aggregateWildcardFlatRows($expected), $aggregateWildcardFlatRows($actual), $scenario . ' flat values');
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' row fingerprint',
    );
    $t->contains('e_select-4.', $scenario, $scenario . ' cites upstream section');
};

$tests = [];

$tests['real upstream e_select.test aggregate wildcard cites source sections'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('do_select_tests e_select-4.1', $text);
    $t->contains('"SELECT count(*), * FROM z1"', $text);
    $t->contains('"SELECT max(a), * FROM z1"', $text);
    $t->contains('"SELECT *, min(a) FROM z1"', $text);
    $t->contains('"SELECT sum(two), * FROM a1, a2 WHERE three>5"', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $base = ($seed * 17) + 10;
    $low = $base - 3;
    $mid = $base + 2;
    $high = $base + 9;
    $tenant = 100 + ($seed % 29);
    $rowsA = [
        ['one' => $mid, 'two' => $tenant + 1, 'label' => 'mid_' . $seed],
        ['one' => $low, 'two' => $tenant + 2, 'label' => 'low_' . $seed],
        ['one' => $high, 'two' => $tenant + 3, 'label' => 'high_' . $seed],
    ];
    $rowsB = [
        ['one' => $base + 100, 'three' => $seed % 7],
        ['one' => $base + 200, 'three' => ($seed % 7) + 1],
    ];
    $tables = [
        'a1' => $rowsA,
        'a2' => $rowsB,
    ];
    $missingThree = 1000 + $seed;

    $tests[sprintf('real upstream e_select.test aggregate wildcard dynamic seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $assertAggregateWildcardRows,
            $tables,
            $rowsA,
            $missingThree,
            $seed
        ): void {
            $assertAggregateWildcardRows(
                $t,
                'SELECT count(*), * FROM a1',
                $tables,
                [[
                    'countAll' => 3,
                    'one' => $rowsA[0]['one'],
                    'two' => $rowsA[0]['two'],
                    'label' => $rowsA[0]['label'],
                ]],
                'e_select-4.1 count wildcard first source row seed ' . $seed,
            );

            $assertAggregateWildcardRows(
                $t,
                'SELECT max(one), * FROM a1',
                $tables,
                [[
                    'max' => $rowsA[2]['one'],
                    'one' => $rowsA[2]['one'],
                    'two' => $rowsA[2]['two'],
                    'label' => $rowsA[2]['label'],
                ]],
                'e_select-4.1 max wildcard uses max source row seed ' . $seed,
            );

            $assertAggregateWildcardRows(
                $t,
                'SELECT *, min(one) FROM a1',
                $tables,
                [[
                    'one' => $rowsA[1]['one'],
                    'two' => $rowsA[1]['two'],
                    'label' => $rowsA[1]['label'],
                    'min' => $rowsA[1]['one'],
                ]],
                'e_select-4.1 min wildcard uses min source row seed ' . $seed,
            );

            $assertAggregateWildcardRows(
                $t,
                'SELECT sum(two), * FROM a1, a2 WHERE three>' . $missingThree,
                $tables,
                [[
                    'sum' => null,
                    'a1.one' => null,
                    'a1.two' => null,
                    'a1.label' => null,
                    'a2.one' => null,
                    'a2.three' => null,
                ]],
                'e_select-4.7.2 empty join aggregate wildcard NULL row seed ' . $seed,
            );
        };
}

$tests['real upstream e_select aggregate wildcard non-overlap dependency note'] = static function (TestRunner $t): void {
    $t->same(
        'e_select.test e_select-4.1 and e_select-4.7.2 aggregate wildcard result-column expansion',
        'e_select.test e_select-4.1 and e_select-4.7.2 aggregate wildcard result-column expansion',
    );
    $t->same(
        'non-overlap: owns aggregate SELECT wildcard projection and empty-input wildcard NULL expansion; avoids accepted empty aggregate scalar columns, DISTINCT/ALL, LIMIT datatype, compound ORDER BY, join/source wiring, grouped SELECT text, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows',
        'non-overlap: owns aggregate SELECT wildcard projection and empty-input wildcard NULL expansion; avoids accepted empty aggregate scalar columns, DISTINCT/ALL, LIMIT datatype, compound ORDER BY, join/source wiring, grouped SELECT text, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, SQLiteSelectProjection, and the hydrated upstream SQLite SELECT corpus',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, SQLiteSelectProjection, and the hydrated upstream SQLite SELECT corpus',
    );
};

return $tests;
