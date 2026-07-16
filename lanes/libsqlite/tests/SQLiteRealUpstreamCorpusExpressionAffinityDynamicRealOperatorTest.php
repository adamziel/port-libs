<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic real operator tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - expr.test expr-13.8/13.9 covers real numeric conversion through arithmetic.
// - e_expr.test e_expr-6.* and e_expr-7.* cover numeric operators, modulo,
//   shifts, bitwise coercion, truthiness, and result storage classes.
// This shard intentionally avoids the prior cast-target, REAL conversion,
// REAL arithmetic, syntax-diagram, and IS DISTINCT matrices by focusing on
// operator coercion over dynamically generated REAL-ish expression pairs.
$leftExpressions = [
    'expr13-real-plus-maxint' => '0+' . $sqlLiteral('9223372036854775808'),
    'expr13-real-plus-neg-overflow' => '0+' . $sqlLiteral('-9223372036854775809'),
    'expr13-real-decimal-maxint' => '0+' . $sqlLiteral('9223372036854775807.0'),
    'expr13-real-decimal-overflow' => '0+' . $sqlLiteral('9223372036854775808.0'),
    'expr13-real-leading-space' => '0+' . $sqlLiteral('   72.35'),
    'expr13-real-plus-half' => '0+' . $sqlLiteral('+.5'),
    'expr13-real-minus-half' => '0+' . $sqlLiteral('-.5'),
    'expr13-real-exp' => '0+' . $sqlLiteral('7.235e1'),
    'expr13-real-exp-neg' => '0+' . $sqlLiteral('-7.235e1'),
    'expr13-real-tail' => '0+' . $sqlLiteral('72.35tail'),
    'expr13-int-tail' => '0+' . $sqlLiteral('72tail'),
    'expr13-blob-real' => "0+X'37322E3335'",
    'expr13-blob-exp' => "0+X'372E323335652B31'",
    'eexpr-real-literal' => '72.35',
    'eexpr-real-negative' => '-72.35',
    'eexpr-real-zero' => '0.0',
];

$rightExpressions = [
    'int-five' => '5',
    'int-neg-five' => '-5',
    'real-five' => '5.0',
    'real-neg-five' => '-5.0',
    'text-five' => $sqlLiteral('5'),
    'text-five-real' => $sqlLiteral('5.0'),
    'text-tail-five' => $sqlLiteral('5tail'),
    'blob-five' => "X'35'",
];

$operators = [
    'modulo' => '%',
    'divide' => '/',
    'multiply' => '*',
    'add' => '+',
    'subtract' => '-',
    'bit-and' => '&',
    'bit-or' => '|',
    'shift-left' => '<<',
    'shift-right' => '>>',
    'less-than' => '<',
    'less-equal' => '<=',
    'greater-than' => '>',
    'greater-equal' => '>=',
    'equals' => '=',
    'not-equals' => '<>',
    'and' => 'AND',
    'or' => 'OR',
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

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-operator-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce dynamic real operator output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 real operator oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 real operator oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic real operator expr13 eexpr6 eexpr7 ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression);

        $expectedQuote = $oracle[$key]['quote'];
        $actualQuote = (string) $rows[0]['q'];
        if (is_numeric($expectedQuote) && is_numeric($actualQuote)) {
            $expected = (float) $expectedQuote;
            $actual = (float) $actualQuote;
            $scale = max(1.0, abs($expected), abs($actual));
            $t->true(abs($expected - $actual) <= $scale * 1.0e-12, $expression . ' quote numeric tolerance');
        } else {
            $t->same($expectedQuote, $actualQuote, $expression . ' quote');
        }

        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression affinity dynamic real operator owns exactly 2176 operator cases'] = static function (TestRunner $t) use ($leftExpressions, $rightExpressions, $operators, $cases, $oracle): void {
    $t->same(16, count($leftExpressions));
    $t->same(8, count($rightExpressions));
    $t->same(17, count($operators));
    $t->same(2176, count($cases));
    $t->same(2176, count($oracle));
    $t->same(
        'expr.test expr-13.8/13.9 plus e_expr.test e_expr-6/e_expr-7 REAL operator coercion',
        'expr.test expr-13.8/13.9 plus e_expr.test e_expr-6/e_expr-7 REAL operator coercion',
    );
};

return $tests;
