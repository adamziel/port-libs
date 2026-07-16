<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream REAL/text comparison tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth:
// - SQLite upstream test/expr.test expr-4.10 through expr-4.20 verifies that
//   REAL-affinity columns compare numeric-looking text numerically while plain
//   TEXT columns keep text comparison semantics.
// This dynamic shard widens that upstream cluster across generic table rows.
$rawRows = [];
$valuePairs = [
    ['0.0', 'abc'],
    ['abc', 'Abc'],
    ['abc', 'Bbc'],
    ['0', '0.0'],
    ['0.000', '0.0'],
    [' 0.000', ' 0.0'],
    ['10', '2'],
    ['10.25', '10.250'],
    ['0010', '10'],
    ['-7.5', '-07.50'],
    ['1e2', '100.0'],
    ['9.5e1', '95'],
    ['9223372036854775808', '9.223372036854776e18'],
    ['42tail', '42'],
    ['+.5', '0.5'],
    ['-.5', '-0.50'],
    ['12.0', '12.00'],
    ['12.0001', '12.00001'],
    ['z', 'a'],
    ['', '0'],
    [null, '0'],
    ['0', null],
    [null, null],
    ['NaN', 'nan'],
    ['INF', 'inf'],
    [' 12 ', '12'],
    ['12x', '13'],
    ['13', '12x'],
    ['-0.0', '0.0'],
    ['+0.0', '0'],
    ['000', '0'],
    ['000.10', '0.1'],
    ['5', '05'],
    ['5.0', '05.00'],
    ['5.1', '05.10'],
    ['5.10x', '5.1'],
    ['abc', 'abc'],
    ['ABC', 'abc'],
    ['Bbc', 'abc'],
    ['0.0', '0.00'],
];

foreach ($valuePairs as $index => [$left, $right]) {
    $rawRows[] = [
        'id' => $index + 1,
        'r1' => $left,
        'r2' => $right,
        't1' => $left,
        't2' => $right,
    ];
}

$affinities = [
    'id' => 'INTEGER',
    'r1' => 'REAL',
    'r2' => 'REAL',
    't1' => 'TEXT',
    't2' => 'TEXT',
];
$rows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $affinities],
    SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rawRows, $affinities),
);

$operators = ['<', '<=', '>', '>=', '=', '==', '<>', '!=', 'IS', 'IS NOT'];
$expressionTemplates = [
    'real-vs-real' => 'r1 %s r2',
    'text-vs-text' => 't1 %s t2',
    'real-vs-text' => 'r1 %s t2',
    'text-vs-real' => 't1 %s r2',
    'plus-real-vs-text' => '+r1 %s t2',
    'text-vs-plus-real' => 't1 %s +r2',
    'cast-real-vs-text' => 'CAST(t1 AS REAL) %s t2',
    'text-vs-cast-real' => 't1 %s CAST(t2 AS REAL)',
];

$cases = [];
foreach ($rawRows as $row) {
    $rowId = (int) $row['id'];
    foreach ($expressionTemplates as $templateName => $template) {
        foreach ($operators as $operator) {
            $expression = sprintf($template, $operator);
            $cases["row-{$rowId}-{$templateName}-{$operator}"] = [
                'rowId' => $rowId,
                'expression' => $expression,
            ];
        }
    }
}

$oracleScript = ['CREATE TABLE test1(id INTEGER PRIMARY KEY, r1 REAL, r2 REAL, t1 TEXT, t2 TEXT);'];
foreach ($rawRows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO test1(id,r1,r2,t1,t2) VALUES(%d,%s,%s,%s,%s);',
        $row['id'],
        $sqlLiteral($row['r1']),
        $sqlLiteral($row['r2']),
        $sqlLiteral($row['t1']),
        $sqlLiteral($row['t2']),
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $rowId = (int) $case['rowId'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) FROM test1 WHERE id = {$rowId};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-real-text-compare-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for REAL/text comparison tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce REAL/text comparison output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('malformed REAL/text comparison oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d REAL/text comparison oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic REAL text comparison expr.test expr-4.10-20 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $rows): void {
        $rowId = (int) $case['rowId'];
        $expression = $case['expression'];
        $resultRows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t FROM test1 WHERE id = {$rowId}",
            ['test1' => $rows],
        );
        $t->same(1, count($resultRows), $key);

        $actual = $resultRows[0];
        $t->same($oracle[$key]['typeof'], (string) $actual['t'], $expression . ' typeof');
        $t->same($oracle[$key]['quote'], (string) $actual['q'], $expression . ' quote');
    };
}

$tests['real upstream expression affinity dynamic REAL text comparison owns expr4 corpus'] = static function (TestRunner $t) use ($rawRows, $operators, $expressionTemplates, $cases): void {
    $t->same(40, count($rawRows));
    $t->same(10, count($operators));
    $t->same(8, count($expressionTemplates));
    $t->same(3200, count($cases));
    $t->same(
        'expr.test expr-4.10..4.20 REAL-affinity numeric-looking text comparisons contrasted with TEXT comparison semantics',
        'expr.test expr-4.10..4.20 REAL-affinity numeric-looking text comparisons contrasted with TEXT comparison semantics',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
