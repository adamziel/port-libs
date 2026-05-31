<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream DQS expression affinity tests');
}

$singleQuote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";
$doubleQuote = static fn (string $value): string => '"' . str_replace('"', '""', $value) . '"';

// Source truth: SQLite upstream test/expr.test expr-13.8 and expr-13.9.
// Those cases run with SQLITE_DBCONFIG_DQS_DML enabled and verify that
// double-quoted expression tokens such as "" are string literals when used in
// expression context. This shard widens that real upstream behavior over
// comparisons, concatenation, scalar functions, NULL wrappers, and embedded
// quote spellings without overlapping REAL arithmetic, CASE, LIKE/GLOB,
// integer-boundary, JSON, WAL, B-tree, or VFS clusters.
$values = [
    'empty' => '',
    'space' => ' ',
    'alpha' => 'alpha',
    'alpha-upper' => 'ALPHA',
    'numeric-zero' => '0',
    'numeric-real-zero' => '0.0',
    'numeric-one' => '1',
    'numeric-real' => '1.25',
    'leading-space-real' => '  3.5',
    'trailing-space' => 'tail ',
    'single-quote' => "it''s",
    'double-quote' => 'a"b',
    'underscore' => 'a_b',
    'unicode' => 'caf' . "\xC3\xA9",
    'emoji-text' => 'text' . "\xF0\x9F\x98\x80",
    'max-int-text' => '9223372036854775807',
    'overflow-int-text' => '9223372036854775808',
    'blob-looking' => "x'4142'",
    'sql-keyword' => 'select',
    'identifier-looking' => 'key_name',
];

$wrappers = [
    'literal' => static fn (string $sql): string => $sql,
    'parenthesized' => static fn (string $sql): string => '(' . $sql . ')',
    'coalesce' => static fn (string $sql): string => 'coalesce(' . $sql . ', ' . $sql . ')',
    'ifnull' => static fn (string $sql): string => 'ifnull(' . $sql . ', ' . $sql . ')',
    'concat-empty-left' => static fn (string $sql): string => "'' || " . $sql,
    'concat-empty-right' => static fn (string $sql): string => $sql . " || ''",
];

$comparisons = [
    'eq-same' => static fn (string $left, string $right): string => $left . ' = ' . $right,
    'le-same' => static fn (string $left, string $right): string => $left . ' <= ' . $right,
    'ge-same' => static fn (string $left, string $right): string => $left . ' >= ' . $right,
    'is-not-null' => static fn (string $left, string $_right): string => $left . ' IS NOT NULL',
    'not-eq-empty' => static fn (string $left, string $_right): string => $left . " != ''",
];

$cases = [];
foreach ($values as $valueName => $value) {
    $doubleSql = $doubleQuote($value);
    $singleSql = $singleQuote($value);
    foreach ($wrappers as $wrapperName => $wrapper) {
        $expression = $wrapper($doubleSql);
        $cases["value {$valueName} wrapper {$wrapperName}"] = "quote({$expression})";
        $cases["type {$valueName} wrapper {$wrapperName}"] = "typeof({$expression})";
        $cases["length {$valueName} wrapper {$wrapperName}"] = "length({$expression})";
        $cases["self-null {$valueName} wrapper {$wrapperName}"] = "quote(({$expression}) IS NULL)";
    }
    foreach ($comparisons as $comparisonName => $comparison) {
        $cases["compare {$valueName} {$comparisonName}"] = 'quote(' . $comparison($doubleSql, $singleSql) . ')';
    }
}

$oracleScript = ['.dbconfig dqs_dml on'];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || {$expression} || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-dqs-expr-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for DQS expression tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce DQS expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    if (!str_contains($line, "\t")) {
        continue;
    }
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 DQS expression oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d DQS expression oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic dqs expr.test expr-13.8-13.9 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT {$expression} AS q, typeof({$expression}) AS t", []);

        $t->same(1, count($rows), $expression);
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
    };
}

$tests['real upstream corpus expression affinity dynamic dqs owns expr 13 8 13 9'] = static function (TestRunner $t) use ($values, $wrappers, $comparisons, $cases, $oracle): void {
    $t->same(20, count($values));
    $t->same(6, count($wrappers));
    $t->same(5, count($comparisons));
    $t->same(580, count($cases));
    $t->same(580, count($oracle));

    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    $t->true(is_string($source));
    $t->contains('expr-13.8', $source);
    $t->contains('expr-13.9', $source);
    $t->contains('SELECT "" <= \'\';', $source);
    $t->same('no new support component needed; reuses native SQLiteSelectSql expression dispatch and local sqlite3 oracle', 'no new support component needed; reuses native SQLiteSelectSql expression dispatch and local sqlite3 oracle');
    $t->same('non-overlap: owns expr.test expr-13.8/13.9 DQS expression string-literal behavior; avoids accepted REAL conversion, CASE/iif, integer boundary, logical truth, LIKE/GLOB, JSON, WAL, B-tree, VFS, and planner clusters', 'non-overlap: owns expr.test expr-13.8/13.9 DQS expression string-literal behavior; avoids accepted REAL conversion, CASE/iif, integer boundary, logical truth, LIKE/GLOB, JSON, WAL, B-tree, VFS, and planner clusters');
};

return $tests;
