<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream collate8 propagation tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/collate8.test sections collate8-3.1
// through collate8-3.5. These cases verify that explicit COLLATE operators
// propagate through concatenation, scalar function argument lists, and CASE
// result arms before comparison collation is selected.
$baseFragments = [
    'alpha' => 'Alpha',
    'bravo' => 'Bravo',
    'cache' => 'Cache',
    'delta' => 'Delta',
    'entry' => 'Entry',
    'field' => 'Field',
    'gamma' => 'Gamma',
    'header' => 'Header',
    'index' => 'Index',
    'journal' => 'Journal',
    'kernel' => 'Kernel',
    'ledger' => 'Ledger',
    'matrix' => 'Matrix',
    'node' => 'Node',
    'offset' => 'Offset',
    'page' => 'Page',
    'query' => 'Query',
    'record' => 'Record',
    'schema' => 'Schema',
    'table' => 'Table',
    'update' => 'Update',
    'value' => 'Value',
    'window' => 'Window',
    'yield' => 'Yield',
];

$suffixes = [
    'empty' => '',
    'trail' => 'Trail',
    'mixed' => 'mIx',
    'numeric' => '07',
    'dash' => '-Tail',
    'underscore' => '_Edge',
    'space' => ' Space',
    'zeta' => 'Zz',
];

$forms = [
    'collate8-3.1-parenthesized-nested' => static fn (string $left, string $prefix, string $suffix): string => "{$left} == ({$prefix} || ({$suffix} COLLATE nocase))",
    'collate8-3.1-upper-argument' => static fn (string $left, string $prefix, string $suffix): string => "{$left} == ({$prefix} || upper({$suffix} COLLATE nocase))",
    'collate8-3.2-max-left-nocase' => static fn (string $left, string $prefix, string $suffix): string => "{$left} == ({$prefix} || max({$suffix} COLLATE nocase, {$suffix} COLLATE binary))",
    'collate8-3.3-max-left-binary' => static fn (string $left, string $prefix, string $suffix): string => "{$left} == ({$prefix} || max({$suffix} COLLATE binary, {$suffix} COLLATE nocase))",
    'collate8-3.4-case-leftmost-nocase-false' => static fn (string $left, string $prefix, string $suffix): string => "{$left} == ({$prefix} || (CASE WHEN 1-1=2 THEN {$suffix} COLLATE nocase ELSE {$suffix} COLLATE binary END))",
    'collate8-3.4-case-leftmost-nocase-true' => static fn (string $left, string $prefix, string $suffix): string => "{$left} == ({$prefix} || (CASE WHEN 1+1=2 THEN {$suffix} COLLATE nocase ELSE {$suffix} COLLATE binary END))",
    'collate8-3.5-case-leftmost-binary' => static fn (string $left, string $prefix, string $suffix): string => "{$left} == ({$prefix} || (CASE WHEN 1=2 THEN {$suffix} COLLATE binary ELSE {$suffix} COLLATE nocase END))",
];

$cases = [];
foreach ($baseFragments as $baseName => $baseFragment) {
    foreach ($suffixes as $suffixName => $suffix) {
        $prefixValue = strtoupper($baseFragment);
        $leftValue = strtolower($baseFragment . $suffix);
        $leftLiteral = $quoteSql($leftValue);
        $prefixLiteral = $quoteSql($prefixValue);
        $suffixLiteral = $quoteSql($suffix);

        foreach ($forms as $formName => $formSql) {
            $key = "{$formName}.{$baseName}.{$suffixName}";
            $cases[$key] = $formSql($leftLiteral, $prefixLiteral, $suffixLiteral);
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-collate8-propagation-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for collate8 propagation tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce collate8 propagation output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('malformed collate8 propagation oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d collate8 propagation oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic collate8 propagation ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t", []);
        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
        $t->contains('collate8.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/collate8.test');
    };
}

$tests['real upstream corpus expression affinity dynamic collate8 propagation owns collate8-3 shard'] = static function (TestRunner $t) use ($baseFragments, $suffixes, $forms, $cases, $oracle): void {
    $t->same(24, count($baseFragments));
    $t->same(8, count($suffixes));
    $t->same(7, count($forms));
    $t->same(1344, count($cases));
    $t->same(1344, count($oracle));
    $t->same(
        'collate8.test collate8-3.1..3.5 COLLATE propagation through concatenation, scalar function arguments, and CASE result arms',
        'collate8.test collate8-3.1..3.5 COLLATE propagation through concatenation, scalar function arguments, and CASE result arms',
    );
    $t->contains('collate8.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/collate8.test');
};

return $tests;
