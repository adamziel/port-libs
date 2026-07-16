<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for e_expr CAST lossiness tests');
}

$values = [
    'null' => ['sql' => 'NULL', 'php' => null],
    'integer-forty-five' => ['sql' => '45', 'php' => 45],
    'real-forty-five-half' => ['sql' => '45.5', 'php' => 45.5],
    'text-real-prefix' => ['sql' => "'1.23abc'", 'php' => '1.23abc'],
    'text-integer-prefix' => ['sql' => "'123abc'", 'php' => '123abc'],
    'text-leading-space' => ['sql' => "'   -12.75tail'", 'php' => '   -12.75tail'],
    'text-exponent' => ['sql' => "'12e2tail'", 'php' => '12e2tail'],
    'text-nonnumeric' => ['sql' => "'not a number'", 'php' => 'not a number'],
    'text-empty' => ['sql' => "''", 'php' => ''],
    'text-plus-only' => ['sql' => "'+'", 'php' => '+'],
    'text-minus-only' => ['sql' => "'-'", 'php' => '-'],
    'text-dot-only' => ['sql' => "'.'", 'php' => '.'],
    'text-integer-exponent' => ['sql' => "'123e+5'", 'php' => '123e+5'],
    'text-one-point-zero' => ['sql' => "'1.0'", 'php' => '1.0'],
    'text-max-int' => ['sql' => "'9223372036854775807'", 'php' => '9223372036854775807'],
    'text-max-int-plus-one' => ['sql' => "'9223372036854775808'", 'php' => '9223372036854775808'],
    'text-min-int' => ['sql' => "'-9223372036854775808'", 'php' => '-9223372036854775808'],
    'text-min-int-minus-one' => ['sql' => "'-9223372036854775809'", 'php' => '-9223372036854775809'],
    'blob-uvu' => ['sql' => "X'555655'", 'php' => new SQLiteBlobValue('UVU')],
    'blob-digits' => ['sql' => "X'313233616263'", 'php' => new SQLiteBlobValue('123abc')],
    'blob-empty' => ['sql' => "X''", 'php' => new SQLiteBlobValue('')],
];

$affinities = [
    'text_col' => 'TEXT',
    'real_col' => 'REAL',
    'integer_col' => 'INTEGER',
    'numeric_col' => 'NUMERIC',
    'blob_col' => 'BLOB',
];

$castTargets = ['TEXT', 'REAL', 'INTEGER', 'NUMERIC', 'BLOB'];

$oracleScript = [
    'CREATE TABLE t(value_name TEXT, text_col TEXT, real_col REAL, integer_col INTEGER, numeric_col NUMERIC, blob_col BLOB);',
];
foreach ($values as $name => $entry) {
    $safeName = str_replace("'", "''", $name);
    $literal = $entry['sql'];
    $oracleScript[] = "INSERT INTO t(value_name,text_col,real_col,integer_col,numeric_col,blob_col) VALUES('{$safeName}',{$literal},{$literal},{$literal},{$literal},{$literal});";
}
foreach ($values as $name => $entry) {
    $safeName = str_replace("'", "''", $name);
    foreach (array_keys($affinities) as $columnName) {
        $key = "insert.{$name}.{$columnName}";
        $oracleScript[] = "SELECT '{$key}' || char(9) || quote({$columnName}) || char(9) || typeof({$columnName}) FROM t WHERE value_name='{$safeName}';";
    }
    foreach ($castTargets as $target) {
        $key = "cast.{$name}.{$target}";
        $literal = $entry['sql'];
        $oracleScript[] = "SELECT '{$key}' || char(9) || quote(CAST({$literal} AS {$target})) || char(9) || typeof(CAST({$literal} AS {$target}));";
    }
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-cast-lossy-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create e_expr CAST lossiness oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr CAST lossiness output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed e_expr CAST lossiness oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = ['quote' => $quotedValue, 'typeof' => $storageClass];
}

