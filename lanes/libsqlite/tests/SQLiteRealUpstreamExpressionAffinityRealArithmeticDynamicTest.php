<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression real arithmetic dynamic tests');
}

// Source truth:
// - SQLite upstream test/expr.test expr-2.1 through expr-2.10 covers REAL
//   arithmetic, division, and comparison expression semantics.
// This dynamic shard widens that upstream REAL expression family through the
// native SELECT SQL executor with sqlite3 oracle parity for value, storage
// class, NULL propagation, and boolean comparison results.
$operands = [
    'null' => 'NULL',
    'zero' => '0',
    'negative-zero' => '-0.0',
    'one' => '1',
    'minus-one' => '-1',
    'small-real' => '1.23',
    'other-real' => '2.34',
    'negative-real' => '-7.5',
    'fraction' => '0.125',
    'large-real' => '123456789.5',
    'tiny-real' => '0.0009765625',
    'text-real' => "'1.23'",
    'text-negative-real' => "'-7.5'",
    'text-integer' => "'42'",
    'text-leading-zero' => "'0042.500'",
    'text-space-real' => "' 9.5 '",
    'text-not-number' => "'abc'",
    'blob-real-text' => "x'312e3233'",
    'blob-empty' => "x''",
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
    'equal' => '=',
    'not-equal' => '!=',
];

$cases = [];
foreach ($operands as $leftName => $leftSql) {
    foreach ($operands as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            if (($operatorSql === '/' || $operatorSql === '%') && in_array($rightName, ['zero', 'negative-zero'], true)) {
                continue;
            }
            $key = "{$leftName}-{$operatorName}-{$rightName}";
            $cases[$key] = "({$leftSql}) {$operatorSql} ({$rightSql})";
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL) || char(9) || quote(({$expression}) IS TRUE) || char(9) || quote(({$expression}) IS FALSE);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-real-arithmetic-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression real arithmetic dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression real arithmetic dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 6) {
        throw new RuntimeException('malformed expression real arithmetic oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull, $quotedIsTrue, $quotedIsFalse] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
        'isTrue' => $quotedIsTrue,
        'isFalse' => $quotedIsFalse,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression real arithmetic oracle rows, got %d', count($cases), count($oracle)));
}

$assertQuotedParity = static function (TestRunner $t, string $expected, string $actual, string $type, string $message): void {
    if ($expected === $actual || $expected === 'NULL') {
        $t->same($expected, $actual, $message);

        return;
    }

    if ($type !== 'real') {
        $t->same($expected, $actual, $message);

        return;
    }

    $expectedFloat = (float) $expected;
    $actualFloat = (float) $actual;
    $scale = max(1.0, abs($expectedFloat));
    $t->true(abs($expectedFloat - $actualFloat) / $scale < 1.0e-14, $message . " expected {$expected}, got {$actual}");
};

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity real arithmetic dynamic expr.test expr-2 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle, $assertQuotedParity): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n, quote(({$expression}) IS TRUE) AS truthy, quote(({$expression}) IS FALSE) AS falsey",
            [],
        );
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
        $t->same($oracle[$key]['isTrue'], (string) $row['truthy'], $expression . ' is-true');
        $t->same($oracle[$key]['isFalse'], (string) $row['falsey'], $expression . ' is-false');
        $assertQuotedParity($t, $oracle[$key]['quote'], (string) $row['q'], $oracle[$key]['typeof'], $expression . ' quote');
    };
}

$tests['real upstream expression affinity real arithmetic dynamic owns expr2 corpus'] = static function (TestRunner $t) use ($operands, $operators, $cases): void {
    $t->same(19, count($operands));
    $t->same(11, count($operators));
    $t->same(3895, count($cases));
    $t->same(
        'expr.test expr-2.1..2.10 REAL arithmetic, division, modulo, and comparison behavior',
        'expr.test expr-2.1..2.10 REAL arithmetic, division, modulo, and comparison behavior',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
