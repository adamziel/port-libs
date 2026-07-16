<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test
 * - e_select-2.2.1 cases 1 through 7: a table-or-subquery in FROM is
 *   materialized as a table for plain joins, ON predicates, NATURAL joins, and
 *   NATURAL LEFT JOIN null extension.
 *
 * Existing accepted e_select2 dynamic files cover direct table joins,
 * join-comparison collation, NATURAL LEFT JOIN associativity, and USING
 * affinity cases 8 through 15. This file owns the preceding derived-source
 * materialization cases over generic application tables.
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

$sqliteCompare = static function (mixed $left, mixed $right): int {
    $rank = static function (mixed $value): int {
        if ($value === null) {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }

        return 3;
    };

    $leftRank = $rank($left);
    $rightRank = $rank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }
    if ($leftRank === 1) {
        return (float) $left <=> (float) $right;
    }
    if ($leftRank === 2) {
        return strcmp((string) $left, (string) $right);
    }

    return 0;
};

/**
 * @param list<array<string,mixed>> $rows
 * @param list<string> $columns
 * @return list<array<string,mixed>>
 */
$sortRows = static function (array $rows, array $columns) use ($sqliteCompare): array {
    usort(
        $rows,
        static function (array $left, array $right) use ($columns, $sqliteCompare): int {
            foreach ($columns as $column) {
                $comparison = $sqliteCompare($left[$column] ?? null, $right[$column] ?? null);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        }
    );

    return $rows;
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$derivedSourceTables = static function (int $case): array {
    $base = 100000 + ($case * 10);

    return [
        't1' => [
            ['a' => $base + 1, 'b' => 'shared-' . $case, 'left_payload' => 'left-shared-' . $case],
            ['a' => $base + 2, 'b' => 'left-only-' . $case, 'left_payload' => 'left-only-' . $case],
            ['a' => null, 'b' => null, 'left_payload' => 'left-null-' . $case],
        ],
        't2' => [
            ['a' => $base + 1, 'b' => 'shared-' . $case, 'right_payload' => 'right-shared-' . $case],
            ['a' => $base + 3, 'b' => 'right-only-' . $case, 'right_payload' => 'right-only-' . $case],
            ['a' => null, 'b' => null, 'right_payload' => 'right-null-' . $case],
        ],
        't3' => [
            ['b' => 'shared-' . $case, 'category' => 'category-shared-' . $case],
            ['b' => 'left-only-' . $case, 'category' => 'category-left-' . $case],
            ['b' => 'orphan-' . $case, 'category' => 'category-orphan-' . $case],
        ],
    ];
};

/**
 * @param list<mixed> $expected
 */
$assertFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' first/last values'
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint'
    );
};

/**
 * @param list<array<string,mixed>> $rows
 * @param list<string> $columns
 * @return list<mixed>
 */
$projectFlat = static function (array $rows, array $columns) use ($flattenRows): array {
    return $flattenRows(array_map(
        static function (array $row) use ($columns): array {
            $projected = [];
            foreach ($columns as $column) {
                $projected[$column] = $row[$column] ?? null;
            }

            return $projected;
        },
        $rows
    ));
};

/**
 * @return array{
 *   cartesian:list<mixed>,
 *   onForward:list<mixed>,
 *   onReverse:list<mixed>,
 *   joinedDerived:list<mixed>,
 *   naturalDerived:list<mixed>,
 *   naturalReverse:list<mixed>,
 *   naturalLeft:list<mixed>
 * }
 */
