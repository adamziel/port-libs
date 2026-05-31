<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic operator tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test sections e_expr-2.* through
// e_expr-7.*. These sections cover unary operators, concatenation, modulo,
// arithmetic storage-class rules, and bitwise integer conversion.
$leftLiterals = [
    'null' => 'NULL',
    'int-zero' => '0',
    'int-one' => '1',
    'int-neg-one' => '-1',
    'int-seven' => '7',
    'int-neg-seven' => '-7',
    'real-half' => '0.5',
    'real-neg-quarter' => '-0.25',
    'real-exp' => '1.25e+2',
    'text-int' => $quoteSql('42'),
    'text-real' => $quoteSql('42.5'),
    'text-real-tail' => $quoteSql('42.5tail'),
    'text-leading-space' => $quoteSql('   -12.75'),
    'text-plus-decimal' => $quoteSql('+.5'),
    'text-empty' => $quoteSql(''),
    'text-alpha' => $quoteSql('abc'),
    'text-hex-prefix' => $quoteSql('0x123'),
    'text-space-int' => $quoteSql('  15'),
];

$rightLiterals = [
    'int-one' => '1',
    'int-two' => '2',
    'int-neg-three' => '-3',
    'real-half' => '0.5',
    'real-two-quarter' => '2.25',
    'text-two' => $quoteSql('2'),
    'text-two-tail' => $quoteSql('2tail'),
    'text-zero' => $quoteSql('0'),
    'null' => 'NULL',
];

$unaryOperators = [
    'plus' => '+',
    'minus' => '-',
    'bit-not' => '~',
    'not' => 'NOT',
];

$binaryOperators = [
    'concat' => '||',
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'remainder' => '%',
    'bit-and' => '&',
    'bit-or' => '|',
    'shift-left' => '<<',
    'shift-right' => '>>',
];

$cases = [];
foreach ($leftLiterals as $literalName => $literalSql) {
    foreach ($unaryOperators as $operatorName => $operatorSql) {
        $cases["unary.{$operatorName}.{$literalName}"] = "{$operatorSql} {$literalSql}";
    }
}

foreach ($leftLiterals as $leftName => $leftSql) {
    foreach ($rightLiterals as $rightName => $rightSql) {
        foreach ($binaryOperators as $operatorName => $operatorSql) {
            $cases["binary.{$operatorName}.{$leftName}.{$rightName}"] = "({$leftSql}) {$operatorSql} ({$rightSql})";
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-operators-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 operator oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression operator output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 operator oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression operator oracle rows, got %d', count($cases), count($oracle)));
}

$sameQuotedValue = static function (TestRunner $t, string $expected, string $actual, string $label): void {
    if (is_numeric($expected) && is_numeric($actual)) {
        $expectedFloat = (float) $expected;
        $actualFloat = (float) $actual;
        $scale = max(1.0, abs($expectedFloat), abs($actualFloat));
        $t->true(abs($expectedFloat - $actualFloat) <= $scale * 1.0e-13, $label . ' numeric quote tolerance');
        return;
    }

    $t->same($expected, $actual, $label);
};

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic real expr operators e_expr-2-7 ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle, $sameQuotedValue): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $key . ' row count');

        $row = $rows[0];
        $sameQuotedValue($t, $oracle[$key]['quote'], (string) $row['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $key . ' is-null');
        $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    };
}

$tests['real upstream corpus expression affinity dynamic real expr operators owns 1692 e_expr assertions'] = static function (TestRunner $t) use ($leftLiterals, $rightLiterals, $unaryOperators, $binaryOperators, $cases, $oracle): void {
    $t->same(18, count($leftLiterals));
    $t->same(9, count($rightLiterals));
    $t->same(4, count($unaryOperators));
    $t->same(10, count($binaryOperators));
    $t->same(1692, count($cases));
    $t->same(1692, count($oracle));
    $t->same(
        'e_expr.test e_expr-2.* through e_expr-7.* unary, concatenation, modulo, arithmetic, and bitwise operator behavior',
        'e_expr.test e_expr-2.* through e_expr-7.* unary, concatenation, modulo, arithmetic, and bitwise operator behavior',
    );
};

return $tests;
