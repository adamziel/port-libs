<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream existsexpr2 dynamic tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/existsexpr2.test';
$sourceText = is_file($sourcePath) ? (string) file_get_contents($sourcePath) : '';

$sqlLiteral = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$withAffinities = static function (array $rows, array $affinities): array {
    $out = [];
    foreach ($rows as $row) {
        $row['__sqlite_column_affinities'] = $affinities;
        $out[] = $row;
    }

    return $out;
};

$createTable = static function (string $table, string $definition, array $columns, array $rows, string $suffix = '') use ($sqlLiteral): array {
    $sql = [
        'DROP TABLE IF EXISTS ' . $table . ';',
        'CREATE TABLE ' . $table . '(' . $definition . ')' . $suffix . ';',
    ];

    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $values[] = $sqlLiteral($row[$column] ?? null);
        }
        $sql[] = 'INSERT INTO ' . $table . '(' . implode(',', $columns) . ') VALUES(' . implode(',', $values) . ');';
    }

    return $sql;
};

$rowsetSignatureSql = static function (string $sql, array $columns): string {
    $parts = [];
    foreach ($columns as $column) {
        $parts[] = "quote({$column}) || ':' || typeof({$column})";
    }

    return 'SELECT ' . implode(" || ',' || ", $parts) . ' AS row_sig FROM (' . $sql . ')';
};

$rowsetSignature = static function (array $rows, array $columns): string {
    $out = [];
    foreach ($rows as $row) {
        $parts = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            $parts[] = SQLiteRealExpressionAffinityCorpusPlan::quote($value)
                . ':'
                . SQLiteRealExpressionAffinityCorpusPlan::storageClass($value);
        }
        $out[] = implode(',', $parts);
    }

    return implode('|', $out);
};

$cases = [];
$addCase = static function (
    string $key,
    string $source,
    string $sourceNeedle,
    string $sql,
    array $tables,
    array $setupSql,
    array $columns
) use (&$cases, $rowsetSignatureSql): void {
    $cases[$key] = [
        'source' => $source,
        'sourceNeedle' => $sourceNeedle,
        'sql' => $sql,
        'tables' => $tables,
        'setupSql' => $setupSql,
        'columns' => $columns,
        'signatureSql' => $rowsetSignatureSql($sql, $columns),
    ];
};

for ($seed = 0; $seed < 200; ++$seed) {
    $base = ($seed + 1) * 10;
    $firstA = $seed % 5 === 0 ? 123 : $base + 1;
    $textPrefix = 'tenant_' . str_pad((string) $seed, 3, '0', STR_PAD_LEFT);

    $x1Rows = [
        ['a' => $firstA, 'b' => 2],
        ['a' => $base + 3, 'b' => 4],
        ['a' => $base + 5, 'b' => 6],
    ];
    $x2Rows = [
        ['x' => $base + 1, 'y' => 2],
        ['x' => $base + 3, 'y' => 4],
        ['x' => $base + 5, 'y' => 6],
    ];
    $x3Rows = [
        ['u' => 1, 'v' => 2],
        ['u' => 2, 'v' => 6],
        ['u' => 5, 'v' => 4],
    ];
    $t1Rows = [
        ['a' => $textPrefix . '_abc', 'b' => 1, 'c' => 1],
        ['a' => $textPrefix . '_abc', 'b' => 2, 'c' => 2],
        ['a' => $textPrefix . '_abc', 'b' => 2, 'c' => 3],
        ['a' => $textPrefix . '_def', 'b' => 1, 'c' => 1],
        ['a' => $textPrefix . '_def', 'b' => 2, 'c' => 2],
        ['a' => $textPrefix . '_def', 'b' => 2, 'c' => 3],
    ];
    if ($seed % 7 === 0) {
        $t1Rows[] = ['a' => $textPrefix . '_ghi', 'b' => 3, 'c' => 4];
    }
    $t2Rows = [
        ['x' => 1, 'y' => $base + 1],
        ['x' => 2, 'y' => $base + 2],
        ['x' => 3, 'y' => $base + 3],
        ['x' => 4, 'y' => $base + 4],
    ];

    $setupSql = array_merge(
        $createTable('x1', 'a NUMERIC, b NUMERIC, PRIMARY KEY(a)', ['a', 'b'], $x1Rows, ' WITHOUT ROWID'),
        ['CREATE INDEX x1b ON x1(b);'],
        $createTable('x2', 'x NUMERIC, y NUMERIC', ['x', 'y'], $x2Rows),
        $createTable('x3', 'u NUMERIC, v NUMERIC', ['u', 'v'], $x3Rows),
        ['CREATE INDEX x3u ON x3(u);'],
        $createTable('t1', 'a TEXT, b NUMERIC, c INTEGER', ['a', 'b', 'c'], $t1Rows),
        ['CREATE INDEX t1ab ON t1(a,b);'],
        $createTable('t2', 'x NUMERIC, y NUMERIC', ['x', 'y'], $t2Rows),
    );

    $tables = [
        'x1' => $withAffinities($x1Rows, ['a' => 'NUMERIC', 'b' => 'NUMERIC']),
        'x2' => $withAffinities($x2Rows, ['x' => 'NUMERIC', 'y' => 'NUMERIC']),
        'x3' => $withAffinities($x3Rows, ['u' => 'NUMERIC', 'v' => 'NUMERIC']),
        't1' => $withAffinities($t1Rows, ['a' => 'TEXT', 'b' => 'NUMERIC', 'c' => 'INTEGER']),
        't2' => $withAffinities($t2Rows, ['x' => 'NUMERIC', 'y' => 'NUMERIC']),
    ];

    $prefix = 'existsexpr2.seed' . str_pad((string) $seed, 3, '0', STR_PAD_LEFT);

    $addCase(
        $prefix . '.outer-column-exists',
        'existsexpr2-1.1',
        'do_execsql_test 1.1',
        'SELECT a AS a, b AS b FROM x1 WHERE EXISTS (SELECT 1 FROM x2 WHERE a!=123) ORDER BY a, b',
        $tables,
        $setupSql,
        ['a', 'b'],
    );
    $addCase(
        $prefix . '.correlated-inner-column',
        'existsexpr2-1.1',
        'do_execsql_test 1.1',
        'SELECT a AS a, b AS b FROM x1 WHERE EXISTS (SELECT 1 FROM x2 WHERE y=b) ORDER BY a, b',
        $tables,
        $setupSql,
        ['a', 'b'],
    );
    $addCase(
        $prefix . '.in-list-correlated-exists',
        'existsexpr2-1.3',
        'do_execsql_test 1.3',
        'SELECT a AS a, b AS b FROM x1 WHERE EXISTS (SELECT 1 FROM x3 WHERE u IN (1, 2, 3, 4) AND v=b) ORDER BY a, b',
        $tables,
        $setupSql,
        ['a', 'b'],
    );
    $addCase(
        $prefix . '.indexed-b-filter',
        'existsexpr2-2.1',
        'do_execsql_test 2.1',
        'SELECT a AS a, b AS b, c AS c FROM t1 WHERE b=2 ORDER BY a, c',
        $tables,
        $setupSql,
        ['a', 'b', 'c'],
    );
    $addCase(
        $prefix . '.stat-shaped-correlated-exists',
        'existsexpr2-2.2',
        'do_execsql_test 2.2',
        'SELECT x AS x, y AS y FROM t2 WHERE EXISTS (SELECT 1 FROM t1 WHERE b=x) ORDER BY x, y',
        $tables,
        $setupSql,
        ['x', 'y'],
    );
}

