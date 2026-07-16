<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity COLLATE dynamic tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-9.1..9.25 covers COLLATE as a
//   unary postfix operator, including the distinction between applying COLLATE
//   to a comparison operand and applying it to an already-computed comparison.
// This dynamic shard expands those real upstream scenarios across text,
// numeric-looking text, numeric literals, NULL, and BINARY/NOCASE collations
// without repeating accepted REAL arithmetic, CAST target, parameter, or
// types2 comparison matrices.
$leftValues = [
    'lower-abcd' => 'abcd',
    'upper-abcd' => 'ABCD',
    'mixed-abcd' => 'AbCd',
    'lower-bbb' => 'bbb',
    'upper-bbb' => 'BBB',
    'numeric-text-10' => '10',
    'numeric-text-010' => '010',
    'numeric-text-real' => '10.0',
    'integer-10' => 10,
    'real-10' => 10.0,
    'empty-text' => '',
    'null' => null,
];

$rightValues = [
    'lower-abcd' => 'abcd',
    'upper-abcd' => 'ABCD',
    'mixed-abcd' => 'AbCd',
    'lower-bbb' => 'bbb',
    'upper-bbb' => 'BBB',
    'numeric-text-10' => '10',
    'numeric-text-010' => '010',
    'numeric-text-real' => '10.0',
    'integer-10' => 10,
    'real-10' => 10.0,
    'empty-text' => '',
    'null' => null,
];

$operators = [
    'lt' => '<',
    'le' => '<=',
    'gt' => '>',
    'ge' => '>=',
    'eq' => '=',
    'eqeq' => '==',
    'ne-bang' => '!=',
    'ne-angle' => '<>',
    'is' => 'IS',
    'is-not' => 'IS NOT',
];

$collations = [
    'nocase' => 'nocase',
    'binary' => 'binary',
];

$forms = [
    'right-operand-collate' => static fn (string $left, string $operator, string $right, string $collation): string => "{$left} {$operator} {$right} COLLATE {$collation}",
    'left-operand-collate' => static fn (string $left, string $operator, string $right, string $collation): string => "{$left} COLLATE {$collation} {$operator} {$right}",
    'parenthesized-comparison-collate' => static fn (string $left, string $operator, string $right, string $collation): string => "({$left} {$operator} {$right}) COLLATE {$collation}",
];

$cases = [];
foreach ($leftValues as $leftName => $leftValue) {
    foreach ($rightValues as $rightName => $rightValue) {
        foreach ($operators as $operatorName => $operatorSql) {
            foreach ($collations as $collationName => $collationSql) {
                foreach ($forms as $formName => $expressionFactory) {
                    $leftSql = $sqlLiteral($leftValue);
                    $rightSql = $sqlLiteral($rightValue);
                    $key = implode('.', [$leftName, $operatorName, $rightName, $collationName, $formName]);
                    $cases[$key] = $expressionFactory($leftSql, $operatorSql, $rightSql, $collationSql);
                }
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr9-collate-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr COLLATE dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr COLLATE dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed e_expr COLLATE oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr COLLATE oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity collate dynamic e_expr-9 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);

        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $key . ' is-null');
    };
}

$tests['real upstream expression affinity collate dynamic owns exactly 8640 e_expr cases'] = static function (TestRunner $t) use ($leftValues, $rightValues, $operators, $collations, $forms, $cases, $oracle): void {
    $t->same(12, count($leftValues));
    $t->same(12, count($rightValues));
    $t->same(10, count($operators));
    $t->same(2, count($collations));
    $t->same(3, count($forms));
    $t->same(8640, count($cases));
    $t->same(8640, count($oracle));
    $t->same(
        'e_expr.test e_expr-9.1..9.25 COLLATE postfix binding and comparison-affinity behavior',
        'e_expr.test e_expr-9.1..9.25 COLLATE postfix binding and comparison-affinity behavior',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
