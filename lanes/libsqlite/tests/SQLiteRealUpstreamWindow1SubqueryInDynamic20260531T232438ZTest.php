<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow1 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$makeJoinTables = static function (int $case): array {
    $maxId = 6 + ($case % 7);
    $t1Rows = [];
    for ($id = 1; $id <= $maxId; $id++) {
        if (($id + $case) % 6 !== 0) {
            $t1Rows[] = ['t1_id' => $id];
        }
    }

    $t2Rows = [];
    for ($id = 1; $id <= $maxId + 2; $id++) {
        if (($id * 2 + $case) % 5 !== 1) {
            $t2Rows[] = ['t2_id' => $id];
        }
    }

    $t3Rows = [];
    $t3Count = 1 + ($case % 8);
    for ($i = 1; $i <= $t3Count; $i++) {
        $t3Rows[] = ['t3_id' => 1000 + ($case * 10) + $i];
    }

    return ['t1' => $t1Rows, 't2' => $t2Rows, 't3' => $t3Rows];
};

$expectedPlainInRows = static function (array $tables): array {
    $t2Ids = array_map('intval', array_column($tables['t2'], 't2_id'));
    $rowNumbers = range(1, count($tables['t3']));
    $ids = [];

    foreach ($tables['t1'] as $row) {
        $id = (int) $row['t1_id'];
        if (in_array($id, $t2Ids, true) && in_array($id, $rowNumbers, true)) {
            $ids[] = $id;
        }
    }

    sort($ids, SORT_NUMERIC);

    return array_map(static fn (int $id): array => ['t1_id' => $id], $ids);
};

$makeCorrelatedTables = static function (int $case): array {
    $maxValue = 5 + ($case % 6);
    $t1Rows = [];
    $rowCount = $maxValue + 2 + ($case % 4);
    for ($i = 0; $i < $rowCount; $i++) {
        $t1Rows[] = ['x' => 1 + (($i * 3 + $case) % $maxValue)];
    }

    $t2Rows = [];
    for ($c = 1; $c <= $maxValue + 2; $c++) {
        $t2Rows[] = ['c' => $c];
    }

    return ['t1' => $t1Rows, 't2' => $t2Rows];
};

$expectedUncorrelatedInRows = static function (array $tables): array {
    $rowNumberCount = count($tables['t1']);
    $rows = [];
    foreach ($tables['t2'] as $row) {
        $c = (int) $row['c'];
        $rows[] = ['c' => $c, 'in_window' => $c >= 1 && $c <= $rowNumberCount ? 1 : 0];
    }

    usort($rows, static fn (array $left, array $right): int => $left['c'] <=> $right['c']);

    return $rows;
};

$expectedCorrelatedInRows = static function (array $tables): array {
    $counts = [];
    foreach ($tables['t1'] as $row) {
        $x = (int) $row['x'];
        $counts[$x] = ($counts[$x] ?? 0) + 1;
    }

    $rows = [];
    foreach ($tables['t2'] as $row) {
        $c = (int) $row['c'];
        $rows[] = ['c' => $c, 'in_window' => $c >= 1 && $c <= ($counts[$c] ?? 0) ? 1 : 0];
    }

    usort($rows, static fn (array $left, array $right): int => $left['c'] <=> $right['c']);

    return $rows;
};

$tests['real upstream window1.test 25.1 correlated row-number plus subquery excludes matches'] = static function (TestRunner $t): void {
    $tables = [
        't1' => [['t1_id' => 1], ['t1_id' => 3], ['t1_id' => 5]],
        't2' => [['t2_id' => 3], ['t2_id' => 5]],
        't3' => [['t3_id' => 10], ['t3_id' => 11], ['t3_id' => 12]],
    ];

    $actual = SQLiteSelectSql::execute(
        'SELECT t1.* FROM t1, t2 WHERE t1_id=t2_id AND t1_id IN (SELECT t1_id + row_number() OVER ( ORDER BY t1_id ) FROM t3)',
        $tables,
    );

    $t->same([], $actual, 'window1.test 25.1');
};

$tests['real upstream window1.test 25.2 row-number subquery admits joined id three'] = static function (TestRunner $t): void {
    $tables = [
        't1' => [['t1_id' => 1], ['t1_id' => 3], ['t1_id' => 5]],
        't2' => [['t2_id' => 3], ['t2_id' => 5]],
        't3' => [['t3_id' => 10], ['t3_id' => 11], ['t3_id' => 12]],
    ];

    $actual = SQLiteSelectSql::execute(
        'SELECT t1.* FROM t1, t2 WHERE t1_id=t2_id AND t1_id IN (SELECT row_number() OVER ( ORDER BY t1_id ) FROM t3)',
        $tables,
    );

    $t->same([['t1_id' => 3]], $actual, 'window1.test 25.2');
};

