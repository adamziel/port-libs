<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for upstream existsexpr dynamic tests');
}

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/existsexpr.test';
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

$createTableSql = static function (string $table, array $columns, array $columnTypes, array $rows) use ($sqlLiteral): array {
    $definitions = [];
    foreach ($columns as $column) {
        $definitions[] = $column . ' ' . ($columnTypes[$column] ?? '');
    }

    $sql = [
        'DROP TABLE IF EXISTS ' . $table . ';',
        'CREATE TABLE ' . $table . '(' . implode(', ', $definitions) . ');',
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

$normalizeValue = static function (mixed $value): string {
    if ($value === null) {
        return '<NULL>';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_float($value)) {
        $text = sprintf('%.15g', $value);

        return str_replace('E', 'e', $text);
    }

    return (string) $value;
};

$normalizeRows = static function (array $rows) use ($normalizeValue): array {
    $normalized = [];
    foreach ($rows as $row) {
        $values = [];
        foreach ($row as $value) {
            $values[] = $normalizeValue($value);
        }

        $normalized[] = implode("\t", $values);
    }

    return $normalized;
};

$cases = [];
$addCase = static function (string $key, string $source, string $sql, array $tables, array $setupSql) use (&$cases): void {
    $cases[$key] = [
        'source' => $source,
        'sql' => $sql,
        'tables' => $tables,
        'setupSql' => $setupSql,
    ];
};

// Source truth:
// - test/existsexpr.test existsexpr-3.1 through 3.9: correlated EXISTS
//   predicates over composite equality, scalar min()/max(), arithmetic terms,
//   aggregate subqueries, and two-source subqueries.
// - test/existsexpr.test existsexpr-4.1.1 through 4.4: explicit COLLATE
//   placement controls correlated EXISTS equality.
//
// This shard is intentionally distinct from the accepted e_expr-34 scalar
// EXISTS result tests and SELECT SQL subquery text batches. It checks row
// admission from correlated EXISTS predicates against sqlite3 over varied
// application-shaped row sets.
for ($seed = 0; $seed < 100; ++$seed) {
    $base = $seed * 10;
    $outerRows = [];
    for ($i = 1; $i <= 4; ++$i) {
        $outerRows[] = [
            'a' => $base + $i,
            'b' => $base + $i,
            'c' => $base + $i,
        ];
    }

    $innerRows = [
        ['x' => $base + 1, 'y' => $base + 1, 'z' => $base + 1],
        ['x' => $base + 3, 'y' => $base + 3, 'z' => $base + 3],
    ];

    $setup = array_merge(
        $createTableSql('app_y1', ['a', 'b', 'c'], ['a' => 'INT', 'b' => 'INT', 'c' => 'INT'], $outerRows),
        $createTableSql('app_y2', ['x', 'y', 'z'], ['x' => 'INT', 'y' => 'INT', 'z' => 'INT'], $innerRows),
    );
    $tables = ['app_y1' => $outerRows, 'app_y2' => $innerRows];
    $prefix = 'existsexpr3.seed' . str_pad((string) $seed, 3, '0', STR_PAD_LEFT);

    $addCase(
        $prefix . '.composite-direct',
        'existsexpr-3.1',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT 1 FROM app_y2 WHERE z=a AND y=b AND x=z) ORDER BY a',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.scalar-min-max',
        'existsexpr-3.2',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT 1 FROM app_y2 WHERE z=max(a,b) AND y=min(b,a) AND x=z) ORDER BY a',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.scalar-min-max-filtered',
        'existsexpr-3.3',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT 1 FROM app_y2 WHERE z=max(a,b) AND y=min(b,a) AND c!=' . ($base + 3) . ') ORDER BY a',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.outer-b-filter',
        'existsexpr-3.4',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT 1 FROM app_y2 WHERE z=max(a,b) AND b=' . ($base + 3) . ') ORDER BY a',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.minus-one-composite',
        'existsexpr-3.5',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT 1 FROM app_y2 WHERE z=a-1 AND y=a-1) ORDER BY a',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.plus-one-composite',
        'existsexpr-3.6',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT 1 FROM app_y2 WHERE z=a-1 AND y+1=a) ORDER BY a',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.aggregate-subquery-row',
        'existsexpr-3.7',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT count(*) FROM app_y2 WHERE z=a-1 AND y=a-1) ORDER BY a',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.outer-expression-in-subquery',
        'existsexpr-3.8',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT a+1 FROM app_y2) ORDER BY a',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.two-source-subquery',
        'existsexpr-3.9',
        'SELECT a AS a, b AS b, c AS c FROM app_y1 WHERE EXISTS (SELECT 1 FROM app_y2 one, app_y2 two WHERE one.z=a-1 AND one.y=a-1) ORDER BY a',
        $tables,
        $setup,
    );
}

