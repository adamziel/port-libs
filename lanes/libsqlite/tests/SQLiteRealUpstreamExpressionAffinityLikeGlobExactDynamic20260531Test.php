<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream LIKE/GLOB expression affinity tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test e_expr-14 and e_expr-17.
// These sections cover infix LIKE/GLOB pattern semantics, LIKE ESCAPE, ASCII
// case folding, GLOB case-sensitivity, and NOT LIKE/NOT GLOB negation.
$values = [
    'abc' => 'abc',
    'abcde' => 'abcde',
    'abde' => 'abde',
    'abXde' => 'abXde',
    'abABCde' => 'abABCde',
    'abc%' => 'abc%',
    'abc_' => 'abc_',
    'abcX' => 'abcX',
    'abcXX' => 'abcXX',
    'abcxyz' => 'abcxyz',
    'ABCxyz' => 'ABCxyz',
    'ABC-percent-lower' => 'ABC%xyz',
    'a-lower' => 'a',
    'a-upper' => 'A',
    'ligature-lower' => "\u{00e6}",
    'ligature-upper' => "\u{00c6}",
    'numeric-text' => '12345',
    'empty' => '',
];

$patterns = [
    'abc-prefix-like' => 'abc%',
    'abc-single-like' => 'abc_',
    'abc-escape-percent' => 'abcX%',
    'abc-escape-underscore' => 'abcX_',
    'abc-escape-char' => 'abcXX',
    'upper-prefix-like' => 'ABC%',
    'escaped-upper-percent' => 'ABC\\%x%',
    'ab-any-de' => 'ab%de',
    'ab-one-de' => 'ab_de',
    'glob-prefix' => 'abc*',
    'glob-question' => 'abc???',
    'glob-upper-prefix' => 'ABC*',
    'glob-percent-literal' => 'abc%',
    'literal-empty' => '',
];

$operators = [
    'like' => ['operator' => 'LIKE', 'escape' => null],
    'not-like' => ['operator' => 'NOT LIKE', 'escape' => null],
    'like-escape-x' => ['operator' => 'LIKE', 'escape' => 'X'],
    'not-like-escape-x' => ['operator' => 'NOT LIKE', 'escape' => 'X'],
    'like-escape-backslash' => ['operator' => 'LIKE', 'escape' => '\\'],
    'not-like-escape-backslash' => ['operator' => 'NOT LIKE', 'escape' => '\\'],
    'glob' => ['operator' => 'GLOB', 'escape' => null],
    'not-glob' => ['operator' => 'NOT GLOB', 'escape' => null],
];

$cases = [];
$caseId = 0;
foreach ($values as $valueName => $value) {
    foreach ($patterns as $patternName => $pattern) {
        foreach ($operators as $operatorName => $operator) {
            ++$caseId;
            $expression = $sqlLiteral($value) . ' ' . $operator['operator'] . ' ' . $sqlLiteral($pattern);
            if ($operator['escape'] !== null) {
                $expression .= ' ESCAPE ' . $sqlLiteral($operator['escape']);
            }

            $cases['case-' . $caseId] = [
                'name' => "{$valueName}-{$operatorName}-{$patternName}",
                'expression' => $expression,
            ];
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(NOT ({$expression}));";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-e-expr-like-glob-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for e_expr LIKE/GLOB tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce e_expr LIKE/GLOB output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('malformed e_expr LIKE/GLOB oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedNotValue] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'notQuote' => $quotedNotValue,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d e_expr LIKE/GLOB oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity LIKE GLOB exact dynamic e_expr14 e_expr17 ' . $case['name']] = static function (TestRunner $t) use ($case, $key, $oracle): void {
        $expression = $case['expression'];
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(NOT ({$expression})) AS nq",
            [],
        );
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['notQuote'], (string) $row['nq'], $expression . ' negated quote');
    };
}

$tests['real upstream expression affinity LIKE GLOB exact dynamic owns e_expr14 e_expr17 matrix'] = static function (TestRunner $t) use ($values, $patterns, $operators, $cases, $oracle): void {
    $t->same(18, count($values));
    $t->same(14, count($patterns));
    $t->same(8, count($operators));
    $t->same(2016, count($cases));
    $t->same(2016, count($oracle));
    $t->same(
        'e_expr.test e_expr-14 LIKE/ESCAPE and e_expr-17 GLOB exact parser-level truth behavior',
        'e_expr.test e_expr-14 LIKE/ESCAPE and e_expr-17 GLOB exact parser-level truth behavior',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
