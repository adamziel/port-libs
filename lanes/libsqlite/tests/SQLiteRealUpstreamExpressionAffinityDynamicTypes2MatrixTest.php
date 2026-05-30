<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $sql) use ($sqlite3): array {
    static $cache = [];

    if (isset($cache[$sql])) {
        return $cache[$sql];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic types2 matrix tests');
    }

    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return $cache[$sql] = explode("\t", rtrim($output, "\r\n"));
};

$tableRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['rowid' => 1, 'i' => 10, 'n' => 10, 't' => 10, 'o' => 10],
    ['rowid' => 2, 'i' => 10.0, 'n' => 10.0, 't' => 10.0, 'o' => 10.0],
    ['rowid' => 3, 'i' => '10', 'n' => '10', 't' => '10', 'o' => '10'],
    ['rowid' => 4, 'i' => '10.0', 'n' => '10.0', 't' => '10.0', 'o' => '10.0'],
    ['rowid' => 5, 'i' => 20, 'n' => 20, 't' => 20, 'o' => 20],
    ['rowid' => 6, 'i' => 20.0, 'n' => 20.0, 't' => 20.0, 'o' => 20.0],
    ['rowid' => 7, 'i' => '20', 'n' => '20', 't' => '20', 'o' => '20'],
    ['rowid' => 8, 'i' => '20.0', 'n' => '20.0', 't' => '20.0', 'o' => '20.0'],
    ['rowid' => 9, 'i' => 30, 'n' => 30, 't' => 30, 'o' => 30],
    ['rowid' => 10, 'i' => 30.0, 'n' => 30.0, 't' => 30.0, 'o' => 30.0],
    ['rowid' => 11, 'i' => '30', 'n' => '30', 't' => '30', 'o' => '30'],
    ['rowid' => 12, 'i' => '30.0', 'n' => '30.0', 't' => '30.0', 'o' => '30.0'],
], [
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'o' => 'BLOB',
]);
$declaredAffinities = ['i' => 'INTEGER', 'n' => 'NUMERIC', 't' => 'TEXT', 'o' => 'BLOB'];
$tableRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $declaredAffinities],
    $tableRows,
);

$oracleSetup = <<<'SQL'
CREATE TABLE t2(i INTEGER, n NUMERIC, t TEXT, o XBLOBY);
INSERT INTO t2 VALUES(10, 10, 10, 10);
INSERT INTO t2 VALUES(10.0, 10.0, 10.0, 10.0);
INSERT INTO t2 VALUES('10', '10', '10', '10');
INSERT INTO t2 VALUES('10.0', '10.0', '10.0', '10.0');
INSERT INTO t2 VALUES(20, 20, 20, 20);
INSERT INTO t2 VALUES(20.0, 20.0, 20.0, 20.0);
INSERT INTO t2 VALUES('20', '20', '20', '20');
INSERT INTO t2 VALUES('20.0', '20.0', '20.0', '20.0');
INSERT INTO t2 VALUES(30, 30, 30, 30);
INSERT INTO t2 VALUES(30.0, 30.0, 30.0, 30.0);
INSERT INTO t2 VALUES('30', '30', '30', '30');
INSERT INTO t2 VALUES('30.0', '30.0', '30.0', '30.0');
SQL;

$portRowids = static function (string $where) use ($tableRows): string {
    $selected = SQLiteSelectSql::execute("SELECT rowid FROM t2 WHERE {$where} ORDER BY rowid", ['t2' => $tableRows]);

    return implode(',', array_map('strval', array_column($selected, 'rowid')));
};

$oracleRowids = static function (string $where) use ($oracle, $oracleSetup): string {
    $result = $oracle($oracleSetup . "\nSELECT coalesce(group_concat(rowid, ','), '') FROM t2 WHERE {$where} ORDER BY rowid;");

    return $result[0] ?? '';
};

$literalPool = [];
for ($value = 0; $value <= 149; $value++) {
    $literalPool[] = (string) $value;
    $literalPool[] = "'" . str_pad((string) $value, 3, '0', STR_PAD_LEFT) . "'";
}
foreach (['09', '10.00', '19.999', '20.00', '29.999', '30.00'] as $value) {
    $literalPool[] = "'{$value}'";
}
$literalPool = array_slice(array_values(array_unique($literalPool)), 0, 156);

$operators = ['=', '==', '<', '<=', '>', '>=', '!=', '<>'];

// Source truth: SQLite upstream test/types2.test types2-2.* through
// types2-4.*. The earlier large corpus already broadened the no-affinity
// `o` column. This file expands the INTEGER, NUMERIC, and TEXT affinity
// columns over additional numeric and text literals, comparing the bounded
// native SELECT executor against sqlite3 for each rowid result set.
foreach (['i', 'n', 't'] as $column) {
    foreach ($operators as $operator) {
        foreach ($literalPool as $literal) {
            $where = "{$column} {$operator} {$literal}";
            $tests["real upstream expression affinity dynamic types2 matrix {$column} {$operator} {$literal}"] = static function (TestRunner $t) use ($portRowids, $oracleRowids, $where): void {
                $t->same($oracleRowids($where), $portRowids($where), $where);
            };
        }
    }
}

return $tests;