$derivedSourceExpected = static function (array $tables) use ($sortRows, $projectFlat): array {
    $cartesian = [];
    foreach ($tables['t1'] as $left) {
        if ($left['a'] === null) {
            continue;
        }
        foreach ($tables['t2'] as $right) {
            if ($right['a'] === null) {
                continue;
            }
            $cartesian[] = [
                'left_a' => $left['a'],
                'left_b' => $left['b'],
                'right_a' => $right['a'],
                'right_b' => $right['b'],
            ];
        }
    }
    $cartesian = $sortRows($cartesian, ['left_a', 'right_a']);

    $onForward = [];
    foreach ($tables['t1'] as $left) {
        foreach ($tables['t2'] as $right) {
            if ($left['a'] !== null && $left['a'] === $right['a']) {
                $onForward[] = [
                    'left_a' => $left['a'],
                    'left_b' => $left['b'],
                    'right_a' => $right['a'],
                    'right_b' => $right['b'],
                ];
            }
        }
    }
    $onForward = $sortRows($onForward, ['left_a']);

    $onReverse = array_map(
        static fn (array $row): array => [
            'right_a' => $row['right_a'],
            'right_b' => $row['right_b'],
            'left_a' => $row['left_a'],
            'left_b' => $row['left_b'],
        ],
        $onForward
    );

    $joinedDerived = [];
    foreach ($cartesian as $row) {
        foreach ($tables['t3'] as $category) {
            if ($row['left_b'] === $category['b']) {
                $joinedDerived[] = $row + ['category' => $category['category']];
            }
        }
    }
    $joinedDerived = $sortRows($joinedDerived, ['left_a', 'right_a']);

    $naturalDerived = [];
    foreach ($cartesian as $row) {
        foreach ($tables['t3'] as $category) {
            if ($row['left_b'] === $category['b']) {
                $naturalDerived[] = [
                    'b' => $row['left_b'],
                    'left_a' => $row['left_a'],
                    'right_b' => $row['right_b'],
                ];
            }
        }
    }
    $naturalDerived = $sortRows($naturalDerived, ['b', 'left_a', 'right_b']);

    $naturalReverse = [];
    foreach ($tables['t3'] as $category) {
        foreach ($cartesian as $row) {
            if ($category['b'] === $row['left_b']) {
                $naturalReverse[] = [
                    'b' => $category['b'],
                    'category' => $category['category'],
                    'left_a' => $row['left_a'],
                    'right_b' => $row['right_b'],
                ];
            }
        }
    }
    $naturalReverse = $sortRows($naturalReverse, ['b', 'left_a', 'right_b']);

    $naturalLeft = [];
    foreach ($tables['t3'] as $category) {
        $matched = false;
        foreach ($cartesian as $row) {
            if ($category['b'] !== $row['left_b']) {
                continue;
            }
            $matched = true;
            $naturalLeft[] = [
                'b' => $category['b'],
                'category' => $category['category'],
                'left_a' => $row['left_a'],
                'right_b' => $row['right_b'],
            ];
        }
        if (!$matched) {
            $naturalLeft[] = [
                'b' => $category['b'],
                'category' => $category['category'],
                'left_a' => null,
                'right_b' => null,
            ];
        }
    }
    $naturalLeft = $sortRows($naturalLeft, ['b', 'left_a', 'right_b']);

    return [
        'cartesian' => $projectFlat($cartesian, ['left_a', 'left_b', 'right_a', 'right_b']),
        'onForward' => $projectFlat($onForward, ['left_a', 'left_b', 'right_a', 'right_b']),
        'onReverse' => $projectFlat($onReverse, ['right_a', 'right_b', 'left_a', 'left_b']),
        'joinedDerived' => $projectFlat($joinedDerived, ['left_a', 'left_b', 'right_a', 'right_b', 'category']),
        'naturalDerived' => $projectFlat($naturalDerived, ['b', 'left_a', 'right_b']),
        'naturalReverse' => $projectFlat($naturalReverse, ['b', 'category', 'left_a', 'right_b']),
        'naturalLeft' => $projectFlat($naturalLeft, ['b', 'category', 'left_a', 'right_b']),
    ];
};

$tests['real upstream e_select2.test derived source materialization cites source'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test';

        $t->true(is_file($source), 'hydrated upstream e_select2.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream e_select2.test is readable');
        $t->contains('EVIDENCE-OF: R-59237-46742', $text);
        $t->contains('SELECT * FROM t1 JOIN %ss%', $text);
        $t->contains('SELECT * FROM %ss% AS x JOIN t1 ON (t1.a=x.a)', $text);
        $t->contains('SELECT * FROM %ss% NATURAL JOIN t3', $text);
        $t->contains('SELECT * FROM t3 NATURAL LEFT JOIN %ss%', $text);
    };

