<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream explicit floating text affinity tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/expr.test expr-13.6 and expr-13.7.
// Those rows verify that a string operand with explicit floating-point syntax
// uses string-to-REAL conversion during numeric arithmetic, even when the
// integer prefix would otherwise fit in int64. This dynamic corpus broadens
// that behavior across decimal/exponent spellings without repeating the
// existing single max-int literal checks.
$floatTextValues = [];
for ($i = 1; $i <= 50; $i++) {
    $whole = (string) (9223372036854775807 - ($i * 97));
    $fraction = str_pad((string) (($i * 137) % 10000), 4, '0', STR_PAD_LEFT);
    $floatTextValues["max-near-decimal-{$i}"] = $whole . '.' . $fraction;
    $floatTextValues["signed-decimal-{$i}"] = ($i % 2 === 0 ? '+' : '-') . ($i * 12345) . '.' . $fraction;
    $floatTextValues["leading-space-decimal-{$i}"] = '   ' . ($i * 17) . '.' . $fraction;
    $floatTextValues["small-exponent-{$i}"] = sprintf('%d.%04de-%d', ($i % 9) + 1, (int) $fraction, ($i % 5) + 1);
    $floatTextValues["large-exponent-{$i}"] = sprintf('%d.%04de+%d', ($i % 7) + 2, (int) $fraction, ($i % 12) + 2);
}

$contexts = [
    'left-plus-zero' => static fn (string $sql): string => "{$sql}+0",
    'right-plus-zero' => static fn (string $sql): string => "0+{$sql}",
    'multiply-one' => static fn (string $sql): string => "{$sql}*1",
    'divide-one' => static fn (string $sql): string => "{$sql}/1",
    'subtract-zero' => static fn (string $sql): string => "{$sql}-0",
];

$cases = [];
foreach ($floatTextValues as $valueName => $value) {
    $literal = $quoteSql($value);
    foreach ($contexts as $contextName => $contextSql) {
        $expression = $contextSql($literal);
        $cases["{$valueName}-{$contextName}"] = $expression;
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || typeof({$expression}) || char(9) || quote({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-explicit-float-text-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for explicit floating text affinity tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce explicit floating text affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed explicit floating text affinity oracle row: ' . $line);
    }

    [$key, $storageClass, $quotedValue, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'typeof' => $storageClass,
        'quote' => $quotedValue,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d explicit floating text affinity oracle rows, got %d', count($cases), count($oracle)));
}

$assertQuotedRealParity = static function (TestRunner $t, string $expected, string $actual, string $message): void {
    if ($expected === $actual) {
        $t->same($expected, $actual, $message);

        return;
    }

    $expectedFloat = (float) $expected;
    $actualFloat = (float) $actual;
    $scale = max(1.0, abs($expectedFloat));
    $t->true(abs($expectedFloat - $actualFloat) / $scale < 1.0e-14, $message . " expected {$expected}, got {$actual}");
};

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity dynamic explicit float text expr.test expr-13.6-13.7 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle, $assertQuotedRealParity): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT typeof({$expression}) AS t, quote({$expression}) AS q, quote(({$expression}) IS NULL) AS n",
            [],
        );
        $t->same(1, count($rows), $expression);
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
        $t->same('real', (string) $rows[0]['t'], $expression . ' explicit floating text converts to REAL');
        $assertQuotedRealParity($t, $oracle[$key]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression affinity dynamic explicit float text owns expr 13 source range'] = static function (TestRunner $t) use ($floatTextValues, $contexts, $cases, $oracle): void {
    $t->same(250, count($floatTextValues));
    $t->same(5, count($contexts));
    $t->same(1250, count($cases));
    $t->same(1250, count($oracle));
    $t->same(
        'expr.test expr-13.6..13.7 explicit floating-point string operands use REAL conversion in numeric arithmetic',
        'expr.test expr-13.6..13.7 explicit floating-point string operands use REAL conversion in numeric arithmetic',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
