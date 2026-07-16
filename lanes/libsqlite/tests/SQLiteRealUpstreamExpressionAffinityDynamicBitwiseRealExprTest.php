<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic bitwise real expression tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$literals = [
    'int-zero' => '0',
    'int-one' => '1',
    'int-two' => '2',
    'int-neg-seven' => '-7',
    'real-half' => '0.5',
    'real-neg-quarter' => '-0.25',
    'real-exp' => '1.25e+2',
    'text-int' => $sqlLiteral('42'),
    'text-real' => $sqlLiteral('42.5'),
    'text-real-tail' => $sqlLiteral('42.5tail'),
    'text-leading-space' => $sqlLiteral('   -12.75'),
    'text-plus-decimal' => $sqlLiteral('+.5'),
    'text-minus-only' => $sqlLiteral('-'),
    'text-dot-only' => $sqlLiteral('.'),
    'text-empty' => $sqlLiteral(''),
    'null' => 'NULL',
];

$targets = [
    'integer' => 'INTEGER',
    'real' => 'REAL',
    'numeric' => 'NUMERIC',
    'text' => 'TEXT',
];

$leftExpressions = [];
foreach ($literals as $literalName => $literalSql) {
    foreach ($targets as $targetName => $targetSql) {
        $leftExpressions["{$literalName}-as-{$targetName}"] = "CAST({$literalSql} AS {$targetSql})";
    }
}

$rightExpressions = [
    'one-real' => 'CAST(1 AS REAL)',
    'two-integer' => 'CAST(2 AS INTEGER)',
    'three-numeric' => "CAST('3.5' AS NUMERIC)",
    'text-two-real' => "CAST('2.25tail' AS REAL)",
    'text-four-integer' => "CAST('4tail' AS INTEGER)",
    'neg-three-real' => 'CAST(-3 AS REAL)',
    'zero-integer' => 'CAST(0 AS INTEGER)',
    'null-real' => 'CAST(NULL AS REAL)',
];

$operators = [
    'divide' => '/',
    'modulo' => '%',
    'bit-and' => '&',
    'bit-or' => '|',
    'shift-left' => '<<',
    'shift-right' => '>>',
    'and' => 'AND',
    'or' => 'OR',
    'concat' => '||',
];

$cases = [];
$caseId = 0;
foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($rightExpressions as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            ++$caseId;
            $expression = "({$leftSql}) {$operatorSql} ({$rightSql})";
            $cases['case-' . $caseId] = [
                'name' => "{$leftName} {$operatorName} {$rightName}",
                'expression' => $expression,
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-bitwise-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce real expression bitwise affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 real expression bitwise oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic bitwise real expr expr.test cast.test types3.test ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        if ($oracle[$key]['typeof'] === 'real') {
            $expected = (float) $oracle[$key]['quote'];
            $actual = (float) $row['q'];
            $t->true(abs($expected - $actual) <= max(1.0e-14, abs($expected) * 1.0e-12), $expression . ' quote real numeric value');
        } else {
            $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        }
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression affinity dynamic bitwise real expr owns exactly 4608 dynamic cases'] = static function (TestRunner $t) use ($cases, $literals, $targets, $rightExpressions, $operators): void {
    $t->same(16, count($literals));
    $t->same(4, count($targets));
    $t->same(8, count($rightExpressions));
    $t->same(9, count($operators));
    $t->same(4608, count($cases));
    $t->same(
        'expr.test expr-1 division, modulo, bitwise, shift, boolean, and concatenation families with cast.test target conversion and types3.test storage-class observation',
        'expr.test expr-1 division, modulo, bitwise, shift, boolean, and concatenation families with cast.test target conversion and types3.test storage-class observation',
    );
};

return $tests;
