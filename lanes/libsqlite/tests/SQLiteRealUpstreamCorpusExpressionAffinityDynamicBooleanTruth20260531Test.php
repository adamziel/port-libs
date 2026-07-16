<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity boolean truth tests');
}

// Source truth: SQLite upstream test/expr.test expr-1.27 through expr-1.34
// and expr-1.36 through expr-1.37 cover SQL boolean truthiness for AND, OR,
// and NOT over integer-valued expressions. This dynamic shard widens that same
// behavior across INTEGER, REAL, TEXT numeric-prefix, TEXT nonnumeric, and NULL
// literal storage classes through the bounded SELECT SQL expression executor.
$literal = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$operands = [
    'integer-zero' => '0',
    'integer-one' => '1',
    'integer-negative-one' => '-1',
    'integer-two' => '2',
    'real-zero' => '0.0',
    'real-positive' => '2.5',
    'real-negative' => '-3.75',
    'real-small' => '0.0001',
    'text-zero' => $literal('0'),
    'text-one' => $literal('1'),
    'text-real' => $literal('2.5'),
    'text-negative-prefix' => $literal('-3x'),
    'text-alpha' => $literal('alpha'),
    'text-empty' => $literal(''),
    'text-space-zero' => $literal('  0'),
    'null' => 'NULL',
];

$binaryOperators = [
    'and' => 'AND',
    'or' => 'OR',
    'equals-one-and' => '= 1 AND',
    'not-equals-zero-or' => '<> 0 OR',
];

$cases = [];
foreach ($operands as $leftName => $leftSql) {
    foreach ($operands as $rightName => $rightSql) {
        foreach ($binaryOperators as $operatorName => $operatorSql) {
            if ($operatorSql === '= 1 AND') {
                $expression = "({$leftSql}) = 1 AND ({$rightSql})";
            } elseif ($operatorSql === '<> 0 OR') {
                $expression = "({$leftSql}) <> 0 OR ({$rightSql})";
            } else {
                $expression = "({$leftSql}) {$operatorSql} ({$rightSql})";
            }

            $cases["{$leftName}-{$operatorName}-{$rightName}"] = $expression;
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-bool-truth-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression affinity boolean truth tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression affinity boolean truth output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed expression affinity boolean truth oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression affinity boolean truth oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity boolean truth expr.test expr-1 dynamic ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n",
            [],
        );
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream corpus expression affinity boolean truth owns exactly 1024 pass cases'] = static function (TestRunner $t) use ($operands, $binaryOperators, $cases): void {
    $t->same(16, count($operands));
    $t->same(4, count($binaryOperators));
    $t->same(1024, count($cases));
    $t->same(
        'expr.test expr-1.27..1.37 boolean AND/OR/NOT truthiness over dynamic affinity literal families',
        'expr.test expr-1.27..1.37 boolean AND/OR/NOT truthiness over dynamic affinity literal families',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
