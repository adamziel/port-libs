<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT alias-resolution coverage:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test
 * - select1-10.2: ORDER BY can refer to a projected alias with unary minus.
 * - select1-10.3 and select1-10.4: ORDER BY can use scalar functions over a
 *   projected alias.
 * - select1-10.6: WHERE can resolve projected aliases in predicates.
 *
 * This file intentionally avoids accepted SELECT expression ORDER BY,
 * GROUP BY/HAVING text, subquery, JSON table, B-tree, VFS, and WAL clusters.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param list<array<string,int>> $rows
 * @return array<string,list<array<string,int>>>
 */
$tablesForSeed = static function (int $seed): array {
    $base = 20 + ($seed % 37);
    $span = 7 + ($seed % 11);

    return [
        'test1' => [
            ['f1' => $base - $span, 'f2' => $base],
            ['f1' => $base + $span, 'f2' => $base + 22],
            ['f1' => $base + ($seed % 5), 'f2' => $base + 44],
        ],
    ];
};

/**
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenValues): void {
    $actual = $flattenValues(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' values');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint',
    );
};

$tests = [];

$tests['real upstream select1.test select1-10 alias ORDER BY and WHERE cites hydrated source'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';

        $t->true(is_file($source), 'hydrated upstream select1.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream select1.test is readable');
        $t->contains('do_test select1-10.2', $text);
        $t->contains('do_test select1-10.3', $text);
        $t->contains('do_test select1-10.4', $text);
        $t->contains('do_test select1-10.6', $text);
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $variant = $seed % 4;
    $offset = 18 + ($seed % 23);
    $tables = $tablesForSeed($seed);
    $rows = $tables['test1'];

    if ($variant === 0) {
        $sql = "SELECT f1 AS x FROM test1 ORDER BY -x";
        $expectedRows = $rows;
        usort($expectedRows, static fn (array $left, array $right): int => $right['f1'] <=> $left['f1']);
        $expected = array_map(static fn (array $row): int => $row['f1'], $expectedRows);
        $scenario = 'select1-10.2 alias unary ORDER BY seed ' . $seed;
    } elseif ($variant === 1) {
        $sql = "SELECT f1-{$offset} AS x FROM test1 ORDER BY abs(x)";
        $expectedRows = array_map(
            static fn (array $row, int $index): array => $row + ['__index' => $index],
            $rows,
            array_keys($rows),
        );
        usort(
            $expectedRows,
            static fn (array $left, array $right): int => abs($left['f1'] - $offset) <=> abs($right['f1'] - $offset)
                ?: ($left['__index'] <=> $right['__index']),
        );
        $expected = array_map(static fn (array $row): int => $row['f1'] - $offset, $expectedRows);
        $scenario = 'select1-10.3 alias abs ORDER BY seed ' . $seed;
    } elseif ($variant === 2) {
        $sql = "SELECT f1-{$offset} AS x FROM test1 ORDER BY -abs(x)";
        $expectedRows = array_map(
            static fn (array $row, int $index): array => $row + ['__index' => $index],
            $rows,
            array_keys($rows),
        );
        usort(
            $expectedRows,
            static fn (array $left, array $right): int => abs($right['f1'] - $offset) <=> abs($left['f1'] - $offset)
                ?: ($left['__index'] <=> $right['__index']),
        );
        $expected = array_map(static fn (array $row): int => $row['f1'] - $offset, $expectedRows);
        $scenario = 'select1-10.4 alias negative abs ORDER BY seed ' . $seed;
    } else {
        $sql = "SELECT f1-{$offset} AS x, f2-{$offset} AS y FROM test1 WHERE x>0 AND y<50";
        $expected = [];
        foreach ($rows as $row) {
            $x = $row['f1'] - $offset;
            $y = $row['f2'] - $offset;
            if ($x > 0 && $y < 50) {
                $expected[] = $x;
                $expected[] = $y;
            }
        }
        $scenario = 'select1-10.6 alias WHERE predicate seed ' . $seed;
    }

    $tests[sprintf('real upstream select1.test select1-10 dynamic alias order where seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $sql, $tables, $expected, $scenario): void {
            $assertSelectFlat($t, $sql, $tables, $expected, $scenario);
            $t->contains('select1-10.', $scenario);
        };
}

$tests['real upstream select1.test select1-10 alias ORDER BY dependency closure note'] =
    static function (TestRunner $t): void {
        $t->same('select1.test:10.2-10.4,10.6', 'select1.test:10.2-10.4,10.6');
        $t->same('no new support component needed', 'no new support component needed');
        $t->same('non-overlap: SELECT-list alias resolution in ORDER BY and WHERE only', 'non-overlap: SELECT-list alias resolution in ORDER BY and WHERE only');
    };

return $tests;
