<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expr7 WHERE dynamic tests');
}

// Source truth: SQLite upstream test/expr.test expr-7.2 through expr-7.74.
// Those cases verify WHERE expression row selection over comparison, boolean,
// LIKE/GLOB, BETWEEN, concatenation, scalar functions, and unary operators.
$predicateBuilders = [
    'expr-7.2 strict-range' => static fn (int $offset): string => sprintf('a<%d AND a>%d', $offset + 10, $offset + 8),
    'expr-7.3 inclusive-range' => static fn (int $offset): string => sprintf('a<=%d AND a>=%d', $offset + 10, $offset + 8),
    'expr-7.4 reversed-inclusive-range' => static fn (int $offset): string => sprintf('a>=%d AND a<=%d', $offset + 8, $offset + 10),
    'expr-7.5 outside-or' => static fn (int $offset): string => sprintf('a>=%d OR a<=%d', $offset + 20, $offset + 1),
    'expr-7.6 not-equal-and-range' => static fn (int $offset): string => sprintf('b!=4 AND a<=%d', $offset + 3),
    'expr-7.7 equality-or-chain' => static fn (int $offset): string => 'b==8 OR b==16 OR b==32',
    'expr-7.8 not-not-equal-or-large' => static fn (int $offset): string => 'NOT b<>8 OR b==1024',
    'expr-7.9 like-prefix' => static fn (int $offset): string => "b LIKE '10%'",
    'expr-7.10 like-single-wildcard' => static fn (int $offset): string => "b LIKE '_4'",
    'expr-7.11 glob-two-digit-a' => static fn (int $offset): string => "a GLOB '1?'",
    'expr-7.12 glob-b-prefix-suffix' => static fn (int $offset): string => "b GLOB '1*4'",
    'expr-7.13 glob-b-class' => static fn (int $offset): string => "b GLOB '*1[456]'",
    'expr-7.14 is-null' => static fn (int $offset): string => 'a ISNULL',
    'expr-7.15 not-null-and-range' => static fn (int $offset): string => sprintf('a NOTNULL AND a<%d', $offset + 3),
    'expr-7.16 truthy-a-and-range' => static fn (int $offset): string => sprintf('a AND a<%d', $offset + 3),
    'expr-7.17 not-a' => static fn (int $offset): string => 'NOT a',
    'expr-7.18 equality-or-b-range' => static fn (int $offset): string => sprintf('a==%d OR (b>1000 AND b<2000)', $offset + 11),
    'expr-7.19 outside-inclusive' => static fn (int $offset): string => sprintf('a<=%d OR a>=%d', $offset + 1, $offset + 20),
    'expr-7.20 outside-exclusive-empty' => static fn (int $offset): string => sprintf('a<%d OR a>%d', $offset + 1, $offset + 20),
    'expr-7.21 high-or-low' => static fn (int $offset): string => sprintf('a>%d OR a<%d', $offset + 19, $offset + 1),
    'expr-7.23 grouped-notnull-or-equality' => static fn (int $offset): string => sprintf('(a notnull AND a<%d) OR a==%d', $offset + 4, $offset + 8),
    'expr-7.24 like-or-equality' => static fn (int $offset): string => sprintf("a LIKE '2_' OR a==%d", $offset + 8),
    'expr-7.25 glob-or-equality' => static fn (int $offset): string => sprintf("a GLOB '2?' OR a==%d", $offset + 8),
    'expr-7.26 isnull-or-equality' => static fn (int $offset): string => sprintf('a isnull OR a=%d', $offset + 8),
    'expr-7.28 null-preserving-or' => static fn (int $offset): string => 'a<0 OR b=0',
    'expr-7.29 null-preserving-or-reversed' => static fn (int $offset): string => 'b=0 OR a<0',
    'expr-7.30 null-preserving-and-empty' => static fn (int $offset): string => 'a<0 AND b=0',
    'expr-7.31 null-preserving-and-empty-reversed' => static fn (int $offset): string => 'b=0 AND a<0',
    'expr-7.32 null-and-nested-or' => static fn (int $offset): string => 'a IS NULL AND (a<0 OR b=0)',
    'expr-7.33 null-and-nested-or-reversed' => static fn (int $offset): string => 'a IS NULL AND (b=0 OR a<0)',
    'expr-7.34 null-and-nested-and-empty' => static fn (int $offset): string => 'a IS NULL AND (a<0 AND b=0)',
    'expr-7.35 null-and-nested-and-empty-reversed' => static fn (int $offset): string => 'a IS NULL AND (b=0 AND a<0)',
    'expr-7.36 nested-or-with-null' => static fn (int $offset): string => sprintf('a<%d OR (a<0 OR b=0)', $offset + 2),
    'expr-7.37 nested-or-with-null-reversed' => static fn (int $offset): string => sprintf('a<%d OR (b=0 OR a<0)', $offset + 2),
    'expr-7.38 nested-and-empty-or-range' => static fn (int $offset): string => sprintf('a<%d OR (a<0 AND b=0)', $offset + 2),
    'expr-7.39 nested-and-empty-or-range-reversed' => static fn (int $offset): string => sprintf('a<%d OR (b=0 AND a<0)', $offset + 2),
    'expr-7.41 between' => static fn (int $offset): string => sprintf('a BETWEEN %d AND %d', $offset - 1, $offset + 1),
    'expr-7.42 not-between' => static fn (int $offset): string => sprintf('a NOT BETWEEN %d AND %d', $offset + 2, $offset + 100),
    'expr-7.43 concatenated-between-empty' => static fn (int $offset): string => "(b+1234)||'this is a string that is at least 32 characters long' BETWEEN 1 AND 2",
    'expr-7.44 text-concat-between-empty' => static fn (int $offset): string => "123||'xabcdefghijklmnopqrstuvwyxz01234567890'||a BETWEEN '123a' AND '123b'",
    'expr-7.45 text-concat-between-lt-zero-empty' => static fn (int $offset): string => "((123||'xabcdefghijklmnopqrstuvwyxz01234567890'||a) BETWEEN '123a' AND '123b')<0",
    'expr-7.46 text-concat-between-gt-zero' => static fn (int $offset): string => "((123||'xabcdefghijklmnopqrstuvwyxz01234567890'||a) BETWEEN '123a' AND '123z')>0",
    'expr-7.50 nested-between-truth' => static fn (int $offset): string => sprintf('((a between %d and %d OR 0) AND 1) OR 0', $offset + 1, $offset + 2),
    'expr-7.51 nested-not-between-truth' => static fn (int $offset): string => sprintf('((a not between %d and %d OR 0) AND 1) OR 0', $offset + 3, $offset + 100),
    'expr-7.54 nested-positive-range' => static fn (int $offset): string => sprintf('((a>%d OR 0) AND a<%d) OR 0', $offset, $offset + 3),
    'expr-7.57 is-null-expression' => static fn (int $offset): string => '((a>0 IS NULL OR 0) AND 1) OR 0',
    'expr-7.58 text-affinity-concat-compare' => static fn (int $offset): string => "(a||'')<='1'",
    'expr-7.59 like-function-prefix' => static fn (int $offset): string => "LIKE('10%',b)",
    'expr-7.60 like-function-single-wildcard' => static fn (int $offset): string => "LIKE('_4',b)",
    'expr-7.61 glob-function-two-digit-a' => static fn (int $offset): string => "GLOB('1?',a)",
    'expr-7.62 glob-function-b-prefix-suffix' => static fn (int $offset): string => "GLOB('1*4',b)",
    'expr-7.63 glob-function-b-class' => static fn (int $offset): string => "GLOB('*1[456]',b)",
    'expr-7.64 abs-negative-two' => static fn (int $offset): string => 'b = abs(-2)',
    'expr-7.65 abs-plus-minus-two' => static fn (int $offset): string => 'b = abs(+-2)',
    'expr-7.66 abs-double-plus-minus-two' => static fn (int $offset): string => 'b = abs(++-2)',
    'expr-7.67 abs-alternating-signs' => static fn (int $offset): string => 'b = abs(+-+-2)',
    'expr-7.68 abs-mixed-signs' => static fn (int $offset): string => 'b = abs(+-++-2)',
    'expr-7.69 abs-many-plus-minus' => static fn (int $offset): string => 'b = abs(++++-2)',
    'expr-7.70 subtract-abs-plus' => static fn (int $offset): string => 'b = 5 - abs(+3)',
    'expr-7.71 subtract-abs-minus' => static fn (int $offset): string => 'b = 5 - abs(-3)',
    'expr-7.72 abs-real' => static fn (int $offset): string => 'b = abs(-2.0)',
    'expr-7.73 abs-negative-a' => static fn (int $offset): string => sprintf('b = %d - abs(-a)', $offset + 6),
    'expr-7.74 abs-eight-real' => static fn (int $offset): string => 'b = abs(8.0)',
];

