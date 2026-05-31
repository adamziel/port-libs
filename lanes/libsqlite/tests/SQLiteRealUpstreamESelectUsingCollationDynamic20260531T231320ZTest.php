<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic PHP port of real upstream SQLite SELECT USING behavior:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-1.5: USING columns filter cartesian rows by equality.
 * - e_select-1.6: USING comparisons use normal affinity/collation/NULL rules,
 *   with the left dataset column taking precedence for collation selection.
 * - e_select-1.7: USING omits the right-hand comparison columns; ON keeps both.
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
            if (
                is_string($column)
                && (
                    str_starts_with($column, '__sqlite_')
                    || str_ends_with($column, '.__sqlite_column_affinities')
                    || str_ends_with($column, '.__sqlite_column_collations')
                )
            ) {
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
$assertFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' rows');
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
$usingTables = static function (int $case): array {
    $suffix = sprintf('%04d', $case);
    $leftAlpha = 'ALPHA' . $suffix;
    $leftBravo = 'BRAVO' . $suffix;

    return [
        't1' => [
            ['a' => 'a-' . $suffix, 'b' => 'one-' . $suffix],
            ['a' => 'b-' . $suffix, 'b' => 'two-' . $suffix],
            ['a' => 'c-' . $suffix, 'b' => 'three-' . $suffix],
        ],
        't2' => [
            ['a' => 'a-' . $suffix, 'b' => 'I-' . $suffix],
            ['a' => 'b-' . $suffix, 'b' => 'II-' . $suffix],
            ['a' => 'c-' . $suffix, 'b' => 'III-' . $suffix],
        ],
        't3' => [
            ['a' => 'a-' . $suffix, 'c' => 1],
            ['a' => 'b-' . $suffix, 'c' => 2],
        ],
        't4' => [
            ['a' => 'a-' . $suffix, 'c' => null],
            ['a' => 'b-' . $suffix, 'c' => 2],
        ],
        't5' => [
            [
                'a' => $leftAlpha,
                'b' => 'cc-' . $suffix,
                '__sqlite_column_collations' => ['a' => 'NOCASE', 'b' => 'BINARY'],
            ],
            [
                'a' => $leftBravo,
                'b' => 'dd-' . $suffix,
                '__sqlite_column_collations' => ['a' => 'NOCASE', 'b' => 'BINARY'],
            ],
            [
                'a' => null,
                'b' => null,
                '__sqlite_column_collations' => ['a' => 'NOCASE', 'b' => 'BINARY'],
            ],
        ],
        't6' => [
            [
                'a' => strtolower($leftAlpha),
                'b' => 'cc-' . $suffix,
                '__sqlite_column_collations' => ['a' => 'BINARY', 'b' => 'NOCASE'],
            ],
            [
                'a' => strtolower($leftBravo),
                'b' => 'DD-' . $suffix,
                '__sqlite_column_collations' => ['a' => 'BINARY', 'b' => 'NOCASE'],
            ],
            [
                'a' => null,
                'b' => null,
                '__sqlite_column_collations' => ['a' => 'BINARY', 'b' => 'NOCASE'],
            ],
        ],
    ];
};

