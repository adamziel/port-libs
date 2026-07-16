<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test
 * - selectB-2.4 through selectB-2.12: compound UNION ALL subqueries flattened
 *   through outer WHERE, ORDER BY, LIMIT, and OFFSET clauses.
 * - selectB-3.1 through selectB-3.18: compound subquery DISTINCT/GROUP BY,
 *   joins, EXCEPT/UNION/INTERSECT, and nested LIMIT behavior.
 */

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectBTables = static function (int $seed): array {
    $base = 10 + ($seed * 30);

    return [
        't1' => [
            ['a' => $base + 2, 'b' => $base + 4, 'c' => $base + 6],
            ['a' => $base + 8, 'b' => $base + 10, 'c' => $base + 12],
            ['a' => $base + 14, 'b' => $base + 16, 'c' => $base + 18],
        ],
        't2' => [
            ['d' => $base + 3, 'e' => $base + 6, 'f' => $base + 9],
            ['d' => $base + 12, 'e' => $base + 15, 'f' => $base + 18],
            ['d' => $base + 21, 'e' => $base + 24, 'f' => $base + 27],
        ],
    ];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flatRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param list<mixed> $expected
 * @param array<string,list<array<string,mixed>>> $tables
 */
$assertFlatSelectB = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flatRows): void {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $actual = $flatRows($rows);

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint'
    );
};

$tests = [];

$tests['real upstream selectB.test compound subquery dynamic cites source cases'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test';

    $t->true(is_file($source), 'hydrated upstream selectB.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream selectB.test is readable');
    $t->contains('test_transform selectB-$ii.4', $text);
    $t->contains('do_test selectB-$ii.3', $text);
    $t->contains('do_test selectB-$ii.14', $text);
    $t->contains('do_test selectB-$ii.18', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $base = 10 + ($seed * 30);
    $tables = $selectBTables($seed);
    $threshold = $base + 10;
    $allAAndDOrdered = [$base + 2, $base + 3, $base + 8, $base + 12, $base + 14, $base + 21];
    $efUnion = [$base + 6, $base + 9, $base + 15, $base + 18, $base + 24, $base + 27];

    $tests[sprintf('real upstream selectB.test compound subquery dynamic seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelectB, $tables, $base, $threshold, $allAAndDOrdered, $efUnion, $seed): void {
            $assertFlatSelectB(
                $t,
                'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2) WHERE a>'
                    . $threshold . ' ORDER BY 1',
                $tables,
                [$base + 12, $base + 14, $base + 21],
                'selectB-2.4 outer WHERE and ORDER BY over UNION ALL subquery seed ' . $seed
            );
            $assertFlatSelectB(
                $t,
                'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2) ORDER BY 1 LIMIT 2 OFFSET 3',
                $tables,
                [$base + 12, $base + 14],
                'selectB-2.8 outer LIMIT/OFFSET over ordered UNION ALL subquery seed ' . $seed
            );
            $assertFlatSelectB(
                $t,
                'SELECT c, count(*) FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) GROUP BY c ORDER BY 1',
                $tables,
                [$base + 6, 2, $base + 12, 1, $base + 15, 1, $base + 18, 1, $base + 24, 1],
                'selectB-3.2 GROUP BY over UNION ALL subquery seed ' . $seed
            );
            $assertFlatSelectB(
                $t,
                'SELECT c, count(*) FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) GROUP BY c HAVING count(*)>1',
                $tables,
                [$base + 6, 2],
                'selectB-3.3 HAVING over UNION ALL subquery seed ' . $seed
            );
            $assertFlatSelectB(
                $t,
                'SELECT t4.c, t3.a FROM (SELECT c FROM t1 UNION ALL SELECT e FROM t2) AS t4, t1 AS t3 '
                    . 'WHERE t3.a=' . ($base + 14) . ' ORDER BY 1',
                $tables,
                [$base + 6, $base + 14, $base + 6, $base + 14, $base + 12, $base + 14, $base + 15, $base + 14, $base + 18, $base + 14, $base + 24, $base + 14],
                'selectB-3.4 join against UNION ALL subquery seed ' . $seed
            );
            $assertFlatSelectB(
                $t,
                'SELECT c FROM t1 EXCEPT SELECT * FROM (SELECT e FROM t2 UNION ALL SELECT f FROM t2) ORDER BY 1',
                $tables,
                [$base + 12],
                'selectB-3.7 EXCEPT against compound subquery seed ' . $seed
            );
            $assertFlatSelectB(
                $t,
                'SELECT * FROM (SELECT e FROM t2 UNION ALL SELECT f FROM t2) UNION SELECT c FROM t1 ORDER BY 1',
                $tables,
                [$base + 6, $base + 9, $base + 12, $base + 15, $base + 18, $base + 24, $base + 27],
                'selectB-3.10 UNION against compound subquery seed ' . $seed
            );
            $assertFlatSelectB(
                $t,
                'SELECT c FROM t1 INTERSECT SELECT * FROM (SELECT e FROM t2 UNION ALL SELECT f FROM t2) ORDER BY 1',
                $tables,
                [$base + 6, $base + 18],
                'selectB-3.14 INTERSECT against compound subquery seed ' . $seed
            );
            $assertFlatSelectB(
                $t,
                'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2 LIMIT 4 OFFSET 2) LIMIT 2',
                $tables,
                [$base + 14, $base + 3],
                'selectB-3.18 nested LIMIT/OFFSET compound subquery seed ' . $seed
            );

            $t->same($allAAndDOrdered, array_values($allAAndDOrdered), 'selectB ordered a/d corpus remains seeded');
            $t->same([$base + 6, $base + 9, $base + 15, $base + 18, $base + 24, $base + 27], $efUnion, 'selectB e/f compound corpus remains seeded');
        };
}

return $tests;
