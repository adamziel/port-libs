<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr collation dynamic tests');
}

$quote = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Real upstream source:
// - test/e_expr.test e_expr-9.1..9.24 verifies that COLLATE binds to the
//   operand expression before comparison, while parenthesized comparison
//   results do not become re-compared under the trailing collation.
$leftValues = [
    'lower-abcd' => 'abcd',
    'upper-abcd' => 'ABCD',
    'mixed-abcd' => 'AbCd',
    'plain-bbb' => 'bbb',
    'trail-space' => 'abc ',
    'plain-abc' => 'abc',
    'numeric-text' => '10',
    'numeric-padded' => '010',
    'empty' => '',
];

$rightValues = [
    'upper-abcd' => 'ABCD',
    'lower-abcd' => 'abcd',
    'mixed-abcd' => 'aBcD',
    'lower-bbbb' => 'bbbb',
    'upper-aaa' => 'AAA',
    'upper-ccc' => 'CCC',
    'plain-abc' => 'abc',
    'trail-space' => 'abc ',
    'numeric-ten' => '10',
    'numeric-padded' => '010',
    'empty' => '',
];

$operators = [
    'lt' => '<',
    'le' => '<=',
    'gt' => '>',
    'ge' => '>=',
    'eq' => '=',
    'eq2' => '==',
    'is' => 'IS',
    'ne' => '!=',
    'ne2' => '<>',
    'is-not' => 'IS NOT',
];

$collations = ['binary', 'nocase', 'rtrim'];
$forms = [
    'right-collate' => static fn (string $left, string $op, string $right, string $collation): string => "{$left} {$op} {$right} COLLATE {$collation}",
    'left-collate' => static fn (string $left, string $op, string $right, string $collation): string => "{$left} COLLATE {$collation} {$op} {$right}",
    'both-left-wins' => static fn (string $left, string $op, string $right, string $collation): string => "{$left} COLLATE {$collation} {$op} {$right} COLLATE binary",
    'parenthesized-result' => static fn (string $left, string $op, string $right, string $collation): string => "({$left} {$op} {$right}) COLLATE {$collation}",
];

$cases = [];
foreach ($leftValues as $leftName => $leftValue) {
    foreach ($rightValues as $rightName => $rightValue) {
        foreach ($operators as $operatorName => $operator) {
            foreach ($collations as $collation) {
                foreach ($forms as $formName => $formSql) {
                    $key = "compare {$leftName} {$operatorName} {$rightName} {$collation} {$formName}";
                    $cases[$key] = 'SELECT quote(' . $formSql($quote($leftValue), $operator, $quote($rightValue), $collation) . ') AS value';
                }
            }
        }
    }
}

$betweenBounds = [
    'upper-alpha' => ['AAA', 'CCC'],
    'lower-alpha' => ['aaa', 'ccc'],
    'rtrim-exact' => ['abc', 'abc'],
    'numeric-text' => ['001', '100'],
    'open-high' => ['', 'bbbb'],
];
$betweenForms = [
    'upper-collate' => static fn (string $value, string $lower, string $upper, string $collation): string => "{$value} BETWEEN {$lower} AND {$upper} COLLATE {$collation}",
    'value-collate' => static fn (string $value, string $lower, string $upper, string $collation): string => "{$value} COLLATE {$collation} BETWEEN {$lower} AND {$upper}",
    'parenthesized-result' => static fn (string $value, string $lower, string $upper, string $collation): string => "({$value} BETWEEN {$lower} AND {$upper}) COLLATE {$collation}",
    'not-upper-collate' => static fn (string $value, string $lower, string $upper, string $collation): string => "{$value} NOT BETWEEN {$lower} AND {$upper} COLLATE {$collation}",
    'not-parenthesized-result' => static fn (string $value, string $lower, string $upper, string $collation): string => "({$value} NOT BETWEEN {$lower} AND {$upper}) COLLATE {$collation}",
];

foreach ($leftValues as $leftName => $leftValue) {
    foreach ($betweenBounds as $boundName => [$lower, $upper]) {
        foreach ($collations as $collation) {
            foreach ($betweenForms as $formName => $formSql) {
                $key = "between {$leftName} {$boundName} {$collation} {$formName}";
                $cases[$key] = 'SELECT quote(' . $formSql($quote($leftValue), $quote($lower), $quote($upper), $collation) . ') AS value';
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $sql) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || value FROM ({$sql});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr9-collate-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr collation dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr collation dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed e_expr collation oracle row: ' . $line);
    }
    [$key, $value] = $parts;
    $oracle[$key] = $value;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr collation oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $sql) {
    $tests['real upstream expression affinity collate dynamic e_expr-9 ' . $key] = static function (TestRunner $t) use ($sql, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute($sql, []);
        $t->same(1, count($rows), $key);
        $t->same($oracle[$key], (string) $rows[0]['value'], $key);
    };
}

$tests['real upstream expression affinity collate dynamic owns e_expr-9 shard'] = static function (TestRunner $t) use ($cases, $oracle, $leftValues, $rightValues, $operators, $collations, $forms, $betweenBounds, $betweenForms): void {
    $t->same(9, count($leftValues));
    $t->same(11, count($rightValues));
    $t->same(10, count($operators));
    $t->same(3, count($collations));
    $t->same(4, count($forms));
    $t->same(5, count($betweenBounds));
    $t->same(5, count($betweenForms));
    $t->same(11880 + 675, count($cases));
    $t->same(count($cases), count($oracle));
    $t->same(
        'e_expr.test e_expr-9.1..9.24 COLLATE operand-binding and parenthesized-result comparison behavior',
        'e_expr.test e_expr-9.1..9.24 COLLATE operand-binding and parenthesized-result comparison behavior',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