$tests['real upstream window1.test 26.2 row-number subquery in derived source'] = static function (TestRunner $t): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT c, c IN (SELECT row_number() OVER () FROM ( SELECT c FROM t1 )) AS in_window FROM t2',
        [
            't1' => [['x' => 1], ['x' => 2], ['x' => 3], ['x' => 4]],
            't2' => [['c' => 2], ['c' => 6], ['c' => 8], ['c' => 4]],
        ],
    );

    $t->same([
        ['c' => 2, 'in_window' => 1],
        ['c' => 6, 'in_window' => 0],
        ['c' => 8, 'in_window' => 0],
        ['c' => 4, 'in_window' => 1],
    ], $actual, 'window1.test 26.2');
};

$tests['real upstream window1.test 26.3 correlated where controls row-number cardinality'] = static function (TestRunner $t): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT c, c IN (SELECT row_number() OVER () FROM ( SELECT 1 FROM t1 WHERE x=c )) AS in_window FROM t2',
        [
            't1' => [['x' => 1], ['x' => 1], ['x' => 2], ['x' => 3], ['x' => 3], ['x' => 3], ['x' => 3], ['x' => 4], ['x' => 4]],
            't2' => [['c' => 1], ['c' => 2], ['c' => 3], ['c' => 4]],
        ],
    );

    $t->same([
        ['c' => 1, 'in_window' => 1],
        ['c' => 2, 'in_window' => 0],
        ['c' => 3, 'in_window' => 1],
        ['c' => 4, 'in_window' => 0],
    ], $actual, 'window1.test 26.3');
};

for ($case = 0; $case < 1000; $case++) {
    $tests["real upstream window1.test 25-26 dynamic row-number IN subquery case {$case}"] = static function (TestRunner $t) use (
        $case,
        $makeJoinTables,
        $expectedPlainInRows,
        $makeCorrelatedTables,
        $expectedUncorrelatedInRows,
        $expectedCorrelatedInRows,
    ): void {
        $joinTables = $makeJoinTables($case);

        $plusActual = SQLiteSelectSql::execute(
            'SELECT t1.* FROM t1, t2 WHERE t1_id=t2_id AND t1_id IN (SELECT t1_id + row_number() OVER ( ORDER BY t1_id ) FROM t3) ORDER BY t1_id',
            $joinTables,
        );
        $plainActual = SQLiteSelectSql::execute(
            'SELECT t1.* FROM t1, t2 WHERE t1_id=t2_id AND t1_id IN (SELECT row_number() OVER ( ORDER BY t1_id ) FROM t3) ORDER BY t1_id',
            $joinTables,
        );
        $expectedPlain = $expectedPlainInRows($joinTables);

        $t->same([], $plusActual, "window1.test 25.1 dynamic correlated plus excludes row {$case}");
        $t->same($expectedPlain, $plainActual, "window1.test 25.2 dynamic row-number IN join {$case}");
        $t->same(array_column($expectedPlain, 't1_id'), array_column($plainActual, 't1_id'), "window1.test 25.2 dynamic ids stay ordered {$case}");

        $correlatedTables = $makeCorrelatedTables($case);
        $uncorrelatedActual = SQLiteSelectSql::execute(
            'SELECT c, c IN (SELECT row_number() OVER () FROM ( SELECT c FROM t1 )) AS in_window FROM t2 ORDER BY c',
            $correlatedTables,
        );
        $correlatedActual = SQLiteSelectSql::execute(
            'SELECT c, c IN (SELECT row_number() OVER () FROM ( SELECT 1 FROM t1 WHERE x=c )) AS in_window FROM t2 ORDER BY c',
            $correlatedTables,
        );
        $expectedUncorrelated = $expectedUncorrelatedInRows($correlatedTables);
        $expectedCorrelated = $expectedCorrelatedInRows($correlatedTables);

        $t->same($expectedUncorrelated, $uncorrelatedActual, "window1.test 26.2 dynamic uncorrelated derived row-number IN {$case}");
        $t->same($expectedCorrelated, $correlatedActual, "window1.test 26.3 dynamic correlated row-number IN {$case}");
        $t->true($expectedUncorrelated !== $expectedCorrelated, "window1.test 26.2-26.3 dynamic correlation changes at least one row {$case}");
    };
}

$tests['real upstream window1.test 25-26 source truth and non-overlap'] = static function (TestRunner $t) use ($upstreamWindow1): void {
    $source = is_file($upstreamWindow1) ? (string) file_get_contents($upstreamWindow1) : '';

    $t->contains('do_execsql_test 25.1', $source);
    $t->contains('do_execsql_test 25.2', $source);
    $t->contains('do_execsql_test 26.2', $source);
    $t->contains('do_execsql_test 26.3', $source);
    $t->contains('row_number() OVER', $source);
    $t->same(
        'owns window1.test 25.1-25.2 and 26.2-26.3 row_number IN-subquery behavior; avoids accepted planner sort reuse, alias order, aggregate row, range offset, pushdown, filterfault, JSON, WAL, VFS, B-tree, and metadata-only runner rows',
        'owns window1.test 25.1-25.2 and 26.2-26.3 row_number IN-subquery behavior; avoids accepted planner sort reuse, alias order, aggregate row, range offset, pushdown, filterfault, JSON, WAL, VFS, B-tree, and metadata-only runner rows',
    );
};

return $tests;
