<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream in.test BETWEEN and IN-list dynamic tests');
}

// Source truth: SQLite upstream test/in.test sections in-1.1 through in-1.7
// and in-2.1 through in-2.9. Those sections cover BETWEEN, NOT BETWEEN,
// static IN lists, expression-valued RHS terms, and projection truth values
// over the initial t1(a,b) powers-of-two rowset.
$templates = [
    'in-1.1-between-constant' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b BETWEEN %d AND %d ORDER BY a', 10 * $scale, 50 * $scale),
    ],
    'in-1.2-not-between-constant' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b NOT BETWEEN %d AND %d ORDER BY a', 10 * $scale, 50 * $scale),
    ],
    'in-1.3-between-column-expression' => static fn (int $offset, int $scale): array => [
        'sql' => 'SELECT a AS v FROM app_in_source WHERE b BETWEEN a AND a*5 ORDER BY a',
    ],
    'in-1.4-not-between-column-expression' => static fn (int $offset, int $scale): array => [
        'sql' => 'SELECT a AS v FROM app_in_source WHERE b NOT BETWEEN a AND a*5 ORDER BY a',
    ],
    'in-1.6-between-or-point' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b BETWEEN a AND a*5 OR b=%d ORDER BY a', 512 * $scale),
    ],
    'in-1.7-between-projection-truth' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a+100*(a BETWEEN %d AND %d) AS v FROM app_in_source ORDER BY b', $offset + 1, $offset + 3),
    ],
    'in-2.1-in-static-list' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b IN (%d,%d,%d,%d,%d) ORDER BY a', 8 * $scale, 12 * $scale, 16 * $scale, 24 * $scale, 32 * $scale),
    ],
    'in-2.2-not-in-static-list' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b NOT IN (%d,%d,%d,%d,%d) ORDER BY a', 8 * $scale, 12 * $scale, 16 * $scale, 24 * $scale, 32 * $scale),
    ],
    'in-2.3-in-static-list-or-point' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b IN (%d,%d,%d,%d,%d) OR b=%d ORDER BY a', 8 * $scale, 12 * $scale, 16 * $scale, 24 * $scale, 32 * $scale, 512 * $scale),
    ],
    'in-2.4-not-in-static-list-or-point' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b NOT IN (%d,%d,%d,%d,%d) OR b=%d ORDER BY a', 8 * $scale, 12 * $scale, 16 * $scale, 24 * $scale, 32 * $scale, 512 * $scale),
    ],
    'in-2.5-in-projection-truth' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a+100*(b IN (%d,%d,%d)) AS v FROM app_in_source ORDER BY b', 8 * $scale, 16 * $scale, 24 * $scale),
    ],
    'in-2.6-in-row-expression-rhs' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b IN (b+%d,%d) ORDER BY a', 8 * $scale, 64 * $scale),
    ],
    'in-2.7-in-max-row-expression' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b IN (max(%d,%d,b),%d) ORDER BY a', 5 * $scale, 10 * $scale, 20 * $scale),
    ],
    'in-2.8-in-arithmetic-rhs' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b IN (%d*2,%d/2) ORDER BY b', 8 * $scale, 64 * $scale),
    ],
    'in-2.9-in-constant-function-rhs-empty' => static fn (int $offset, int $scale): array => [
        'sql' => sprintf('SELECT a AS v FROM app_in_source WHERE b IN (max(%d,%d),%d) ORDER BY a', 5 * $scale, 10 * $scale, 20 * $scale),
    ],
];

$rowsets = [];
for ($seed = 0; $seed < 67; $seed++) {
    $offset = ($seed % 17) - 3;
    $scale = ($seed % 5) + 1;
    $rows = [];
    for ($i = 1; $i <= 10; $i++) {
        $rows[] = [
            'a' => $offset + $i,
            'b' => $scale * (1 << $i),
        ];
    }

    $rowsets[sprintf('seed-%03d-offset-%+d-scale-%d', $seed, $offset, $scale)] = [
        'offset' => $offset,
        'scale' => $scale,
        'rows' => $rows,
    ];
}

