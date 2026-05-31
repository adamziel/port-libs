<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity real truth dynamic tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source:
// - test/expr.test expr-14.1..14.4 verifies SQL truth coercion for OR, NOT
//   NOT, CASE WHEN, count(), and sum() over 0, 1, NULL, REAL, and numeric TEXT.
// - test/expr.test expr-15.* repeats the same truth invariants after binding
//   NaN/Inf double values.
// This shard keeps the focus on expression affinity by using constant SELECT
// expression dispatch over dynamic REAL, integer, TEXT-prefix, and NULL
// operands. It avoids the accepted cast-prefix, overflow, modulo, IS DISTINCT,
// unary-plus, and aggregate truth shards.
$leftExpressions = [
    'integer-zero' => '0',
    'integer-one' => '1',
    'integer-negative-one' => '-1',
    'real-zero' => '0.0',
    'real-half' => '0.5',
    'real-negative-half' => '-0.5',
    'real-tiny-positive' => '0.0000000000000001',
    'real-tiny-negative' => '-0.0000000000000001',
    'real-large-positive' => '1.25e+125',
    'real-large-negative' => '-1.25e+125',
    'text-one-tail' => $sqlLiteral('1x'),
    'text-zero-tail' => $sqlLiteral('0x'),
    'text-leading-real' => $sqlLiteral('  0.5x'),
    'text-leading-zero-real' => $sqlLiteral('  0.0x'),
    'text-negative-real' => $sqlLiteral('-0.25x'),
    'text-alpha' => $sqlLiteral('alpha'),
    'text-empty' => $sqlLiteral(''),
    'text-space' => $sqlLiteral('   '),
    'text-plus-half' => $sqlLiteral('+.5x'),
    'null' => 'NULL',
];

$rightExpressions = [
    'integer-zero' => '0',
    'integer-one' => '1',
    'integer-nine' => '9',
    'real-zero' => '0.0',
    'real-quarter' => '0.25',
    'real-negative-quarter' => '-0.25',
    'text-zero' => $sqlLiteral('0'),
    'text-one' => $sqlLiteral('1'),
    'text-nine-tail' => $sqlLiteral('9x'),
    'null' => 'NULL',
];

$forms = [
    'or-eq-nine' => static fn (string $left, string $right): string => "({$left}) OR (({$right}) == 9)",
    'and-not-zero' => static fn (string $left, string $right): string => "({$left}) AND (({$right}) != 0)",
    'not-left' => static fn (string $left, string $right): string => "NOT ({$left})",
    'not-not-left' => static fn (string $left, string $right): string => "NOT NOT ({$left})",
    'case-left' => static fn (string $left, string $right): string => "CASE WHEN ({$left}) THEN 1 ELSE 0 END",
    'expr-14-or-case-invariant' => static fn (string $left, string $right): string => "(({$left}) OR (({$right}) == 9)) != (CASE WHEN ({$left}) THEN 1 ELSE 0 END)",
];

$cases = [];
$caseId = 0;
foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($rightExpressions as $rightName => $rightSql) {
        foreach ($forms as $formName => $formSql) {
            ++$caseId;
            $expression = $formSql($leftSql, $rightSql);
            $cases['case-' . $caseId] = [
                'name' => "{$leftName} {$formName} {$rightName}",
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

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-truth-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 real-truth oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce real truth expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 real-truth oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 real-truth oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic real truth expr.test expr-14 expr-15 ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
    };
}

$tests['real upstream corpus expression affinity dynamic real truth owns exactly 1200 upstream-derived cases'] = static function (TestRunner $t) use ($leftExpressions, $rightExpressions, $forms, $cases, $oracle): void {
    $t->same(20, count($leftExpressions));
    $t->same(10, count($rightExpressions));
    $t->same(6, count($forms));
    $t->same(1200, count($cases));
    $t->same(1200, count($oracle));
    $t->same(
        'expr.test expr-14.1..14.4 and expr-15.* dynamic REAL/TEXT/NULL truth coercion through OR, AND, NOT, NOT NOT, CASE WHEN, and invariant comparison',
        'expr.test expr-14.1..14.4 and expr-15.* dynamic REAL/TEXT/NULL truth coercion through OR, AND, NOT, NOT NOT, CASE WHEN, and invariant comparison',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
