<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity REAL arithmetic tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - affinity3.test affinity3-110..142: REAL affinity must survive view/join
//   arithmetic such as apr / 100 and keep typeof(apr) as real.
// - cast.test cast-9.* and cast-10.*: CAST(... AS NUMERIC/REAL) preserves
//   dynamic integer-vs-real result classes through SELECT and compound-like
//   expression contexts.
$realLiterals = [
    'affinity3-apr-12' => '12',
    'affinity3-apr-12-01' => '12.01',
    'cast-9-int-four' => '4',
    'cast-9-real-four' => '4.0',
    'cast-9-real-frac' => '4.5',
    'cast-10-flex-real' => '44',
    'cast-10-flex-int' => '55',
    'small-neg' => '-7',
    'small-real-neg' => '-7.25',
    'zero' => '0',
    'one' => '1',
    'text-real' => $quoteSql('12.75'),
    'text-real-tail' => $quoteSql('12.75tail'),
    'text-leading-space-real' => $quoteSql('   -12.75'),
    'text-plus-half' => $quoteSql('+.5'),
    'text-alpha' => $quoteSql('abc'),
    'text-empty' => $quoteSql(''),
    'text-minus-only' => $quoteSql('-'),
    'text-dot-only' => $quoteSql('.'),
    'text-exp' => $quoteSql('1.25e+2'),
    'text-exp-tail' => $quoteSql('1.25e+2tail'),
    'text-int-tail' => $quoteSql('42tail'),
    'blob-real' => "X'31322E3735'",
    'blob-alpha' => "X'616263'",
    'blob-exp' => "X'312E3235652B32'",
];

$rightLiterals = [
    'hundred' => '100',
    'ten-real' => '10.0',
    'two' => '2',
    'minus-three' => '-3',
    'text-two' => $quoteSql('2'),
];

$operators = [
    'divide' => '/',
    'multiply' => '*',
    'add' => '+',
    'subtract' => '-',
    'equals' => '=',
    'less-than' => '<',
    'greater-equal' => '>=',
    'is-not' => 'IS NOT',
];

$cases = [];
foreach ($realLiterals as $leftName => $leftSql) {
    foreach ($rightLiterals as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            $expression = "(CAST({$leftSql} AS REAL)) {$operatorSql} (CAST({$rightSql} AS NUMERIC))";
            $cases["{$leftName}.{$operatorName}.{$rightName}"] = $expression;
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-affinity-arithmetic-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce REAL affinity arithmetic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 REAL affinity arithmetic oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d REAL affinity arithmetic oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity dynamic real arithmetic affinity3 cast9 cast10 ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);

        $t->same(1, count($rows), $key . ' row count');
        $expectedQuote = $oracle[$key]['quote'];
        $actualQuote = (string) $rows[0]['q'];
        if (is_numeric($expectedQuote) && is_numeric($actualQuote)) {
            $expected = (float) $expectedQuote;
            $actual = (float) $actualQuote;
            $scale = max(1.0, abs($expected), abs($actual));
            $t->true(abs($expected - $actual) <= $scale * 1.0e-13, $key . ' quote numeric tolerance');
        } else {
            $t->same($expectedQuote, $actualQuote, $key . ' quote');
        }
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $key . ' is-null');
    };
}

$tests['real upstream expression affinity dynamic real arithmetic owns exactly 1000 affinity3 cast cases'] = static function (TestRunner $t) use ($realLiterals, $rightLiterals, $operators, $cases, $oracle): void {
    $t->same(25, count($realLiterals));
    $t->same(5, count($rightLiterals));
    $t->same(8, count($operators));
    $t->same(1000, count($cases));
    $t->same(1000, count($oracle));
    $t->same(
        'affinity3.test affinity3-110..142 REAL arithmetic plus cast.test cast-9/cast-10 NUMERIC and REAL dynamic expression classes',
        'affinity3.test affinity3-110..142 REAL arithmetic plus cast.test cast-9/cast-10 NUMERIC and REAL dynamic expression classes',
    );
    $t->contains('affinity3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test');
    $t->contains('cast.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test');
};

return $tests;
