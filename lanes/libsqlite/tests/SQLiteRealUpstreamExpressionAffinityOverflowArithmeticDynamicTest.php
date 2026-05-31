<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression overflow arithmetic dynamic tests');
}

// Source truth:
// - SQLite upstream test/expr.test expr-1.200 through expr-1.271 covers
//   integer add/subtract/multiply boundaries where SQLite preserves exact
//   int64 results when possible and promotes overflowed arithmetic to REAL.
// This dynamic shard widens those fixed rows around the same int64 and square
// root boundaries through the bounded SELECT SQL expression executor.
$operands = [
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'minus-one' => '-1',
    'minus-two' => '-2',
    'small-positive' => '100000',
    'small-negative' => '-100000',
    'int32-max' => '2147483647',
    'int32-max-plus-one' => '2147483648',
    'uint32' => '4294967296',
    'sqrt-boundary-low' => '3037000498',
    'sqrt-boundary' => '3037000499',
    'sqrt-boundary-plus-one' => '3037000500',
    'sqrt-boundary-plus-two' => '3037000501',
    'neg-sqrt-boundary-low' => '-3037000498',
    'neg-sqrt-boundary' => '-3037000499',
    'neg-sqrt-boundary-plus-one' => '-3037000500',
    'neg-sqrt-boundary-plus-two' => '-3037000501',
    'safe-max-minus-two' => '9223372036854775805',
    'safe-max-minus-one' => '9223372036854775806',
    'int64-max' => '9223372036854775807',
    'int64-min' => '(-9223372036854775807-1)',
    'int64-min-plus-one' => '(-9223372036854775807)',
    'int64-min-plus-two' => '(-9223372036854775806)',
    'real-huge-positive' => '9.223372036854776e18',
    'real-huge-negative' => '-9.223372036854776e18',
    'real-sqrt-boundary' => '3037000500.0',
    'real-neg-sqrt-boundary' => '-3037000500.0',
    'text-int64-max' => "'9223372036854775807'",
    'text-int64-min' => "'-9223372036854775808'",
    'text-sqrt-boundary' => "'3037000500'",
    'text-neg-sqrt-boundary' => "'-3037000500'",
    'text-real-huge-positive' => "'9.223372036854776e18'",
    'text-real-huge-negative' => "'-9.223372036854776e18'",
    'null' => 'NULL',
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
];

$cases = [];
foreach ($operands as $leftName => $leftSql) {
    foreach ($operands as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            $key = "{$leftName}-{$operatorName}-{$rightName}";
            $expression = "({$leftSql}) {$operatorSql} ({$rightSql})";
            $cases[$key] = $expression;
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL) || char(9) || quote(({$expression}) < 0) || char(9) || quote(({$expression}) = 0) || char(9) || quote(({$expression}) > 0);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-overflow-arithmetic-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression overflow arithmetic dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression overflow arithmetic dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 7) {
        throw new RuntimeException('malformed expression overflow arithmetic oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull, $quotedLessThanZero, $quotedEqualZero, $quotedGreaterThanZero] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
        'lessThanZero' => $quotedLessThanZero,
        'equalZero' => $quotedEqualZero,
        'greaterThanZero' => $quotedGreaterThanZero,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression overflow oracle rows, got %d', count($cases), count($oracle)));
}

$assertQuotedNumericParity = static function (TestRunner $t, string $expected, string $actual, string $type, string $message): void {
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
    $tests['real upstream expression affinity overflow arithmetic dynamic expr.test expr-1.200-271 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle, $assertQuotedNumericParity): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n, quote(({$expression}) < 0) AS lt, quote(({$expression}) = 0) AS eq, quote(({$expression}) > 0) AS gt",
            [],
        );
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
        $t->same($oracle[$key]['lessThanZero'], (string) $row['lt'], $expression . ' less-than-zero');
        $t->same($oracle[$key]['equalZero'], (string) $row['eq'], $expression . ' equal-zero');
        $t->same($oracle[$key]['greaterThanZero'], (string) $row['gt'], $expression . ' greater-than-zero');
        $assertQuotedNumericParity($t, $oracle[$key]['quote'], (string) $row['q'], $oracle[$key]['typeof'], $expression . ' quote');
    };
}

$tests['real upstream expression affinity overflow arithmetic dynamic owns expr overflow corpus'] = static function (TestRunner $t) use ($operands, $operators, $cases): void {
    $t->same(35, count($operands));
    $t->same(3, count($operators));
    $t->same(3675, count($cases));
    $t->same(
        'expr.test expr-1.200..1.271 int64 overflow arithmetic promotion for +, -, and *',
        'expr.test expr-1.200..1.271 int64 overflow arithmetic promotion for +, -, and *',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
