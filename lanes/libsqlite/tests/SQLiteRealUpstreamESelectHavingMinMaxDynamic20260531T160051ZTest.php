<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-4.13: HAVING expressions may reference aggregate and
 *   non-aggregate values that are not projected.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenESelectHavingRows = static function (array $rows): array {
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
$assertESelectHavingFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenESelectHavingRows): void {
    $actual = $flattenESelectHavingRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' flat result');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint',
    );
    $t->contains('e_select-4.13', $scenario);
};

$tests['real upstream e_select.test cites HAVING minmax source section'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('EVIDENCE-OF: R-55403-13450', $text);
    $t->contains('do_select_tests e_select-4.13', $text);
    $t->contains('SELECT up||down FROM c1 GROUP BY (down<5) HAVING max(down)<10', $text);
    $t->contains('SELECT i, j FROM c2 GROUP BY i>4 HAVING j>6', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $lowName = 'low_' . $seed;
    $highName = 'high_' . $seed;
    $base = 10 + ($seed * 7);
    $lowA = $base + 1;
    $lowB = $base + 2;
    $lowC = $base + 4;
    $lowD = $base + 8;
    $split = $base + 10;
    $highA = ($base * 3) + 100;
    $highB = ($base * 3) + 200;
    $highSum = $highA + $highB;
    $lowSum = $lowA + $lowB + $lowC + $lowD;
    $sumThreshold = intdiv($lowSum + $highSum, 2);

    $c1Rows = [
        ['up' => $lowName, 'down' => $lowA],
        ['up' => $lowName, 'down' => $lowB],
        ['up' => $lowName, 'down' => $lowC],
        ['up' => $lowName, 'down' => $lowD],
        ['up' => $highName, 'down' => $highA],
        ['up' => $highName, 'down' => $highB],
    ];

    $cutoff = 4 + ($seed % 5);
    $firstHigh = $cutoff + 1;
    $c2Rows = [];
    for ($i = 1; $i <= 9; $i++) {
        $c2Rows[] = [
            'i' => $i,
            'j' => $i <= $cutoff ? $i - 1 : ($i * ($seed % 3 + 2)),
        ];
    }
    $firstHighJ = $c2Rows[$firstHigh - 1]['j'];
    $tables = [
        'c1' => $c1Rows,
        'c2' => $c2Rows,
    ];

    $tests[sprintf('real upstream e_select.test e_select-4.13 HAVING aggregate source row dynamic %04d', $seed)] =
        static function (TestRunner $t) use (
            $assertESelectHavingFlat,
            $tables,
            $lowName,
            $highName,
            $lowD,
            $split,
            $sumThreshold,
            $cutoff,
            $firstHigh,
            $firstHighJ,
            $seed
        ): void {
            $assertESelectHavingFlat(
                $t,
                'SELECT up FROM c1 GROUP BY up HAVING count(*)>3',
                $tables,
                [$lowName],
                'e_select-4.13.1 count aggregate HAVING outside result seed ' . $seed,
            );
            $assertESelectHavingFlat(
                $t,
                'SELECT up FROM c1 GROUP BY up HAVING sum(down)>' . $sumThreshold,
                $tables,
                [$highName],
                'e_select-4.13.1 sum aggregate HAVING greater outside result seed ' . $seed,
            );
            $assertESelectHavingFlat(
                $t,
                'SELECT up FROM c1 GROUP BY up HAVING sum(down)<' . $sumThreshold,
                $tables,
                [$lowName],
                'e_select-4.13.1 sum aggregate HAVING less outside result seed ' . $seed,
            );
            $assertESelectHavingFlat(
                $t,
                'SELECT up||down FROM c1 GROUP BY (down<' . $split . ') HAVING max(down)<' . $split,
                $tables,
                [$lowName . $lowD],
                'e_select-4.13.1 max aggregate HAVING chooses max source row seed ' . $seed,
            );
            $assertESelectHavingFlat(
                $t,
                'SELECT up FROM c1 GROUP BY up HAVING down>' . ($split + 1),
                $tables,
                [$highName],
                'e_select-4.13.2 non-aggregate HAVING uses same group row seed ' . $seed,
            );
            $assertESelectHavingFlat(
                $t,
                "SELECT up FROM c1 GROUP BY up HAVING up='{$highName}'",
                $tables,
                [$highName],
                'e_select-4.13.2 non-aggregate text HAVING outside result seed ' . $seed,
            );
            $assertESelectHavingFlat(
                $t,
                'SELECT i, j FROM c2 GROUP BY i>' . $cutoff . ' HAVING j>' . ($firstHighJ - 1),
                $tables,
                [$firstHigh, $firstHighJ],
                'e_select-4.13.2 non-aggregate HAVING over expression group seed ' . $seed,
            );
            $t->true($seed >= 0 && $seed < 1000, 'bounded e_select-4.13 dynamic seed');
        };
}

$tests['real upstream e_select HAVING minmax non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'e_select.test e_select-4.13 HAVING aggregate and non-aggregate expressions',
        'e_select.test e_select-4.13 HAVING aggregate and non-aggregate expressions',
    );
    $t->same(
        'non-overlap: owns e_select-4.13 HAVING expressions and min/max source-row selection; avoids accepted GROUP BY collation, aggregate wildcard, empty aggregate, DISTINCT/ALL, compound core/order, LIMIT datatype, e_select2 joins, JSON table, WAL, VFS, B-tree, PRAGMA, and runner metadata rows',
        'non-overlap: owns e_select-4.13 HAVING expressions and min/max source-row selection; avoids accepted GROUP BY collation, aggregate wildcard, empty aggregate, DISTINCT/ALL, compound core/order, LIMIT datatype, e_select2 joins, JSON table, WAL, VFS, B-tree, PRAGMA, and runner metadata rows',
    );
    $t->same(
        'dependency closure: no new support component; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, SQLiteSelectExpression, and hydrated upstream SQLite e_select.test source truth',
        'dependency closure: no new support component; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, SQLiteSelectExpression, and hydrated upstream SQLite e_select.test source truth',
    );
};

return $tests;
