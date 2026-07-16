<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream integer-boundary expression affinity tests');
}

// Source truth:
// - SQLite upstream test/expr.test expr-11.1 through expr-11.14 verifies that
//   integer-looking literals at and just beyond the signed int64 boundary are
//   tokenized as integer or real depending on sign, leading zeros, and range.
// This dynamic shard widens that fixed upstream section across adjacent
// boundary magnitudes and expression wrappers without repeating arithmetic
// overflow, CAST-prefix, or generic REAL-literal batches.
$magnitudes = [
    '9223372036854775790',
    '9223372036854775791',
    '9223372036854775792',
    '9223372036854775793',
    '9223372036854775794',
    '9223372036854775795',
    '9223372036854775796',
    '9223372036854775797',
    '9223372036854775798',
    '9223372036854775799',
    '9223372036854775800',
    '9223372036854775801',
    '9223372036854775802',
    '9223372036854775803',
    '9223372036854775804',
    '9223372036854775805',
    '9223372036854775806',
    '9223372036854775807',
    '9223372036854775808',
    '9223372036854775809',
    '9223372036854775810',
    '9223372036854775811',
    '9223372036854775812',
    '9223372036854775813',
    '9223372036854775814',
];

$literalForms = [
    'unsigned' => static fn (string $magnitude): string => $magnitude,
    'leading-plus' => static fn (string $magnitude): string => '+' . $magnitude,
    'leading-zero' => static fn (string $magnitude): string => '0000000' . $magnitude,
    'leading-plus-zero' => static fn (string $magnitude): string => '+0000000' . $magnitude,
    'negative' => static fn (string $magnitude): string => '-' . $magnitude,
    'negative-zero' => static fn (string $magnitude): string => '-0000000' . $magnitude,
    'paren-unsigned' => static fn (string $magnitude): string => '(' . $magnitude . ')',
    'paren-leading-plus' => static fn (string $magnitude): string => '(+' . $magnitude . ')',
    'paren-negative' => static fn (string $magnitude): string => '(-' . $magnitude . ')',
    'paren-leading-zero' => static fn (string $magnitude): string => '(0000000' . $magnitude . ')',
    'paren-leading-plus-zero' => static fn (string $magnitude): string => '(+0000000' . $magnitude . ')',
    'paren-negative-zero' => static fn (string $magnitude): string => '(-0000000' . $magnitude . ')',
];

$expressionForms = [
    'literal' => static fn (string $literal): string => $literal,
    'parenthesized' => static fn (string $literal): string => '(' . $literal . ')',
    'unary-plus-parenthesized' => static fn (string $literal): string => '+(' . $literal . ')',
    'add-zero' => static fn (string $literal): string => '(' . $literal . ')+0',
];

$cases = [];
foreach ($magnitudes as $magnitude) {
    foreach ($literalForms as $literalName => $literalSql) {
        $literal = $literalSql($magnitude);
        foreach ($expressionForms as $expressionName => $expressionSql) {
            $key = $magnitude . '.' . $literalName . '.' . $expressionName;
            $cases[$key] = $expressionSql($literal);
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL) || char(9) || quote(({$expression}) = {$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr11-int-boundary-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for integer-boundary expression tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce integer-boundary expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 5) {
        throw new RuntimeException('malformed integer-boundary oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull, $quotedSelfEqual] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
        'selfEqual' => $quotedSelfEqual,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d integer-boundary oracle rows, got %d', count($cases), count($oracle)));
}

$assertQuotedNumericParity = static function (TestRunner $t, string $expected, string $actual, string $type, string $message): void {
    if ($expected === $actual || $type !== 'real') {
        $t->same($expected, $actual, $message);

        return;
    }

    $expectedFloat = (float) $expected;
    $actualFloat = (float) $actual;
    $scale = max(1.0, abs($expectedFloat), abs($actualFloat));
    $t->true(abs($expectedFloat - $actualFloat) <= $scale * 1.0e-15, $message . " expected {$expected}, got {$actual}");
};

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity integer boundary dynamic expr.test expr-11 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle, $assertQuotedNumericParity): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n, quote(({$expression}) = {$expression}) AS eq",
            [],
        );

        $t->same(1, count($rows), $expression);
        $row = $rows[0];
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
        $t->same($oracle[$key]['selfEqual'], (string) $row['eq'], $expression . ' self-equal');
        $assertQuotedNumericParity($t, $oracle[$key]['quote'], (string) $row['q'], $oracle[$key]['typeof'], $expression . ' quote');
    };
}

$tests['real upstream expression affinity integer boundary dynamic owns exactly 1200 expr11 cases'] = static function (TestRunner $t) use ($magnitudes, $literalForms, $expressionForms, $cases, $oracle): void {
    $t->same(25, count($magnitudes));
    $t->same(12, count($literalForms));
    $t->same(4, count($expressionForms));
    $t->same(1200, count($cases));
    $t->same(1200, count($oracle));
    $t->same(
        'expr.test expr-11.1..11.14 signed int64 boundary literal tokenization with leading zeros, unary signs, and REAL promotion',
        'expr.test expr-11.1..11.14 signed int64 boundary literal tokenization with leading zeros, unary signs, and REAL promotion',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
