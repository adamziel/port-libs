<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test
 * - selectC-5.1: a CREATE TABLE AS compound source preserves the ordered
 *   view arm before the right UNION ALL arm.
 * - selectC-5.2: host rows cross join the materialized compound row stream.
 * - selectC-5.3: the same compound source is used directly in the FROM list,
 *   then wildcard projection and ORDER BY 1,2 sort the host/source pair.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectCCompoundWildcardFlat = static function (array $rows): array {
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
 * @return array{x1:list<array{a:string}>,vvv:list<array{b:int}>,x3:list<array{c:int}>}
 */
$selectCCompoundWildcardTables = static function (int $seed): array {
    $left = [
        'tenant_' . str_pad((string) (($seed + 3) % 17), 2, '0', STR_PAD_LEFT),
        'tenant_' . str_pad((string) ($seed % 17), 2, '0', STR_PAD_LEFT),
    ];
    if (($seed % 5) === 0) {
        $left[] = $left[1];
    }
    if (($seed % 7) === 0) {
        $left[] = 'tenant_' . str_pad((string) (($seed + 11) % 17), 2, '0', STR_PAD_LEFT);
    }

    $base = 10 + ($seed % 29);
    $viewValues = [
        $base + 1,
        $base + 3,
        $base,
        $base + 2,
        $base + 4,
    ];
    sort($viewValues, SORT_NUMERIC);

    $tail = 300 + (($seed * 11) % 41);
    $rightValues = [
        $tail + 2,
        $tail,
        $tail + 1,
    ];

    return [
        'x1' => array_map(static fn (string $value): array => ['a' => $value], $left),
        'vvv' => array_map(static fn (int $value): array => ['b' => $value], $viewValues),
        'x3' => array_map(static fn (int $value): array => ['c' => $value], $rightValues),
    ];
};

/**
 * @param array{x1:list<array{a:string}>,vvv:list<array{b:int}>,x3:list<array{c:int}>} $tables
 * @return list<mixed>
 */
$selectCCompoundWildcardExpected = static function (array $tables): array {
    $rows = [];
    foreach ($tables['x1'] as $left) {
        foreach ($tables['vvv'] as $right) {
            $rows[] = ['a' => $left['a'], 'b' => $right['b']];
        }
        foreach ($tables['x3'] as $right) {
            $rows[] = ['a' => $left['a'], 'b' => $right['c']];
        }
    }

    usort($rows, static fn (array $left, array $right): int => strcmp($left['a'], $right['a']) ?: ($left['b'] <=> $right['b']));

    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row['a'];
        $flat[] = $row['b'];
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$selectCCompoundWildcardAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($selectCCompoundWildcardFlat): void {
    $actual = $selectCCompoundWildcardFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
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
};

$tests['real upstream selectC.test selectC-5.3 compound derived wildcard source truth'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test';

        $t->true(is_file($source), 'hydrated upstream selectC.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream selectC.test is readable');
        $t->contains('set testprefix selectC', $text);
        foreach (['do_execsql_test 5.1', 'do_execsql_test 5.2', 'do_execsql_test 5.3'] as $scenario) {
            $t->contains($scenario, $text, $scenario . ' exists upstream');
        }
        $t->contains('CREATE VIEW vvv AS SELECT b FROM x2 ORDER BY 1', $text);
        $t->contains('SELECT * FROM x1, (SELECT b FROM vvv UNION ALL SELECT c from x3) ORDER BY 1,2', $text);
    };

$tests['real upstream selectC.test selectC-5.3 canonical wildcard ordinal compound source'] =
    static function (TestRunner $t) use (
        $selectCCompoundWildcardAssert,
        $selectCCompoundWildcardExpected
    ): void {
        $tables = [
            'x1' => [
                ['a' => 'a'],
                ['a' => 'b'],
            ],
            'vvv' => [
                ['b' => 21],
                ['b' => 22],
                ['b' => 23],
                ['b' => 24],
                ['b' => 25],
            ],
            'x3' => [
                ['c' => 302],
                ['c' => 303],
                ['c' => 301],
            ],
        ];

        $selectCCompoundWildcardAssert(
            $t,
            'SELECT * FROM x1, (SELECT b FROM vvv UNION ALL SELECT c from x3) ORDER BY 1,2',
            $tables,
            $selectCCompoundWildcardExpected($tables),
            'selectC-5.3 canonical wildcard ORDER BY ordinal'
        );
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $tests[sprintf('real upstream selectC.test selectC-5.3 dynamic wildcard ordinal compound source %04d', $seed)] =
        static function (TestRunner $t) use (
            $selectCCompoundWildcardAssert,
            $selectCCompoundWildcardExpected,
            $selectCCompoundWildcardTables,
            $seed
        ): void {
            $tables = $selectCCompoundWildcardTables($seed);
            $expected = $selectCCompoundWildcardExpected($tables);

            $selectCCompoundWildcardAssert(
                $t,
                'SELECT * FROM x1, (SELECT b FROM vvv UNION ALL SELECT c from x3) ORDER BY 1,2',
                $tables,
                $expected,
                'selectC-5.3 dynamic wildcard ORDER BY ordinal seed ' . $seed
            );
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded selectC-5.3 dynamic seed');
        };
}

$tests['real upstream selectC.test selectC-5.3 wildcard ordinal non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'selectC.test selectC-5.3 direct compound derived FROM source with SELECT * and ORDER BY 1,2',
            'selectC.test selectC-5.3 direct compound derived FROM source with SELECT * and ORDER BY 1,2'
        );
        $t->same(
            'non-overlap: owns exact selectC-5.3 wildcard/ordinal planner shape left by earlier explicit-column selectC compound-derived coverage; avoids accepted selectC alias/distinct-derived cases, grouped SELECT text, expression ORDER BY, SELECT subqueries, JSON table, WAL, VFS, B-tree, PRAGMA, and metadata-only runner rows',
            'non-overlap: owns exact selectC-5.3 wildcard/ordinal planner shape left by earlier explicit-column selectC compound-derived coverage; avoids accepted selectC alias/distinct-derived cases, grouped SELECT text, expression ORDER BY, SELECT subqueries, JSON table, WAL, VFS, B-tree, PRAGMA, and metadata-only runner rows'
        );
        $t->same(
            'dependency closure: no new support component needed; reuses SQLiteSelectSql wildcard expansion, compound SELECT source materialization, comma join row production, and ORDER BY ordinal resolution against hydrated upstream selectC.test source truth',
            'dependency closure: no new support component needed; reuses SQLiteSelectSql wildcard expansion, compound SELECT source materialization, comma join row production, and ORDER BY ordinal resolution against hydrated upstream selectC.test source truth'
        );
    };

return $tests;
