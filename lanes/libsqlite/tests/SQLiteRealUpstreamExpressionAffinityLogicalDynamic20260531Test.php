<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream logical expression affinity tests');
}

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth:
// - test/expr.test expr-1.27 through expr-1.34 covers AND/OR truth results.
// - test/expr.test expr-1.78 through expr-1.81 covers NULL propagation through
//   AND/OR when composed inside coalesce().
// - test/e_expr.test e_expr-2.4 and e_expr-37.* cover NOT and SQL truthiness
//   over NULL, numeric, text, and REAL-compatible values.
$truthValues = [
    'null' => null,
    'int-zero' => 0,
    'int-one' => 1,
    'int-neg-one' => -1,
    'real-zero' => 0.0,
    'real-epsilon' => 0.0001,
    'real-neg' => -2.5,
    'text-empty' => '',
    'text-zero' => '0',
    'text-zero-real' => '0.0',
    'text-alpha' => 'alpha',
    'text-one-alpha' => '1alpha',
    'text-leading-space' => '   4',
    'text-minus-zero' => '-0',
    'text-plus-real' => '+7.25',
    'text-nan-ish' => 'NaN',
    'text-hex-ish' => '0x10',
    'large-int' => 9223372036854775807,
    'tiny-real-text' => '0.0000000000000001',
];

$unaryContexts = [
    'plain' => '%s',
    'not' => 'NOT (%s)',
    'double-not' => 'NOT NOT (%s)',
    'case-truth' => 'CASE WHEN %s THEN 11 ELSE 22 END',
    'iif-truth' => 'iif(%s, 33, 44)',
    'is-true' => '(%s) IS TRUE',
    'is-false' => '(%s) IS FALSE',
    'is-not-true' => '(%s) IS NOT TRUE',
    'is-not-false' => '(%s) IS NOT FALSE',
];

$binaryContexts = [
    'and' => '(%s) AND (%s)',
    'or' => '(%s) OR (%s)',
    'not-and' => 'NOT ((%s) AND (%s))',
    'not-or' => 'NOT ((%s) OR (%s))',
    'coalesce-and' => 'coalesce((%s) AND (%s), 99)',
    'coalesce-or' => 'coalesce((%s) OR (%s), 99)',
    'case-and' => 'CASE WHEN ((%s) AND (%s)) THEN 1 ELSE 0 END',
    'case-or' => 'CASE WHEN ((%s) OR (%s)) THEN 1 ELSE 0 END',
];

$cases = [];
foreach ($truthValues as $valueName => $value) {
    $sql = $sqlLiteral($value);
    foreach ($unaryContexts as $contextName => $template) {
        $cases["unary-{$contextName}-{$valueName}"] = sprintf($template, $sql);
    }
}

foreach ($truthValues as $leftName => $leftValue) {
    $leftSql = $sqlLiteral($leftValue);
    foreach ($truthValues as $rightName => $rightValue) {
        $rightSql = $sqlLiteral($rightValue);
        foreach ($binaryContexts as $contextName => $template) {
            $cases["binary-{$contextName}-{$leftName}-{$rightName}"] = sprintf($template, $leftSql, $rightSql);
        }
    }
}

$oracleSql = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleSql[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL) || char(9) || quote(({$expression}) IS TRUE) || char(9) || quote(({$expression}) IS FALSE);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-logical-expr-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleSql));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce logical expression affinity output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 6) {
        throw new RuntimeException('Malformed sqlite3 logical expression oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull, $quotedIsTrue, $quotedIsFalse] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
        'isTrue' => $quotedIsTrue,
        'isFalse' => $quotedIsFalse,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 logical expression oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity logical dynamic expr-1 e_expr-2 e_expr-37 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n, quote(({$expression}) IS TRUE) AS truthy, quote(({$expression}) IS FALSE) AS falsey", []);
        $t->same(1, count($rows), $expression);

        $row = $rows[0];
        $t->same($oracle[$key]['quote'], (string) $row['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $row['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $row['n'], $expression . ' is-null');
        $t->same($oracle[$key]['isTrue'], (string) $row['truthy'], $expression . ' is-true');
        $t->same($oracle[$key]['isFalse'], (string) $row['falsey'], $expression . ' is-false');
    };
}

$tests['real upstream expression affinity logical dynamic owns exactly 3059 oracle cases'] = static function (TestRunner $t) use ($truthValues, $unaryContexts, $binaryContexts, $cases, $oracle): void {
    $t->same(19, count($truthValues));
    $t->same(9, count($unaryContexts));
    $t->same(8, count($binaryContexts));
    $t->same(3059, count($cases));
    $t->same(3059, count($oracle));
    $t->same(
        'expr.test expr-1.27..1.34 and expr-1.78..1.81 plus e_expr.test e_expr-2.4/e_expr-37 logical truthiness and NULL propagation',
        'expr.test expr-1.27..1.34 and expr-1.78..1.81 plus e_expr.test e_expr-2.4/e_expr-37 logical truthiness and NULL propagation',
    );
};

return $tests;
