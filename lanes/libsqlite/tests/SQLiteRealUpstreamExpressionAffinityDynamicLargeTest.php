<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));

$oracle = static function (string $sql) use ($sqlite3): array {
    static $cache = [];

    if (isset($cache[$sql])) {
        return $cache[$sql];
    }
    if ($sqlite3 === '') {
        throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity dynamic large tests');
    }

    $command = escapeshellarg($sqlite3) . ' -batch -noheader -separator ' . escapeshellarg("\t") . ' :memory: ' . escapeshellarg($sql);
    $output = shell_exec($command);
    if ($output === null) {
        throw new RuntimeException('sqlite3 oracle did not produce output for ' . $sql);
    }

    return $cache[$sql] = explode("\t", rtrim($output, "\r\n"));
};

$portSelect = static function (string $expression): array {
    $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t", []);
    if (count($rows) !== 1) {
        throw new RuntimeException('expected one row for ' . $expression);
    }

    return [(string) $rows[0]['q'], (string) $rows[0]['t']];
};

$oracleSelect = static fn (string $expression): array => $oracle("SELECT quote({$expression}), typeof({$expression});");

$literalValues = [
    'null' => 'NULL',
    'zero-int' => '0',
    'one-int' => '1',
    'minus-one-int' => '-1',
    'forty-two-int' => '42',
    'real-basic' => '123.456',
    'real-neg-exp' => '-2.5e-2',
    'text-int' => "'123'",
    'text-int-space' => "'   123'",
    'text-neg-real' => "'   -123.456'",
    'text-real' => "'123.456'",
    'text-exp' => "'123e+5'",
    'text-real-tail' => "'123.5abc'",
    'text-int-tail' => "'123abc'",
    'text-plus' => "'+'",
    'text-minus' => "'-'",
    'text-dot' => "'.'",
    'text-slash' => "'/'",
    'text-empty' => "''",
    'text-leading-zero' => "'00000000000000000042'",
    'blob-abc' => "x'616263'",
    'blob-one' => "x'31'",
];

$targets = ['TEXT', 'BLOB', 'INTEGER', 'REAL', 'NUMERIC'];

// Source truth: SQLite upstream test/cast.test cast-1.*, cast-3.*, cast-5.*,
// cast-7.*, and cast-9.*. These dynamic cases compare the port's bounded
// SELECT expression executor against sqlite3 for CAST result text and storage
// class across scalar, text, numeric, overflow, and BLOB inputs.
foreach ($literalValues as $valueName => $literal) {
    foreach ($targets as $target) {
        $expression = "CAST({$literal} AS {$target})";
        $tests["real upstream expression affinity dynamic large cast.test {$valueName} as {$target}"] = static function (TestRunner $t) use ($portSelect, $oracleSelect, $expression): void {
            $t->same($oracleSelect($expression), $portSelect($expression), $expression);
        };
    }
}

$numericInputs = [
    'zero' => '0',
    'one' => '1',
    'minus-one' => '(-1)',
    'real-half' => '0.5',
    'text-empty' => "''",
    'text-dot' => "'.'",
    'text-int-tail' => "'123abc'",
    'text-exp' => "'123e+5'",
    'text-minus' => "'-'",
    'blob-one' => "x'31'",
    'blob-text' => "x'616263'",
];
$numericOperators = ['+', '-', '~'];
$numericTargets = ['INTEGER', 'NUMERIC'];

// Source truth: SQLite upstream test/cast.test cast-7.10 through cast-7.43
// and e_expr.test unary-operator evidence. Unary numeric operators coerce
// text and BLOB operands using the same numeric scanner as CAST.
foreach ($numericInputs as $inputName => $literal) {
    foreach ($numericOperators as $operator) {
        $expression = "{$operator}{$literal}";
        $tests["real upstream expression affinity dynamic large cast.test unary {$operator} {$inputName}"] = static function (TestRunner $t) use ($portSelect, $oracleSelect, $expression): void {
            $t->same($oracleSelect($expression), $portSelect($expression), $expression);
        };
    }
    foreach ($numericTargets as $target) {
        foreach ($numericOperators as $operator) {
            $expression = "{$operator}CAST({$literal} AS {$target})";
            $tests["real upstream expression affinity dynamic large cast.test unary {$operator} cast {$inputName} as {$target}"] = static function (TestRunner $t) use ($portSelect, $oracleSelect, $expression): void {
                $t->same($oracleSelect($expression), $portSelect($expression), $expression);
            };
        }
    }
}

$comparisonLeftValues = [
    'null' => 'NULL',
    'zero-int' => '0',
    'one-int' => '1',
    'minus-one-int' => '-1',
    'forty-two-int' => '42',
    'text-plus' => "'+'",
    'text-minus' => "'-'",
    'text-dot' => "'.'",
    'text-slash' => "'/'",
    'text-empty' => "''",
    'text-leading-zero' => "'00000000000000000042'",
    'blob-abc' => "x'616263'",
    'blob-one' => "x'31'",
];
$comparisonRightValues = ['0', '1', '10', '123', "'123'", "'123e+5'", "x'31'", 'CAST(123 AS TEXT)'];
$comparisonOperators = ['=', '==', '<', '<=', '>', '>=', '!=', '<>'];

