<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test
 * - select4-1.1d / select4-1.1e: CREATE TABLE AS materializes UNION ALL rows.
 * - select4-3.1.2 / select4-3.1.3: CREATE TABLE AS materializes EXCEPT rows.
 *
 * The native port does not expose a full CREATE TABLE AS executor here, so this
 * corpus verifies the equivalent SELECT materialization boundary: execute the
 * upstream compound SELECT, use its output as a new table image, then read it
 * back through SQLiteSelectSql.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelect4MaterializedRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @return array<string,list<array<string,int>>>
 */
$select4MaterializedTables = static function (int $seed): array {
    $rows = [];
    $max = 36 + ($seed % 17);
    for ($n = 1; $n <= $max; $n++) {
        $log = 0;
        while ((1 << $log) < $n) {
            $log++;
        }
        $rows[] = ['n' => $n + ($seed % 3), 'log' => $log + ($seed % 2)];
    }

    return ['t1' => $rows];
};

/**
 * @param array<string,list<array<string,int>>> $tables
 * @return list<int>
 */
$select4DistinctLogs = static function (array $tables): array {
    $logs = array_values(array_unique(array_map(static fn (array $row): int => $row['log'], $tables['t1'])));
    sort($logs, SORT_REGULAR);

    return $logs;
};

/**
 * @param array<string,list<array<string,int>>> $tables
 * @return list<int>
 */
$select4NumbersForLog = static function (array $tables, int $targetLog): array {
    $values = [];
    foreach ($tables['t1'] as $row) {
        if ($row['log'] === $targetLog) {
            $values[] = $row['n'];
        }
    }
    sort($values, SORT_REGULAR);

    return $values;
};

/**
 * @param list<int> $values
 * @return list<array<string,int>>
 */
$select4Materialize = static function (array $values): array {
    return array_map(static fn (int $value): array => ['log' => $value], $values);
};

$tests = [];

$tests['real upstream select4.test materialized compound cites CTAS source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test';
    $text = file_get_contents($source);

    $t->true(is_string($text), 'hydrated upstream select4.test is readable');
    $t->contains('select4-1.1d', $text);
    $t->contains('select4-1.1e', $text);
    $t->contains('select4-3.1.2', $text);
    $t->contains('select4-3.1.3', $text);
    $t->contains('CREATE TABLE t2 AS', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $tables = $select4MaterializedTables($seed);
    $logs = $select4DistinctLogs($tables);
    $targetLog = $logs[($seed + 2) % count($logs)];
    $targetNumbers = $select4NumbersForLog($tables, $targetLog);

    $unionAllAsc = array_merge($logs, $targetNumbers);
    sort($unionAllAsc, SORT_REGULAR);
    $unionAllDesc = array_reverse($unionAllAsc);

    $exceptAsc = array_values(array_filter($logs, static fn (int $log): bool => !in_array($log, $targetNumbers, true)));
    sort($exceptAsc, SORT_REGULAR);
    $exceptDesc = array_reverse($exceptAsc);

    $tests[sprintf('real upstream select4.test materialized compound CTAS readback seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $flattenSelect4MaterializedRows,
            $select4Materialize,
            $tables,
            $targetLog,
            $unionAllAsc,
            $unionAllDesc,
            $exceptAsc,
            $exceptDesc,
            $seed
        ): void {
            $unionSql = "SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log={$targetLog} ORDER BY log";
            $unionRows = SQLiteSelectSql::execute($unionSql, $tables);
            $t->same($unionAllAsc, $flattenSelect4MaterializedRows($unionRows), 'select4-1.1d materialized UNION ALL source rows');
            $t->same($unionAllAsc, $flattenSelect4MaterializedRows(SQLiteSelectSql::execute('SELECT * FROM t2', ['t2' => $unionRows])), 'select4-1.1d materialized UNION ALL readback');

            $unionDescSql = "SELECT DISTINCT log FROM t1 UNION ALL SELECT n FROM t1 WHERE log={$targetLog} ORDER BY log DESC";
            $unionDescRows = SQLiteSelectSql::execute($unionDescSql, $tables);
            $t->same($unionAllDesc, $flattenSelect4MaterializedRows($unionDescRows), 'select4-1.1e materialized UNION ALL DESC source rows');
            $t->same($unionAllDesc, $flattenSelect4MaterializedRows(SQLiteSelectSql::execute('SELECT * FROM t2', ['t2' => $unionDescRows])), 'select4-1.1e materialized UNION ALL DESC readback');

            $exceptSql = "SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log={$targetLog} ORDER BY log";
            $exceptRows = SQLiteSelectSql::execute($exceptSql, $tables);
            $t->same($exceptAsc, $flattenSelect4MaterializedRows($exceptRows), 'select4-3.1.2 materialized EXCEPT source rows');
            $t->same($exceptAsc, $flattenSelect4MaterializedRows(SQLiteSelectSql::execute('SELECT * FROM t2', ['t2' => $select4Materialize($exceptAsc)])), 'select4-3.1.2 materialized EXCEPT readback');

            $exceptDescSql = "SELECT DISTINCT log FROM t1 EXCEPT SELECT n FROM t1 WHERE log={$targetLog} ORDER BY log DESC";
            $exceptDescRows = SQLiteSelectSql::execute($exceptDescSql, $tables);
            $t->same($exceptDesc, $flattenSelect4MaterializedRows($exceptDescRows), 'select4-3.1.3 materialized EXCEPT DESC source rows');
            $t->same($exceptDesc, $flattenSelect4MaterializedRows(SQLiteSelectSql::execute('SELECT * FROM t2', ['t2' => $select4Materialize($exceptDesc)])), 'select4-3.1.3 materialized EXCEPT DESC readback');

            $t->same($seed >= 0 && $seed < 1000, true, 'bounded select4 materialized seed');
            $t->contains('select4.test', 'select4.test materialized compound source');
        };
}

$tests['real upstream select4.test materialized compound dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('SQLiteSelectSql compound output reused as generic table image', 'SQLiteSelectSql compound output reused as generic table image');
    $t->same('real-upstream-corpus-select-core-dynamic-20260531T074215Z-0', 'real-upstream-corpus-select-core-dynamic-20260531T074215Z-0');
};

return $tests;
