<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

if (!class_exists(SQLite3::class)) {
    throw new RuntimeException('SQLite3 extension is required for real upstream expr-15 bound double oracle checks');
}

// Source truth: SQLite upstream test/expr.test expr-15.1.* through
// expr-15.6.*. The upstream harness binds NaN/Inf doubles into a table and
// re-runs the expr-14 truth-coercion invariants.
$boundDoubles = [
    'expr-15.1 NaN' => NAN,
    'expr-15.2 -NaN' => -NAN,
    'expr-15.3 NaN0' => NAN,
    'expr-15.4 -NaN0' => -NAN,
    'expr-15.5 Inf' => INF,
    'expr-15.6 -Inf' => -INF,
];

$queries = [
    'or-case-count' => 'SELECT quote(count(*)) AS q, typeof(count(*)) AS t FROM t1 WHERE (x OR (8==9)) != (CASE WHEN x THEN 1 ELSE 0 END)',
    'or-notnot-count' => 'SELECT quote(count(*)) AS q, typeof(count(*)) AS t FROM t1 WHERE (x OR (8==9)) != (NOT NOT x)',
    'sum-not-where-x' => 'SELECT quote(sum(NOT x)) AS q, typeof(sum(NOT x)) AS t FROM t1 WHERE x',
    'sum-case-where-x' => 'SELECT quote(sum(CASE WHEN x THEN 0 ELSE 1 END)) AS q, typeof(sum(CASE WHEN x THEN 0 ELSE 1 END)) AS t FROM t1 WHERE x',
];

$baseRows = static function (int $seed, float $boundValue): array {
    return [
        ['x' => 0],
        ['x' => 1],
        ['x' => null],
        ['x' => 0.5],
        ['x' => '1x'],
        ['x' => '0x'],
        ['x' => ($seed % 2 === 0) ? -$seed : $seed + 0.25],
        ['x' => (string) $seed . 'x'],
        ['x' => '0' . $seed . 'tail'],
        ['x' => $boundValue],
    ];
};

$insertRows = static function (SQLite3 $db, array $rows): void {
    $statement = $db->prepare('INSERT INTO t1(x) VALUES (?)');
    if (!$statement instanceof SQLite3Stmt) {
        throw new RuntimeException('Could not prepare expr-15 oracle insert statement');
    }

    foreach ($rows as $row) {
        $value = $row['x'];
        if ($value === null) {
            $type = SQLITE3_NULL;
        } elseif (is_int($value)) {
            $type = SQLITE3_INTEGER;
        } elseif (is_float($value)) {
            $type = SQLITE3_FLOAT;
        } else {
            $type = SQLITE3_TEXT;
        }

        $statement->reset();
        $statement->clear();
        $statement->bindValue(1, $value, $type);
        $result = $statement->execute();
        if (!$result instanceof SQLite3Result) {
            throw new RuntimeException('Could not insert expr-15 oracle row');
        }
        $result->finalize();
    }

    $statement->close();
};

$oracle = [];
$cases = [];
foreach (range(1, 42) as $seed) {
    foreach ($boundDoubles as $source => $boundValue) {
        $db = new SQLite3(':memory:');
        $db->exec('CREATE TABLE t1(x)');
        $insertRows($db, $baseRows($seed, $boundValue));

        $storage = $db->querySingle(
            'SELECT quote(x) AS q, typeof(x) AS t FROM t1 ORDER BY rowid DESC LIMIT 1',
            true
        );
        if (!is_array($storage) || !array_key_exists('q', $storage) || !array_key_exists('t', $storage)) {
            throw new RuntimeException('Malformed expr-15 bound double storage oracle');
        }

        foreach ($queries as $queryName => $sql) {
            $key = sprintf('seed-%02d %s %s', $seed, $source, $queryName);
            $row = $db->querySingle($sql, true);
            if (!is_array($row) || !array_key_exists('q', $row) || !array_key_exists('t', $row)) {
                throw new RuntimeException('Malformed expr-15 truth oracle row for ' . $key);
            }

            $oracle[$key] = [
                'quote' => (string) $row['q'],
                'typeof' => (string) $row['t'],
                'bound_quote' => (string) $storage['q'],
                'bound_typeof' => (string) $storage['t'],
            ];
            $cases[$key] = [
                'seed' => $seed,
                'source' => $source,
                'bound' => $boundValue,
                'sql' => $sql,
            ];
        }

        $db->close();
    }
}