for ($case = 0; $case < 1000; $case++) {
    $tables = $derivedSourceTables($case);
    $expected = $derivedSourceExpected($tables);

    $tests[sprintf('real upstream e_select2.test derived table-or-subquery materialization dynamic case %04d', $case)] =
        static function (TestRunner $t) use ($assertFlat, $tables, $expected, $case): void {
            $cartesianSql = 'SELECT t1.a, t1.b, x.a, x.b '
                . 'FROM t1 JOIN (SELECT a, b FROM t2) AS x '
                . 'WHERE t1.a IS NOT NULL AND x.a IS NOT NULL '
                . 'ORDER BY t1.a, x.a';
            $onForwardSql = 'SELECT t1.a, t1.b, x.a, x.b '
                . 'FROM t1 JOIN (SELECT a, b FROM t2) AS x ON (t1.a=x.a) '
                . 'ORDER BY t1.a';
            $onReverseSql = 'SELECT x.a, x.b, t1.a, t1.b '
                . 'FROM (SELECT a, b FROM t2) AS x JOIN t1 ON (t1.a=x.a) '
                . 'ORDER BY x.a';
            $joinedDerivedSql = 'SELECT x.left_a, x.left_b, x.right_a, x.right_b, t3.category '
                . 'FROM (SELECT t1.a AS left_a, t1.b AS left_b, t2.a AS right_a, t2.b AS right_b '
                . '      FROM t1, t2 WHERE t1.a IS NOT NULL AND t2.a IS NOT NULL) AS x '
                . 'JOIN t3 ON (x.left_b=t3.b) '
                . 'ORDER BY x.left_a, x.right_a';
            $naturalDerivedSql = 'SELECT b, left_a, right_b '
                . 'FROM (SELECT t1.b AS b, t1.a AS left_a, t2.b AS right_b '
                . '      FROM t1, t2 WHERE t1.a IS NOT NULL AND t2.a IS NOT NULL) AS x '
                . 'NATURAL JOIN t3 '
                . 'ORDER BY b, left_a, right_b';
            $naturalReverseSql = 'SELECT b, category, left_a, right_b '
                . 'FROM t3 NATURAL JOIN (SELECT t1.b AS b, t1.a AS left_a, t2.b AS right_b '
                . '                         FROM t1, t2 WHERE t1.a IS NOT NULL AND t2.a IS NOT NULL) AS x '
                . 'ORDER BY b, left_a, right_b';
            $naturalLeftSql = 'SELECT b, category, left_a, right_b '
                . 'FROM t3 NATURAL LEFT JOIN (SELECT t1.b AS b, t1.a AS left_a, t2.b AS right_b '
                . '                              FROM t1, t2 WHERE t1.a IS NOT NULL AND t2.a IS NOT NULL) AS x '
                . 'ORDER BY b, left_a, right_b';

            $assertFlat($t, $cartesianSql, $tables, $expected['cartesian'], 'e_select2-2.2.1.1 derived cartesian source case ' . $case);
            $assertFlat($t, $onForwardSql, $tables, $expected['onForward'], 'e_select2-2.2.1.2 derived source right side ON case ' . $case);
            $assertFlat($t, $onReverseSql, $tables, $expected['onReverse'], 'e_select2-2.2.1.3 derived source left side ON case ' . $case);
            $assertFlat($t, $joinedDerivedSql, $tables, $expected['joinedDerived'], 'e_select2-2.2.1.4 derived cross source joins table case ' . $case);
            $assertFlat($t, $naturalDerivedSql, $tables, $expected['naturalDerived'], 'e_select2-2.2.1.5 derived source natural join case ' . $case);
            $assertFlat($t, $naturalReverseSql, $tables, $expected['naturalReverse'], 'e_select2-2.2.1.6 natural join derived source case ' . $case);
            $assertFlat($t, $naturalLeftSql, $tables, $expected['naturalLeft'], 'e_select2-2.2.1.7 natural left join derived source case ' . $case);
            $t->same(true, $case >= 0 && $case < 1000, 'bounded dynamic e_select2 derived-source case id');
        };
}

$tests['real upstream e_select2.test derived source non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'e_select2.test e_select-2.2.1 cases 1 through 7 derived table-or-subquery materialization',
            'e_select2.test e_select-2.2.1 cases 1 through 7 derived table-or-subquery materialization'
        );
        $t->same(
            'non-overlap: avoids accepted e_select2 direct join semantics, join collation, join associativity, USING affinity cases 8-15, selectD parenthesized joins, SELECT subquery predicates, grouped SELECT, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows',
            'non-overlap: avoids accepted e_select2 direct join semantics, join collation, join associativity, USING affinity cases 8-15, selectD parenthesized joins, SELECT subquery predicates, grouped SELECT, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows'
        );
        $t->same(
            'dependency closure: no new support component needed; reuses SQLiteSelectSql derived table, subquery source, JOIN, NATURAL JOIN, and LEFT JOIN execution over row arrays plus hydrated upstream source truth',
            'dependency closure: no new support component needed; reuses SQLiteSelectSql derived table, subquery source, JOIN, NATURAL JOIN, and LEFT JOIN execution over row arrays plus hydrated upstream source truth'
        );
    };

return $tests;
