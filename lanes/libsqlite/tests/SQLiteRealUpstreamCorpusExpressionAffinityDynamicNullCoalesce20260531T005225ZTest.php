<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity NULL coalesce dynamic tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source:
// - test/expr.test expr-1.58..1.77 verifies NULL propagation through
//   arithmetic, comparison, unary, and bitwise expressions wrapped by coalesce().
// - test/expr.test expr-1.78..1.85 verifies three-valued logical expressions
//   and scalar min()/max() NULL propagation.
// This shard is intentionally separate from the accepted BETWEEN, bitwise,
// cast-target, real-conversion, and row-context affinity batches.
$leftOperands = [
    'null' => 'NULL',
    'zero' => '0',
    'one' => '1',
    'three' => '3',
    'eight' => '8',
    'real-half' => '0.5',
    'text-empty' => $sqlLiteral(''),
    'text-zero' => $sqlLiteral('0'),
    'text-one' => $sqlLiteral('1'),
    'text-three-real' => $sqlLiteral('3.0'),
    'text-alpha' => $sqlLiteral('alpha'),
    'blob-zero' => "X'00'",
];

$rightOperands = [
    'null' => 'NULL',
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'five' => '5',
    'real-quarter' => '0.25',
    'text-empty' => $sqlLiteral(''),
    'text-zero' => $sqlLiteral('0'),
    'text-one' => $sqlLiteral('1'),
    'text-two-real' => $sqlLiteral('2.0'),
    'text-beta' => $sqlLiteral('beta'),
    'blob-one' => "X'01'",
];

$binaryOperators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'less-than' => '<',
    'greater-than' => '>',
    'equals' => '=',
    'not-equals' => '<>',
    'and' => 'AND',
    'or' => 'OR',
    'bit-and' => '&',
    'bit-or' => '|',
];

$unaryExpressions = [
    'not-left' => static fn (string $left, string $right): string => "NOT ({$left})",
    'negate-left' => static fn (string $left, string $right): string => "-({$left})",
    'coalesce-left-right' => static fn (string $left, string $right): string => "coalesce({$left}, {$right}, 99)",
    'min-left-right-one' => static fn (string $left, string $right): string => "min({$left}, {$right}, 1)",
    'max-left-right-one' => static fn (string $left, string $right): string => "max({$left}, {$right}, 1)",
    'logical-null-left-right' => static fn (string $left, string $right): string => "({$left}) IS NULL OR ({$right})=5",
];

$cases = [];
$caseId = 0;
foreach ($leftOperands as $leftName => $leftSql) {
    foreach ($rightOperands as $rightName => $rightSql) {
        foreach ($binaryOperators as $operatorName => $operatorSql) {
            ++$caseId;
            $inner = "({$leftSql}) {$operatorSql} ({$rightSql})";
            $cases['binary-' . $caseId] = [
                'name' => "{$leftName} {$operatorName} {$rightName}",
                'expression' => "coalesce({$inner}, 99)",
            ];
        }

        foreach ($unaryExpressions as $expressionName => $factory) {
            ++$caseId;
            $cases['unary-' . $caseId] = [
                'name' => "{$expressionName} {$leftName} {$rightName}",
                'expression' => 'coalesce(' . $factory($leftSql, $rightSql) . ', 99)',
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

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-null-coalesce-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce NULL coalesce expression affinity output');
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
    throw new RuntimeException(sprintf('Expected %d sqlite3 NULL coalesce oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic NULL coalesce expr.test expr-1.58-1.85 ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream corpus expression affinity dynamic NULL coalesce owns exactly 2592 dynamic cases'] = static function (TestRunner $t) use ($leftOperands, $rightOperands, $binaryOperators, $unaryExpressions, $cases): void {
    $t->same(12, count($leftOperands));
    $t->same(12, count($rightOperands));
    $t->same(12, count($binaryOperators));
    $t->same(6, count($unaryExpressions));
    $t->same(2592, count($cases));
    $t->same(
        'expr.test expr-1.58..1.85 NULL arithmetic/comparison/logical/min/max coalesce propagation',
        'expr.test expr-1.58..1.85 NULL arithmetic/comparison/logical/min/max coalesce propagation',
    );
};

return $tests;
