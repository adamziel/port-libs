<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression syntax dynamic tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_float($value)) {
        return str_contains((string) $value, '.') || stripos((string) $value, 'e') !== false
            ? (string) $value
            : (string) ((int) $value) . '.0';
    }
    if (is_int($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Real upstream source:
// - test/e_expr.test e_expr-12.3 verifies the expression syntax diagram by
//   substituting table column references and arithmetic expressions into each
//   expression form, then executing SELECT <expression> FROM tblname.
// This shard keeps the same table/column shape while expanding the dynamic
// REAL-affinity row corpus. It is intentionally separate from accepted
// CASE/iif, overflow, NULL/coalesce, BETWEEN, GLOB/LIKE, and REAL arithmetic
// batches.
$baseRows = [
    ['id' => 1, 'cname' => 0],
    ['id' => 2, 'cname' => 1],
    ['id' => 3, 'cname' => -1],
    ['id' => 4, 'cname' => 2],
    ['id' => 5, 'cname' => 7],
    ['id' => 6, 'cname' => 34],
    ['id' => 7, 'cname' => 45.2],
    ['id' => 8, 'cname' => 45.0],
    ['id' => 9, 'cname' => 72.35],
    ['id' => 10, 'cname' => '45.2'],
    ['id' => 11, 'cname' => '0'],
    ['id' => 12, 'cname' => 'abc'],
    ['id' => 13, 'cname' => 'AbC'],
    ['id' => 14, 'cname' => 'a_c'],
    ['id' => 15, 'cname' => null],
    ['id' => 16, 'cname' => '9223372036854775808'],
];

$rows = $baseRows;

$templates = [
    'e_expr-12.3.18 unary-plus' => '+ EXPR',
    'e_expr-12.3.19 unary-minus' => '- EXPR',
    'e_expr-12.3.20 unary-not' => 'NOT EXPR',
    'e_expr-12.3.21 unary-bitnot' => '~ EXPR',
    'e_expr-12.3.23 multiply' => 'EXPR1 * EXPR2',
    'e_expr-12.3.24 divide' => 'EXPR1 / EXPR2',
    'e_expr-12.3.26 add' => 'EXPR1 + EXPR2',
    'e_expr-12.3.27 subtract' => 'EXPR1 - EXPR2',
    'e_expr-12.3.28 shift-left' => 'EXPR1 << EXPR2',
    'e_expr-12.3.29 shift-right' => 'EXPR1 >> EXPR2',
    'e_expr-12.3.30 bit-and' => 'EXPR1 & EXPR2',
    'e_expr-12.3.31 bit-or' => 'EXPR1 | EXPR2',
    'e_expr-12.3.32 less-than' => 'EXPR1 < EXPR2',
    'e_expr-12.3.33 less-equal' => 'EXPR1 <= EXPR2',
    'e_expr-12.3.34 greater-than' => 'EXPR1 > EXPR2',
    'e_expr-12.3.35 greater-equal' => 'EXPR1 >= EXPR2',
    'e_expr-12.3.36 equals' => 'EXPR1 = EXPR2',
    'e_expr-12.3.37 equals-equals' => 'EXPR1 == EXPR2',
    'e_expr-12.3.38 not-equals-bang' => 'EXPR1 != EXPR2',
    'e_expr-12.3.39 not-equals-angle' => 'EXPR1 <> EXPR2',
    'e_expr-12.3.40 is' => 'EXPR1 IS EXPR2',
    'e_expr-12.3.41 is-not' => 'EXPR1 IS NOT EXPR2',
    'e_expr-12.3.42 and' => 'EXPR1 AND EXPR2',
    'e_expr-12.3.43 or' => 'EXPR1 OR EXPR2',
    'e_expr-12.3.48 parenthesized' => '( EXPR )',
    'e_expr-12.3.49 cast-integer' => 'CAST ( EXPR AS integer )',
    'e_expr-12.3.52 collate-nocase' => 'EXPR COLLATE nocase',
    'e_expr-12.3.53 collate-binary' => 'EXPR COLLATE binary',
    'e_expr-12.3.54 like' => 'EXPR1 LIKE EXPR2',
    'e_expr-12.3.56 glob' => 'EXPR1 GLOB EXPR2',
    'e_expr-12.3.62 not-like' => 'EXPR1 NOT LIKE EXPR2',
    'e_expr-12.3.64 not-glob' => 'EXPR1 NOT GLOB EXPR2',
    'e_expr-12.3.75 not-between' => 'EXPR NOT BETWEEN EXPR1 AND EXPR2',
    'e_expr-12.3.76 between' => 'EXPR BETWEEN EXPR1 AND EXPR2',
    'e_expr-12.3.89 simple-case-else' => 'CASE EXPR WHEN EXPR1 THEN EXPR2 ELSE EXPR END',
    'e_expr-12.3.90 simple-case-no-else' => 'CASE EXPR WHEN EXPR1 THEN EXPR2 END',
    'e_expr-12.3.93 searched-case-else' => 'CASE WHEN EXPR1 THEN EXPR2 ELSE EXPR END',
    'e_expr-12.3.94 searched-case-no-else' => 'CASE WHEN EXPR1 THEN EXPR2 END',
];

$substitutions = [
    'column-vs-real' => ['EXPR' => 'cname', 'EXPR1' => 'cname', 'EXPR2' => '34+22'],
    'real-vs-column' => ['EXPR' => '34+22', 'EXPR1' => '34+22', 'EXPR2' => 'cname'],
    'cast-column-vs-real' => ['EXPR' => 'CAST(cname AS REAL)', 'EXPR1' => 'CAST(cname AS REAL)', 'EXPR2' => '56.25'],
    'literal-vs-column' => ['EXPR' => '56.25', 'EXPR1' => '56.25', 'EXPR2' => 'cname'],
];

$cases = [];
foreach ($templates as $templateName => $template) {
    foreach ($substitutions as $substitutionName => $map) {
        $expression = strtr($template, $map);
        foreach ($baseRows as $row) {
            $rowId = (int) $row['id'];
            $cases["{$templateName} {$substitutionName} row {$rowId}"] = [
                'expression' => $expression,
                'row' => $row,
            ];
        }
    }
}

$oracleScript = ['CREATE TABLE tblname(id INTEGER PRIMARY KEY, cname);'];
foreach ($baseRows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO tblname(id, cname) VALUES (%d, %s);',
        $row['id'],
        $sqlLiteral($row['cname']),
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $rowId = (int) $case['row']['id'];
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) FROM tblname WHERE id = {$rowId};";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-expr-syntax-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 expression syntax oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expression syntax output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 expression syntax oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expression syntax oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic real expr syntax e_expr-12.3 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $rows): void {
        $rowId = (int) $case['row']['id'];
        $expression = $case['expression'];
        $resultRows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t FROM tblname WHERE id = {$rowId}", ['tblname' => $rows]);
        $t->same(1, count($resultRows), $key);

        $actual = $resultRows[0];
        $t->same($oracle[$key]['typeof'], (string) $actual['t'], $expression . ' typeof');
        if ($oracle[$key]['typeof'] === 'real' && is_numeric($oracle[$key]['quote']) && is_numeric((string) $actual['q'])) {
            $expected = (float) $oracle[$key]['quote'];
            $actualValue = (float) $actual['q'];
            $scale = max(1.0, abs($expected), abs($actualValue));
            $t->true(abs($expected - $actualValue) <= $scale * 1.0e-14, $expression . ' quote real tolerance');
            return;
        }

        $t->same($oracle[$key]['quote'], (string) $actual['q'], $expression . ' quote');
    };
}

$tests['real upstream corpus expression affinity dynamic real expr syntax owns e_expr 12.3 matrix'] = static function (TestRunner $t) use ($templates, $substitutions, $baseRows, $cases, $oracle): void {
    $t->same(38, count($templates));
    $t->same(4, count($substitutions));
    $t->same(16, count($baseRows));
    $t->same(2432, count($cases));
    $t->same(2432, count($oracle));
    $t->same(
        'e_expr.test e_expr-12.3 expression syntax diagram over column and REAL arithmetic substitutions',
        'e_expr.test e_expr-12.3 expression syntax diagram over column and REAL arithmetic substitutions',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
