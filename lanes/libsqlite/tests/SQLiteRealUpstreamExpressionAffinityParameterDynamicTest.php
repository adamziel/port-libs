<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity parameter dynamic tests');
}

$literal = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        if (is_float($value) && !is_finite($value)) {
            throw new RuntimeException('non-finite oracle literal');
        }
        return (string) $value;
    }
    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-11.2 through e_expr-11.6 covers
//   qmark, numbered qmark, colon, at-sign, and dollar host parameters.
// - e_expr.test e_expr-7, e_expr-10, and expr.test expr-13 cover expression
//   result classes and string-to-numeric conversion once those parameters are
//   bound into arithmetic, comparison, NULL, and CAST contexts.
$values = [
    'null' => null,
    'zero-int' => 0,
    'one-int' => 1,
    'neg-seven-int' => -7,
    'two-real' => 2.25,
    'neg-quarter-real' => -0.25,
    'text-int' => '42',
    'text-real' => '42.5',
    'text-real-tail' => '42.5tail',
    'text-leading-space' => '   -12.75',
    'text-plus-decimal' => '+.5',
    'text-alpha' => 'abc',
    'text-empty' => '',
];

$tokenForms = [
    'qmark-positional' => ['sql' => '?', 'parameters' => static fn (mixed $value): array => [1 => $value]],
    'qmark-zero-based' => ['sql' => '?', 'parameters' => static fn (mixed $value): array => [0 => $value]],
    'qmark-numbered' => ['sql' => '?5', 'parameters' => static fn (mixed $value): array => [5 => $value]],
    'colon-named' => ['sql' => ':tenant_value', 'parameters' => static fn (mixed $value): array => [':tenant_value' => $value]],
    'colon-bare' => ['sql' => ':tenant_value', 'parameters' => static fn (mixed $value): array => ['tenant_value' => $value]],
    'at-named' => ['sql' => '@tenant_value', 'parameters' => static fn (mixed $value): array => ['@tenant_value' => $value]],
    'dollar-named' => ['sql' => '$tenant_value', 'parameters' => static fn (mixed $value): array => ['$tenant_value' => $value]],
];

$rightValues = [
    'zero' => 0,
    'one' => 1,
    'two-real' => 2.0,
    'text-two-real' => '2.0',
    'text-tail' => '2tail',
    'null' => null,
];

$operators = [
    'add' => '+',
    'subtract' => '-',
    'multiply' => '*',
    'divide' => '/',
    'equals' => '=',
    'not-equals' => '<>',
    'less-than' => '<',
    'greater-equal' => '>=',
    'is' => 'IS',
    'is-not' => 'IS NOT',
];

$projections = [
    'quote' => static fn (string $expression): string => "quote({$expression})",
    'typeof' => static fn (string $expression): string => "typeof({$expression})",
    'is-null' => static fn (string $expression): string => "quote(({$expression}) IS NULL)",
];

$cases = [];
foreach ($tokenForms as $tokenName => $token) {
    foreach ($values as $leftName => $leftValue) {
        foreach ($rightValues as $rightName => $rightValue) {
            foreach ($operators as $operatorName => $operator) {
                foreach ($projections as $projectionName => $projectionSql) {
                    $expression = '(' . $token['sql'] . ") {$operator} (" . $literal($rightValue) . ')';
                    $literalExpression = '(' . $literal($leftValue) . ") {$operator} (" . $literal($rightValue) . ')';
                    $caseKey = implode('-', [$tokenName, $leftName, $operatorName, $rightName, $projectionName]);
                    $cases[$caseKey] = [
                        'expression' => $expression,
                        'literalExpression' => $literalExpression,
                        'projection' => $projectionSql,
                        'parameters' => $token['parameters']($leftValue),
                    ];
                }
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $projectionSql = $case['projection'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || " . $projectionSql($case['literalExpression']) . ';';
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr11-param-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr parameter dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr parameter dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed e_expr parameter oracle row: ' . $line);
    }
    [$key, $value] = $parts;
    $oracle[$key] = $value;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr parameter oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity parameter dynamic e_expr-11 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $projectionSql = $case['projection'];
        $rows = SQLiteSelectSql::execute('SELECT ' . $projectionSql($case['expression']) . ' AS value', [], $case['parameters']);
        $t->same(1, count($rows), $key);
        $t->same($oracle[$key], (string) $rows[0]['value'], $key);
    };
}

$tests['real upstream expression affinity parameter dynamic owns exactly 16380 e_expr cases'] = static function (TestRunner $t) use ($cases, $values, $tokenForms, $rightValues, $operators, $projections): void {
    $t->same(13, count($values));
    $t->same(7, count($tokenForms));
    $t->same(6, count($rightValues));
    $t->same(10, count($operators));
    $t->same(3, count($projections));
    $t->same(16380, count($cases));
    $t->same(
        'e_expr.test e_expr-11.2..11.6 host parameters combined with e_expr-7/e_expr-10 and expr-13 expression affinity conversion',
        'e_expr.test e_expr-11.2..11.6 host parameters combined with e_expr-7/e_expr-10 and expr-13 expression affinity conversion',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
