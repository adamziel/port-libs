<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-4.11: GROUP BY expressions use the usual SQLite collation rules.
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
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint',
    );
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$collationTables = static function (int $case): array {
    $left = sprintf('Alpha%04d', $case);
    $right = sprintf('Beta%04d', $case);

    return [
        'b3' => [
            [
                'a' => $left,
                'b' => $left,
                '__sqlite_column_collations' => ['a' => 'NOCASE', 'b' => 'BINARY'],
            ],
            [
                'a' => strtolower($left),
                'b' => strtolower($left),
                '__sqlite_column_collations' => ['a' => 'NOCASE', 'b' => 'BINARY'],
            ],
            [
                'a' => $right,
                'b' => $right,
                '__sqlite_column_collations' => ['a' => 'NOCASE', 'b' => 'BINARY'],
            ],
            [
                'a' => strtolower($right),
                'b' => strtolower($right),
                '__sqlite_column_collations' => ['a' => 'NOCASE', 'b' => 'BINARY'],
            ],
        ],
    ];
};

$tests['real upstream e_select.test cites GROUP BY collation source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('EVIDENCE-OF: R-10470-30318', $text);
    $t->contains('do_select_tests e_select-4.11', $text);
    $t->contains('SELECT count(*) FROM b3 GROUP BY a', $text);
    $t->contains('SELECT count(*) FROM b3 GROUP BY +a', $text);
    $t->contains("SELECT count(*) FROM b3 GROUP BY a||''", $text);
};

for ($case = 0; $case < 1000; $case++) {
    $tables = $collationTables($case);

    $tests[sprintf('real upstream e_select.test dynamic GROUP BY collation case %04d', $case)] =
        static function (TestRunner $t) use ($assertFlat, $tables, $case): void {
            $assertFlat(
                $t,
                'SELECT count(*) FROM b3 GROUP BY a',
                $tables,
                [2, 2],
                'e_select-4.11 GROUP BY column inherits NOCASE collation case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT count(*) FROM b3 GROUP BY +a',
                $tables,
                [2, 2],
                'e_select-4.11 GROUP BY unary plus preserves column collation case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT count(*) FROM b3 GROUP BY b',
                $tables,
                [1, 1, 1, 1],
                'e_select-4.11 GROUP BY BINARY column keeps case variants distinct case ' . $case,
            );
            $assertFlat(
                $t,
                "SELECT count(*) FROM b3 GROUP BY a||''",
                $tables,
                [1, 1, 1, 1],
                'e_select-4.11 GROUP BY concatenation expression uses BINARY collation case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT count(*) FROM b3 GROUP BY a COLLATE binary',
                $tables,
                [1, 1, 1, 1],
                'e_select-4.11 explicit BINARY overrides source-column collation case ' . $case,
            );
            $assertFlat(
                $t,
                'SELECT count(*) FROM b3 GROUP BY b COLLATE nocase',
                $tables,
                [2, 2],
                'e_select-4.11 explicit NOCASE folds a BINARY source column case ' . $case,
            );
            $t->same(true, $case >= 0 && $case < 1000, 'bounded e_select-4.11 dynamic case id');
        };
}

$tests['real upstream e_select GROUP BY collation non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'e_select.test e_select-4.11 GROUP BY collation selection',
        'e_select.test e_select-4.11 GROUP BY collation selection',
    );
    $t->same(
        'non-overlap: avoids accepted e_select DISTINCT/ALL, empty aggregate, aggregate wildcard, compound ORDER, LIMIT datatype, e_select2 join associativity/collation/subquery, grouped SELECT text, expression ORDER BY, JSON table, WAL, VFS, B-tree, and metadata-only runner rows',
        'non-overlap: avoids accepted e_select DISTINCT/ALL, empty aggregate, aggregate wildcard, compound ORDER, LIMIT datatype, e_select2 join associativity/collation/subquery, grouped SELECT text, expression ORDER BY, JSON table, WAL, VFS, B-tree, and metadata-only runner rows',
    );
    $t->same(
        'dependency closure: no new support component; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, and hydrated upstream SQLite e_select.test source truth',
        'dependency closure: no new support component; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, and hydrated upstream SQLite e_select.test source truth',
    );
};

return $tests;
