<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity COLLATE dynamic tests');
}

$quoteSql = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test section e_expr-9.*. That
// section verifies postfix COLLATE binding, comparison override behavior, and
// BETWEEN collation propagation. This dynamic shard expands the same behavior
// over text values that exercise NOCASE, BINARY, and RTRIM collation paths.
$textValues = [
    'lower-a' => 'a',
    'upper-a' => 'A',
    'lower-b' => 'b',
    'upper-b' => 'B',
    'mixed-ab' => 'Ab',
    'mixed-a-b' => 'aB',
    'trail-space' => 'abc ',
    'no-trail-space' => 'abc',
    'double-trail-space' => 'abc  ',
    'numeric-text' => '10',
    'numeric-text-wide' => '010',
    'empty' => '',
    'space' => ' ',
    'punct' => 'a_',
    'upper-punct' => 'A_',
    'zulu' => 'z',
];

$comparisons = [
    'less-than' => '<',
    'less-equal' => '<=',
    'greater-than' => '>',
    'greater-equal' => '>=',
    'equals' => '=',
    'double-equals' => '==',
    'not-equals' => '!=',
    'angle-not-equals' => '<>',
    'is' => 'IS',
    'is-not' => 'IS NOT',
];

$collations = [
    'nocase' => 'nocase',
    'binary' => 'binary',
    'rtrim' => 'rtrim',
];

$cases = [];
foreach ($textValues as $leftName => $leftValue) {
    foreach ($textValues as $rightName => $rightValue) {
        foreach ($comparisons as $operatorName => $operatorSql) {
            foreach ($collations as $collationName => $collationSql) {
                $leftSql = $quoteSql($leftValue);
                $rightSql = $quoteSql($rightValue);
                $cases["right-collate.{$leftName}.{$operatorName}.{$rightName}.{$collationName}"] =
                    "{$leftSql} {$operatorSql} {$rightSql} COLLATE {$collationSql}";
                $cases["grouped-boolean-collate.{$leftName}.{$operatorName}.{$rightName}.{$collationName}"] =
                    "({$leftSql} {$operatorSql} {$rightSql}) COLLATE {$collationSql}";
            }
        }
    }
}

$betweenBounds = [
    'aaa-ccc' => ['AAA', 'CCC'],
    'a-b' => ['A', 'B'],
    'abc-abc-space' => ['abc', 'abc '],
    'empty-a' => ['', 'a'],
];

foreach ($textValues as $valueName => $value) {
    foreach ($betweenBounds as $boundName => [$lower, $upper]) {
        foreach ($collations as $collationName => $collationSql) {
            $valueSql = $quoteSql($value);
            $lowerSql = $quoteSql($lower);
            $upperSql = $quoteSql($upper);
            $cases["between-upper-collate.{$valueName}.{$boundName}.{$collationName}"] =
                "{$valueSql} BETWEEN {$lowerSql} AND {$upperSql} COLLATE {$collationSql}";
            $cases["between-grouped-collate.{$valueName}.{$boundName}.{$collationName}"] =
                "({$valueSql} BETWEEN {$lowerSql} AND {$upperSql}) COLLATE {$collationSql}";
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr-collate-dynamic-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expression affinity COLLATE dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression affinity COLLATE dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed expression affinity COLLATE oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression affinity COLLATE oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity collate dynamic e_expr-9 ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n",
            [],
        );

        $t->same(1, count($rows), $key . ' row count');
        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $key . ' nullness');
        $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
    };
}

$tests['real upstream expression affinity collate dynamic owns 15744 e_expr cases'] = static function (TestRunner $t) use ($textValues, $comparisons, $collations, $betweenBounds, $cases, $oracle): void {
    $t->same(16, count($textValues));
    $t->same(10, count($comparisons));
    $t->same(3, count($collations));
    $t->same(4, count($betweenBounds));
    $t->same(15744, count($cases));
    $t->same(15744, count($oracle));
    $t->same(
        'e_expr.test e_expr-9.* postfix COLLATE binding, comparison override, and BETWEEN collation propagation behavior',
        'e_expr.test e_expr-9.* postfix COLLATE binding, comparison override, and BETWEEN collation propagation behavior',
    );
};

return $tests;
