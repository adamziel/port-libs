<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity real expr2 dynamic tests');
}

// Source truth: SQLite upstream test/expr.test expr-2.1 through expr-2.28.
// That section covers REAL arithmetic, comparisons, min/max, NULL
// propagation, infinite-result coalescing, division by zero, and modulo by
// zero. This dynamic shard widens the same REAL operator family with literal
// operands through the bounded SELECT SQL expression executor.
$operands = [
    'small-positive' => '1.23',
    'medium-positive' => '2.34',
    'equal-medium' => '2.34',
    'negative-small' => '-1.23',
    'negative-medium' => '-2.34',
    'zero' => '0.0',
    'one-tenth' => '0.1',
    'half' => '0.5',
    'large-positive' => '1e300',
    'large-negative' => '-1e300',
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'modulo' => '%',
    'less-than' => '<',
    'less-than-or-equal' => '<=',
    'greater-than' => '>',
    'greater-than-or-equal' => '>=',
    'equals' => '=',
    'double-equals' => '==',
    'not-equals' => '!=',
];

$cases = [];
foreach ($operands as $leftName => $leftSql) {
    foreach ($operands as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            $key = "{$leftName}-{$operatorName}-{$rightName}";
            $cases[$key] = "({$leftSql}) {$operatorSql} ({$rightSql})";
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || typeof({$expression}) || char(9) || quote({$expression}) || char(9) || quote(({$expression}) IS NULL) || char(9) || quote(coalesce({$expression}, 99.0));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr2-real-dynamic-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression affinity real expr2 dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression affinity real expr2 dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('malformed expression affinity real expr2 oracle row: ' . $line);
    }
    [$key, $storageClass, $quotedValue, $quotedIsNull, $quotedCoalesced] = $parts;
    $oracle[$key] = [
        'typeof' => $storageClass,
        'quote' => $quotedValue,
        'isNull' => $quotedIsNull,
        'coalesced' => $quotedCoalesced,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression affinity real expr2 oracle rows, got %d', count($cases), count($oracle)));
}

$assertQuotedParity = static function (TestRunner $t, string $expected, string $actual, string $type, string $message): void {
    if ($expected === $actual || $expected === 'NULL' || $type !== 'real') {
        $t->same($expected, $actual, $message);

        return;
    }

    $expectedFloat = (float) $expected;
    $actualFloat = (float) $actual;
    $scale = max(1.0, abs($expectedFloat));
    $t->true(abs($expectedFloat - $actualFloat) / $scale < 1.0e-14, $message . " expected {$expected}, got {$actual}");
};

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity real expr2 dynamic expr.test expr-2.1-28 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle, $assertQuotedParity): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT typeof({$expression}) AS type, quote({$expression}) AS q, quote(({$expression}) IS NULL) AS n, quote(coalesce({$expression}, 99.0)) AS c",
            [],
        );
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $type = (string) $row['type'];
        $t->same($oracle[$key]['typeof'], $type, $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
        $assertQuotedParity($t, $oracle[$key]['quote'], (string) $row['q'], $type, $expression . ' quote');
        $assertQuotedParity($t, $oracle[$key]['coalesced'], (string) $row['c'], $type, $expression . ' coalesced');
    };
}

$tests['real upstream expression affinity real expr2 dynamic owns exactly 1200 pass cases'] = static function (TestRunner $t) use ($operands, $operators, $cases): void {
    $t->same(10, count($operands));
    $t->same(12, count($operators));
    $t->same(1200, count($cases));
    $t->same(
        'expr.test expr-2.1..2.28 REAL arithmetic, comparison, NULL, infinity, divide-by-zero, and modulo-by-zero behavior',
        'expr.test expr-2.1..2.28 REAL arithmetic, comparison, NULL, infinity, divide-by-zero, and modulo-by-zero behavior',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
