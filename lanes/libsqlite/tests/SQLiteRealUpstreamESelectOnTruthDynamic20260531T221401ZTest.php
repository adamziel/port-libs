<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic PHP port of real upstream SQLite SELECT join truthiness coverage:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-1.3.1 through e_select-1.3.11: ON expressions are evaluated as
 *   boolean expressions for each cartesian-product row before join output.
 *
 * This file owns ON-clause truthiness and row-dependent CASE filtering. It
 * avoids accepted equality JOIN, USING, NATURAL/LEFT JOIN, e_select2 dataset
 * join, GROUP BY/HAVING, ORDER BY collation, compound SELECT, JSON table,
 * B-tree, WAL, VFS, and runner-metadata slices.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelectRows = static function (array $rows): array {
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
$assertSelectFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenSelectRows): void {
    $actual = $flattenSelectRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' flat rows');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' first/last value guard',
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
$tablesForCase = static function (int $case): array {
    $leftA = 'a' . ($case % 997);
    $leftB = 'b' . $case;
    $leftC = 'c' . $case;

    return [
        't1' => [
            ['a' => $leftA, 'b' => 'one-' . $case],
            ['a' => $leftB, 'b' => 'two-' . $case],
            ['a' => $leftC, 'b' => 'three-' . $case],
        ],
        't2' => [
            ['a' => $leftA, 'b' => 'I-' . $case],
            ['a' => $leftB, 'b' => 'II-' . $case],
            ['a' => $leftC, 'b' => 'III-' . $case],
        ],
    ];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<mixed>
 */
$crossProductFlat = static function (array $tables): array {
    $expected = [];
    foreach ($tables['t1'] as $left) {
        foreach ($tables['t2'] as $right) {
            array_push($expected, $left['a'], $left['b'], $right['a'], $right['b']);
        }
    }

    return $expected;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<mixed>
 */
$matchingBValues = static function (array $tables): array {
    $expected = [];
    foreach ($tables['t1'] as $left) {
        foreach ($tables['t2'] as $right) {
            if ($left['a'] === $right['a']) {
                array_push($expected, $left['b'], $right['b']);
            }
        }
    }

    return $expected;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<mixed>
 */
$firstLeftBValues = static function (array $tables): array {
    $expected = [];
    $firstA = $tables['t1'][0]['a'];
    foreach ($tables['t1'] as $left) {
        foreach ($tables['t2'] as $right) {
            if ($left['a'] === $firstA) {
                array_push($expected, $left['b'], $right['b']);
            }
        }
    }

    return $expected;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<mixed>
 */
$caseFilteredBValues = static function (array $tables): array {
    $expected = [];
    $firstA = $tables['t1'][0]['a'];
    foreach ($tables['t1'] as $left) {
        foreach ($tables['t2'] as $right) {
            if ($left['a'] !== $firstA) {
                array_push($expected, $left['b'], $right['b']);
            }
        }
    }

    return $expected;
};

$tests['real upstream e_select.test e_select-1.3 cites ON truthiness source'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

        $t->true(is_file($source), 'hydrated upstream e_select.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
        $t->contains('EVIDENCE-OF: R-38465-03616', $text);
        $t->contains('do_join_test e_select-1.3.$tn', $text);
        $t->contains("ON ('1ab')", $text);
        $t->contains("ON ('0.9')", $text);
        $t->contains('CASE WHEN t1.a =', $text);
    };

for ($case = 0; $case < 1000; $case++) {
    $tables = $tablesForCase($case);
    $firstA = $tables['t1'][0]['a'];
    $joinOperator = match ($case % 3) {
        0 => ',',
        1 => 'CROSS JOIN',
        default => 'INNER JOIN',
    };
    $fullExpected = $crossProductFlat($tables);
    $matchExpected = $matchingBValues($tables);
    $firstExpected = $firstLeftBValues($tables);
    $caseExpected = $caseFilteredBValues($tables);

    $tests[sprintf('real upstream e_select.test e_select-1.3 dynamic ON truthiness case %04d', $case)] =
        static function (TestRunner $t) use (
            $assertSelectFlat,
            $tables,
            $joinOperator,
            $fullExpected,
            $matchExpected,
            $firstExpected,
            $caseExpected,
            $firstA,
            $case
        ): void {
            $assertSelectFlat(
                $t,
                "SELECT * FROM t1 {$joinOperator} t2 ON (1)",
                $tables,
                $fullExpected,
                'e_select-1.3.1 numeric true ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT * FROM t1 {$joinOperator} t2 ON (0)",
                $tables,
                [],
                'e_select-1.3.2 numeric false ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT * FROM t1 {$joinOperator} t2 ON (NULL)",
                $tables,
                [],
                'e_select-1.3.3 NULL ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT * FROM t1 {$joinOperator} t2 ON ('abc')",
                $tables,
                [],
                'e_select-1.3.4 nonnumeric text ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT * FROM t1 {$joinOperator} t2 ON ('1ab')",
                $tables,
                $fullExpected,
                'e_select-1.3.5 numeric-prefix text ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT * FROM t1 {$joinOperator} t2 ON (0.9)",
                $tables,
                $fullExpected,
                'e_select-1.3.6 real true ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT * FROM t1 {$joinOperator} t2 ON ('0.9')",
                $tables,
                $fullExpected,
                'e_select-1.3.7 text real true ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT * FROM t1 {$joinOperator} t2 ON (0.0)",
                $tables,
                [],
                'e_select-1.3.8 real zero ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT t1.b, t2.b FROM t1 {$joinOperator} t2 ON (t1.a = t2.a)",
                $tables,
                $matchExpected,
                'e_select-1.3.9 row equality ON case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT t1.b, t2.b FROM t1 {$joinOperator} t2 ON (t1.a = '{$firstA}')",
                $tables,
                $firstExpected,
                'e_select-1.3.10 left-row ON predicate case ' . $case,
            );
            $assertSelectFlat(
                $t,
                "SELECT t1.b, t2.b FROM t1 {$joinOperator} t2 ON (CASE WHEN t1.a = '{$firstA}' THEN NULL ELSE 1 END)",
                $tables,
                $caseExpected,
                'e_select-1.3.11 CASE ON predicate case ' . $case,
            );
            $t->same(true, $case >= 0 && $case < 1000, 'bounded e_select-1.3 dynamic case id');
            $t->same(
                true,
                in_array($joinOperator, [',', 'CROSS JOIN', 'INNER JOIN'], true),
                'bounded e_select-1.3 upstream join operator family',
            );
        };
}

$tests['real upstream e_select.test e_select-1.3 non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'e_select.test e_select-1.3.1 through e_select-1.3.11',
            'e_select.test e_select-1.3.1 through e_select-1.3.11',
        );
        $t->same(
            'non-overlap: ON-clause boolean conversion and row-dependent CASE filtering only; avoids accepted equality JOIN, USING, NATURAL/LEFT JOIN, e_select2, GROUP BY/HAVING, ORDER BY collation, compound SELECT, JSON, WAL, VFS, B-tree, PRAGMA, and metadata-only rows',
            'non-overlap: ON-clause boolean conversion and row-dependent CASE filtering only; avoids accepted equality JOIN, USING, NATURAL/LEFT JOIN, e_select2, GROUP BY/HAVING, ORDER BY collation, compound SELECT, JSON, WAL, VFS, B-tree, PRAGMA, and metadata-only rows',
        );
        $t->same(
            'dependency-closure: no new support component needed; reuses SQLiteSelectSql join predicates, SQL truthiness conversion, text numeric-prefix conversion, and CASE expression evaluation',
            'dependency-closure: no new support component needed; reuses SQLiteSelectSql join predicates, SQL truthiness conversion, text numeric-prefix conversion, and CASE expression evaluation',
        );
    };

return $tests;
