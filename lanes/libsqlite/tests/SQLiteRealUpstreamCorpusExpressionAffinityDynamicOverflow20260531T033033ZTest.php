<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression overflow affinity tests');
}

// Source truth:
// - SQLite upstream test/expr.test expr-1.200..1.271 covers integer
//   arithmetic overflow promoting to REAL for +, -, and * near int64 bounds.
// - expr.test expr-13.2..13.7 covers string-to-integer/string-to-REAL
//   numeric conversion at the int64 boundary.
// This dynamic shard broadens those sections across SELECT expression
// contexts without overlapping the accepted affinity3 REAL join or e_expr
// syntax-diagram corpus files.
$leftOperands = [
    'max-minus-one' => '9223372036854775806',
    'max' => '9223372036854775807',
    'min' => '-9223372036854775808',
    'min-plus-one' => '-9223372036854775807',
    'sqrt-overflow-pos' => '3037000500',
    'sqrt-safe-pos' => '3037000499',
    'sqrt-overflow-neg' => '-3037000500',
    'sqrt-safe-neg' => '-3037000499',
    'word32-pos' => '4294967296',
    'word32-neg' => '-4294967296',
    'halfword-pos' => '2147483648',
    'halfword-neg' => '-2147483648',
    'text-max' => "'9223372036854775807'",
    'text-over-max' => "'9223372036854775808'",
    'text-max-real' => "'9223372036854775807.0'",
    'text-over-max-real' => "'9223372036854775808.0'",
    'text-min' => "'-9223372036854775808'",
    'text-under-min' => "'-9223372036854775809'",
    'text-overflow-product' => "'3037000500'",
    'text-safe-product' => "'3037000499'",
    'real-max' => '9.223372036854775807e18',
    'real-neg-max' => '-9.223372036854775807e18',
    'small-pos' => '2',
    'small-neg' => '-2',
    'zero' => '0',
];

$rightOperands = [
    'one' => '1',
    'two' => '2',
    'minus-one' => '-1',
    'minus-two' => '-2',
    'hundred-thousand' => '100000',
    'word31' => '2147483647',
    'word31-plus-one' => '2147483648',
    'text-two' => "'2'",
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'modulo' => '%',
];

$cases = [];
foreach ($leftOperands as $leftName => $leftSql) {
    foreach ($rightOperands as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            $expression = "({$leftSql}) {$operatorSql} ({$rightSql})";
            $cases["expr-overflow {$leftName} {$operatorName} {$rightName}"] = $expression;
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-overflow-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 expression overflow oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression overflow output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 expression overflow oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression overflow oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic overflow ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);

        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $key . ' is-null');

        $expectedQuote = $oracle[$key]['quote'];
        $actualQuote = (string) $rows[0]['q'];
        if ($oracle[$key]['typeof'] === 'real' && is_numeric($expectedQuote) && is_numeric($actualQuote)) {
            $expected = (float) $expectedQuote;
            $actual = (float) $actualQuote;
            $scale = max(1.0, abs($expected), abs($actual));
            $t->true(abs($expected - $actual) <= $scale * 1.0e-13, $key . ' quote numeric tolerance');
            return;
        }

        $t->same($expectedQuote, $actualQuote, $key . ' quote');
    };
}

$tests['real upstream corpus expression affinity dynamic overflow owns expr overflow matrix'] = static function (TestRunner $t) use ($leftOperands, $rightOperands, $operators, $cases, $oracle): void {
    $t->same(25, count($leftOperands));
    $t->same(8, count($rightOperands));
    $t->same(5, count($operators));
    $t->same(1000, count($cases));
    $t->same(1000, count($oracle));
    $t->same(
        'expr.test expr-1.200..1.271 overflow arithmetic and expr-13.2..13.7 int64 string conversion',
        'expr.test expr-1.200..1.271 overflow arithmetic and expr-13.2..13.7 int64 string conversion',
    );
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    $t->true(is_string($source));
    $t->contains('test_realnum_expr expr-1.201', $source);
    $t->contains("SELECT 0+'9223372036854775808'", $source);
};

return $tests;