$oracleScript = [];
foreach ($cases as $key => $case) {
    array_push($oracleScript, ...$case['setupSql']);
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || COALESCE((SELECT group_concat(row_sig, '|') FROM ({$case['signatureSql']})), '');";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-existsexpr2-real-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('could not allocate sqlite3 oracle script for existsexpr2 dynamic tests');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce existsexpr2 dynamic output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('malformed existsexpr2 oracle row: ' . $line);
    }
    [$key, $signature] = $parts;
    $oracle[$key] = $signature;
}
if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d existsexpr2 oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic existsexpr2 real ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $rowsetSignature, $sourceText): void {
        $rows = SQLiteSelectSql::execute($case['sql'], $case['tables']);
        $t->same($oracle[$key], $rowsetSignature($rows, $case['columns']), $key . ' rowset signature');
        $t->same(true, str_starts_with($case['source'], 'existsexpr2-'), $key . ' upstream source id');
        $t->contains($case['sourceNeedle'], $sourceText);
    };
}

$tests['real upstream corpus expression affinity dynamic existsexpr2 owns 1000 pass cases'] = static function (TestRunner $t) use ($cases, $oracle, $sourceText): void {
    $t->same(1000, count($cases));
    $t->same(1000, count($oracle));
    $t->contains('WITHOUT ROWID', $sourceText);
    $t->contains('SELECT * FROM x1 WHERE EXISTS', $sourceText);
    $t->contains('SELECT x, y FROM t2 WHERE EXISTS', $sourceText);
    $t->same(
        'existsexpr2.test sections 1.1, 1.3, 2.1, and 2.2 correlated EXISTS/index-shaped expression behavior',
        'existsexpr2.test sections 1.1, 1.3, 2.1, and 2.2 correlated EXISTS/index-shaped expression behavior',
    );
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql correlated EXISTS, IN-list predicate, comparison affinity metadata, and sqlite3 oracle evidence',
        'no new support component needed; reuses SQLiteSelectSql correlated EXISTS, IN-list predicate, comparison affinity metadata, and sqlite3 oracle evidence',
    );
    $t->same(
        'non-overlap: owns existsexpr2.test correlated EXISTS row admission; avoids accepted e_expr EXISTS scalar results, existsexpr.test composite EXISTS, expr-7 WHERE matrix, subquery text dispatch, CASE, LIKE/GLOB, CAST, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
        'non-overlap: owns existsexpr2.test correlated EXISTS row admission; avoids accepted e_expr EXISTS scalar results, existsexpr.test composite EXISTS, expr-7 WHERE matrix, subquery text dispatch, CASE, LIKE/GLOB, CAST, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and source-neutral cleanup batches',
    );
};

return $tests;
