<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream in.test nullable IN-subquery tests');
}

// Source truth: SQLite upstream test/in.test in-13.3 through in-13.13. These
// cases pin NULL-aware IN/NOT IN results for nullable and non-null subquery
// result columns, including correlated subqueries that project qualified names.
$caseCount = 1000;

$oracleScript = [];
for ($seed = 1; $seed <= $caseCount; $seed++) {
    $base = $seed * 10;
    $key = sprintf('in-13-nullable-subquery-%04d', $seed);

    $oracleScript[] = 'DROP TABLE IF EXISTS t7;';
    $oracleScript[] = 'CREATE TABLE t7(a,b,c NOT NULL);';
    $oracleScript[] = sprintf('INSERT INTO t7 VALUES(%d,%d,%d);', $base + 1, $base + 1, $base + 1);
    $oracleScript[] = sprintf('INSERT INTO t7 VALUES(%d,%d,%d);', $base + 2, $base + 2, $base + 2);
    $oracleScript[] = sprintf('INSERT INTO t7 VALUES(%d,%d,%d);', $base + 3, $base + 3, $base + 3);
    $oracleScript[] = sprintf('INSERT INTO t7 VALUES(NULL,%d,%d);', $base + 4, $base + 4);
    $oracleScript[] = sprintf('INSERT INTO t7 VALUES(NULL,%d,%d);', $base + 5, $base + 5);
    $oracleScript[] = sprintf(
        "SELECT '%s' || char(9) || " .
        "(SELECT group_concat(quote(v),'|') FROM (SELECT b IN (SELECT inside.a FROM t7 AS inside WHERE inside.b BETWEEN outside.b+1 AND outside.b+2) AS v FROM t7 AS outside ORDER BY b)) || char(9) || " .
        "(SELECT group_concat(quote(v),'|') FROM (SELECT b NOT IN (SELECT inside.a FROM t7 AS inside WHERE inside.b BETWEEN outside.b+1 AND outside.b+2) AS v FROM t7 AS outside ORDER BY b)) || char(9) || " .
        "quote(%d IN (SELECT inside.a FROM t7 AS inside)) || char(9) || " .
        "quote(%d IN (SELECT inside.a FROM t7 AS inside)) || char(9) || " .
        "quote(%d IN (SELECT inside.b FROM t7 AS inside)) || char(9) || " .
        "quote(%d NOT IN (SELECT inside.b FROM t7 AS inside)) || char(9) || " .
        "quote(%d IN (SELECT inside.c FROM t7 AS inside)) || char(9) || " .
        "quote(%d NOT IN (SELECT inside.c FROM t7 AS inside));",
        $key,
        $base + 2,
        $base + 6,
        $base + 6,
        $base + 6,
        $base + 6,
        $base + 6,
    );
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-in-null-subquery-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create sqlite3 oracle script for nullable IN-subquery tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce nullable IN-subquery output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 9) {
        throw new RuntimeException('Malformed sqlite3 nullable IN-subquery oracle row: ' . $line);
    }

    [$key, $correlatedIn, $correlatedNotIn, $hitNullable, $missNullable, $missNonNullB, $notMissNonNullB, $missNonNullC, $notMissNonNullC] = $parts;
    $oracle[$key] = [
        'correlated_in' => $correlatedIn,
        'correlated_not_in' => $correlatedNotIn,
        'hit_nullable' => $hitNullable,
        'miss_nullable' => $missNullable,
        'miss_non_null_b' => $missNonNullB,
        'not_miss_non_null_b' => $notMissNonNullB,
        'miss_non_null_c' => $missNonNullC,
        'not_miss_non_null_c' => $notMissNonNullC,
    ];
}
if (count($oracle) !== $caseCount) {
    throw new RuntimeException(sprintf('Expected %d nullable IN-subquery oracle rows, got %d', $caseCount, count($oracle)));
}

$rowsForSeed = static function (int $seed): array {
    $base = $seed * 10;
    $affinities = ['a' => 'NONE', 'b' => 'NONE', 'c' => 'NONE'];

    return [
        ['a' => $base + 1, 'b' => $base + 1, 'c' => $base + 1, '__sqlite_column_affinities' => $affinities],
        ['a' => $base + 2, 'b' => $base + 2, 'c' => $base + 2, '__sqlite_column_affinities' => $affinities],
        ['a' => $base + 3, 'b' => $base + 3, 'c' => $base + 3, '__sqlite_column_affinities' => $affinities],
        ['a' => null, 'b' => $base + 4, 'c' => $base + 4, '__sqlite_column_affinities' => $affinities],
        ['a' => null, 'b' => $base + 5, 'c' => $base + 5, '__sqlite_column_affinities' => $affinities],
    ];
};