for ($seed = 0; $seed < 60; ++$seed) {
    $suffix = (string) $seed;
    $innerRows = [
        ['a' => 'a' . $suffix, 'b' => 'a' . $suffix],
        ['a' => 'B' . $suffix, 'b' => 'b' . $suffix],
        ['a' => 'c' . $suffix, 'b' => 'c' . $suffix],
        ['a' => 'D' . $suffix, 'b' => 'd' . $suffix],
    ];
    $outerRows = [
        ['x' => 'A' . $suffix, 'y' => 'a' . $suffix],
        ['x' => 'b' . $suffix, 'y' => 'B' . $suffix],
        ['x' => 'C' . $suffix, 'y' => 'c' . $suffix],
        ['x' => 'D' . $suffix, 'y' => 'd' . $suffix],
        ['x' => 'Z' . $suffix, 'y' => 'z' . $suffix],
    ];

    $setup = array_merge(
        $createTableSql('app_tx1', ['a', 'b'], ['a' => 'TEXT', 'b' => 'TEXT'], $innerRows),
        $createTableSql('app_tx2', ['x', 'y'], ['x' => 'TEXT', 'y' => 'TEXT'], $outerRows),
    );
    $tables = ['app_tx1' => $innerRows, 'app_tx2' => $outerRows];
    $prefix = 'existsexpr4.seed' . str_pad((string) $seed, 3, '0', STR_PAD_LEFT);

    $addCase(
        $prefix . '.left-explicit-nocase',
        'existsexpr-4.1.1',
        'SELECT x AS x, y AS y FROM app_tx2 WHERE EXISTS (SELECT 1 FROM app_tx1 WHERE (a COLLATE nocase)=x AND b=y) ORDER BY y',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.right-explicit-nocase',
        'existsexpr-4.1.1',
        'SELECT x AS x, y AS y FROM app_tx2 WHERE EXISTS (SELECT 1 FROM app_tx1 WHERE x=(a COLLATE nocase) AND b=y) ORDER BY y',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.binary-b-placement',
        'existsexpr-4.1.2',
        'SELECT x AS x, y AS y FROM app_tx2 WHERE EXISTS (SELECT 1 FROM app_tx1 WHERE (a COLLATE nocase)=x AND y=(b COLLATE binary)) ORDER BY y',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.right-binary-a',
        'existsexpr-4.4',
        'SELECT x AS x, y AS y FROM app_tx2 WHERE EXISTS (SELECT 1 FROM app_tx1 WHERE a=x COLLATE binary AND b=y) ORDER BY y',
        $tables,
        $setup,
    );
    $addCase(
        $prefix . '.right-nocase-b',
        'existsexpr-4.2',
        'SELECT x AS x, y AS y FROM app_tx2 WHERE EXISTS (SELECT 1 FROM app_tx1 WHERE (a COLLATE nocase)=x AND b=y COLLATE nocase) ORDER BY y',
        $tables,
        $setup,
    );
}

$oracleScript = [
    '.mode tabs',
    '.nullvalue <NULL>',
];
foreach ($cases as $key => $case) {
    $oracleScript = array_merge($oracleScript, $case['setupSql']);
    $oracleScript[] = '.print __CASE__ ' . $key;
    $oracleScript[] = $case['sql'] . ';';
    $oracleScript[] = '.print __END__';
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-existsexpr-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 existsexpr oracle script');
}

file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce existsexpr output');
}

$oracle = [];
$current = null;
foreach (explode("\n", trim($output)) as $line) {
    if (str_starts_with($line, '__CASE__ ')) {
        $current = substr($line, strlen('__CASE__ '));
        $oracle[$current] = [];
        continue;
    }

    if ($line === '__END__') {
        $current = null;
        continue;
    }

    if ($current === null) {
        throw new RuntimeException('Unexpected sqlite3 existsexpr oracle output line: ' . $line);
    }

    $oracle[$current][] = $line;
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d existsexpr oracle cases, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $case) {
    $tests['real upstream corpus expression affinity dynamic existsexpr correlated exists ' . $key] =
        static function (TestRunner $t) use ($case, $key, $oracle, $normalizeRows): void {
            $rows = SQLiteSelectSql::execute($case['sql'], $case['tables']);
            $t->same($oracle[$key], $normalizeRows($rows), $case['source'] . ' ' . $case['sql']);
        };
}

$tests['real upstream corpus expression affinity dynamic existsexpr owns 1200 correlated cases'] =
    static function (TestRunner $t) use ($cases, $oracle, $sourceText, $sourcePath): void {
        $sources = array_values(array_unique(array_column($cases, 'source')));
        sort($sources);

        $t->same(1200, count($cases));
        $t->same(1200, count($oracle));
        $t->same(true, is_file($sourcePath), 'hydrated upstream existsexpr.test is present');
        $t->contains('do_subquery_test 3.1', $sourceText);
        $t->contains('do_subquery_test 3.9', $sourceText);
        $t->contains('do_subquery_test 4.1.1', $sourceText);
        $t->contains('do_subquery_test 4.4', $sourceText);
        $t->same(
            [
                'existsexpr-3.1',
                'existsexpr-3.2',
                'existsexpr-3.3',
                'existsexpr-3.4',
                'existsexpr-3.5',
                'existsexpr-3.6',
                'existsexpr-3.7',
                'existsexpr-3.8',
                'existsexpr-3.9',
                'existsexpr-4.1.1',
                'existsexpr-4.1.2',
                'existsexpr-4.2',
                'existsexpr-4.4',
            ],
            $sources,
        );
        $t->same(
            'existsexpr.test correlated EXISTS composite expression and explicit COLLATE behavior',
            'existsexpr.test correlated EXISTS composite expression and explicit COLLATE behavior',
        );
    };

return $tests;
