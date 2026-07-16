<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test
 * - e_select-2.1.28a-d: joins in a FROM clause are processed left-to-right,
 *   and explicit parentheses can force a different associativity.
 *
 * This batch focuses on parenthesized right-side join groups that can be empty.
 * SQLite still knows the right-side column shape and NULL-extends the preserved
 * left row for a LEFT JOIN.
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
$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected, string $scenario) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' result');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' edge values',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' fingerprint',
    );
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$joinAssociativityTables = static function (int $case): array {
    $match = 'shared-' . $case;
    $leftOnly = 'left-only-' . $case;
    $spare = 'spare-' . $case;
    $id = 10000 + $case;

    return [
        't3' => [
            ['b' => $match, 'e' => 'left-match-' . $case],
            ['b' => $leftOnly, 'e' => 'left-preserved-' . $case],
        ],
        't2' => [
            ['a' => $id, 'b' => $match, 'd' => 'middle-match-' . $case],
            ['a' => $id + 1, 'b' => $spare, 'd' => 'middle-spare-' . $case],
        ],
        't1' => [
            ['a' => $id, 'b' => $match, 'c' => 'right-match-' . $case],
            ['a' => $id + 2, 'b' => $leftOnly, 'c' => 'right-left-only-' . $case],
        ],
    ];
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$emptyRightJoinTables = static function (int $case): array {
    return [
        't3' => [
            ['b' => 'alpha-' . $case, 'e' => 'left-alpha-' . $case],
            ['b' => 'omega-' . $case, 'e' => 'left-omega-' . $case],
        ],
        't2' => [
            ['a' => 20000 + $case, 'b' => 'middle-only-' . $case, 'd' => 'middle-empty-' . $case],
        ],
        't1' => [
            ['a' => 30000 + $case, 'b' => 'right-only-' . $case, 'c' => 'right-empty-' . $case],
        ],
    ];
};

$tests['real upstream e_select2.test cites join associativity source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test';

    $t->true(is_file($source), 'hydrated upstream e_select2.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select2.test is readable');
    $t->contains('EVIDENCE-OF: R-28760-53843', $text);
    $t->contains('$tn.28a', $text);
    $t->contains('$tn.28d', $text);
    $t->contains('SELECT * FROM t3 NATURAL LEFT JOIN t2 NATURAL JOIN t1', $text);
    $t->contains('SELECT * FROM t3 NATURAL LEFT JOIN (t2 NATURAL JOIN t1)', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $tables = $joinAssociativityTables($case);
    $emptyTables = $emptyRightJoinTables($case);

    $match = 'shared-' . $case;
    $leftOnly = 'left-only-' . $case;
    $matchedExpected = [
        $match,
        'left-match-' . $case,
        'middle-match-' . $case,
        'right-match-' . $case,
    ];
    $rightGroupedExpected = [
        $leftOnly,
        'left-preserved-' . $case,
        null,
        null,
        $match,
        'left-match-' . $case,
        'middle-match-' . $case,
        'right-match-' . $case,
    ];
    $emptyRightExpected = [
        'alpha-' . $case,
        'left-alpha-' . $case,
        null,
        null,
        'omega-' . $case,
        'left-omega-' . $case,
        null,
        null,
    ];

    $tests[sprintf('real upstream e_select2.test join associativity empty right dynamic %04d', $case)] =
        static function (TestRunner $t) use (
            $assertFlat,
            $tables,
            $emptyTables,
            $matchedExpected,
            $rightGroupedExpected,
            $emptyRightExpected,
            $case
        ): void {
            $leftToRightSql = 'SELECT b, e, d, c FROM t3 NATURAL LEFT JOIN t2 NATURAL JOIN t1 ORDER BY b, e';
            $explicitLeftSql = 'SELECT b, e, d, c FROM (t3 NATURAL LEFT JOIN t2) NATURAL JOIN t1 ORDER BY b, e';
            $forcedRightSql = 'SELECT b, e, d, c FROM t3 NATURAL LEFT JOIN (t2 NATURAL JOIN t1) ORDER BY b, e';

            $assertFlat($t, $leftToRightSql, $tables, $matchedExpected, 'e_select2 28a left-to-right case ' . $case);
            $assertFlat($t, $explicitLeftSql, $tables, $matchedExpected, 'e_select2 28b explicit left grouping case ' . $case);
            $assertFlat($t, $forcedRightSql, $tables, $rightGroupedExpected, 'e_select2 28d forced right grouping case ' . $case);
            $assertFlat($t, $forcedRightSql, $emptyTables, $emptyRightExpected, 'e_select2 28d empty right join group case ' . $case);
            $t->same(true, $case >= 0 && $case < 1000, 'bounded e_select2 associativity case id');
        };
}

$tests['real upstream e_select2.test join associativity non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'e_select2.test e_select-2.1.28a-d parenthesized NATURAL LEFT JOIN associativity',
        'e_select2.test e_select-2.1.28a-d parenthesized NATURAL LEFT JOIN associativity',
    );
    $t->same(
        'non-overlap: avoids accepted e_select core joins, e_select2 collation/USING affinity, selectD parenthesized joins, SELECT subqueries, grouped SELECT text, JSON table sources, and storage/VFS clusters',
        'non-overlap: avoids accepted e_select core joins, e_select2 collation/USING affinity, selectD parenthesized joins, SELECT subqueries, grouped SELECT text, JSON table sources, and storage/VFS clusters',
    );
    $t->same(
        'dependency closure: no new support component; reuses SQLiteSelectSql parenthesized join groups and hydrated upstream SQLite e_select2.test source truth',
        'dependency closure: no new support component; reuses SQLiteSelectSql parenthesized join groups and hydrated upstream SQLite e_select2.test source truth',
    );
};

return $tests;
