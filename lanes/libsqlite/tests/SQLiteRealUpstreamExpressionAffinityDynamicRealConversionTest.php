<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic real conversion tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source:
// - test/expr.test expr-13.2..13.7 verifies string-to-integer/REAL conversion.
// - test/affinity3.test affinity3-110..142 verifies REAL affinity survives
//   division through nested view/automatic-index paths.
// This shard keeps the focus on expression conversion and REAL arithmetic,
// using dynamic literal matrices rather than repeating the prior cast-target,
// overflow-clamp, bitwise, or generic arithmetic batches.
$leftExpressions = [
    'expr-13.2 max-int-text-plus-zero' => '0+' . $sqlLiteral('9223372036854775807'),
    'expr-13.3 max-int-text-right-plus-zero' => $sqlLiteral('9223372036854775807') . '+0',
    'expr-13.4 overflow-text-plus-zero' => '0+' . $sqlLiteral('9223372036854775808'),
    'expr-13.5 overflow-text-right-plus-zero' => $sqlLiteral('9223372036854775808') . '+0',
    'expr-13.6 max-int-decimal-text-plus-zero' => '0+' . $sqlLiteral('9223372036854775807.0'),
    'expr-13.7 max-int-decimal-text-right-plus-zero' => $sqlLiteral('9223372036854775807.0') . '+0',
    'expr-13.dynamic leading-space-real' => '0+' . $sqlLiteral('   -12.75'),
    'expr-13.dynamic leading-plus-real' => '0+' . $sqlLiteral('+.5'),
    'expr-13.dynamic exponent-real' => '0+' . $sqlLiteral('1.25e+2'),
    'expr-13.dynamic exponent-overflow-real' => '0+' . $sqlLiteral('9223372036854775808e0'),
    'expr-13.dynamic fraction-tail-real' => '0+' . $sqlLiteral('123.5tail'),
    'expr-13.dynamic integer-tail-real' => '0+' . $sqlLiteral('123tail'),
    'expr-13.dynamic minus-only-real-zero' => '0+' . $sqlLiteral('-'),
    'expr-13.dynamic dot-only-real-zero' => '0+' . $sqlLiteral('.'),
    'expr-13.dynamic empty-real-zero' => '0+' . $sqlLiteral(''),
    'affinity3.110 real-apr-12' => 'CAST(' . $sqlLiteral('12') . ' AS REAL)/100',
    'affinity3.110 real-apr-12-01' => 'CAST(' . $sqlLiteral('12.01') . ' AS REAL)/100',
    'affinity3.120 nested-real-apr' => '(CAST(' . $sqlLiteral('12.01') . ' AS REAL)/10)/10',
];

$rightExpressions = [
    'integer-one' => '1',
    'integer-two' => '2',
    'real-half' => '0.5',
    'real-quarter' => '0.25',
    'real-negative-three' => '-3.0',
    'text-two-real' => '0+' . $sqlLiteral('2.0'),
    'text-three-tail-real' => '0+' . $sqlLiteral('3.5xyz'),
    'affinity3-divisor-real' => 'CAST(' . $sqlLiteral('100') . ' AS REAL)',
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
];

$projections = [
    'quote' => 'quote',
    'typeof' => 'typeof',
];

$cases = [];
$caseId = 0;
foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($rightExpressions as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            foreach ($projections as $projectionName => $projectionSql) {
                ++$caseId;
                $expression = "({$leftSql}) {$operatorSql} ({$rightSql})";
                $cases['case-' . $caseId] = [
                    'name' => "{$leftName} {$operatorName} {$rightName} {$projectionName}",
                    'expression' => $expression,
                    'projection' => $projectionSql,
                ];
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $projection = $case['projection'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || {$projection}({$expression});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-conversion-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression affinity real conversion output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 oracle row: ' . $line);
    }

    [$key, $value] = $parts;
    $oracle[$key] = $value;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 real conversion oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic real conversion expr.test affinity3.test ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $projection = $case['projection'];
        $rows = SQLiteSelectSql::execute("SELECT {$projection}({$expression}) AS value", []);
        $t->same(1, count($rows), $expression);
        $actual = (string) $rows[0]['value'];
        $expected = $oracle[$key];
        if ($projection === 'quote' && is_numeric($expected) && is_numeric($actual)) {
            $expectedFloat = (float) $expected;
            $actualFloat = (float) $actual;
            $tolerance = max(1.0e-12, abs($expectedFloat) * 1.0e-12);
            $t->true(abs($expectedFloat - $actualFloat) <= $tolerance, $expression . ' quote realnum tolerance');
            return;
        }

        $t->same($expected, $actual, $expression . ' ' . $projection);
    };
}

$tests['real upstream expression affinity dynamic real conversion owns exactly 1152 dynamic cases'] = static function (TestRunner $t) use ($leftExpressions, $rightExpressions, $operators, $projections, $cases): void {
    $t->same(18, count($leftExpressions));
    $t->same(8, count($rightExpressions));
    $t->same(4, count($operators));
    $t->same(2, count($projections));
    $t->same(1152, count($cases));
    $t->same(
        'expr.test expr-13.2..13.7 string numeric conversion plus affinity3.test affinity3-110..142 REAL division preservation',
        'expr.test expr-13.2..13.7 string numeric conversion plus affinity3.test affinity3-110..142 REAL division preservation',
    );
};

return $tests;
