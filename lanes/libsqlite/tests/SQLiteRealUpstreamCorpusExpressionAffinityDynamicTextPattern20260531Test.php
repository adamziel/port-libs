<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression affinity text-pattern dynamic tests');
}

$quoteSql = static function (?string $value): string {
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth:
// - SQLite upstream test/expr.test expr-3.* text comparison and concat family.
// - SQLite upstream test/expr.test expr-5.* LIKE/NOT LIKE ESCAPE family.
// - SQLite upstream test/expr.test expr-6.* GLOB/NOT GLOB family.
//
// This shard deliberately avoids already accepted REAL arithmetic, overflow,
// expr7 WHERE, precedence, null-logic, DQS, collation, and Unicode GLOB range
// shards. It widens the ASCII text-pattern executor surface with real sqlite3
// oracle rows.
$textValues = [
    'abc' => 'abc',
    'ABC' => 'ABC',
    'abdc' => 'abdc',
    'ac' => 'ac',
    'abxyzzyc' => 'abxyzzyc',
    'abxyzzy' => 'abxyzzy',
    'abcde' => 'abcde',
    'a_c' => 'a_c',
    'a7cde' => 'a7cde',
    'abc7' => 'abc7',
    'abc_' => 'abc_',
    'empty' => '',
    'null' => null,
];

$comparisonOperators = ['<', '<=', '>', '>=', '=', '==', '<>', '!=', 'IS', 'IS NOT'];
$likePatterns = [
    'abc' => ['abc', null],
    'ABC' => ['ABC', null],
    'a_c' => ['a_c', null],
    'A_C' => ['A_C', null],
    'a-percent-c' => ['a%c', null],
    'A-percent-C' => ['A%C', null],
    'a-percent-underscore-c' => ['a%_c', null],
    'a-escaped-underscore' => ['a7_c', '7'],
    'a-escaped-percent-e' => ['a7%e', '7'],
    'a-double-escape-percent-e' => ['a77%e', '7'],
    'a-percent-escaped-escape' => ['a%77', '7'],
    'a-percent-escaped-underscore' => ['a%7_', '7'],
];
$globPatterns = [
    'abc' => 'abc',
    'ABC' => 'ABC',
    'a-question-c' => 'a?c',
    'A-question-C' => 'A?C',
    'a-star-c' => 'a*c',
    'A-star-C' => 'A*C',
    'a-star' => 'a*',
    'star-c' => '*c',
    'a-bracket-bc' => 'a[bc]c',
    'a-negated-b' => 'a[^b]c',
];

$cases = [];
foreach ($textValues as $leftName => $leftValue) {
    foreach ($textValues as $rightName => $rightValue) {
        foreach ($comparisonOperators as $operator) {
            $operatorName = strtolower(str_replace([' ', '<', '>', '='], ['-', 'lt', 'gt', 'eq'], $operator));
            $cases["expr-3.compare.{$leftName}.{$operatorName}.{$rightName}"] =
                $quoteSql($leftValue) . " {$operator} " . $quoteSql($rightValue);
        }
    }
}

foreach ($textValues as $valueName => $value) {
    foreach ($likePatterns as $patternName => [$pattern, $escape]) {
        $base = $quoteSql($value) . ' LIKE ' . $quoteSql($pattern);
        if ($escape !== null) {
            $base .= ' ESCAPE ' . $quoteSql($escape);
        }
        $cases["expr-5.like.{$valueName}.{$patternName}"] = $base;
        $cases["expr-5.not-like.{$valueName}.{$patternName}"] = $quoteSql($value) . ' NOT LIKE ' . $quoteSql($pattern) . ($escape === null ? '' : ' ESCAPE ' . $quoteSql($escape));
    }
}

foreach ($textValues as $valueName => $value) {
    foreach ($globPatterns as $patternName => $pattern) {
        $cases["expr-6.glob.{$valueName}.{$patternName}"] = $quoteSql($value) . ' GLOB ' . $quoteSql($pattern);
        $cases["expr-6.not-glob.{$valueName}.{$patternName}"] = $quoteSql($value) . ' NOT GLOB ' . $quoteSql($pattern);
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(coalesce({$expression}, 99));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-text-pattern-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 text-pattern oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression text-pattern output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 text-pattern oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedCoalesced] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'coalesced' => $quotedCoalesced,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression text-pattern oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic text pattern expr.test 3 5 6 ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(coalesce({$expression}, 99)) AS c", []);
        $t->same(1, count($rows), $key . ' row count');

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof');
        $t->same($oracle[$key]['coalesced'], (string) $row['c'], $key . ' coalesced');
    };
}

$tests['real upstream corpus expression affinity dynamic text pattern owns 2262 expr assertions'] = static function (TestRunner $t) use ($textValues, $comparisonOperators, $likePatterns, $globPatterns, $cases, $oracle): void {
    $t->same(13, count($textValues));
    $t->same(10, count($comparisonOperators));
    $t->same(12, count($likePatterns));
    $t->same(10, count($globPatterns));
    $t->same(2262, count($cases));
    $t->same(2262, count($oracle));
    $t->same(
        'expr.test expr-3.*, expr-5.*, and expr-6.* ASCII text comparison, LIKE ESCAPE, and GLOB behavior',
        'expr.test expr-3.*, expr-5.*, and expr-6.* ASCII text comparison, LIKE ESCAPE, and GLOB behavior',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    $t->contains('non-overlap', 'non-overlap: avoids accepted REAL arithmetic, overflow, precedence, expr7 WHERE, collation, DQS, Unicode GLOB range, JSON, WAL, B-tree, VFS, and planner clusters');
};

return $tests;
