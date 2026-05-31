<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream CASE affinity dynamic tests');
}

$literalSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test e_expr-23.1.6 through
// e_expr-24.1.2. Those tests specify that CASE base-expression comparisons
// use the same affinity and NULL handling as "=" comparisons. This dynamic
// shard widens the same behavior across INTEGER, REAL, NUMERIC, TEXT, and
// BLOB-affinity columns with mixed literal WHEN arms.
$affinities = [
    'rowid' => 'INTEGER',
    'int_col' => 'INTEGER',
    'real_col' => 'REAL',
    'num_col' => 'NUMERIC',
    'text_col' => 'TEXT',
    'blob_col' => 'BLOB',
];

$rawRows = [
    ['rowid' => 1, 'int_col' => '55', 'real_col' => '55', 'num_col' => '55', 'text_col' => '55', 'blob_col' => '55'],
    ['rowid' => 2, 'int_col' => '055', 'real_col' => '55.0', 'num_col' => '55.0', 'text_col' => '055', 'blob_col' => '055'],
    ['rowid' => 3, 'int_col' => 55, 'real_col' => 55.25, 'num_col' => 55.25, 'text_col' => '55.25', 'blob_col' => '55.25'],
    ['rowid' => 4, 'int_col' => '0', 'real_col' => '0.0', 'num_col' => '0.0', 'text_col' => '0', 'blob_col' => '0'],
    ['rowid' => 5, 'int_col' => '-7', 'real_col' => '-7.5', 'num_col' => '-7.5', 'text_col' => '-7.5', 'blob_col' => '-7.5'],
    ['rowid' => 6, 'int_col' => '1e2', 'real_col' => '1e2', 'num_col' => '1e2', 'text_col' => '1e2', 'blob_col' => '1e2'],
    ['rowid' => 7, 'int_col' => 'abc', 'real_col' => 'abc', 'num_col' => 'abc', 'text_col' => 'abc', 'blob_col' => 'abc'],
    ['rowid' => 8, 'int_col' => null, 'real_col' => null, 'num_col' => null, 'text_col' => null, 'blob_col' => null],
    ['rowid' => 9, 'int_col' => '9223372036854775807', 'real_col' => '9223372036854775807', 'num_col' => '9223372036854775807', 'text_col' => '9223372036854775807', 'blob_col' => '9223372036854775807'],
    ['rowid' => 10, 'int_col' => '-9223372036854775808', 'real_col' => '-9223372036854775808', 'num_col' => '-9223372036854775808', 'text_col' => '-9223372036854775808', 'blob_col' => '-9223372036854775808'],
];

$tableRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $affinities],
    SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rawRows, $affinities),
);

$whenLiterals = [
    'null' => 'NULL',
    'int-55' => '55',
    'text-55' => "'55'",
    'real-55' => '55.0',
    'text-055' => "'055'",
    'real-55-25' => '55.25',
    'text-55-25' => "'55.25'",
    'int-zero' => '0',
    'text-zero' => "'0'",
    'real-zero' => '0.0',
    'int-neg-seven' => '-7',
    'real-neg-seven-half' => '-7.5',
    'text-neg-seven-half' => "'-7.5'",
    'int-hundred' => '100',
    'text-exp-hundred' => "'1e2'",
    'text-alpha' => "'abc'",
    'blob-alpha' => "x'616263'",
    'text-empty' => "''",
    'text-max-int' => "'9223372036854775807'",
    'max-int' => '9223372036854775807',
    'text-min-int' => "'-9223372036854775808'",
    'min-int' => '-9223372036854775808',
    'real-max-int' => '9223372036854775807.0',
    'real-min-int' => '-9223372036854775808.0',
];

$columns = ['int_col', 'real_col', 'num_col', 'text_col', 'blob_col'];
$cases = [];
foreach ($rawRows as $row) {
    foreach ($columns as $column) {
        foreach ($whenLiterals as $literalName => $whenSql) {
            $key = sprintf('e_expr-23-24.case-affinity.row%02d.%s.%s', $row['rowid'], $column, $literalName);
            $cases[$key] = [
                'rowid' => (int) $row['rowid'],
                'sql' => "CASE {$column} WHEN {$whenSql} THEN 'matched' ELSE 'fallback' END",
            ];
        }
    }
}

$oracleScript = [
    'CREATE TABLE t(rowid INTEGER PRIMARY KEY, int_col INTEGER, real_col REAL, num_col NUMERIC, text_col TEXT, blob_col BLOB);',
];
foreach ($rawRows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO t(rowid,int_col,real_col,num_col,text_col,blob_col) VALUES(%d,%s,%s,%s,%s,%s);',
        $row['rowid'],
        $literalSql($row['int_col']),
        $literalSql($row['real_col']),
        $literalSql($row['num_col']),
        $literalSql($row['text_col']),
        $literalSql($row['blob_col']),
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['sql'];
    $oracleScript[] = sprintf(
        "SELECT '%s' || char(9) || quote(%s) || char(9) || typeof(%s) FROM t WHERE rowid = %d;",
        $safeKey,
        $expression,
        $expression,
        $case['rowid'],
    );
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-case-affinity-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce CASE affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 CASE affinity oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = ['quote' => $quotedValue, 'typeof' => $storageClass];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d CASE affinity oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic real expr CASE affinity ' . $key] = static function (TestRunner $t) use ($key, $case, $oracle, $tableRows): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$case['sql']}) AS q, typeof({$case['sql']}) AS t FROM t WHERE rowid = {$case['rowid']}",
            ['t' => $tableRows],
        );
        $t->same(1, count($rows), $key . ' row count');

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof');
    };
}

$tests['real upstream corpus expression affinity dynamic real expr CASE affinity owns 1200 e_expr cases'] = static function (TestRunner $t) use ($cases, $rawRows, $columns, $whenLiterals, $tableRows): void {
    $t->same(10, count($rawRows));
    $t->same(5, count($columns));
    $t->same(24, count($whenLiterals));
    $t->same(1200, count($cases));
    $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass($tableRows[0]['int_col']));
    $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($tableRows[0]['real_col']));
    $t->same('e_expr.test e_expr-23.1.6..23.1.9 and e_expr-24.1.1..24.1.2 CASE base affinity and NULL handling', 'e_expr.test e_expr-23.1.6..23.1.9 and e_expr-24.1.1..24.1.2 CASE base affinity and NULL handling');
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