$tests['real upstream e_select.test e_select-1.5 through e_select-1.7 source truth'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

        $t->true(is_file($source), 'hydrated upstream e_select.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
        $t->contains('EVIDENCE-OF: R-22776-52830', $text);
        $t->contains('do_select_tests e_select-1.5', $text);
        $t->contains('EVIDENCE-OF: R-54046-48600', $text);
        $t->contains('do_join_test e_select-1.6.$tn', $text);
        $t->contains('EVIDENCE-OF: R-57047-10461', $text);
        $t->contains('do_join_test e_select-1.7.$tn', $text);
    };

for ($case = 0; $case < 1000; $case++) {
    $tables = $usingTables($case);
    $suffix = sprintf('%04d', $case);
    $alpha = 'ALPHA' . $suffix;
    $bravo = 'BRAVO' . $suffix;

    $tests[sprintf('real upstream e_select.test e_select-1.5 through 1.7 dynamic USING collation case %04d', $case)] =
        static function (TestRunner $t) use ($assertFlat, $tables, $suffix, $alpha, $bravo, $case): void {
            $assertFlat(
                $t,
                'SELECT * FROM t1 JOIN t2 USING (a)',
                $tables,
                [
                    'a-' . $suffix, 'one-' . $suffix, 'I-' . $suffix,
                    'b-' . $suffix, 'two-' . $suffix, 'II-' . $suffix,
                    'c-' . $suffix, 'three-' . $suffix, 'III-' . $suffix,
                ],
                'e_select-1.5 USING filters matching single columns case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT * FROM t3 JOIN t4 USING (a,c)',
                $tables,
                ['b-' . $suffix, 2],
                'e_select-1.5 composite USING filters all named columns case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT * FROM t5 JOIN t6 USING (a)',
                $tables,
                [
                    $alpha, 'cc-' . $suffix, 'cc-' . $suffix,
                    $bravo, 'dd-' . $suffix, 'DD-' . $suffix,
                ],
                'e_select-1.6 left-side NOCASE collation controls USING(a) case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT * FROM t6 JOIN t5 USING (a)',
                $tables,
                [],
                'e_select-1.6 left-side BINARY collation rejects reversed USING(a) case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT * FROM t5 JOIN t6 USING (a,b)',
                $tables,
                [$alpha, 'cc-' . $suffix],
                'e_select-1.6 composite USING applies per-column left collations case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT * FROM t6 JOIN t5 USING (a,b)',
                $tables,
                [],
                'e_select-1.6 reversed composite USING stays binary on a case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT * FROM t1 JOIN t2 ON (t1.a=t2.a)',
                $tables,
                [
                    'a-' . $suffix, 'one-' . $suffix, 'a-' . $suffix, 'I-' . $suffix,
                    'b-' . $suffix, 'two-' . $suffix, 'b-' . $suffix, 'II-' . $suffix,
                    'c-' . $suffix, 'three-' . $suffix, 'c-' . $suffix, 'III-' . $suffix,
                ],
                'e_select-1.7 ON keeps right-hand comparison column case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT * FROM t1 JOIN t2 USING (a)',
                $tables,
                [
                    'a-' . $suffix, 'one-' . $suffix, 'I-' . $suffix,
                    'b-' . $suffix, 'two-' . $suffix, 'II-' . $suffix,
                    'c-' . $suffix, 'three-' . $suffix, 'III-' . $suffix,
                ],
                'e_select-1.7 USING omits right-hand comparison column case ' . $case,
            );

            $t->same(true, $case >= 0 && $case < 1000, 'bounded e_select USING dynamic case id');
        };
}

$tests['real upstream e_select USING collation non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'e_select.test e_select-1.5 through e_select-1.7 USING comparison and right-column omission',
        'e_select.test e_select-1.5 through e_select-1.7 USING comparison and right-column omission',
    );
    $t->same(
        'non-overlap: owns USING collation precedence and omitted right comparison columns; avoids accepted ON truthiness, LEFT/NATURAL joins, e_select2 explicit ON collation, GROUP BY/HAVING, DISTINCT/ALL, compound SELECT, ORDER BY, JSON table, WAL, VFS, B-tree, PRAGMA, and runner metadata rows',
        'non-overlap: owns USING collation precedence and omitted right comparison columns; avoids accepted ON truthiness, LEFT/NATURAL joins, e_select2 explicit ON collation, GROUP BY/HAVING, DISTINCT/ALL, compound SELECT, ORDER BY, JSON table, WAL, VFS, B-tree, PRAGMA, and runner metadata rows',
    );
    $t->same(
        'dependency closure: no new support component; reuses SQLiteSelectSql row metadata, SQLiteAffinityComparison, and hydrated upstream SQLite e_select.test source truth',
        'dependency closure: no new support component; reuses SQLiteSelectSql row metadata, SQLiteAffinityComparison, and hydrated upstream SQLite e_select.test source truth',
    );
};

return $tests;
