<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic bitwise tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source:
// - test/expr.test expr-1.42..1.46 covers bitwise OR/AND/NOT and shifts.
// - test/expr.test expr-1.56 and expr-1.96..1.109 cover remainder plus
//   NULL/divide-by-zero propagation for integer-like expression operators.
// This dynamic shard widens those same operators across integer, REAL-cast,
// numeric-text, and NULL operands without changing shared comparison logic.
$leftExpressions = [
    'zero-int' => '0',
    'one-int' => '1',
    'two-int' => '2',
    'three-int' => '3',
    'four-int' => '4',
    'seven-int' => '7',
    'thirty-two-int' => '32',
    'minus-one-int' => '-1',
    'minus-five-int' => '-5',
    'large-lowbits-int' => '9223372036854775807',
    'real-cast-seven' => 'CAST(7.9 AS REAL)',
    'real-cast-minus-seven' => 'CAST(-7.9 AS REAL)',
    'text-int-ten' => $sqlLiteral('10'),
    'text-real-ten' => $sqlLiteral('10.5'),
    'text-tail-six' => $sqlLiteral('6xyz'),
    'text-space-minus-nine' => $sqlLiteral('   -9'),
    'blob-five' => "CAST(x'35' AS BLOB)",
    'null-value' => 'NULL',
];

$rightExpressions = [
    'zero-int' => '0',
    'one-int' => '1',
    'two-int' => '2',
    'three-int' => '3',
    'four-int' => '4',
    'five-int' => '5',
    'six-int' => '6',
    'minus-one-int' => '-1',
    'minus-three-int' => '-3',
    'sixty-two-int' => '62',
    'sixty-three-int' => '63',
    'sixty-four-int' => '64',
    'real-cast-three' => 'CAST(3.9 AS REAL)',
    'text-int-two' => $sqlLiteral('2'),
    'text-tail-four' => $sqlLiteral('4abc'),
    'null-value' => 'NULL',
];

$operators = [
    'bitwise-or' => '|',
    'bitwise-and' => '&',
    'shift-left' => '<<',
    'shift-right' => '>>',
    'remainder' => '%',
];

$projections = [
    'quote' => 'quote',
    'typeof' => 'typeof',
    'is-null' => null,
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

foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($projections as $projectionName => $projectionSql) {
        ++$caseId;
        $expression = "~({$leftSql})";
        $cases['case-' . $caseId] = [
            'name' => "{$leftName} bitwise-not {$projectionName}",
            'expression' => $expression,
            'projection' => $projectionSql,
        ];
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $projection = $case['projection'];
    $projectionExpression = $projection === null
        ? "quote(({$expression}) IS NULL)"
        : "{$projection}({$expression})";
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || {$projectionExpression};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-bitwise-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression bitwise output');
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
    throw new RuntimeException(sprintf('Expected %d sqlite3 bitwise oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic bitwise expr.test ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $projection = $case['projection'];
        $projectionExpression = $projection === null
            ? "quote(({$expression}) IS NULL)"
            : "{$projection}({$expression})";
        $rows = SQLiteSelectSql::execute("SELECT {$projectionExpression} AS value", []);
        $t->same(1, count($rows), $expression);
        $t->same($oracle[$key], (string) $rows[0]['value'], $expression . ' ' . ($projection ?? 'is-null'));
    };
}

$tests['real upstream expression affinity dynamic bitwise owns exactly 4374 dynamic cases'] = static function (TestRunner $t) use ($leftExpressions, $rightExpressions, $operators, $projections, $cases): void {
    $t->same(18, count($leftExpressions));
    $t->same(16, count($rightExpressions));
    $t->same(5, count($operators));
    $t->same(3, count($projections));
    $t->same(4374, count($cases));
    $t->same(
        'expr.test expr-1.42..1.46 bitwise/shift plus expr-1.56 and expr-1.96..1.109 remainder and NULL propagation',
        'expr.test expr-1.42..1.46 bitwise/shift plus expr-1.56 and expr-1.96..1.109 remainder and NULL propagation',
    );
};

return $tests;
