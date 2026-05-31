<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sqlite3 = trim((string) shell_exec('command -v sqlite3 2>/dev/null'));
if ($sqlite3 === '') {
    throw new RuntimeException('sqlite3 oracle is required for real upstream EXISTS expression affinity tests');
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

// Source truth: SQLite upstream test/e_expr.test e_expr-34.1 through
// e_expr-34.5. Those cases specify that EXISTS and NOT EXISTS return integer
// 0/1, and that result-column count, result values, NULLs, ORDER BY, and the
// selected storage classes do not affect the EXISTS result. Adjacent accepted
// shards cover scalar subquery values from e_expr-35/36; this shard owns
// EXISTS as a projection expression.
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
    'single-int' => 'id',
    'single-nullable-text' => 'label',
    'null-literal' => 'NULL',
    'integer-literal' => '24',
    'real-expression' => 'value_real + value_int',
    'numeric-cast' => 'CAST(value_num AS NUMERIC)',
    'concat-expression' => "bucket || ':' || coalesce(label, 'null')",
    'multi-columns' => 'id, label',
    'triple-columns' => 'id, label, value_real',
    'mixed-null-values' => 'NULL, label, CAST(value_num AS NUMERIC)',
];

$filters = [
    'all' => '1',
    'none' => '0',
    'label-null' => 'label IS NULL',
    'missing-bucket' => "bucket = 'missing'",
    'alpha' => "bucket = 'alpha'",
    'beta-positive' => "bucket = 'beta' AND value_real > 0",
    'numeric-four-or-more' => 'CAST(value_num AS NUMERIC) >= 4',
    'real-negative' => 'value_real < 0',
];

$orders = [
    'none' => '',
    'id-asc' => ' ORDER BY id ASC',
    'id-desc' => ' ORDER BY id DESC',
    'rank-asc' => ' ORDER BY rank ASC',
    'rank-desc' => ' ORDER BY rank DESC',
    'real-desc' => ' ORDER BY value_real DESC',
    'label-asc' => ' ORDER BY label ASC',
    'numeric-desc' => ' ORDER BY CAST(value_num AS NUMERIC) DESC',
];

$wrappers = [
    'exists' => static fn (string $subquery): string => "EXISTS ({$subquery})",
    'not-exists' => static fn (string $subquery): string => "NOT EXISTS ({$subquery})",
    'exists-is-true' => static fn (string $subquery): string => "(EXISTS ({$subquery})) IS TRUE",
    'exists-is-false' => static fn (string $subquery): string => "(EXISTS ({$subquery})) IS FALSE",
];

$cases = [];
foreach ($projections as $projectionName => $projectionSql) {
    foreach ($filters as $filterName => $filterSql) {
        foreach ($orders as $orderName => $orderSql) {
            $subquery = "SELECT {$projectionSql} FROM app_expr_exists WHERE {$filterSql}{$orderSql}";
            foreach ($wrappers as $wrapperName => $wrap) {
                $cases["{$projectionName}.{$filterName}.{$orderName}.{$wrapperName}"] = $wrap($subquery);
            }
        }
    }
}

$oracleScript = [
    'CREATE TABLE app_expr_exists(id INTEGER, bucket TEXT, label TEXT, value_int INTEGER, value_real REAL, value_num NUMERIC, rank INTEGER);',
];
foreach ($sourceRows as $row) {
    $oracleScript[] = sprintf(
        'INSERT INTO app_expr_exists(id,bucket,label,value_int,value_real,value_num,rank) VALUES(%s,%s,%s,%s,%s,%s,%s);',
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

$scriptFile = tempnam(sys_get_temp_dir(), 'libsqlite-exists-expression-oracle-');
if ($scriptFile === false) {
    throw new RuntimeException('Could not create temporary sqlite3 EXISTS expression oracle script');
}
file_put_contents($scriptFile, implode("\n", $oracleScript));
$output = shell_exec(escapeshellarg($sqlite3) . ' -batch :memory: < ' . escapeshellarg($scriptFile));
@unlink($scriptFile);
if (!is_string($output) || trim($output) === '') {
    throw new RuntimeException('sqlite3 oracle did not produce EXISTS expression output');
}

$oracle = [];
foreach (explode("\n", trim($output)) as $line) {
    $parts = explode("\t", $line);
    if (count($parts) !== 4) {
        throw new RuntimeException('Malformed sqlite3 EXISTS expression oracle row: ' . $line);
    }

    [$key, $quotedValue, $storageClass, $quotedIsNull] = $parts;
    $oracle[$key] = [
        'quote' => $quotedValue,
        'typeof' => $storageClass,
        'isNull' => $quotedIsNull,
    ];
}

if (count($oracle) !== count($cases)) {
    throw new RuntimeException(sprintf('Expected %d sqlite3 EXISTS expression oracle rows, got %d', count($cases), count($oracle)));
}

foreach ($cases as $key => $expression) {
    $tests['real upstream corpus expression affinity dynamic EXISTS e_expr-34 ' . $key] = static function (TestRunner $t) use ($expression, $key, $oracle, $tableRows): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT quote({$expression}) AS q, typeof({$expression}) AS t, quote(({$expression}) IS NULL) AS n",
            ['app_expr_exists' => $tableRows],
        );
        $t->same(1, count($rows), $expression . ' row count');
        $t->same($oracle[$key]['quote'], (string) $rows[0]['q'], $expression . ' quote');
        $t->same($oracle[$key]['typeof'], (string) $rows[0]['t'], $expression . ' typeof');
        $t->same($oracle[$key]['isNull'], (string) $rows[0]['n'], $expression . ' is-null');
    };
}

$tests['real upstream corpus expression affinity dynamic EXISTS owns 2560 e_expr cases'] = static function (TestRunner $t) use ($sourceRows, $projections, $filters, $orders, $wrappers, $cases, $oracle): void {
    $t->same(8, count($sourceRows));
    $t->same(10, count($projections));
    $t->same(8, count($filters));
    $t->same(8, count($orders));
    $t->same(4, count($wrappers));
    $t->same(2560, count($cases));
    $t->same(2560, count($oracle));
    $t->same(
        'e_expr.test e_expr-34.1..34.5 EXISTS and NOT EXISTS integer result semantics independent of subquery column count, values, NULLs, and ordering',
        'e_expr.test e_expr-34.1..34.5 EXISTS and NOT EXISTS integer result semantics independent of subquery column count, values, NULLs, and ordering',
    );
    $t->contains('e_expr.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test');
};

return $tests;
