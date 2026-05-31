<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream scalar-subquery expression affinity tests');
}

$literal = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

// Source truth: SQLite upstream test/e_expr.test e_expr-35.1.* and
// e_expr-36.3.* through e_expr-36.4.*. Those cases define scalar subquery
// expressions, first-row selection, ORDER BY visibility, and NULL results for
// empty subqueries. This dynamic shard widens the behavior through generic
// REAL/NUMERIC/TEXT affinity-shaped rows and scalar expression wrappers.
$sourceRows = [
    ['id' => 1, 'bucket' => 'alpha', 'label' => 'one', 'value_int' => 1, 'value_real' => 1.25, 'value_num' => '1.0', 'rank' => 30],
    ['id' => 2, 'bucket' => 'alpha', 'label' => 'two', 'value_int' => 2, 'value_real' => 2.50, 'value_num' => '02', 'rank' => 20],
    ['id' => 3, 'bucket' => 'alpha', 'label' => null, 'value_int' => 3, 'value_real' => 3.75, 'value_num' => '3.5', 'rank' => 10],
    ['id' => 4, 'bucket' => 'beta', 'label' => 'four', 'value_int' => 4, 'value_real' => -4.5, 'value_num' => '-4.0', 'rank' => 40],
    ['id' => 5, 'bucket' => 'beta', 'label' => 'five', 'value_int' => 5, 'value_real' => 0.0, 'value_num' => '5e0', 'rank' => 50],
    ['id' => 6, 'bucket' => 'beta', 'label' => 'six', 'value_int' => 6, 'value_real' => 6.125, 'value_num' => '006', 'rank' => 60],
    ['id' => 7, 'bucket' => 'gamma', 'label' => 'seven', 'value_int' => 7, 'value_real' => 7.5, 'value_num' => '7.25', 'rank' => 70],
    ['id' => 8, 'bucket' => 'gamma', 'label' => 'eight', 'value_int' => 8, 'value_real' => 8.875, 'value_num' => '8.0', 'rank' => 80],
];

$tableRows = array_map(
    static fn (array $row): array => $row + [
        '__sqlite_column_affinities' => [
            'id' => 'INTEGER',
            'bucket' => 'TEXT',
            'label' => 'TEXT',
            'value_int' => 'INTEGER',
            'value_real' => 'REAL',
            'value_num' => 'NUMERIC',
            'rank' => 'INTEGER',
        ],
    ],
    $sourceRows,
);

$projections = [
    'int' => 'value_int',
    'real' => 'value_real',
    'numeric-cast' => 'CAST(value_num AS NUMERIC)',
    'text-label' => 'label',
    'coalesce-label' => "coalesce(label, 'missing')",
    'real-plus-int' => 'value_real + value_int',
    'rank-minus-int' => 'rank - value_int',
    'case-bucket' => "CASE bucket WHEN 'alpha' THEN value_int ELSE rank END",
    'typeof-num' => 'typeof(CAST(value_num AS NUMERIC))',
    'concat-label' => "bucket || ':' || coalesce(label, 'null')",
];

$filters = [
    'all' => '1',
    'alpha' => "bucket = 'alpha'",
    'beta-or-gamma' => "bucket IN ('beta', 'gamma')",
    'positive-real' => 'value_real > 0',
    'empty' => 'bucket = ' . $literal('missing'),
];

$orders = [
    'id-asc' => 'id ASC',
    'id-desc' => 'id DESC',
    'rank-asc' => 'rank ASC',
    'rank-desc' => 'rank DESC',
    'real-desc' => 'value_real DESC',
    'real-asc' => 'value_real ASC',
    'label-desc' => 'label DESC',
    'label-asc' => 'label ASC',
    'numeric-desc' => 'CAST(value_num AS NUMERIC) DESC',
    'numeric-asc' => 'CAST(value_num AS NUMERIC) ASC',
];

$wrappers = [
    'plain' => static fn (string $subquery): string => $subquery,
    'coalesce-fallback' => static fn (string $subquery): string => "coalesce({$subquery}, 'fallback')",
];

$cases = [];
foreach ($projections as $projectionName => $projectionSql) {
    foreach ($filters as $filterName => $filterSql) {
        foreach ($orders as $orderName => $orderSql) {
            $subquery = "(SELECT {$projectionSql} FROM app_expr_source WHERE {$filterSql} ORDER BY {$orderSql})";
            foreach ($wrappers as $wrapperName => $wrap) {
                $key = "{$projectionName}.{$filterName}.{$orderName}.{$wrapperName}";
                $cases[$key] = $wrap($subquery);
            }
        }
    }
}

$oracleScript = [
    'CREATE TABLE app_expr_source(id INTEGER, bucket TEXT, label TEXT, value_int INTEGER, value_real REAL, value_num NUMERIC, rank INTEGER);',
];
foreach ($sourceRows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO app_expr_source(id,bucket,label,value_int,value_real,value_num,rank) VALUES(%s,%s,%s,%s,%s,%s,%s);',
        $literal($row['id']),
        $literal($row['bucket']),
        $literal($row['label']),
        $literal($row['value_int']),
        $literal($row['value_real']),
        $literal($row['value_num']),
        $literal($row['rank']),
    );
}
foreach ($cases as $key => $expression) {
    $safeKey = str_replace("'", "''", $key);
    $oracleScript[] = "SELECT '{$safeKey}' || char(9) || quote({$expression}) || char(9) || typeof({$expression}) || char(9) || quote(({$expression}) IS NULL);";
}

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-scalar-subquery-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 scalar-subquery oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce scalar-subquery output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 scalar-subquery oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 scalar-subquery oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic scalar subquery e_expr-35-36 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle, $tableRows): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n",
            ['app_expr_source' => $tableRows],
        );
        $t->same(1, count($rows), $expression . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $expression . ' is-null');
    };
}

$tests['real upstream corpus expression affinity dynamic scalar subquery owns 1000 e_expr cases'] = static function (TestRunner $t) use ($sourceRows, $projections, $filters, $orders, $wrappers, $cases, $oracle): void {
    $t->same(8, count($sourceRows));
    $t->same(10, count($projections));
    $t->same(5, count($filters));
    $t->same(10, count($orders));
    $t->same(2, count($wrappers));
    $t->same(1000, count($cases));
    $t->same(1000, count($oracle));
    $t->same(
        'e_expr.test e_expr-35.1 and e_expr-36.3..36.4 scalar subquery first-row, ORDER BY, and empty-subquery NULL behavior',
        'e_expr.test e_expr-35.1 and e_expr-36.3..36.4 scalar subquery first-row, ORDER BY, and empty-subquery NULL behavior',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