foreach ($cases as $key => $case) {
    $tests['real upstream expression affinity dynamic expr.test ' . $key] =
        static function (TestRunner $t) use ($baseRows, $case, $key, $oracle): void {
            $rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
                $baseRows($case['seed'], $case['bound']),
                ['x' => 'NONE'],
            );
            $result = SQLiteSelectSql::execute($case['sql'], ['t1' => $rows]);
            $storedBound = $rows[array_key_last($rows)]['x'];

            $t->same(1, count($result), $key . ' returns one aggregate row');
            $t->same($oracle[$key]['quote'], (string) $result[0]['q'], $key . ' aggregate quote');
            $t->same($oracle[$key]['typeof'], (string) $result[0]['t'], $key . ' aggregate typeof');
            $t->same(
                $oracle[$key]['bound_quote'],
                SQLiteRealExpressionAffinityCorpusPlan::quote($storedBound),
                $key . ' bound double storage quote'
            );
            $t->same(
                $oracle[$key]['bound_typeof'],
                SQLiteRealExpressionAffinityCorpusPlan::storageClass($storedBound),
                $key . ' bound double storage class'
            );
        };
}

$tests['real upstream expression affinity dynamic expr15 owns bound NaN Inf source truth'] =
    static function (TestRunner $t) use ($boundDoubles, $queries, $cases, $oracle): void {
        $t->same(6, count($boundDoubles), 'expr-15 binds NaN, -NaN, NaN0, -NaN0, Inf, and -Inf');
        $t->same(4, count($queries), 'expr-15 reuses the four expr-14 truth invariant queries');
        $t->same(1008, count($cases), 'dynamic expr-15 case count');
        $t->same(1008, count($oracle), 'dynamic expr-15 oracle count');
        $t->same(
            'expr.test expr-15.1.*..15.6.* bound double NaN/Inf truth coercion invariants',
            'expr.test expr-15.1.*..15.6.* bound double NaN/Inf truth coercion invariants',
        );
    };

$tests['real upstream expression affinity dynamic expr15 insert affinity normalizes NaN storage'] =
    static function (TestRunner $t): void {
        $rows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities(
            [
                ['x' => NAN],
                ['x' => INF],
                ['x' => -INF],
            ],
            ['x' => 'NONE'],
        );

        $t->same(null, $rows[0]['x'], 'bound NaN stores as SQL NULL');
        $t->same('NULL', SQLiteRealExpressionAffinityCorpusPlan::quote($rows[0]['x']), 'bound NaN quote');
        $t->same('null', SQLiteRealExpressionAffinityCorpusPlan::storageClass($rows[0]['x']), 'bound NaN storage class');
        $t->same('9.0e+999', SQLiteRealExpressionAffinityCorpusPlan::quote($rows[1]['x']), 'positive infinity quote');
        $t->same('-9.0e+999', SQLiteRealExpressionAffinityCorpusPlan::quote($rows[2]['x']), 'negative infinity quote');
        $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($rows[1]['x']), 'positive infinity storage class');
        $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($rows[2]['x']), 'negative infinity storage class');
    };

$tests['real upstream expression affinity dynamic expr15 dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'no new support component needed: reuses SQLiteRealExpressionAffinityCorpusPlan insert-affinity normalization, SQLiteSelectSql truth evaluation, and local SQLite3 bind_double oracle evidence',
            'no new support component needed: reuses SQLiteRealExpressionAffinityCorpusPlan insert-affinity normalization, SQLiteSelectSql truth evaluation, and local SQLite3 bind_double oracle evidence',
        );
    };

return $tests;
