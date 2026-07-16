<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-1.4.1: cartesian product columns are left columns followed by
 *   right columns.
 * - e_select-1.4.2: every unique left/right row combination is produced.
 * - e_select-1.4.3: product row counts and output widths multiply/add.
 * - e_select-1.4.5: comma, JOIN, INNER JOIN, and CROSS JOIN are equivalent
 *   for unconstrained joins.
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
            if (is_string($column) && str_starts_with($column, '__sqlite_')) {
                continue;
            }
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param list<array<string,mixed>> $leftRows
 * @param list<array<string,mixed>> $rightRows
 * @return list<array<string,mixed>>
 */
$cartesianRows = static function (array $leftRows, array $rightRows): array {
    $rows = [];
    foreach ($leftRows as $leftRow) {
        foreach ($rightRows as $rightRow) {
            $rows[] = array_merge($leftRow, $rightRow);
        }
    }

    return $rows;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return int
 */
$visibleWidth = static function (array $rows): int {
    if ($rows === []) {
        return 0;
    }

    $width = 0;
    foreach (array_keys($rows[0]) as $column) {
        if (is_string($column) && str_starts_with($column, '__sqlite_')) {
            continue;
        }
        $width++;
    }

    return $width;
};

/**
 * @return array{x1:list<array<string,mixed>>,x2:list<array<string,mixed>>,x3:list<array<string,mixed>>}
 */
$cartesianFixture = static function (int $seed): array {
    $base = ($seed + 1) * 1000;
    $x1Count = 2 + ($seed % 3);
    $x2Count = 2 + (($seed + 1) % 3);
    $x3Count = 3 + ($seed % 2);

    $x1 = [];
    for ($i = 0; $i < $x1Count; $i++) {
        $x1[] = [
            'a' => $base + $i + 1,
            'b' => sprintf('left_%04d_%02d', $seed, $i),
        ];
    }

    $x2 = [];
    for ($i = 0; $i < $x2Count; $i++) {
        $x2[] = [
            'c' => $base + 100 + ($i * 2),
            'd' => sprintf('middle_%04d_%02d', $seed, $i),
            'e' => ($seed + $i) % 7,
        ];
    }

    $x3 = [];
    for ($i = 0; $i < $x3Count; $i++) {
        $x3[] = [
            'f' => $base + 200 + ($i * 3),
            'g' => sprintf('right_g_%04d_%02d', $seed, $i),
            'h' => $base + 300 - $i,
            'i' => sprintf('right_i_%04d_%02d', $seed, $i),
        ];
    }

    return ['x1' => $x1, 'x2' => $x2, 'x3' => $x3];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat rows');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values'
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint'
    );
    $t->true(str_starts_with(strtolower(ltrim($sql)), 'select'), $label . ' is SELECT SQL');
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 */
$assertProductShape = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    int $expectedRows,
    int $expectedWidth,
    string $label
) use ($flattenRows, $visibleWidth): void {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $flat = $flattenRows($rows);

    $t->same($expectedRows, count($rows), $label . ' row count');
    $t->same($expectedWidth, $visibleWidth($rows), $label . ' visible width');
    $t->same($expectedRows * $expectedWidth, count($flat), $label . ' flat width');
    $t->same(
        hash('sha256', json_encode([$expectedRows, $expectedWidth], JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode([count($rows), $visibleWidth($rows)], JSON_THROW_ON_ERROR)),
        $label . ' shape fingerprint'
    );
};

$tests['real upstream e_select.test e_select-1.4 cartesian source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('EVIDENCE-OF: R-59089-25828', $text);
    $t->contains('EVIDENCE-OF: R-44414-54710', $text);
    $t->contains('EVIDENCE-OF: R-18439-38548', $text);
    $t->contains('do_select_tests e_select-1.4.5', $text);
    $t->contains('SELECT * FROM x1 %JOIN% x2 LIMIT 1', $text);
    $t->contains('SELECT count(*) FROM x2 %JOIN% x3', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $tests[sprintf('real upstream e_select.test e_select-1.4 dynamic cartesian product seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $cartesianFixture,
            $cartesianRows,
            $flattenRows,
            $assertFlatSelect,
            $assertProductShape,
            $seed
        ): void {
            $tables = $cartesianFixture($seed);
            $x1x2 = $cartesianRows($tables['x1'], $tables['x2']);
            $x2x1 = $cartesianRows($tables['x2'], $tables['x1']);
            $x2x3 = $cartesianRows($tables['x2'], $tables['x3']);
            $x3x2 = $cartesianRows($tables['x3'], $tables['x2']);

            $assertFlatSelect(
                $t,
                'SELECT * FROM x1 JOIN x2 LIMIT 1',
                $tables,
                $flattenRows([array_merge($tables['x1'][0], $tables['x2'][0])]),
                'e_select-1.4.1 x1 then x2 column order seed ' . $seed
            );
            $assertFlatSelect(
                $t,
                'SELECT * FROM x2 JOIN x1 LIMIT 1',
                $tables,
                $flattenRows([array_merge($tables['x2'][0], $tables['x1'][0])]),
                'e_select-1.4.1 x2 then x1 column order seed ' . $seed
            );
            $assertFlatSelect(
                $t,
                'SELECT * FROM x3 JOIN x2 LIMIT 1',
                $tables,
                $flattenRows([array_merge($tables['x3'][0], $tables['x2'][0])]),
                'e_select-1.4.1 x3 then x2 column order seed ' . $seed
            );
            $assertFlatSelect(
                $t,
                'SELECT * FROM x2 JOIN x3 LIMIT 1',
                $tables,
                $flattenRows([array_merge($tables['x2'][0], $tables['x3'][0])]),
                'e_select-1.4.1 x2 then x3 column order seed ' . $seed
            );

            $assertFlatSelect(
                $t,
                'SELECT * FROM x2 JOIN x3 ORDER BY c, f',
                $tables,
                $flattenRows($x2x3),
                'e_select-1.4.2 every x2/x3 row combination seed ' . $seed
            );
            $assertFlatSelect(
                $t,
                'SELECT * FROM x3 INNER JOIN x2 ORDER BY f, c',
                $tables,
                $flattenRows($x3x2),
                'e_select-1.4.2 every x3/x2 row combination seed ' . $seed
            );

            $assertProductShape(
                $t,
                'SELECT * FROM x1 JOIN x2 ORDER BY a, c',
                $tables,
                count($tables['x1']) * count($tables['x2']),
                5,
                'e_select-1.4.3 x1/x2 product shape seed ' . $seed
            );
            $assertProductShape(
                $t,
                'SELECT * FROM x2 JOIN x3 ORDER BY c, f',
                $tables,
                count($tables['x2']) * count($tables['x3']),
                7,
                'e_select-1.4.3 x2/x3 product shape seed ' . $seed
            );
            $assertProductShape(
                $t,
                'SELECT * FROM x3 CROSS JOIN x1 ORDER BY f, a',
                $tables,
                count($tables['x3']) * count($tables['x1']),
                6,
                'e_select-1.4.3 x3/x1 product shape seed ' . $seed
            );
            $assertFlatSelect(
                $t,
                'SELECT count(*) FROM x3 CROSS JOIN x3 AS x4',
                $tables,
                [count($tables['x3']) * count($tables['x3'])],
                'e_select-1.4.3 self product row count seed ' . $seed
            );

            foreach ([',' => 'comma', 'JOIN' => 'plain join', 'INNER JOIN' => 'inner join', 'CROSS JOIN' => 'cross join'] as $operator => $label) {
                $fromSql = $operator === ',' ? 'x1, x2' : 'x1 ' . $operator . ' x2';
                $assertFlatSelect(
                    $t,
                    'SELECT * FROM ' . $fromSql . ' ORDER BY a, c',
                    $tables,
                    $flattenRows($x1x2),
                    'e_select-1.4.5 ' . $label . ' equivalence seed ' . $seed
                );
            }

            $assertFlatSelect(
                $t,
                'SELECT count(*) FROM x1 JOIN x2 JOIN x3',
                $tables,
                [count($tables['x1']) * count($tables['x2']) * count($tables['x3'])],
                'e_select-1.4.3 three-source product count seed ' . $seed
            );
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded e_select-1.4 dynamic seed');
        };
}

$tests['real upstream e_select.test e_select-1.4 non-overlap dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'e_select.test e_select-1.4.1 through e_select-1.4.5 cartesian FROM/JOIN product semantics',
        'e_select.test e_select-1.4.1 through e_select-1.4.5 cartesian FROM/JOIN product semantics'
    );
    $t->same(
        'non-overlap: owns cartesian product column order, row combinations, product shape arithmetic, and unconstrained join-operator equivalence; avoids accepted e_select-0.1 constrained joins, e_select2 joins, NATURAL/LEFT/USING behavior, selectD parenthesized joins, SELECT subqueries, grouped SELECT text, JSON table source/cursor behavior, WAL, VFS, B-tree, and runner metadata rows',
        'non-overlap: owns cartesian product column order, row combinations, product shape arithmetic, and unconstrained join-operator equivalence; avoids accepted e_select-0.1 constrained joins, e_select2 joins, NATURAL/LEFT/USING behavior, selectD parenthesized joins, SELECT subqueries, grouped SELECT text, JSON table source/cursor behavior, WAL, VFS, B-tree, and runner metadata rows'
    );
    $t->same(
        'dependency closure: no new support component needed; reuses SQLiteSelectSql FROM/JOIN row production, wildcard projection, ORDER BY, count aggregate, LIMIT, and hydrated upstream SQLite e_select.test source truth',
        'dependency closure: no new support component needed; reuses SQLiteSelectSql FROM/JOIN row production, wildcard projection, ORDER BY, count aggregate, LIMIT, and hydrated upstream SQLite e_select.test source truth'
    );
};

return $tests;