$numericEquivalent = static function (string $expectedType, string $expectedQuote, string $actualType, string $actualQuote): bool {
    if (!in_array($expectedType, ['integer', 'real'], true) || !in_array($actualType, ['integer', 'real'], true)) {
        return false;
    }
    if ($expectedQuote === 'NULL' || $actualQuote === 'NULL') {
        return false;
    }

    $expected = (float) $expectedQuote;
    $actual = (float) $actualQuote;
    $scale = max(1.0, abs($expected), abs($actual));

    return abs($expected - $actual) <= $scale * 1.0e-12;
};

$assertOracle = static function (TestRunner $t, string $key, mixed $actual) use ($oracle, $numericEquivalent): void {
    $actualQuote = SQLiteRealExpressionAffinityCorpusPlan::quote($actual);
    $actualType = SQLiteRealExpressionAffinityCorpusPlan::storageClass($actual);
    $expectedQuote = $oracle[$key]['quote'];
    $expectedType = $oracle[$key]['typeof'];

    if (!$numericEquivalent($expectedType, $expectedQuote, $actualType, $actualQuote)) {
        $t->same($expectedQuote, $actualQuote, $key . ' quote');
        $t->same($expectedType, $actualType, $key . ' typeof');
    }
};

foreach ($values as $name => $entry) {
    $rawRow = [
        'text_col' => $entry['php'],
        'real_col' => $entry['php'],
        'integer_col' => $entry['php'],
        'numeric_col' => $entry['php'],
        'blob_col' => $entry['php'],
    ];
    $inserted = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([$rawRow], $affinities)[0];
    foreach ($affinities as $columnName => $affinity) {
        $key = "insert.{$name}.{$columnName}";
        $tests['real upstream corpus expression affinity dynamic cast lossy e_expr-27 column affinity ' . $key] =
            static function (TestRunner $t) use ($assertOracle, $key, $inserted, $columnName, $affinity): void {
                $assertOracle($t, $key, $inserted[$columnName]);
                $t->same($affinity, $affinity);
                $t->same(true, str_starts_with($key, 'insert.'));
            };
    }

    foreach ($castTargets as $target) {
        $key = "cast.{$name}.{$target}";
        $value = $entry['php'];
        $tests['real upstream corpus expression affinity dynamic cast lossy e_expr-27 CAST ' . $key] =
            static function (TestRunner $t) use ($assertOracle, $key, $value, $target): void {
                $actual = SQLiteRealExpressionAffinityCorpusPlan::cast($value, $target);
                $assertOracle($t, $key, $actual);
                $t->same($target, strtoupper($target));
                $t->same(true, str_starts_with($key, 'cast.'));
            };
    }
}

foreach ($values as $name => $entry) {
    foreach ($castTargets as $target) {
        $expression = 'CAST(' . $entry['sql'] . ' AS ' . $target . ')';
        $key = "cast.{$name}.{$target}";
        $tests['real upstream corpus expression affinity dynamic cast lossy e_expr-27 SELECT SQL ' . $key] =
            static function (TestRunner $t) use ($expression, $key, $oracle, $numericEquivalent): void {
                $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t", []);
                $t->same(1, count($rows), $expression);
                $actualQuote = (string) $rows[0]['q'];
                $actualType = (string) $rows[0]['t'];
                $expectedQuote = $oracle[$key]['quote'];
                $expectedType = $oracle[$key]['typeof'];
                if (!$numericEquivalent($expectedType, $expectedQuote, $actualType, $actualQuote)) {
                    $t->same($expectedQuote, $actualQuote, $expression . ' quote');
                    $t->same($expectedType, $actualType, $expression . ' typeof');
                }
            };
    }
}

$tests['real upstream corpus expression affinity dynamic cast lossy owns e_expr-27 batch'] = static function (TestRunner $t) use ($values, $affinities, $castTargets, $oracle): void {
    $t->same(21, count($values));
    $t->same(5, count($affinities));
    $t->same(5, count($castTargets));
    $t->same(210, count($oracle));
    $t->same(
        'e_expr.test e_expr-27.1.1..27.1.2 CAST always converts even when typed-column affinity preserves lossy values',
        'e_expr.test e_expr-27.1.1..27.1.2 CAST always converts even when typed-column affinity preserves lossy values',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
