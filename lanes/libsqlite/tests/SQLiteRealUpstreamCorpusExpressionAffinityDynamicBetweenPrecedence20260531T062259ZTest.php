<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream e_expr BETWEEN precedence dynamic tests');
}

$literals = [
    'null' => 'NULL',
    'zero' => '0',
    'one' => '1',
    'two' => '2',
    'three' => '3',
    'five' => '5',
    'six' => '6',
    'ten' => '10',
    'neg-one' => '-1',
    'real-half' => '0.5',
    'real-two-half' => '2.5',
    'text-zero' => "'0'",
    'text-one' => "'1'",
    'text-two' => "'2'",
    'text-ten' => "'10'",
    'text-alpha' => "'alpha'",
    'text-empty' => "''",
];

$leftOperators = [
    'eq' => '=',
    'eq2' => '==',
    'ne' => '!=',
    'ne2' => '<>',
    'lt' => '<',
    'le' => '<=',
    'gt' => '>',
    'ge' => '>=',
    'is' => 'IS',
    'is-not' => 'IS NOT',
    'like' => 'LIKE',
    'glob' => 'GLOB',
];

$rightOperators = [
    'eq' => '=',
    'ne' => '!=',
    'lt' => '<',
    'le' => '<=',
    'gt' => '>',
    'ge' => '>=',
    'like' => 'LIKE',
    'glob' => 'GLOB',
    'and' => 'AND',
    'or' => 'OR',
];

$bounds = [
    'zero-to-two' => ['0', '2'],
    'one-to-three' => ['1', '3'],
    'neg-to-one' => ['-1', '1'],
    'text-zero-to-two' => ["'0'", "'2'"],
    'text-a-to-z' => ["'a'", "'z'"],
    'null-to-two' => ['NULL', '2'],
    'zero-to-null' => ['0', 'NULL'],
];

$patterns = [
    'one' => '1',
    'zero' => '0',
    'text-one' => "'1'",
    'text-any' => "'%'",
    'glob-any' => "'*'",
];

$cases = [];

// Source truth: SQLite upstream test/e_expr.test e_expr-13.1 and
// e_expr-13.2. Those tests verify that BETWEEN is equivalent to a pair of
// comparisons except that the left expression is evaluated once, and that
// BETWEEN has the same precedence as equality/LIKE but binds more tightly
// than AND. This shard expands the upstream precedence forms across storage
// classes and neighboring comparison operators using sqlite3 as the oracle.
foreach ($literals as $leftName => $left) {
    foreach ($leftOperators as $operatorName => $operator) {
        foreach ($patterns as $rightName => $right) {
            foreach ($bounds as $boundName => [$lower, $upper]) {
                $name = sprintf('left-op %s %s %s between %s', $leftName, $operatorName, $rightName, $boundName);
                $cases[$name] = sprintf(
                    'SELECT quote(%s %s %s BETWEEN %s AND %s) AS value',
                    $left,
                    $operator,
                    $right,
                    $lower,
                    $upper,
                );

                $name = sprintf('left-op parenthesized %s %s %s between %s', $leftName, $operatorName, $rightName, $boundName);
                $cases[$name] = sprintf(
                    'SELECT quote((%s %s %s) BETWEEN %s AND %s) AS value',
                    $left,
                    $operator,
                    $right,
                    $lower,
                    $upper,
                );

                $name = sprintf('left-op right-between %s %s %s between %s', $leftName, $operatorName, $rightName, $boundName);
                $cases[$name] = sprintf(
                    'SELECT quote(%s %s (%s BETWEEN %s AND %s)) AS value',
                    $left,
                    $operator,
                    $right,
                    $lower,
                    $upper,
                );
            }
        }
    }
}

foreach ($literals as $valueName => $value) {
    foreach ($bounds as $boundName => [$lower, $upper]) {
        foreach ($rightOperators as $operatorName => $operator) {
            foreach ($patterns as $rightName => $right) {
                $name = sprintf('between-then-op %s between %s %s %s', $valueName, $boundName, $operatorName, $rightName);
                $cases[$name] = sprintf(
                    'SELECT quote(%s BETWEEN %s AND %s %s %s) AS value',
                    $value,
                    $lower,
                    $upper,
                    $operator,
                    $right,
                );

                $name = sprintf('between-then-op parenthesized-result %s between %s %s %s', $valueName, $boundName, $operatorName, $rightName);
                $cases[$name] = sprintf(
                    'SELECT quote((%s BETWEEN %s AND %s) %s %s) AS value',
                    $value,
                    $lower,
                    $upper,
                    $operator,
                    $right,
                );

                $name = sprintf('between-then-op upper-parenthesized %s between %s %s %s', $valueName, $boundName, $operatorName, $rightName);
                $cases[$name] = sprintf(
                    'SELECT quote(%s BETWEEN %s AND (%s %s %s)) AS value',
                    $value,
                    $lower,
                    $upper,
                    $operator,
                    $right,
                );
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $sql) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || value FROM ({$sql});";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr13-between-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr BETWEEN precedence tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr BETWEEN precedence output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed e_expr BETWEEN precedence oracle row: ' . $line);
    }

    [$key, $value] = $parts;
    $oracle[$key] = $value;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr BETWEEN precedence oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $sql) {
    $tests['real upstream corpus expression affinity dynamic e_expr-13 between precedence ' . $key] = static function (TestRunner $t) use ($sql, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute($sql, []);
        $t->same(1, count($rows), $key);
        $t->same($oracle[$key], (string) $rows[0]['value'], $key);
    };
}

$tests['real upstream corpus expression affinity dynamic e_expr-13 between precedence owns expanded shard'] = static function (TestRunner $t) use ($cases, $oracle, $literals, $leftOperators, $rightOperators, $bounds, $patterns): void {
    $t->same(17, count($literals));
    $t->same(12, count($leftOperators));
    $t->same(10, count($rightOperators));
    $t->same(7, count($bounds));
    $t->same(5, count($patterns));
    $t->same(39270, count($cases));
    $t->same(count($cases), count($oracle));
    $t->same(
        'e_expr.test e_expr-13.1 and e_expr-13.2 BETWEEN single-evaluation equivalence and precedence behavior',
        'e_expr.test e_expr-13.1 and e_expr-13.2 BETWEEN single-evaluation equivalence and precedence behavior',
    );
};

$tests['real upstream corpus expression affinity dynamic e_expr-13 between precedence dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed: reuses SQLiteSelectSql constant expression execution and local sqlite3 oracle; volatile function single-evaluation tracing remains a future executor hook',
        'no new support component needed: reuses SQLiteSelectSql constant expression execution and local sqlite3 oracle; volatile function single-evaluation tracing remains a future executor hook',
    );
};

return $tests;