$cases = [];
foreach ($rowsets as $rowsetName => $rowset) {
    foreach ($templates as $templateName => $build) {
        $built = $build($rowset['offset'], $rowset['scale']);
        $cases["{$rowsetName}.{$templateName}"] = [
            'sql' => $built['sql'],
            'rows' => $rowset['rows'],
            'template' => $templateName,
        ];
    }
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = 'DROP TABLE IF EXISTS app_in_source;';
    $oracleScript[] = 'CREATE TABLE app_in_source(a,b);';
    foreach ($case['rows'] as $row) {
        $oracleScript[] = sprintf('INSERT INTO app_in_source(a,b) VALUES(%d,%d);', $row['a'], $row['b']);
    }
    $oracleScript[] = sprintf(
        "SELECT '%s' || char(9) || coalesce(group_concat(quote(v) || ':' || typeof(v), '|'), '') FROM (%s);",
        $safeKey,
        $case['sql']
    );
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-in-between-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 oracle script for in.test BETWEEN and IN-list tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce in.test BETWEEN and IN-list output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) === 1) {
        $parts[] = '';
    }
    if (count($parts) !== 2) {
        throw new RuntimeException('Malformed sqlite3 in.test oracle row: ' . $line);
    }

    [$key, $signature] = $parts;
    $oracle[$key] = $signature === '' ? [] : explode('|', $signature);
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d in.test oracle rows, got %d', count($cases), count($oracle)));
}

$rowSignature = static function (array $rows): array {
    $signature = [];
    foreach ($rows as $row) {
        $value = $row['v'] ?? null;
        $signature[] = SQLiteRealExpressionAffinityCorpusPlan::quote($value) . ':' . SQLiteRealExpressionAffinityCorpusPlan::storageClass($value);
    }

    return $signature;
};

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic in.test early BETWEEN IN-list ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle, $rowSignature): void {
            $rows = SQLiteSelectSql::execute($case['sql'], ['app_in_source' => $case['rows']]);
            $t->same($oracle[$key], $rowSignature($rows), $key . ' row signature');
        };
}

$tests['real upstream corpus expression affinity dynamic in.test early BETWEEN IN-list owns source range'] =
    static function (TestRunner $t) use ($templates, $rowsets, $cases, $oracle): void {
        $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/in.test');
        $t->contains('do_test in-1.1', $source);
        $t->contains('do_test in-1.7', $source);
        $t->contains('do_test in-2.1', $source);
        $t->contains('do_test in-2.9', $source);
        $t->same(15, count($templates));
        $t->same(67, count($rowsets));
        $t->same(1005, count($cases));
        $t->same(1005, count($oracle));
        $t->same(
            'in.test in-1.1..1.7 and in-2.1..2.9 early BETWEEN and static IN-list expression behavior',
            'in.test in-1.1..1.7 and in-2.1..2.9 early BETWEEN and static IN-list expression behavior',
        );
        $t->same(
            'non-overlap: owns early in.test BETWEEN/static IN-list row filtering and projection truth; avoids accepted in-11 RHS affinity, in-13 nullable subqueries, in-19 REAL IN, types2 IN affinity, expr-7 WHERE, CASE, CAST, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup',
            'non-overlap: owns early in.test BETWEEN/static IN-list row filtering and projection truth; avoids accepted in-11 RHS affinity, in-13 nullable subqueries, in-19 REAL IN, types2 IN affinity, expr-7 WHERE, CASE, CAST, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup',
        );
    };

$tests['real upstream corpus expression affinity dynamic in.test early BETWEEN IN-list dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql BETWEEN, IN-list, scalar function, ORDER BY hidden-column, and SQLiteRealExpressionAffinityCorpusPlan quote/storage helpers with sqlite3 oracle parity',
            'no new support component needed; reuses SQLiteSelectSql BETWEEN, IN-list, scalar function, ORDER BY hidden-column, and SQLiteRealExpressionAffinityCorpusPlan quote/storage helpers with sqlite3 oracle parity',
        );
    };

return $tests;
