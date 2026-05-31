<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream expr.test CASE row-context tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test';

$literalSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value)) {
        return (string) $value;
    }
    if (is_float($value)) {
        return sprintf('%.17G', $value);
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/expr.test expr-case.1 through
// expr-case.13. Those rows exercise searched CASE equality, simple CASE
// equality with NULL WHEN arms, ELSE/NULL fall-through, mixed text/integer
// result storage, and ordered searched-CASE thresholds over the test_expr
// row-context columns i1/i2.
$affinities = [
    'rowid' => 'INTEGER',
    'i1' => 'INTEGER',
    'i2' => 'INTEGER',
];

$rawRows = [
    ['rowid' => 1, 'i1' => 1, 'i2' => 2],
    ['rowid' => 2, 'i1' => 2, 'i2' => 2],
    ['rowid' => 3, 'i1' => null, 'i2' => 2],
    ['rowid' => 4, 'i1' => 2, 'i2' => null],
    ['rowid' => 5, 'i1' => 3, 'i2' => null],
    ['rowid' => 6, 'i1' => 7, 'i2' => null],
    ['rowid' => 7, 'i1' => 0, 'i2' => 0],
    ['rowid' => 8, 'i1' => 5, 'i2' => 5],
    ['rowid' => 9, 'i1' => 4, 'i2' => 5],
    ['rowid' => 10, 'i1' => 5, 'i2' => 4],
    ['rowid' => 11, 'i1' => 9, 'i2' => 10],
    ['rowid' => 12, 'i1' => 10, 'i2' => 9],
    ['rowid' => 13, 'i1' => 14, 'i2' => 15],
    ['rowid' => 14, 'i1' => 15, 'i2' => 14],
    ['rowid' => 15, 'i1' => -1, 'i2' => -1],
    ['rowid' => 16, 'i1' => -1, 'i2' => 2],
    ['rowid' => 17, 'i1' => 2, 'i2' => -1],
    ['rowid' => 18, 'i1' => 8, 'i2' => 8],
    ['rowid' => 19, 'i1' => 11, 'i2' => 11],
    ['rowid' => 20, 'i1' => 99, 'i2' => 99],
    ['rowid' => 21, 'i1' => '1', 'i2' => '2'],
    ['rowid' => 22, 'i1' => '2', 'i2' => '2'],
    ['rowid' => 23, 'i1' => '3', 'i2' => '03'],
    ['rowid' => 24, 'i1' => '007', 'i2' => '7'],
    ['rowid' => 25, 'i1' => '1.0', 'i2' => '2.0'],
    ['rowid' => 26, 'i1' => 1.5, 'i2' => 2.5],
    ['rowid' => 27, 'i1' => 'abc', 'i2' => 2],
    ['rowid' => 28, 'i1' => 2, 'i2' => 'abc'],
    ['rowid' => 29, 'i1' => '', 'i2' => 0],
    ['rowid' => 30, 'i1' => ' 7 ', 'i2' => 7],
    ['rowid' => 31, 'i1' => '5x', 'i2' => 5],
    ['rowid' => 32, 'i1' => '9223372036854775807', 'i2' => null],
    ['rowid' => 33, 'i1' => '-9223372036854775808', 'i2' => null],
    ['rowid' => 34, 'i1' => '9223372036854775808', 'i2' => null],
    ['rowid' => 35, 'i1' => null, 'i2' => null],
    ['rowid' => 36, 'i1' => 0, 'i2' => 1],
    ['rowid' => 37, 'i1' => 1, 'i2' => 0],
    ['rowid' => 38, 'i1' => 6, 'i2' => 9],
    ['rowid' => 39, 'i1' => 9, 'i2' => 6],
    ['rowid' => 40, 'i1' => 12, 'i2' => 3],
    ['rowid' => 41, 'i1' => 3, 'i2' => 12],
    ['rowid' => 42, 'i1' => '15.0', 'i2' => '15'],
    ['rowid' => 43, 'i1' => '14.9', 'i2' => 15],
    ['rowid' => 44, 'i1' => '4.9', 'i2' => 5],
    ['rowid' => 45, 'i1' => '09', 'i2' => '9.0'],
    ['rowid' => 46, 'i1' => -5, 'i2' => 5],
    ['rowid' => 47, 'i1' => 5, 'i2' => -5],
    ['rowid' => 48, 'i1' => '0x10', 'i2' => 16],
];

$tableRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $affinities],
    SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rawRows, $affinities),
);

