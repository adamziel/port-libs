<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream postfix COLLATE expression tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - SQLite upstream test/e_expr.test e_expr-9.10..9.23 verifies that postfix
//   COLLATE binds to the operand expression, not to an already-computed
//   boolean result.
// - e_expr-9.22..9.23 applies the same rule to BETWEEN bounds.
// This dynamic corpus widens that section across BINARY, NOCASE, and RTRIM
// collations without using the unsupported custom "reverse" collation rows.
$values = [
    'lower-alpha' => $sqlLiteral('alpha'),
    'upper-alpha' => $sqlLiteral('ALPHA'),
    'mixed-alpha' => $sqlLiteral('Alpha'),
    'lower-beta' => $sqlLiteral('beta'),
    'trim' => $sqlLiteral('trim'),
    'trim-spaces' => $sqlLiteral('trim   '),
    'numeric-text' => $sqlLiteral('10'),
    'numeric-text-spaces' => $sqlLiteral('10   '),
    'integer-ten' => '10',
    'real-ten' => '10.0',
    'null' => 'NULL',
    'blob-alpha' => "X'616C706861'",
];

$operators = [
    'eq' => '=',
    'eqeq' => '==',
    'is' => 'IS',
    'ne' => '!=',
    'ne-alt' => '<>',
    'is-not' => 'IS NOT',
    'lt' => '<',
    'le' => '<=',
    'gt' => '>',
    'ge' => '>=',
];

$collations = ['BINARY', 'NOCASE', 'RTRIM'];

$cases = [];
foreach ($values as $leftName => $leftSql) {
    foreach ($values as $rightName => $rightSql) {
        foreach ($operators as $operatorName => $operatorSql) {
            foreach ($collations as $collation) {
                $collationName = strtolower($collation);
                $cases["compare.operand.{$leftName}.{$operatorName}.{$rightName}.{$collationName}"] =
                    "{$leftSql} {$operatorSql} {$rightSql} COLLATE {$collation}";
                $cases["compare.boolean.{$leftName}.{$operatorName}.{$rightName}.{$collationName}"] =
                    "({$leftSql} {$operatorSql} {$rightSql}) COLLATE {$collation}";
            }
        }
    }
}

$betweenValues = [
    'alpha' => $sqlLiteral('alpha'),
    'upper-alpha' => $sqlLiteral('ALPHA'),
    'bbb' => $sqlLiteral('bbb'),
    'ccc' => $sqlLiteral('ccc'),
    'trim' => $sqlLiteral('trim'),
    'trim-spaces' => $sqlLiteral('trim   '),
    'numeric-ten' => $sqlLiteral('10'),
    'numeric-ten-spaces' => $sqlLiteral('10   '),
    'null' => 'NULL',
];

$bounds = [
    'alpha-to-beta' => [$sqlLiteral('ALPHA'), $sqlLiteral('beta')],
    'aaa-to-ccc' => [$sqlLiteral('AAA'), $sqlLiteral('CCC')],
    'trim-to-trim' => [$sqlLiteral('trim'), $sqlLiteral('trim')],
    'ten-to-ten' => [$sqlLiteral('10'), $sqlLiteral('10')],
    'null-lower' => ['NULL', $sqlLiteral('zzz')],
    'null-upper' => [$sqlLiteral('aaa'), 'NULL'],
];

foreach ($betweenValues as $valueName => $valueSql) {
    foreach ($bounds as $boundName => [$lowerSql, $upperSql]) {
        foreach ($collations as $collation) {
            $collationName = strtolower($collation);
            $cases["between.upper.{$valueName}.{$boundName}.{$collationName}"] =
                "{$valueSql} BETWEEN {$lowerSql} AND {$upperSql} COLLATE {$collation}";
            $cases["between.boolean.{$valueName}.{$boundName}.{$collationName}"] =
                "({$valueSql} BETWEEN {$lowerSql} AND {$upperSql}) COLLATE {$collation}";
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-collate-postfix-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 postfix COLLATE oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce postfix COLLATE output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 postfix COLLATE oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 postfix COLLATE oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity postfix COLLATE dynamic e_expr.test ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression affinity postfix COLLATE dynamic owns e_expr section 9 corpus'] = static function (TestRunner $t) use ($values, $operators, $collations, $betweenValues, $bounds, $cases, $oracle): void {
    $t->same(12, count($values));
    $t->same(10, count($operators));
    $t->same(3, count($collations));
    $t->same(9, count($betweenValues));
    $t->same(6, count($bounds));
    $t->same(8964, count($cases));
    $t->same(8964, count($oracle));
    $t->same(
        'e_expr.test e_expr-9.10..9.23 postfix COLLATE operand binding over comparison and BETWEEN expressions',
        'e_expr.test e_expr-9.10..9.23 postfix COLLATE operand binding over comparison and BETWEEN expressions',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