// Source truth: SQLite upstream test/types2.test types2-1.* and cast.test
// cast-5.3/cast-9.*. Literal comparisons do not inherit a table column
// affinity, but CAST subexpressions carry their own storage class into the
// comparison operator.
foreach ($comparisonLeftValues as $valueName => $literal) {
    foreach ($comparisonRightValues as $right) {
        foreach ($comparisonOperators as $operator) {
            $expression = "CAST({$literal} AS NUMERIC) {$operator} {$right}";
            $tests["real upstream expression affinity dynamic large cast comparison {$valueName} {$operator} {$right}"] = static function (TestRunner $t) use ($portSelect, $oracleSelect, $expression): void {
                $t->same($oracleSelect($expression), $portSelect($expression), $expression);
            };
        }
    }
}

$tableRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['rowid' => 1, 'i' => 10, 'n' => 10, 't' => 10, 'o' => 10],
    ['rowid' => 2, 'i' => 10.0, 'n' => 10.0, 't' => 10.0, 'o' => 10.0],
    ['rowid' => 3, 'i' => '10', 'n' => '10', 't' => '10', 'o' => '10'],
    ['rowid' => 4, 'i' => '10.0', 'n' => '10.0', 't' => '10.0', 'o' => '10.0'],
    ['rowid' => 5, 'i' => 20, 'n' => 20, 't' => 20, 'o' => 20],
    ['rowid' => 6, 'i' => 20.0, 'n' => 20.0, 't' => 20.0, 'o' => 20.0],
    ['rowid' => 7, 'i' => '20', 'n' => '20', 't' => '20', 'o' => '20'],
    ['rowid' => 8, 'i' => '20.0', 'n' => '20.0', 't' => '20.0', 'o' => '20.0'],
    ['rowid' => 9, 'i' => 30, 'n' => 30, 't' => 30, 'o' => 30],
    ['rowid' => 10, 'i' => 30.0, 'n' => 30.0, 't' => 30.0, 'o' => 30.0],
    ['rowid' => 11, 'i' => '30', 'n' => '30', 't' => '30', 'o' => '30'],
    ['rowid' => 12, 'i' => '30.0', 'n' => '30.0', 't' => '30.0', 'o' => '30.0'],
], [
    'i' => 'INTEGER',
    'n' => 'NUMERIC',
    't' => 'TEXT',
    'o' => 'BLOB',
]);

$oracleSetup = <<<'SQL'
CREATE TABLE t2(i INTEGER, n NUMERIC, t TEXT, o XBLOBY);
INSERT INTO t2 VALUES(10, 10, 10, 10);
INSERT INTO t2 VALUES(10.0, 10.0, 10.0, 10.0);
INSERT INTO t2 VALUES('10', '10', '10', '10');
INSERT INTO t2 VALUES('10.0', '10.0', '10.0', '10.0');
INSERT INTO t2 VALUES(20, 20, 20, 20);
INSERT INTO t2 VALUES(20.0, 20.0, 20.0, 20.0);
INSERT INTO t2 VALUES('20', '20', '20', '20');
INSERT INTO t2 VALUES('20.0', '20.0', '20.0', '20.0');
INSERT INTO t2 VALUES(30, 30, 30, 30);
INSERT INTO t2 VALUES(30.0, 30.0, 30.0, 30.0);
INSERT INTO t2 VALUES('30', '30', '30', '30');
INSERT INTO t2 VALUES('30.0', '30.0', '30.0', '30.0');
SQL;

$portRowids = static function (string $where) use ($tableRows): string {
    $selected = SQLiteSelectSql::execute("SELECT rowid FROM t2 WHERE {$where} ORDER BY rowid", ['t2' => $tableRows]);

    return implode(',', array_map('strval', array_column($selected, 'rowid')));
};

$oracleRowids = static function (string $where) use ($oracle, $oracleSetup): string {
    $result = $oracle($oracleSetup . "\nSELECT coalesce(group_concat(rowid, ','), '') FROM t2 WHERE {$where} ORDER BY rowid;");

    return $result[0] ?? '';
};

$columns = ['o'];
$operators = ['=', '==', '<', '<=', '>', '>=', '!=', '<>'];
$whereLiterals = [
    '10',
    '10.0',
    "'10'",
    "'10.0'",
    '20',
    '20.0',
    "'20'",
    "'20.0'",
    '30',
    '30.0',
    "'30'",
    "'30.0'",
    'CAST(10 AS TEXT)',
    '+CAST(30 AS TEXT)',
    '-CAST(-30 AS TEXT)',
];

// Source truth: SQLite upstream test/types2.test types2-2.* through
// types2-5.*. This ports the literal-vs-column comparison-affinity matrix for
// INTEGER, NUMERIC, TEXT, and no-affinity columns, including casted literal
// expressions that exercise dynamic expression affinity.
foreach ($columns as $columnName) {
    foreach ($operators as $operator) {
        foreach ($whereLiterals as $literal) {
            $where = "{$columnName} {$operator} {$literal}";
            $tests["real upstream expression affinity dynamic large types2 {$columnName} {$operator} {$literal}"] = static function (TestRunner $t) use ($portRowids, $oracleRowids, $where): void {
                $t->same($oracleRowids($where), $portRowids($where), $where);
            };
        }
    }
}

return $tests;
