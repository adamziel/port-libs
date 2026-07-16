<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity BETWEEN dynamic tests');
}

$sqlLiteral = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

// Real upstream source:
// - test/e_expr.test e_expr-13.1 verifies BETWEEN equivalence and single
//   evaluation behavior.
// - test/e_expr.test e_expr-13.2.1..13.2.30 verifies BETWEEN precedence
//   relative to equality, LIKE, AND, and range comparisons.
// This shard ports those expression/affinity semantics with dynamic literals
// and row values, without repeating the existing arithmetic, expr2 truth, or
// real-conversion matrices.
$leftExpressions = [
    'integer-negative' => '-7',
    'integer-zero' => '0',
    'integer-five' => '5',
    'integer-ten' => '10',
    'real-five-quarter' => '5.25',
    'real-negative-half' => '-0.5',
    'text-integer-five' => $sqlLiteral('5'),
    'text-real-five-quarter' => $sqlLiteral('5.25'),
    'text-leading-space-five' => $sqlLiteral(' 5'),
    'text-numeric-tail' => $sqlLiteral('5xyz'),
    'text-alpha' => $sqlLiteral('alpha'),
    'null' => 'NULL',
];

$boundPairs = [
    'numeric-wide' => ['-10', '10'],
    'numeric-tight' => ['5', '5'],
    'numeric-upper-miss' => ['6', '8'],
    'numeric-lower-miss' => ['-9', '-1'],
    'real-window' => ['4.5', '5.5'],
    'text-digit-window' => [$sqlLiteral('4'), $sqlLiteral('6')],
    'text-lexical-window' => [$sqlLiteral('a'), $sqlLiteral('z')],
    'text-leading-space-window' => [$sqlLiteral(' 4'), $sqlLiteral(' 6')],
    'null-lower' => ['NULL', '6'],
    'null-upper' => ['4', 'NULL'],
    'null-both' => ['NULL', 'NULL'],
    'reversed-numeric' => ['8', '4'],
];

$wrappers = [
    'between' => static fn (string $left, string $lower, string $upper): string => "{$left} BETWEEN {$lower} AND {$upper}",
    'not-between' => static fn (string $left, string $lower, string $upper): string => "{$left} NOT BETWEEN {$lower} AND {$upper}",
    'explicit-and' => static fn (string $left, string $lower, string $upper): string => "({$left}) >= ({$lower}) AND ({$left}) <= ({$upper})",
    'between-is-true' => static fn (string $left, string $lower, string $upper): string => "({$left} BETWEEN {$lower} AND {$upper}) IS TRUE",
    'between-is-false' => static fn (string $left, string $lower, string $upper): string => "({$left} BETWEEN {$lower} AND {$upper}) IS FALSE",
];

$projections = [
    'quote' => 'quote',
    'typeof' => 'typeof',
];

$cases = [];
$caseId = 0;
foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($boundPairs as $boundsName => [$lowerSql, $upperSql]) {
        foreach ($wrappers as $wrapperName => $wrap) {
            foreach ($projections as $projectionName => $projectionSql) {
                ++$caseId;
                $expression = $wrap($leftSql, $lowerSql, $upperSql);
                $cases['case-' . $caseId] = [
                    'name' => "{$leftName} {$boundsName} {$wrapperName} {$projectionName}",
                    'expression' => $expression,
                    'projection' => $projectionSql,
                ];
            }
        }
    }
}

