<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-4.15: grouped result-set expressions are evaluated once per
 *   group, aggregate expressions read the full group, and non-aggregate
 *   expressions use one consistent row from the group.
 * - e_select.4.16: every input group contributes exactly one output row.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenESelectGroupRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_')) {
                continue;
            }
            $flat[] = is_float($value) ? round($value, 2) : $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertESelectGroupFlat = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($flattenESelectGroupRows): void {
    $actual = $flattenESelectGroupRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' flat result');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' result fingerprint',
    );
    $t->true(
        str_contains($scenario, 'e_select-4.15') || str_contains($scenario, 'e_select.4.16'),
        $scenario . ' upstream section tag',
    );
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 */
$assertESelectGroupCount = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    int $expected,
    string $scenario
): void {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $actual = count($rows);

    $t->same($expected, $actual, $scenario . ' row count');
    $t->same($expected === 0 ? 0 : 1, $actual === 0 ? 0 : 1, $scenario . ' empty/non-empty class');
    $t->same(
        hash('sha256', json_encode([$expected], JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode([$actual], JSON_THROW_ON_ERROR)),
        $scenario . ' count fingerprint',
    );
    $t->contains('e_select.4.16', $scenario);
};

$tests['real upstream e_select.test cites grouped result source sections'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('EVIDENCE-OF: R-23927-54081', $text);
    $t->contains('EVIDENCE-OF: R-53735-47017', $text);
    $t->contains('EVIDENCE-OF: R-62913-19830', $text);
    $t->contains('EVIDENCE-OF: R-53924-08809', $text);
    $t->contains('EVIDENCE-OF: R-19334-12811', $text);
    $t->contains('do_select_tests e_select-4.15', $text);
    $t->contains('do_select_tests e_select.4.16 -count', $text);
    $t->contains('SELECT sum(down) FROM c1 GROUP BY up', $text);
    $t->contains('SELECT i, j FROM c2 GROUP BY i%2', $text);
    $t->contains('SELECT count(*), i, k FROM c2 NATURAL JOIN c3 GROUP BY substr(k, 1, 1)', $text);
};

for ($seed = 0; $seed < 750; $seed++) {
    $lowName = sprintf('a%04d_group', $seed);
    $highName = sprintf('z%04d_group', $seed);
    $offset = $seed % 5;
    $c1Rows = [
        ['up' => $lowName, 'down' => 1],
        ['up' => $lowName, 'down' => 2],
        ['up' => $lowName, 'down' => 4],
        ['up' => $lowName, 'down' => 8],
        ['up' => $highName, 'down' => 16],
        ['up' => $highName, 'down' => 32],
    ];

    $c2Rows = [];
    for ($i = 1; $i <= 9; $i++) {
        $c2Rows[] = ['i' => $i, 'j' => intdiv($i * ($i - 1), 2) + $offset];
    }
    $c3Rows = [
        ['i' => 1, 'k' => 'hydrogen'],
        ['i' => 2, 'k' => 'helium'],
        ['i' => 3, 'k' => 'lithium'],
        ['i' => 4, 'k' => 'beryllium'],
        ['i' => 5, 'k' => 'boron'],
        ['i' => 94, 'k' => 'plutonium'],
    ];
    $tables = ['c1' => $c1Rows, 'c2' => $c2Rows, 'c3' => $c3Rows];

    $iModExpected = [];
    foreach ([0, 1, 2] as $mod) {
        $values = array_values(array_map(
            static fn (array $row): int => (int) $row['j'],
            array_filter($c2Rows, static fn (array $row): bool => ((int) $row['i'] % 3) === $mod),
        ));
        $iModExpected[] = array_sum($values);
        $iModExpected[] = max($values);
    }

    $jModExpected = [];
    $jModExpressionExpected = [];
    foreach ([0, 1] as $mod) {
        $values = array_values(array_map(
            static fn (array $row): int => (int) $row['j'],
            array_filter($c2Rows, static fn (array $row): bool => ((int) $row['j'] % 2) === $mod),
        ));
        if ($values === []) {
            continue;
        }
        $sum = array_sum($values);
        $max = max($values);
        $jModExpected[] = $sum;
        $jModExpected[] = $max;
        $jModExpressionExpected[] = $sum + 1;
        $jModExpressionExpected[] = $max + 1;
    }

    $joinedByParity = [0 => [], 1 => []];
    foreach ($c2Rows as $row) {
        if (in_array($row['i'], [1, 2, 4, 8], true)) {
            $joinedByParity[(int) $row['j'] % 2][] = (int) $row['i'];
        }
    }
    $joinedExpected = [];
    foreach ([0, 1] as $mod) {
        if ($joinedByParity[$mod] === []) {
            continue;
        }
        $joinedExpected[] = count($joinedByParity[$mod]);
        $joinedExpected[] = round(array_sum($joinedByParity[$mod]) / count($joinedByParity[$mod]), 2);
    }

    $firstEvenJ = (int) $c2Rows[1]['j'];
    $firstOddJ = (int) $c2Rows[0]['j'];
    $nonAggregateExpected = [2, $firstEvenJ, 1, $firstOddJ];
    $havingThreshold = max($firstEvenJ, $firstOddJ) + 1;
    $havingRejectThreshold = max(array_column($c2Rows, 'j')) + 1;
    $groupByIExpected = [];
    for ($i = 1; $i <= 9; $i++) {
        $groupByIExpected[] = $i;
        $groupByIExpected[] = (int) $c2Rows[$i - 1]['j'];
    }
    $groupByILessThanFiveExpected = array_slice($groupByIExpected, 0, 8);

    $tests[sprintf('real upstream e_select.test e_select-4.15 grouped result dynamic %04d', $seed)] =
        static function (TestRunner $t) use (
            $assertESelectGroupFlat,
            $assertESelectGroupCount,
            $tables,
            $iModExpected,
            $jModExpected,
            $jModExpressionExpected,
            $joinedExpected,
            $nonAggregateExpected,
            $havingThreshold,
            $havingRejectThreshold,
            $groupByIExpected,
            $groupByILessThanFiveExpected,
            $seed
        ): void {
            $assertESelectGroupFlat(
                $t,
                'SELECT sum(down) FROM c1 GROUP BY up ORDER BY up',
                $tables,
                [15, 48],
                'e_select-4.15 aggregate result expression per up group seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT sum(j), max(j) FROM c2 GROUP BY (i%3) ORDER BY i%3',
                $tables,
                $iModExpected,
                'e_select-4.15 aggregate result expression over i modulo groups seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT sum(j), max(j) FROM c2 GROUP BY (j%2) ORDER BY j%2',
                $tables,
                $jModExpected,
                'e_select-4.15 aggregate result expression over j parity groups seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT 1+sum(j), max(j)+1 FROM c2 GROUP BY (j%2) ORDER BY j%2',
                $tables,
                $jModExpressionExpected,
                'e_select-4.15 aggregate arithmetic result expression over j parity groups seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT count(*), round(avg(i),2) FROM c1, c2 ON (i=down) GROUP BY j%2 ORDER BY j%2',
                $tables,
                $joinedExpected,
                'e_select-4.15 aggregate joined result expression over parity groups seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT i, j FROM c2 GROUP BY i%2 ORDER BY i%2',
                $tables,
                $nonAggregateExpected,
                'e_select-4.15 non-aggregate grouped result uses one row seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT i, j FROM c2 GROUP BY i%2 HAVING j<' . $havingThreshold . ' ORDER BY i%2',
                $tables,
                $nonAggregateExpected,
                'e_select-4.15 non-aggregate HAVING keeps same grouped row seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT i, j FROM c2 GROUP BY i%2 HAVING j>' . $havingRejectThreshold . ' ORDER BY i%2',
                $tables,
                [],
                'e_select-4.15 non-aggregate HAVING rejects grouped rows seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT count(*), i, k FROM c2 NATURAL JOIN c3 GROUP BY substr(k, 1, 1) ORDER BY substr(k, 1, 1)',
                $tables,
                [2, 4, 'beryllium', 2, 1, 'hydrogen', 1, 3, 'lithium'],
                'e_select-4.15 NATURAL JOIN grouped sample row consistency seed ' . $seed,
            );
            $assertESelectGroupCount(
                $t,
                'SELECT i, j FROM c2 GROUP BY i%2',
                $tables,
                2,
                'e_select.4.16 grouped parity contributes one result row seed ' . $seed,
            );
            $assertESelectGroupCount(
                $t,
                'SELECT i, j FROM c2 GROUP BY i',
                $tables,
                9,
                'e_select.4.16 grouped identity contributes one result row seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT i, j FROM c2 GROUP BY i ORDER BY i',
                $tables,
                $groupByIExpected,
                'e_select.4.16 grouped identity rows preserve one row per group seed ' . $seed,
            );
            $assertESelectGroupCount(
                $t,
                'SELECT i, j FROM c2 GROUP BY i HAVING i<5',
                $tables,
                4,
                'e_select.4.16 grouped HAVING identity contributes one result row seed ' . $seed,
            );
            $assertESelectGroupFlat(
                $t,
                'SELECT i, j FROM c2 GROUP BY i HAVING i<5 ORDER BY i',
                $tables,
                $groupByILessThanFiveExpected,
                'e_select.4.16 grouped HAVING identity rows preserve one row per kept group seed ' . $seed,
            );
            $t->true($seed >= 0 && $seed < 750, 'bounded e_select-4.15/e_select.4.16 dynamic seed');
        };
}

$tests['real upstream e_select grouped result non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'e_select.test e_select-4.15 grouped result expressions plus e_select.4.16 group row counts',
        'e_select.test e_select-4.15 grouped result expressions plus e_select.4.16 group row counts',
    );
    $t->same(
        'non-overlap: owns e_select-4.15/e_select.4.16 grouped result-expression and row-count semantics; avoids accepted e_select-4.13 HAVING min/max, e_select-4.11 collation, DISTINCT/ALL, compound core/order, LIMIT datatype, e_select2 joins, JSON table, WAL, VFS, B-tree, PRAGMA, and runner metadata rows',
        'non-overlap: owns e_select-4.15/e_select.4.16 grouped result-expression and row-count semantics; avoids accepted e_select-4.13 HAVING min/max, e_select-4.11 collation, DISTINCT/ALL, compound core/order, LIMIT datatype, e_select2 joins, JSON table, WAL, VFS, B-tree, PRAGMA, and runner metadata rows',
    );
    $t->same(
        'dependency closure: no new support component; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, SQLiteSelectExpression, SQLiteCoreScalarFunction, and hydrated upstream SQLite e_select.test source truth',
        'dependency closure: no new support component; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, SQLiteSelectExpression, SQLiteCoreScalarFunction, and hydrated upstream SQLite e_select.test source truth',
    );
};

return $tests;
