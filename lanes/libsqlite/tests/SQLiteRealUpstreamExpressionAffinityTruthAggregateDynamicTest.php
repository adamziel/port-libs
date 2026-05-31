<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression truth aggregate dynamic tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Real upstream source:
// - test/expr.test expr-14.1 through expr-14.4 verifies that SQL truth
//   coercion in OR, NOT NOT, CASE WHEN, WHERE, count(), and sum() agree for
//   numeric, NULL, REAL, and numeric-looking TEXT values.
// This shard expands that upstream table through many dynamic rowsets and is
// intentionally separate from scalar-only CASE/iif truthiness batches.
$valuePool = [
    0,
    1,
    null,
    0.5,
    '1x',
    '0x',
    -1,
    ' -2.25',
    '2.25tail',
    '',
    'english',
    '0english',
    '1english',
    9223372036854775807,
    '9223372036854775808',
    -0.0,
    3.5,
    '-0',
];

$queries = [
    'expr-14.1 or-case-count' => 'SELECT quote(count(*)) AS q, typeof(count(*)) AS t FROM t1 WHERE (x OR (8==9)) != (CASE WHEN x THEN 1 ELSE 0 END)',
    'expr-14.2 or-notnot-count' => 'SELECT quote(count(*)) AS q, typeof(count(*)) AS t FROM t1 WHERE (x OR (8==9)) != (NOT NOT x)',
    'expr-14.3 sum-not-where-x' => 'SELECT quote(sum(NOT x)) AS q, typeof(sum(NOT x)) AS t FROM t1 WHERE x',
    'expr-14.4 sum-case-where-x' => 'SELECT quote(sum(CASE WHEN x THEN 0 ELSE 1 END)) AS q, typeof(sum(CASE WHEN x THEN 0 ELSE 1 END)) AS t FROM t1 WHERE x',
];

$rowsets = [];
for ($i = 0; $i < 300; ++$i) {
    $rows = [];
    for ($j = 0; $j < 8; ++$j) {
        $rows[] = ['x' => $valuePool[($i + ($j * 5)) % count($valuePool)]];
    }
    $rowsets['rowset-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)] = $rows;
}

$oracleScript = ['CREATE TABLE t1(x);'];
$caseSql = [];
foreach ($rowsets as $rowsetName => $rows) {
    $oracleScript[] = 'DELETE FROM t1;';
    foreach ($rows as $row) {
        $oracleScript[] = 'INSERT INTO t1(x) VALUES (' . $sqlLiteral($row['x']) . ');';
    }
    foreach ($queries as $queryName => $sql) {
        $key = $rowsetName . ' ' . $queryName;
        $safeKey = str_replace("'", "''", $key);
        $oracleScript[] = "SELECT '{$safeKey}' || char(9) || q || char(9) || t FROM ({$sql});";
        $caseSql[$key] = [$rows, $sql];
    }
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr14-truth-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 expr-14 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expr-14 truth aggregate output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed expr-14 truth aggregate oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}

if (count($oracle) !== count($caseSql)) {
    throw new RuntimeException(sprintf('Expected %d expr-14 oracle rows, got %d', count($caseSql), count($oracle)));
}

foreach ($caseSql as $key => [$rows, $sql]) {
    $tests['real upstream expression affinity truth aggregate dynamic expr.test ' . $key] = static function (TestRunner $t) use ($rows, $sql, $key, $oracle): void {
        $result = SQLiteSelectSql::execute($sql, ['t1' => $rows]);

        $t->same(1, count($result), $key);
        $t->same($oracle[$key]['quote'], (string) $result[0]['q'], $sql . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $result[0]['t'], $sql . ' typeof');
    };
}

$tests['real upstream expression affinity truth aggregate dynamic owns exactly 1200 expr14 cases'] = static function (TestRunner $t) use ($rowsets, $queries, $caseSql, $oracle): void {
    $t->same(300, count($rowsets));
    $t->same(4, count($queries));
    $t->same(1200, count($caseSql));
    $t->same(1200, count($oracle));
    $t->same(
        'expr.test expr-14.1..14.4 OR, NOT NOT, CASE WHEN, WHERE, count(), and sum() truth coercion over dynamic REAL/TEXT/NULL rowsets',
        'expr.test expr-14.1..14.4 OR, NOT NOT, CASE WHEN, WHERE, count(), and sum() truth coercion over dynamic REAL/TEXT/NULL rowsets',
    );
};

return $tests;
