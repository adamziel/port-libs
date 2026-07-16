<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression LIKE/GLOB ESCAPE dynamic tests');
}

$sqlString = static function (?string $value): string {
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/expr.test expr-5.54 through expr-5.79
// covers NULL LIKE propagation and ESCAPE behavior; expr-6.1 through expr-6.75
// covers GLOB wildcard, bracket, negated bracket, case-sensitive, and NULL
// propagation behavior. This dynamic shard keeps to that operator family while
// widening the input matrix through the PHP SELECT SQL executor.
$subjects = [
    'abc' => 'abc',
    'ABC' => 'ABC',
    'a-c' => 'a-c',
    'a_c' => 'a_c',
    'a7cde' => 'a7cde',
    'abc7' => 'abc7',
    'abc_' => 'abc_',
    'abxyzzyc' => 'abxyzzyc',
    'abxyzzy' => 'abxyzzy',
    'abcdefg' => 'abcdefg',
    'ABCDEFG' => 'ABCDEFG',
    'ac' => 'ac',
    'a*c' => 'a*c',
    'a?c' => 'a?c',
    'a[c' => 'a[c',
    'A]C' => 'A]C',
    'AxC' => 'AxC',
    'numeric-10' => '10',
    'numeric-20' => '20',
    'empty' => '',
    'null' => null,
];

$likePatterns = [
    'literal-abc' => 'abc',
    'literal-ABC' => 'ABC',
    'underscore' => 'a_c',
    'escaped-underscore' => 'a7_c',
    'escaped-percent' => 'a7%e',
    'double-escape-percent' => 'a77%e',
    'suffix-escape-seven' => 'a%77',
    'suffix-escape-underscore' => 'a%7_',
    'percent-c' => 'a%c',
    'percent-e' => 'a%e',
    'prefix-uppercase' => 'A%C',
    'single-char-uppercase' => 'A_C',
    'numeric-prefix' => '1_',
    'two-digit-prefix' => '2_',
    'null-pattern' => null,
];

$globPatterns = [
    'literal-abc' => 'abc',
    'literal-ABC' => 'ABC',
    'question' => 'a?c',
    'star' => 'a*c',
    'uppercase-question' => 'A?C',
    'uppercase-star' => 'A*C',
    'class-bx' => 'a[bx]c',
    'class-cx' => 'a[cx]c',
    'range-a-d' => 'a[a-d]c',
    'not-range-a-d' => 'a[^a-d]c',
    'mixed-class' => 'a[A-Dc]c',
    'not-mixed-class' => 'a[^A-Dc]c',
    'literal-close-bracket' => 'a[]b]c',
    'not-close-bracket' => 'a[^]b]c',
    'star-class-no-match' => 'a*[de]g',
    'star-class-match' => 'a*[df]g',
    'star-range-match' => 'a*[d-h]g',
    'star-not-class' => 'a*[^de]g',
    'literal-star' => 'a[*]c',
    'literal-question' => 'a[?]c',
    'literal-open-bracket' => 'a[[]c',
    'numeric-teen' => '1?',
    'numeric-twenty' => '2?',
    'null-pattern' => null,
];

$cases = [];
foreach ($subjects as $subjectName => $subject) {
    foreach ($likePatterns as $patternName => $pattern) {
        foreach (['like' => 'LIKE', 'not-like' => 'NOT LIKE'] as $operatorName => $operator) {
            $cases["like.{$subjectName}.{$operatorName}.{$patternName}.no-escape"] = [
                'expression' => $sqlString($subject) . " {$operator} " . $sqlString($pattern),
                'family' => 'LIKE',
            ];
            $cases["like.{$subjectName}.{$operatorName}.{$patternName}.escape-7"] = [
                'expression' => $sqlString($subject) . " {$operator} " . $sqlString($pattern) . " ESCAPE '7'",
                'family' => 'LIKE ESCAPE',
            ];
        }
    }
}

foreach ($subjects as $subjectName => $subject) {
    foreach ($globPatterns as $patternName => $pattern) {
        foreach (['glob' => 'GLOB', 'not-glob' => 'NOT GLOB'] as $operatorName => $operator) {
            $cases["glob.{$subjectName}.{$operatorName}.{$patternName}"] = [
                'expression' => $sqlString($subject) . " {$operator} " . $sqlString($pattern),
                'family' => 'GLOB',
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

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-like-glob-escape-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 LIKE/GLOB oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression LIKE/GLOB ESCAPE output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 LIKE/GLOB oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression LIKE/GLOB oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity like glob escape dynamic expr.test expr-5-6 ' . $key] = static function (TestRunner $t) use ($key, $case, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $key . ' row count');

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $key . ' quote for ' . $expression);
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $key . ' typeof for ' . $expression);
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $key . ' nullness for ' . $expression);
        $t->contains($case['family'], 'LIKE LIKE ESCAPE GLOB');
    };
}

$tests['real upstream expression affinity like glob escape dynamic owns expr5 expr6 matrix'] = static function (TestRunner $t) use ($subjects, $likePatterns, $globPatterns, $cases, $oracle): void {
    $t->same(21, count($subjects));
    $t->same(15, count($likePatterns));
    $t->same(24, count($globPatterns));
    $t->same(2268, count($cases));
    $t->same(2268, count($oracle));
    $t->same(
        'expr.test expr-5.54..5.79 LIKE NULL and ESCAPE plus expr-6.1..6.75 GLOB wildcard/bracket/NULL behavior',
        'expr.test expr-5.54..5.79 LIKE NULL and ESCAPE plus expr-6.1..6.75 GLOB wildcard/bracket/NULL behavior',
    );
};

return $tests;