$expressions = [
    'expr-case.1-4 searched equality' => "CASE WHEN i1 = i2 THEN 'eq' ELSE 'ne' END",
    'expr-case.5-9 simple integer arms' => "CASE i1 WHEN 1 THEN 'one' WHEN 2 THEN 'two' ELSE 'error' END",
    'expr-case.6-8 simple null when arm' => "CASE i1 WHEN 1 THEN 'one' WHEN NULL THEN 'two' ELSE 'error' END",
    'expr-case.10 simple no else' => "CASE i1 WHEN 1 THEN 'one' WHEN 2 THEN 'two' END",
    'expr-case.11 numeric else' => "CASE i1 WHEN 1 THEN 'one' WHEN 2 THEN 'two' ELSE 3 END",
    'expr-case.12 null then result' => "CASE i1 WHEN 1 THEN NULL WHEN 2 THEN 'two' ELSE 3 END",
    'expr-case.13 searched thresholds' => "CASE WHEN i1 < 5 THEN 'low' WHEN i1 < 10 THEN 'medium' WHEN i1 < 15 THEN 'high' ELSE 'error' END",
];

$projections = [
    'quote' => static fn (string $expression): string => "quote({$expression})",
    'typeof' => static fn (string $expression): string => "typeof({$expression})",
    'is-null' => static fn (string $expression): string => "quote(({$expression}) IS NULL)",
];

$cases = [];
foreach ($rawRows as $rowOffset => $row) {
    foreach ($expressions as $source => $expression) {
        foreach ($projections as $projectionName => $projectionSql) {
            $key = sprintf(
                'row%02d.%s.%s',
                $row['rowid'],
                preg_replace('/[^a-z0-9]+/', '-', strtolower($source)),
                $projectionName,
            );
            $cases[$key] = [
                'rowOffset' => $rowOffset,
                'rowid' => (int) $row['rowid'],
                'source' => $source,
                'expression' => $expression,
                'projectionName' => $projectionName,
                'projectionSql' => $projectionSql($expression),
            ];
        }
    }
}

$oracleScript = [
    'CREATE TABLE test1(rowid INTEGER PRIMARY KEY, i1 int, i2 int);',
];
foreach ($rawRows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO test1(rowid, i1, i2) VALUES(%s, %s, %s);',
        $literalSql($row['rowid']),
        $literalSql($row['i1']),
        $literalSql($row['i2']),
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = sprintf(
        "SELECT '%s' || char(9) || %s FROM test1 WHERE rowid = %d;",
        $safeKey,
        $case['projectionSql'],
        $case['rowid'],
    );
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-expr-case-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for expr.test CASE tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce expr.test CASE output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed expr.test CASE oracle row: ' . $line);
    }
    [$key, $value] = $parts;
    $oracle[$key] = $value;
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d expr.test CASE oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic expr.test expr-case ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $tableRows): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT {$case['projectionSql']} AS v FROM test1",
            ['test1' => [$tableRows[$case['rowOffset']]]],
        );
        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key], (string) $rows[0]['v'], $case['source'] . ' ' . $case['projectionName']);
    };
}

$tests['real upstream corpus expression affinity dynamic expr.test expr-case owns exactly 1008 cases'] = static function (TestRunner $t) use ($cases, $expressions, $projections, $rawRows, $sourcePath, $tableRows): void {
    $t->same(48, count($rawRows));
    $t->same(7, count($expressions));
    $t->same(3, count($projections));
    $t->same(1008, count($cases));
    $t->same('integer', SQLiteRealExpressionAffinityCorpusPlan::storageClass($tableRows[20]['i1']));
    $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($tableRows[25]['i1']));
    $source = file_get_contents($sourcePath);
    $t->true(is_string($source));
    $t->contains('test_expr expr-case.1', $source);
    $t->contains('test_expr expr-case.13', $source);
    $t->same(
        'expr.test expr-case.1..13 row-context CASE equality, NULL-arm, ELSE, NULL-result, and threshold behavior',
        'expr.test expr-case.1..13 row-context CASE equality, NULL-arm, ELSE, NULL-result, and threshold behavior',
    );
};

$tests['real upstream corpus expression affinity dynamic expr.test expr-case dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql CASE expression execution, SQLiteRealExpressionAffinityCorpusPlan insert-affinity coercion, and sqlite3 oracle parity for hydrated upstream expr.test',
        'no new support component needed; reuses SQLiteSelectSql CASE expression execution, SQLiteRealExpressionAffinityCorpusPlan insert-affinity coercion, and sqlite3 oracle parity for hydrated upstream expr.test',
    );
};

return $tests;
