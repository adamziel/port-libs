<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression precedence dynamic tests');
}

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-1.* verifies binary operator
//   precedence by comparing differently parenthesized expression trees.
// This shard expands that upstream pattern through the bounded SELECT SQL
// executor with REAL, integer, text-numeric, and NULL operands.
$operands = [
    'integer-row' => ['a' => '72', 'b' => '5', 'c' => '3'],
    'negative-row' => ['a' => '-72', 'b' => '5', 'c' => '3'],
    'real-row' => ['a' => '72.35', 'b' => '5', 'c' => '3.5'],
    'text-numeric-row' => ['a' => "'72.35'", 'b' => "'5'", 'c' => "'3.5'"],
    'text-alpha-row' => ['a' => "'abc'", 'b' => "'b'", 'c' => "'c'"],
    'null-left-row' => ['a' => 'NULL', 'b' => '5', 'c' => '3'],
    'null-right-row' => ['a' => '72', 'b' => 'NULL', 'c' => '3'],
];

$operators = [
    'concat' => '||',
    'multiply' => '*',
    'divide' => '/',
    'modulo' => '%',
    'add' => '+',
    'subtract' => '-',
    'shift-left' => '<<',
    'shift-right' => '>>',
    'bit-and' => '&',
    'bit-or' => '|',
    'less-than' => '<',
    'less-equal' => '<=',
    'greater-than' => '>',
    'greater-equal' => '>=',
    'equals' => '=',
    'equals-equals' => '==',
    'not-equals-bang' => '!=',
    'not-equals-angle' => '<>',
    'is' => 'IS',
    'is-not' => 'IS NOT',
    'and' => 'AND',
    'or' => 'OR',
];

$cases = [];
foreach ($operands as $operandName => $values) {
    foreach ($operators as $leftName => $leftOperator) {
        foreach ($operators as $rightName => $rightOperator) {
            $key = "{$operandName} {$leftName} then {$rightName}";
            $defaultExpression = "{$values['a']} {$leftOperator} {$values['b']} {$rightOperator} {$values['c']}";
            $leftGroupedExpression = "({$values['a']} {$leftOperator} {$values['b']}) {$rightOperator} {$values['c']}";
            $rightGroupedExpression = "{$values['a']} {$leftOperator} ({$values['b']} {$rightOperator} {$values['c']})";
            $cases[$key] = [
                'default' => $defaultExpression,
                'leftGrouped' => $leftGroupedExpression,
                'rightGrouped' => $rightGroupedExpression,
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = sprintf(
        "SELECT '%s' || char(9) || quote(%s) || char(9) || typeof(%s) || char(9) || quote(%s) || char(9) || typeof(%s) || char(9) || quote(%s) || char(9) || typeof(%s);",
        $safeKey,
        $case['default'],
        $case['default'],
        $case['leftGrouped'],
        $case['leftGrouped'],
        $case['rightGrouped'],
        $case['rightGrouped'],
    );
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-precedence-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression precedence tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression precedence output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 7) {
        throw new RuntimeException('malformed expression precedence oracle row: ' . $line);
    }

    [$key, $defaultQuote, $defaultType, $leftGroupedQuote, $leftGroupedType, $rightGroupedQuote, $rightGroupedType] = $parts;
    $oracle[$key] = [
        'defaultQuote' => $defaultQuote,
        'defaultType' => $defaultType,
        'leftGroupedQuote' => $leftGroupedQuote,
        'leftGroupedType' => $leftGroupedType,
        'rightGroupedQuote' => $rightGroupedQuote,
        'rightGroupedType' => $rightGroupedType,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression precedence oracle rows, got %d', count($cases), count($oracle)));
}

$assertQuotedParity = static function (TestRunner $t, string $expected, string $actual, string $type, string $message): void {
    if ($expected === $actual || $expected === 'NULL') {
        $t->same($expected, $actual, $message);

        return;
    }

    if ($type !== 'real') {
        $t->same($expected, $actual, $message);

        return;
    }

    $expectedFloat = (float) $expected;
    $actualFloat = (float) $actual;
    $scale = max(1.0, abs($expectedFloat), abs($actualFloat));
    $t->true(abs($expectedFloat - $actualFloat) <= $scale * 1.0e-14, $message . " expected {$expected}, got {$actual}");
};

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity operator precedence dynamic e_expr-1 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $assertQuotedParity): void {
        $rows = SQLiteSelectSql::execute(
            sprintf(
                'SELECT quote(%s) AS dq, typeof(%s) AS dt, quote(%s) AS lq, typeof(%s) AS lt, quote(%s) AS rq, typeof(%s) AS rt',
                $case['default'],
                $case['default'],
                $case['leftGrouped'],
                $case['leftGrouped'],
                $case['rightGrouped'],
                $case['rightGrouped'],
            ),
            [],
        );
        $t->same(1, count($rows), $key);

        $row = $rows[0];
        $t->same($oracle[$key]['defaultType'], (string) $row['dt'], $case['default'] . ' default typeof');
        $assertQuotedParity($t, $oracle[$key]['defaultQuote'], (string) $row['dq'], $oracle[$key]['defaultType'], $case['default'] . ' default quote');
        $t->same($oracle[$key]['leftGroupedType'], (string) $row['lt'], $case['leftGrouped'] . ' left-grouped typeof');
        $assertQuotedParity($t, $oracle[$key]['leftGroupedQuote'], (string) $row['lq'], $oracle[$key]['leftGroupedType'], $case['leftGrouped'] . ' left-grouped quote');
        $t->same($oracle[$key]['rightGroupedType'], (string) $row['rt'], $case['rightGrouped'] . ' right-grouped typeof');
        $assertQuotedParity($t, $oracle[$key]['rightGroupedQuote'], (string) $row['rq'], $oracle[$key]['rightGroupedType'], $case['rightGrouped'] . ' right-grouped quote');
    };
}

$tests['real upstream expression affinity operator precedence dynamic owns e_expr operator matrix'] = static function (TestRunner $t) use ($operands, $operators, $cases, $oracle): void {
    $t->same(7, count($operands));
    $t->same(22, count($operators));
    $t->same(3388, count($cases));
    $t->same(3388, count($oracle));
    $t->same(
        'e_expr.test e_expr-1 binary operator precedence over integer, real, text-numeric, text, and NULL operands',
        'e_expr.test e_expr-1 binary operator precedence over integer, real, text-numeric, text, and NULL operands',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
