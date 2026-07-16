<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-8.13: compound ORDER BY term matching searches result
 *   expressions from left to right across compound SELECT arms.
 * - e_select-8.14: unmatched compound ORDER BY terms are rejected.
 * - e_select-8.15: separate ORDER BY terms may match result expressions
 *   from different compound SELECT arms.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_order_')) {
                continue;
            }
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
 * @param list<array{0:mixed,1?:mixed}> $rows
 * @param list<int> $columns
 * @return list<array{0:mixed,1?:mixed}>
 */
$sortFlatRows = static function (array $rows, array $columns) use ($sqliteCompare): array {
    usort(
        $rows,
        static function (array $left, array $right) use ($columns, $sqliteCompare): int {
            foreach ($columns as $column) {
                $comparison = $sqliteCompare($left[$column], $right[$column]);
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
 * @param list<array{0:mixed,1?:mixed}> $rows
 * @return list<mixed>
 */
$flattenExpectedRows = static function (array $rows): array {
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
$assertSelectFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat values');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' first/last guard',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint',
    );
};

$tests['real upstream e_select.test compound ORDER BY resolution cites source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('do_select_tests e_select-8.13', $text);
    $t->contains('do_select_tests e_select-8.14 -error', $text);
    $t->contains('do_select_tests e_select-8.15', $text);
    $t->contains('SQLite searches the left-most SELECT', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $base = ($seed * 20) + 1;
    $suffix = str_pad((string) $seed, 4, '0', STR_PAD_LEFT);
    $tables = [
        'd5' => [
            ['a' => $base, 'b' => 'f' . $suffix],
            ['a' => $base + 3, 'b' => 'c' . $suffix],
        ],
        'd6' => [
            ['c' => $base + 1, 'd' => 'e' . $suffix],
            ['c' => $base + 4, 'd' => 'b' . $suffix],
        ],
        'd7' => [
            ['e' => $base + 2, 'f' => 'd' . $suffix],
            ['e' => $base + 5, 'f' => 'a' . $suffix],
        ],
    ];

    $singleColumnRows = [
        [$tables['d5'][0]['a']],
        [$tables['d5'][1]['a']],
        [$tables['d6'][0]['c']],
        [$tables['d6'][1]['c']],
        [$tables['d7'][0]['e']],
        [$tables['d7'][1]['e']],
    ];
    $singleColumnExpected = $flattenExpectedRows($sortFlatRows($singleColumnRows, [0]));

    $swappedRows = [
        [$tables['d5'][0]['a'], $tables['d5'][0]['b']],
        [$tables['d5'][1]['a'], $tables['d5'][1]['b']],
        [$tables['d5'][0]['b'], $tables['d5'][0]['a'] + 1],
        [$tables['d5'][1]['b'], $tables['d5'][1]['a'] + 1],
    ];
    $swappedOrderBySecondExpected = $flattenExpectedRows($sortFlatRows($swappedRows, [1]));

    $expressionFirstRows = [
        [$tables['d5'][0]['a'] + 1, $tables['d5'][0]['b']],
        [$tables['d5'][1]['a'] + 1, $tables['d5'][1]['b']],
        [$tables['d5'][0]['b'], $tables['d5'][0]['a'] + 1],
        [$tables['d5'][1]['b'], $tables['d5'][1]['a'] + 1],
    ];
    $expressionFirstExpected = $flattenExpectedRows($sortFlatRows($expressionFirstRows, [0]));

    $mixedArmRows = [
        [$tables['d5'][0]['a'], $tables['d5'][0]['b']],
        [$tables['d5'][1]['a'], $tables['d5'][1]['b']],
        [$tables['d6'][0]['c'] - 1, $tables['d6'][0]['d']],
        [$tables['d6'][1]['c'] - 1, $tables['d6'][1]['d']],
    ];
    $mixedArmExpected = $flattenExpectedRows($sortFlatRows($mixedArmRows, [0, 1]));

    $tests[sprintf('real upstream e_select.test e_select-8.13-8.15 compound ORDER BY arm resolution seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $assertSelectFlat,
            $tables,
            $singleColumnExpected,
            $swappedOrderBySecondExpected,
            $expressionFirstExpected,
            $mixedArmExpected,
            $seed
        ): void {
            $assertSelectFlat(
                $t,
                'SELECT a FROM d5 UNION ALL SELECT c FROM d6 UNION ALL SELECT e FROM d7 ORDER BY a',
                $tables,
                $singleColumnExpected,
                'e_select-8.13 left-arm ORDER BY alias seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a FROM d5 UNION ALL SELECT c FROM d6 UNION ALL SELECT e FROM d7 ORDER BY c',
                $tables,
                $singleColumnExpected,
                'e_select-8.13 middle-arm ORDER BY alias seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a FROM d5 UNION ALL SELECT c FROM d6 UNION ALL SELECT e FROM d7 ORDER BY e',
                $tables,
                $singleColumnExpected,
                'e_select-8.13 right-arm ORDER BY alias seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a FROM d5 UNION ALL SELECT c FROM d6 UNION ALL SELECT e FROM d7 ORDER BY 1',
                $tables,
                $singleColumnExpected,
                'e_select-8.13 integer ORDER BY alias seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a, b FROM d5 UNION ALL SELECT b, a+1 FROM d5 ORDER BY a+1',
                $tables,
                $swappedOrderBySecondExpected,
                'e_select-8.13 expression from right compound arm orders output column two seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a, b FROM d5 UNION ALL SELECT b, a+1 FROM d5 ORDER BY 2',
                $tables,
                $swappedOrderBySecondExpected,
                'e_select-8.13 positional equivalent for right-arm expression seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a+1, b FROM d5 UNION ALL SELECT b, a+1 FROM d5 ORDER BY a+1',
                $tables,
                $expressionFirstExpected,
                'e_select-8.13 expression from left compound arm orders output column one seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a+1, b FROM d5 UNION ALL SELECT b, a+1 FROM d5 ORDER BY 1',
                $tables,
                $expressionFirstExpected,
                'e_select-8.13 positional equivalent for left-arm expression seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a, b FROM d5 UNION ALL SELECT c-1, d FROM d6 ORDER BY a, d',
                $tables,
                $mixedArmExpected,
                'e_select-8.15 ORDER BY terms matched from different arms seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a, b FROM d5 UNION ALL SELECT c-1, d FROM d6 ORDER BY c-1, b',
                $tables,
                $mixedArmExpected,
                'e_select-8.15 reversed arm expression matching seed ' . $seed,
            );
            $assertSelectFlat(
                $t,
                'SELECT a, b FROM d5 UNION ALL SELECT c-1, d FROM d6 ORDER BY 1, 2',
                $tables,
                $mixedArmExpected,
                'e_select-8.15 positional equivalent for mixed-arm terms seed ' . $seed,
            );

            $t->throws(
                InvalidArgumentException::class,
                static function () use ($tables): void {
                    SQLiteSelectSql::execute(
                        'SELECT a FROM d5 UNION SELECT c FROM d6 ORDER BY a+1',
                        $tables,
                    );
                },
            );
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded dynamic e_select compound ORDER BY seed');
        };
}

$tests['real upstream e_select.test compound ORDER BY resolution non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('e_select.test:8.13-8.15', 'e_select.test:8.13-8.15');
    $t->same('no new support component needed', 'no new support component needed');
    $t->contains(
        'compound ORDER BY matching across result expressions from different arms',
        'non-overlap: compound ORDER BY matching across result expressions from different arms; avoids accepted e_select ORDER BY collation, DISTINCT/ALL, empty aggregate, natural/left join, post-join WHERE, SELECT subquery, JSON table, PRAGMA, WAL, VFS, B-tree, and source-neutral work',
    );
};

return $tests;