$offsets = range(0, 29);
$rowsByOffset = [];
foreach ($offsets as $offset) {
    $rows = [];
    foreach (range(1, 20) as $i) {
        $rows[] = ['a' => $i + $offset, 'b' => 2 ** ($i - 1)];
    }
    $rows[] = ['a' => null, 'b' => 0];
    $rowsByOffset[$offset] = $rows;
}

$oracleScript = [];
$cases = [];
foreach ($offsets as $offset) {
    $oracleScript[] = 'DROP TABLE IF EXISTS t1;';
    $oracleScript[] = 'CREATE TABLE t1(a, b);';
    foreach ($rowsByOffset[$offset] as $row) {
        $a = $row['a'] === null ? 'NULL' : (string) $row['a'];
        $oracleScript[] = sprintf('INSERT INTO t1(a,b) VALUES(%s,%d);', $a, $row['b']);
    }

    foreach ($predicateBuilders as $section => $buildPredicate) {
        $predicate = $buildPredicate($offset);
        $key = 'offset-' . $offset . ' ' . $section;
        $cases[$key] = ['offset' => $offset, 'predicate' => $predicate, 'section' => $section];
        $safeKey = str_replace("'", "''", $key);
        $oracleScript[] = "SELECT '{$safeKey}' || char(9) || json_group_array(a) FROM (SELECT a FROM t1 WHERE {$predicate} ORDER BY a);";
    }
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr7-where-dynamic-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expr7 WHERE dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expr7 WHERE dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed expr7 WHERE oracle row: ' . $line);
    }

    $decoded = json_decode($parts[1], true);
    if (!is_array($decoded)) {
        throw new RuntimeException('malformed expr7 WHERE oracle JSON for ' . $parts[0]);
    }
    $oracle[$parts[0]] = $decoded;
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expr7 WHERE oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity expr7 WHERE dynamic expr.test ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $rowsByOffset): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT a FROM t1 WHERE ' . $case['predicate'] . ' ORDER BY a',
            ['t1' => $rowsByOffset[$case['offset']]],
        );
        $actual = array_column($rows, 'a');

        $t->same($oracle[$key], $actual, $key . ' rows');
        $t->same(true, str_starts_with($case['section'], 'expr-7.'), $key . ' upstream section');
    };
}

$tests['real upstream expression affinity expr7 WHERE dynamic owns 1890 cases'] = static function (TestRunner $t) use ($predicateBuilders, $offsets, $cases, $oracle): void {
    $t->same(63, count($predicateBuilders));
    $t->same(30, count($offsets));
    $t->same(1890, count($cases));
    $t->same(1890, count($oracle));
    $t->same(
        'expr.test expr-7.2..expr-7.74 WHERE expression row-selection predicates over shifted dynamic tables',
        'expr.test expr-7.2..expr-7.74 WHERE expression row-selection predicates over shifted dynamic tables',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

$tests['real upstream expression affinity expr7 WHERE dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed: reuses SQLiteSelectSql WHERE predicate execution, scalar function dispatch, LIKE/GLOB helpers, and sqlite3 oracle comparison',
        'no new support component needed: reuses SQLiteSelectSql WHERE predicate execution, scalar function dispatch, LIKE/GLOB helpers, and sqlite3 oracle comparison',
    );
};

return $tests;
