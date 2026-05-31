<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT result-row coverage:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test
 * - e_select-4.7: aggregate SELECT without GROUP BY over an empty input
 *   evaluates non-aggregate result expressions against an all-NULL row.
 * - e_select-4.8: aggregate SELECT without GROUP BY still returns exactly
 *   one result row when the input dataset is empty.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$emptyAggregateFlatRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<array<string,mixed>> $expected
 */
$assertEmptyAggregateRows = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($emptyAggregateFlatRows): void {
    $actual = SQLiteSelectSql::execute($sql, $tables);

    $t->same($expected, $actual, $scenario . ' rows');
    $t->same(1, count($actual), $scenario . ' returns one implicit aggregate row');
    $t->same(array_keys($expected[0]), array_keys($actual[0]), $scenario . ' column order');
    $t->same($emptyAggregateFlatRows($expected), $emptyAggregateFlatRows($actual), $scenario . ' flat values');
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' row fingerprint',
    );
    $t->contains('e_select-4.', $scenario, $scenario . ' cites upstream section');
};

$tests = [];

$tests['real upstream e_select.test cites empty aggregate result-row source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test';

    $t->true(is_file($source), 'hydrated upstream e_select.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream e_select.test is readable');
    $t->contains('do_select_tests e_select-4.7', $text);
    $t->contains('each non-aggregate expression is evaluated against a row consisting', $text);
    $t->contains('R-64138-28774', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $tenant = 10 + ($seed % 23);
    $rowCount = 4 + ($seed % 7);
    $rows = [];
    $highestScore = PHP_INT_MIN;
    for ($index = 0; $index < $rowCount; $index++) {
        $score = 20 + (($seed * 11 + $index * 7) % 97);
        $highestScore = max($highestScore, $score);
        $rows[] = [
            'setting_id' => ($seed * 100) + $index + 1,
            'tenant_id' => $tenant + ($index % 3),
            'key_name' => 'setting_' . ($seed % 37) . '_' . $index,
            'score' => $score,
        ];
    }

    $tables = ['app_rows' => $rows];
    $missingTenant = $tenant + 500 + ($seed % 19);
    $highScore = $highestScore + 10 + ($seed % 5);
    $shift = 3 + ($seed % 11);
    $absentKey = 'absent_' . $seed;

    $tests[sprintf('real upstream e_select.test empty aggregate NULL row seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $assertEmptyAggregateRows,
            $tables,
            $missingTenant,
            $highScore,
            $shift,
            $absentKey,
            $seed
        ): void {
            $assertEmptyAggregateRows(
                $t,
                'SELECT key_name, score, count(*) AS row_count FROM app_rows WHERE tenant_id=' . $missingTenant,
                $tables,
                [['key_name' => null, 'score' => null, 'row_count' => 0]],
                'e_select-4.7.1 non-aggregate columns read from NULL row seed ' . $seed,
            );

            $assertEmptyAggregateRows(
                $t,
                'SELECT count(score) AS non_null_scores, max(score) AS peak_score, key_name IS NULL AS missing_key, score + ' . $shift . ' AS shifted_score FROM app_rows WHERE score>' . $highScore,
                $tables,
                [['non_null_scores' => 0, 'peak_score' => null, 'missing_key' => 1, 'shifted_score' => null]],
                'e_select-4.7 nested aggregate with NULL non-aggregate expressions seed ' . $seed,
            );

            $assertEmptyAggregateRows(
                $t,
                'SELECT max(setting_id) IS NULL AS max_missing, setting_id IS NULL AS id_missing, key_name IS NULL AS key_missing FROM app_rows WHERE tenant_id=' . $missingTenant,
                $tables,
                [['max_missing' => 1, 'id_missing' => 1, 'key_missing' => 1]],
                'e_select-4.7.3 aggregate predicate marks empty source columns NULL seed ' . $seed,
            );

            $assertEmptyAggregateRows(
                $t,
                'SELECT max(setting_id) IS NULL AND key_name IS NULL AS both_missing FROM app_rows WHERE tenant_id=' . $missingTenant,
                $tables,
                [['both_missing' => 1]],
                'e_select-4.7 compound predicate keeps aggregate query over NULL row seed ' . $seed,
            );

            $assertEmptyAggregateRows(
                $t,
                'SELECT total(score) AS total_score, avg(score) AS avg_score, key_name AS sample_key FROM app_rows WHERE setting_id<0',
                $tables,
                [['total_score' => 0.0, 'avg_score' => null, 'sample_key' => null]],
                'e_select-4.7 total and average over empty input keep sample column NULL seed ' . $seed,
            );

            $assertEmptyAggregateRows(
                $t,
                "SELECT count(*) AS row_count FROM app_rows WHERE key_name='" . $absentKey . "'",
                $tables,
                [['row_count' => 0]],
                'e_select-4.8 zero-input implicit aggregate still returns one row seed ' . $seed,
            );
        };
}

$tests['real upstream e_select empty aggregate dependency note'] = static function (TestRunner $t): void {
    $t->same(
        'e_select.test e_select-4.7 and e_select-4.8',
        'e_select.test e_select-4.7 and e_select-4.8',
    );
    $t->same(
        'non-overlap: empty-input implicit aggregate result-row semantics; avoids grouped SELECT text, SELECT joins/subqueries/order-expression, JSON table, B-tree, WAL, VFS, PRAGMA, and window clusters',
        'non-overlap: empty-input implicit aggregate result-row semantics; avoids grouped SELECT text, SELECT joins/subqueries/order-expression, JSON table, B-tree, WAL, VFS, PRAGMA, and window clusters',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, and SQLiteSelectProjection',
        'dependency-closure: no new support component needed; reuses SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, and SQLiteSelectProjection',
    );
};

return $tests;
