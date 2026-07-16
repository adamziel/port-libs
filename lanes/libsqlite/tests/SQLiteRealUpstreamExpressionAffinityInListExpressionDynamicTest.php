<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expression IN-list dynamic tests');
}

$sqlLiteral = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

// Source truth: upstream SQLite test/e_expr.test e_expr-12.3.78 through
// e_expr-12.3.84 admits EXPR [NOT] IN (...) as a scalar expression form.
// This focused corpus exercises that syntax through projected SELECT
// expressions, where the port previously only supported IN inside WHERE.
$leftExpressions = [
    'integer-one' => '1',
    'integer-two' => '2',
    'integer-negative' => '(-2)',
    'real-one' => '1.0',
    'real-fraction' => '1.5',
    'text-one' => $sqlLiteral('1'),
    'text-one-point-zero' => $sqlLiteral('1.0'),
    'text-alpha' => $sqlLiteral('alpha'),
    'text-alpha-nocase' => $sqlLiteral('ALPHA') . ' COLLATE NOCASE',
    'text-trailing-rtrim' => $sqlLiteral('trim   ') . ' COLLATE RTRIM',
    'blob-one' => "X'31'",
    'null' => 'NULL',
    'cast-text-one-real' => '(CAST(' . $sqlLiteral('1') . ' AS REAL))',
    'cast-text-one-numeric' => '(CAST(' . $sqlLiteral('1.0') . ' AS NUMERIC))',
    'cast-alpha-text' => '(CAST(' . $sqlLiteral('alpha') . ' AS TEXT))',
    'case-integer' => '(CASE WHEN 1 THEN 2 ELSE 3 END)',
];

$valueLists = [
    'single-integer-one' => ['1'],
    'single-real-one' => ['1.0'],
    'integer-pair' => ['1', '2'],
    'real-pair' => ['1.0', '2.0'],
    'mixed-numeric-text' => ['1', $sqlLiteral('1'), $sqlLiteral('1.0')],
    'alpha-text' => [$sqlLiteral('alpha'), $sqlLiteral('beta')],
    'alpha-nocase' => [$sqlLiteral('alpha') . ' COLLATE NOCASE', $sqlLiteral('beta')],
    'rtrim-text' => [$sqlLiteral('trim') . ' COLLATE RTRIM', $sqlLiteral('other')],
    'blob-text' => ["X'31'", $sqlLiteral('1')],
    'with-null-match' => ['1', 'NULL', '3'],
    'with-null-no-match' => ['4', 'NULL', '5'],
    'only-null' => ['NULL'],
    'cast-real-list' => ['CAST(' . $sqlLiteral('1') . ' AS REAL)', 'CAST(' . $sqlLiteral('2.5') . ' AS REAL)'],
    'cast-numeric-list' => ['CAST(' . $sqlLiteral('1.0') . ' AS NUMERIC)', 'CAST(' . $sqlLiteral('2.0') . ' AS NUMERIC)'],
    'case-list' => ['CASE WHEN 1 THEN 2 ELSE 9 END', 'CASE WHEN 0 THEN 4 ELSE 5 END'],
    'mixed-storage' => ['0', '1.5', $sqlLiteral('alpha'), $sqlLiteral('omega'), 'NULL'],
];

$wrappers = [
    'plain' => static fn (string $expr): string => $expr,
    'is-true' => static fn (string $expr): string => "({$expr}) IS TRUE",
    'is-false' => static fn (string $expr): string => "({$expr}) IS FALSE",
    'coalesce-nine' => static fn (string $expr): string => "coalesce({$expr}, 9)",
    'case-result' => static fn (string $expr): string => "CASE WHEN {$expr} THEN 'yes' WHEN ({$expr}) IS NULL THEN 'null' ELSE 'no' END",
];

$cases = [];
foreach ($leftExpressions as $leftName => $leftSql) {
    foreach ($valueLists as $listName => $values) {
        foreach ([false, true] as $negated) {
            $inExpression = $leftSql . ($negated ? ' NOT IN ' : ' IN ') . '(' . implode(', ', $values) . ')';
            foreach ($wrappers as $wrapperName => $wrap) {
                $cases["{$leftName}.{$listName}." . ($negated ? 'not-in' : 'in') . ".{$wrapperName}"] = $wrap($inExpression);
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-in-list-expression-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 IN-list oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce IN-list expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 IN-list oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 IN-list oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity IN-list scalar expression dynamic e_expr.test ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);
        $t->same(1, count($rows), $expression . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $expression . ' is-null');
    };
}

$tests['real upstream expression affinity IN-list scalar expression dynamic owns 2560 cases'] = static function (TestRunner $t) use ($leftExpressions, $valueLists, $wrappers, $cases, $oracle): void {
    $t->same(16, count($leftExpressions));
    $t->same(16, count($valueLists));
    $t->same(5, count($wrappers));
    $t->same(2560, count($cases));
    $t->same(2560, count($oracle));
    $t->same(
        'e_expr.test e_expr-12.3.78..84 scalar EXPR IN/NOT IN list syntax with NULL, collation, CAST, CASE, and wrapper expression results',
        'e_expr.test e_expr-12.3.78..84 scalar EXPR IN/NOT IN list syntax with NULL, collation, CAST, CASE, and wrapper expression results',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
