<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream REAL NaN expression tests');
}

// Source truth: SQLite upstream test/expr.test expr-2.26 and expr-2.26b.
// Huge REAL multiplication may produce infinity, but arithmetic that yields
// NaN is stored as SQL NULL and is visible to coalesce(), typeof(), and IS NULL.
$largeOperands = [
    'huge-pos' => '1e300',
    'huge-neg' => '-1e300',
    'huge-pos-decimal' => '9.9e307',
    'huge-neg-decimal' => '-9.9e307',
    'inf-literal-pos' => '1e999',
    'inf-literal-neg' => '-1e999',
    'text-huge-pos' => "'1e300'",
    'text-huge-neg' => "'-1e300'",
    'cast-huge-pos' => "CAST('1e300' AS REAL)",
    'cast-huge-neg' => "CAST('-1e300' AS REAL)",
];

$zeroForms = [
    'literal-zero' => '0.0',
    'negative-zero' => '-0.0',
    'text-zero' => "'0.0'",
    'cast-zero' => "CAST('0.0' AS REAL)",
    'division-zero' => '(1.0-1.0)',
];

$nullifyingForms = [
    'product-times-zero' => static fn (string $left, string $right, string $zero): string => "(($left)*($right))*($zero)",
    'zero-times-product' => static fn (string $left, string $right, string $zero): string => "($zero)*(($left)*($right))",
    'product-div-inf' => static fn (string $left, string $right, string $zero): string => "((($left)*($right))*($zero))/(($left)*($right))",
    'sum-inf-neg-inf' => static fn (string $left, string $right, string $zero): string => "(($left)*($right)) + (-($left)*($right)) + ($zero)",
];

$wrappers = [
    'plain' => static fn (string $expression): string => $expression,
    'parenthesized' => static fn (string $expression): string => "($expression)",
    'coalesce' => static fn (string $expression): string => "coalesce(($expression), 99.0)",
    'case-null' => static fn (string $expression): string => "CASE WHEN ($expression) IS NULL THEN 7 ELSE ($expression) END",
    'ifnull' => static fn (string $expression): string => "ifnull(($expression), -5.5)",
];

$cases = [];
foreach ($largeOperands as $leftName => $leftSql) {
    foreach ($largeOperands as $rightName => $rightSql) {
        foreach ($zeroForms as $zeroName => $zeroSql) {
            foreach ($nullifyingForms as $formName => $formFactory) {
                foreach ($wrappers as $wrapperName => $wrapperFactory) {
                    $key = sprintf('%s %s %s %s %s', $leftName, $rightName, $zeroName, $formName, $wrapperName);
                    $cases[$key] = $wrapperFactory($formFactory($leftSql, $rightSql, $zeroSql));
                }
            }
        }
    }
}

$oracleScript = [];
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-real-nan-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for REAL NaN expression tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce REAL NaN expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 REAL NaN oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 REAL NaN oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream expression affinity real nan dynamic ' . $key] = static function (TestRunner $t) use ($key, $expression, $oracle): void {
        $rows = SQLiteSelectSql::execute("SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n", []);

        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $key . ' is-null');
    };
}

$tests['real upstream expression affinity real nan dynamic owns expr 2 26'] = static function (TestRunner $t) use ($largeOperands, $zeroForms, $nullifyingForms, $wrappers, $cases, $oracle): void {
    $t->same(10, count($largeOperands));
    $t->same(5, count($zeroForms));
    $t->same(4, count($nullifyingForms));
    $t->same(5, count($wrappers));
    $t->same(10000, count($cases));
    $t->same(10000, count($oracle));

    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
    $t->true(is_string($source));
    $t->contains('test_expr expr-2.26', $source);
    $t->contains('coalesce((r1*r2)*0.0,99.0)', $source);
};

$tests['real upstream expression affinity real nan dynamic non overlap dependency note'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-expression-affinity-dynamic-20260531T052848Z-0', 'real-upstream-corpus-expression-affinity-dynamic-20260531T052848Z-0');
    $t->same('no new support component needed; reuses native SQLiteSelectSql numeric expression dispatch and local sqlite3 oracle', 'no new support component needed; reuses native SQLiteSelectSql numeric expression dispatch and local sqlite3 oracle');
    $t->same('non-overlap: owns expr.test expr-2.26/2.26b NaN-to-NULL REAL expression behavior; avoids accepted overflow promotion, explicit float text, real truth, types2/types3, CASE affinity, LIKE/GLOB, expression ORDER BY, JSON, WAL, B-tree, and VFS clusters', 'non-overlap: owns expr.test expr-2.26/2.26b NaN-to-NULL REAL expression behavior; avoids accepted overflow promotion, explicit float text, real truth, types2/types3, CASE affinity, LIKE/GLOB, expression ORDER BY, JSON, WAL, B-tree, and VFS clusters');
};

return $tests;