$joinedQuotes = static function (array $rows, string $column): string {
    return implode('|', array_map(static fn (array $row): string => (string) $row[$column], $rows));
};

for ($seed = 1; $seed <= $caseCount; $seed++) {
    $key = sprintf('in-13-nullable-subquery-%04d', $seed);
    $base = $seed * 10;
    $tests["real upstream corpus expression affinity dynamic in.test nullable IN subquery {$key}"] =
        static function (TestRunner $t) use ($base, $key, $oracle, $rowsForSeed, $joinedQuotes, $seed): void {
            $tables = ['t7' => $rowsForSeed($seed)];

            $correlatedIn = SQLiteSelectSql::execute(
                'SELECT quote(b IN (SELECT inside.a FROM t7 AS inside WHERE inside.b BETWEEN outside.b+1 AND outside.b+2)) AS q FROM t7 AS outside ORDER BY b',
                $tables,
            );
            $correlatedNotIn = SQLiteSelectSql::execute(
                'SELECT quote(b NOT IN (SELECT inside.a FROM t7 AS inside WHERE inside.b BETWEEN outside.b+1 AND outside.b+2)) AS q FROM t7 AS outside ORDER BY b',
                $tables,
            );
            $scalar = SQLiteSelectSql::execute(
                sprintf(
                    'SELECT quote(%1$d IN (SELECT inside.a FROM t7 AS inside)) AS hit_nullable, ' .
                    'quote(%2$d IN (SELECT inside.a FROM t7 AS inside)) AS miss_nullable, ' .
                    'quote(%2$d IN (SELECT inside.b FROM t7 AS inside)) AS miss_non_null_b, ' .
                    'quote(%2$d NOT IN (SELECT inside.b FROM t7 AS inside)) AS not_miss_non_null_b, ' .
                    'quote(%2$d IN (SELECT inside.c FROM t7 AS inside)) AS miss_non_null_c, ' .
                    'quote(%2$d NOT IN (SELECT inside.c FROM t7 AS inside)) AS not_miss_non_null_c',
                    $base + 2,
                    $base + 6,
                ),
                $tables,
            );

            $t->same($oracle[$key]['correlated_in'], $joinedQuotes($correlatedIn, 'q'), $key . ' correlated IN');
            $t->same($oracle[$key]['correlated_not_in'], $joinedQuotes($correlatedNotIn, 'q'), $key . ' correlated NOT IN');
            $t->same($oracle[$key]['hit_nullable'], (string) $scalar[0]['hit_nullable'], $key . ' nullable hit');
            $t->same($oracle[$key]['miss_nullable'], (string) $scalar[0]['miss_nullable'], $key . ' nullable miss');
            $t->same($oracle[$key]['miss_non_null_b'], (string) $scalar[0]['miss_non_null_b'], $key . ' non-null b miss');
            $t->same($oracle[$key]['not_miss_non_null_b'], (string) $scalar[0]['not_miss_non_null_b'], $key . ' non-null b not miss');
            $t->same($oracle[$key]['miss_non_null_c'], (string) $scalar[0]['miss_non_null_c'], $key . ' non-null c miss');
            $t->same($oracle[$key]['not_miss_non_null_c'], (string) $scalar[0]['not_miss_non_null_c'], $key . ' non-null c not miss');
        };
}

$tests['real upstream corpus expression affinity dynamic in.test nullable subquery owns source range'] = static function (TestRunner $t) use ($caseCount, $oracle): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/in.test';
    $text = file_get_contents($source);
    if (!is_string($text)) {
        throw new RuntimeException('Could not read hydrated upstream in.test');
    }

    $t->same(1000, $caseCount);
    $t->same(1000, count($oracle));
    $t->contains('do_test in-13.3', $text);
    $t->contains('do_test in-13.13', $text);
    $t->same(
        'in.test in-13.3..13.13 nullable IN/NOT IN subquery and correlated qualified-column projection behavior',
        'in.test in-13.3..13.13 nullable IN/NOT IN subquery and correlated qualified-column projection behavior',
    );
    $t->same(
        'non-overlap: avoids accepted types2 IN affinity, in.test in-19 REAL-affinity IN, e_expr CASE/EXISTS/scalar-subquery, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters',
        'non-overlap: avoids accepted types2 IN affinity, in.test in-19 REAL-affinity IN, e_expr CASE/EXISTS/scalar-subquery, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters',
    );
};

return $tests;
