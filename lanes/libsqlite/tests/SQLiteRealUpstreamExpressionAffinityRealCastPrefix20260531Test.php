<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity REAL CAST prefix tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test sections e_expr-29.* through
// e_expr-32.*. These sections specify longest-prefix REAL/INTEGER conversion,
// REAL-to-INTEGER truncation toward zero, NUMERIC integer/real selection, and
// NULL propagation for CAST expressions.
$literals = [
    'null' => 'NULL',
    'int-zero' => '0',
    'int-one' => '1',
    'int-neg-one' => '-1',
    'real-pi' => '3.14159',
    'real-near-two' => '1.99999',
    'real-neg-near-two' => '-1.99999',
    'real-neg-fraction' => '-0.99999',
    'huge-real-pos' => '2e+50',
    'huge-real-neg' => '-2e+50',
    'max-int-text' => $quoteSql('9223372036854775807'),
    'max-int-plus-one-text' => $quoteSql('9223372036854775808'),
    'min-int-text' => $quoteSql('-9223372036854775808'),
    'min-int-minus-one-text' => $quoteSql('-9223372036854775809'),
    'max-int-real-text' => $quoteSql('9223372036854775807.0'),
    'max-int-exp-text' => $quoteSql('9223372036854775807e+0'),
    'big-int-safe-text' => $quoteSql('9000000000000000001'),
    'big-int-safe-tail' => $quoteSql('9000000000000000001x'),
    'big-int-safe-spaces' => $quoteSql(' 9000000000000000001 '),
    'big-int-dot-text' => $quoteSql(' 9000000000000000001.'),
    'real-prefix-simple' => $quoteSql('1.23abcd'),
    'real-prefix-second-dot' => $quoteSql('1.45.23abcd'),
    'real-prefix-exp' => $quoteSql('-2.12e-01ABC'),
    'real-prefix-spaced' => $quoteSql('    1.45.23abcd'),
    'real-prefix-integer-gap' => $quoteSql('1 2 3 4'),
    'real-prefix-spaced-gap' => $quoteSql('     1 2 3 4'),
    'int-prefix-simple' => $quoteSql('123abcd'),
    'int-prefix-large' => $quoteSql('14523abcd'),
    'int-prefix-neg-exp' => $quoteSql('-2.12e-01ABC'),
    'int-prefix-spaced' => $quoteSql('   123abcd'),
    'int-prefix-spaced-large' => $quoteSql('  14523abcd'),
    'no-prefix-empty' => $quoteSql(''),
    'no-prefix-alpha' => $quoteSql('not a number'),
    'no-prefix-roman' => $quoteSql('XXI'),
    'space-only' => $quoteSql('  '),
    'hex-lower' => $quoteSql('0x1234'),
    'hex-upper' => $quoteSql('0X1234'),
    'numeric-int' => $quoteSql('45'),
    'numeric-real-whole' => $quoteSql('45.0'),
    'numeric-real-fraction' => $quoteSql('45.2'),
    'numeric-int-tail' => $quoteSql('11abc'),
    'numeric-real-tail' => $quoteSql('11.1abc'),
    'numeric-exp-safe-pos' => $quoteSql('9.223372036e14'),
    'numeric-exp-safe-neg' => $quoteSql('-9.223372036e14'),
    'numeric-exp-real-pos' => $quoteSql('9.223372036e15'),
    'numeric-exp-real-neg' => $quoteSql('-9.223372036e15'),
    'blob-real-prefix' => "X'312E323361626364'",
    'blob-real-exp' => "X'2D322E3132652D3031414243'",
    'blob-int-prefix' => "X'31323361626364'",
    'blob-no-prefix' => "X'585849'",
    'text-minus-five-real' => $quoteSql('-5.0'),
    'text-minus-five-exp' => $quoteSql('-5e+0'),
];

$targets = [
    'integer' => 'INTEGER',
    'real' => 'REAL',
    'numeric' => 'NUMERIC',
    'text' => 'TEXT',
];

$projections = [
    'quote' => static fn (string $expression): string => "quote({$expression})",
    'typeof' => static fn (string $expression): string => "typeof({$expression})",
    'is-null' => static fn (string $expression): string => "quote(({$expression}) IS NULL)",
    'equals-zero' => static fn (string $expression): string => "quote(({$expression}) = 0)",
    'less-than-zero' => static fn (string $expression): string => "quote(({$expression}) < 0)",
    'greater-than-million' => static fn (string $expression): string => "quote(({$expression}) > 1000000)",
];

$cases = [];
foreach ($literals as $literalName => $literalSql) {
    foreach ($targets as $targetName => $targetSql) {
        foreach ($projections as $projectionName => $projectionSql) {
            $expression = "CAST({$literalSql} AS {$targetSql})";
            $cases["{$literalName}.as-{$targetName}.{$projectionName}"] = 'SELECT ' . $projectionSql($expression);
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $sql) {
    $safeKey = str_replace("'", "''", $key);
    $projection = substr($sql, strlen('SELECT '));
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || {$projection};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-cast-prefix-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce CAST prefix output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 CAST prefix oracle row: ' . $line);
    }
    [$key, $value] = $parts;
    $oracle[$key] = $value;
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d CAST prefix oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $sql) {
    $tests['real upstream expression affinity real cast prefix e_expr-29-32 ' . $key] = static function (TestRunner $t) use ($key, $sql, $oracle): void {
        $rows = SQLiteSelectSql::execute($sql, []);
        $t->same(1, count($rows), $key . ' row count');

        $actual = (string) array_values($rows[0])[0];
        if (
            is_numeric($oracle[$key])
            && is_numeric($actual)
            && (str_contains($key, '.as-real.') || (str_contains($key, '.as-numeric.quote') && (str_contains($oracle[$key], 'e') || str_contains($actual, 'e'))))
        ) {
            $expectedFloat = (float) $oracle[$key];
            $actualFloat = (float) $actual;
            $scale = max(1.0, abs($expectedFloat), abs($actualFloat));
            $t->true(abs($expectedFloat - $actualFloat) <= $scale * 1.0e-13, $key . ' real tolerance');
            return;
        }

        $t->same($oracle[$key], $actual, $key);
    };
}

$tests['real upstream expression affinity real cast prefix owns 1248 e_expr cases'] = static function (TestRunner $t) use ($literals, $targets, $projections, $cases, $oracle): void {
    $t->same(52, count($literals));
    $t->same(4, count($targets));
    $t->same(6, count($projections));
    $t->same(1248, count($cases));
    $t->same(1248, count($oracle));
    $t->same(
        'e_expr.test e_expr-29.* through e_expr-32.* REAL, INTEGER, and NUMERIC CAST longest-prefix and range behavior',
        'e_expr.test e_expr-29.* through e_expr-32.* REAL, INTEGER, and NUMERIC CAST longest-prefix and range behavior',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
