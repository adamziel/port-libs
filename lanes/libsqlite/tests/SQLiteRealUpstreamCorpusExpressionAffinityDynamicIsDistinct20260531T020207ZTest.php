<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream IS DISTINCT expression affinity tests');
}

$literalSql = static function (mixed $value): string {
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

// Source truth: SQLite upstream test/expr.test expr-1.111 through expr-1.126.
// Those cases pin IS / IS NOT and the IS DISTINCT FROM spelling, including
// CASE predicate dispatch around NULL and equal/non-equal pairs. This dynamic
// shard keeps the same behavior but widens the row corpus across INTEGER,
// REAL, NUMERIC, TEXT, and BLOB-affinity columns.
$affinities = [
    'rowid' => 'INTEGER',
    'i1' => 'INTEGER',
    'i2' => 'INTEGER',
    'r1' => 'REAL',
    'r2' => 'REAL',
    'n1' => 'NUMERIC',
    'n2' => 'NUMERIC',
    't1' => 'TEXT',
    't2' => 'TEXT',
    'b1' => 'BLOB',
    'b2' => 'BLOB',
];

$rawRows = [];
foreach (range(1, 64) as $i) {
    $left = match ($i % 16) {
        0 => null,
        1 => 8,
        2 => '8',
        3 => '08',
        4 => 8.0,
        5 => '8.0',
        6 => -8,
        7 => '-8',
        8 => 0,
        9 => '0',
        10 => 0.0,
        11 => '0.0',
        12 => '8e0',
        13 => 'text-' . $i,
        14 => '',
        default => '9223372036854775808',
    };

    $right = match ((int) floor(($i - 1) / 4) % 16) {
        0 => null,
        1 => 8,
        2 => '8',
        3 => '08',
        4 => 8.0,
        5 => '8.0',
        6 => -8,
        7 => '-8',
        8 => 0,
        9 => '0',
        10 => 0.0,
        11 => '0.0',
        12 => '8e0',
        13 => 'text-' . $i,
        14 => '',
        default => '9223372036854775808',
    };

    $rawRows[] = [
        'rowid' => $i,
        'i1' => $left,
        'i2' => $right,
        'r1' => $left,
        'r2' => $right,
        'n1' => $left,
        'n2' => $right,
        't1' => $left,
        't2' => $right,
        'b1' => $left,
        'b2' => $right,
    ];
}

$tableRows = array_map(
    static fn (array $row): array => $row + ['__sqlite_column_affinities' => $affinities],
    SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities($rawRows, $affinities),
);

$columnPairs = [
    'integer' => ['i1', 'i2'],
    'real' => ['r1', 'r2'],
    'numeric' => ['n1', 'n2'],
    'text' => ['t1', 't2'],
    'blob' => ['b1', 'b2'],
];

$expressionTemplates = [
    'expr-1.111 is' => '%s IS %s',
    'expr-1.111b is-not-distinct-from' => '%s IS NOT DISTINCT FROM %s',
    'expr-1.119 is-not' => '%s IS NOT %s',
    'expr-1.119b is-distinct-from' => '%s IS DISTINCT FROM %s',
    'expr-1.115 case-is' => "CASE WHEN %s IS %s THEN 'yes' ELSE 'no' END",
    'expr-1.115b case-is-not-distinct-from' => "CASE WHEN %s IS NOT DISTINCT FROM %s THEN 'yes' ELSE 'no' END",
    'expr-1.123 case-is-not' => "CASE WHEN %s IS NOT %s THEN 'yes' ELSE 'no' END",
    'expr-1.123b case-is-distinct-from' => "CASE WHEN %s IS DISTINCT FROM %s THEN 'yes' ELSE 'no' END",
    'expr-1.116 null-guarded-case-is' => "CASE WHEN %s IS NULL OR %s IS %s THEN 'yes' ELSE 'no' END",
    'expr-1.124 null-guarded-case-is-not' => "CASE WHEN %s IS NULL OR %s IS NOT %s THEN 'yes' ELSE 'no' END",
];

$cases = [];
foreach ($rawRows as $row) {
    foreach ($columnPairs as $pairName => [$leftColumn, $rightColumn]) {
        foreach ($expressionTemplates as $section => $template) {
            $placeholderCount = substr_count($template, '%s');
            $args = $placeholderCount === 3
                ? [$template, $leftColumn, $leftColumn, $rightColumn]
                : [$template, $leftColumn, $rightColumn];
            $expression = sprintf(...$args);
            $cases[sprintf('%s row%03d %s', $section, $row['rowid'], $pairName)] = [
                'rowid' => (int) $row['rowid'],
                'expression' => $expression,
            ];
        }
    }
}

$oracleScript = [
    'CREATE TABLE t(rowid INTEGER PRIMARY KEY, i1 INTEGER, i2 INTEGER, r1 REAL, r2 REAL, n1 NUMERIC, n2 NUMERIC, t1 TEXT, t2 TEXT, b1 BLOB, b2 BLOB);',
];
foreach ($rawRows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO t(rowid,i1,i2,r1,r2,n1,n2,t1,t2,b1,b2) VALUES(%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s);',
        $row['rowid'],
        $literalSql($row['i1']),
        $literalSql($row['i2']),
        $literalSql($row['r1']),
        $literalSql($row['r2']),
        $literalSql($row['n1']),
        $literalSql($row['n2']),
        $literalSql($row['t1']),
        $literalSql($row['t2']),
        $literalSql($row['b1']),
        $literalSql($row['b2']),
    );
}
foreach ($cases as $key => $case) {
    $safeKey = str_replace("'", "''", $key);
    $expression = $case['expression'];
    $oracleScript[] = sprintf(
        "SELECT '%s' || char(9) || quote(%s) || char(9) || typeof(%s) FROM t WHERE rowid = %d;",
        $safeKey,
        $expression,
        $expression,
        $case['rowid'],
    );
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-is-distinct-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 IS DISTINCT oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce IS DISTINCT expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed sqlite3 IS DISTINCT oracle row: ' . $line);
    }
    [$key, $quotedValue, $storageClass] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d IS DISTINCT oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic is distinct expr-1.111-1.126 ' . $key] = static function (TestRunner $t) use ($case, $key, $oracle, $tableRows): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$case['expression']}) AS q, typeof({$case['expression']}) AS t FROM t WHERE rowid = {$case['rowid']}",
            ['t' => $tableRows],
        );

        $t->same(1, count($rows), $key . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $key . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $key . ' typeof');
    };
}

$tests['real upstream corpus expression affinity dynamic is distinct owns expr test matrix'] = static function (TestRunner $t) use ($rawRows, $columnPairs, $expressionTemplates, $cases, $oracle): void {
    $t->same(64, count($rawRows));
    $t->same(5, count($columnPairs));
    $t->same(10, count($expressionTemplates));
    $t->same(3200, count($cases));
    $t->same(3200, count($oracle));
    $t->same(
        'expr.test expr-1.111..1.126 IS, IS NOT, IS DISTINCT FROM, and CASE predicate dispatch over NULL/equal/non-equal pairs',
        'expr.test expr-1.111..1.126 IS, IS NOT, IS DISTINCT FROM, and CASE predicate dispatch over NULL/equal/non-equal pairs',
    );
    $t->contains('expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test');
};

return $tests;