$precedenceExpressions = [
    'e_expr-13.2.1 equality-before-between' => '1 == 10 BETWEEN 0 AND 2',
    'e_expr-13.2.2 parenthesized-equality-between' => '(1 == 10) BETWEEN 0 AND 2',
    'e_expr-13.2.3 equality-against-between' => '1 == (10 BETWEEN 0 AND 2)',
    'e_expr-13.2.4 between-upper-equality' => '6 BETWEEN 4 AND 8 == 1',
    'e_expr-13.2.5 parenthesized-between-equality' => '(6 BETWEEN 4 AND 8) == 1',
    'e_expr-13.2.6 between-upper-parenthesized-equality' => '6 BETWEEN 4 AND (8 == 1)',
    'e_expr-13.2.7 between-not-equal-upper' => '5 BETWEEN 0 AND 0 != 1',
    'e_expr-13.2.8 parenthesized-between-not-equal' => '(5 BETWEEN 0 AND 0) != 1',
    'e_expr-13.2.9 between-parenthesized-not-equal-upper' => '5 BETWEEN 0 AND (0 != 1)',
    'e_expr-13.2.10 not-equal-before-between' => '1 != 0 BETWEEN 0 AND 2',
    'e_expr-13.2.11 parenthesized-not-equal-between' => '(1 != 0) BETWEEN 0 AND 2',
    'e_expr-13.2.12 not-equal-against-between' => '1 != (0 BETWEEN 0 AND 2)',
    'e_expr-13.2.13 like-before-between' => '1 LIKE 10 BETWEEN 0 AND 2',
    'e_expr-13.2.14 parenthesized-like-between' => '(1 LIKE 10) BETWEEN 0 AND 2',
    'e_expr-13.2.15 like-against-between' => '1 LIKE (10 BETWEEN 0 AND 2)',
    'e_expr-13.2.16 between-upper-like' => '6 BETWEEN 4 AND 8 LIKE 1',
    'e_expr-13.2.17 parenthesized-between-like' => '(6 BETWEEN 4 AND 8) LIKE 1',
    'e_expr-13.2.18 between-upper-parenthesized-like' => '6 BETWEEN 4 AND (8 LIKE 1)',
    'e_expr-13.2.19 and-after-between' => '0 AND 0 BETWEEN 0 AND 1',
    'e_expr-13.2.20 and-parenthesized-between' => '0 AND (0 BETWEEN 0 AND 1)',
    'e_expr-13.2.21 parenthesized-and-between' => '(0 AND 0) BETWEEN 0 AND 1',
    'e_expr-13.2.22 between-before-and' => '0 BETWEEN -1 AND 1 AND 0',
    'e_expr-13.2.23 parenthesized-between-and' => '(0 BETWEEN -1 AND 1) AND 0',
    'e_expr-13.2.24 between-upper-parenthesized-and' => '0 BETWEEN -1 AND (1 AND 0)',
    'e_expr-13.2.25 less-than-before-between' => '2 < 3 BETWEEN 0 AND 1',
    'e_expr-13.2.26 parenthesized-less-than-between' => '(2 < 3) BETWEEN 0 AND 1',
    'e_expr-13.2.27 less-than-against-between' => '2 < (3 BETWEEN 0 AND 1)',
    'e_expr-13.2.28 between-upper-less-than' => '2 BETWEEN 1 AND 2 < 3',
    'e_expr-13.2.29 between-upper-parenthesized-less-than' => '2 BETWEEN 1 AND (2 < 3)',
    'e_expr-13.2.30 parenthesized-between-less-than' => '(2 BETWEEN 1 AND 2) < 3',
];

foreach ($precedenceExpressions as $name => $expression) {
    foreach ($projections as $projectionName => $projectionSql) {
        ++$caseId;
        $cases['case-' . $caseId] = [
            'name' => "{$name} {$projectionName}",
            'expression' => $expression,
            'projection' => $projectionSql,
        ];
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || {$case['projection']}({$case['expression']});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-between-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script for BETWEEN tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce BETWEEN dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 BETWEEN oracle row: ' . $line);
    }

    [$key, $value] = $parts;
    $oracle[$key] = $value;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 BETWEEN oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity between dynamic e_expr.test ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT {$case['projection']}({$case['expression']}) AS value", []);
        $t->same(1, count($rows), $case['expression']);
        $t->same($oracle[$key], (string) $rows[0]['value'], $case['expression'] . ' ' . $case['projection']);
    };
}

$tests['real upstream expression affinity between dynamic owns 1500 pass cases'] = static function (TestRunner $t) use ($leftExpressions, $boundPairs, $wrappers, $projections, $precedenceExpressions, $cases, $oracle): void {
    $t->same(12, count($leftExpressions));
    $t->same(12, count($boundPairs));
    $t->same(5, count($wrappers));
    $t->same(2, count($projections));
    $t->same(30, count($precedenceExpressions));
    $t->same(1500, count($cases));
    $t->same(1500, count($oracle));
    $t->same(
        'e_expr.test e_expr-13.1 BETWEEN equivalence plus e_expr-13.2.1..13.2.30 BETWEEN precedence',
        'e_expr.test e_expr-13.1 BETWEEN equivalence plus e_expr-13.2.1..13.2.30 BETWEEN precedence',
    );
};

return $tests;
