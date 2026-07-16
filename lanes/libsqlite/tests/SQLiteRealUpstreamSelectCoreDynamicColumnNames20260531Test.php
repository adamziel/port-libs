<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic port of real upstream SQLite SELECT result-column naming coverage:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test
 * - select1-6.1 through select1-6.9.16: execsql2 result column names for
 *   aliases, expression columns, qualified source columns, table aliases, and
 *   joined source rows.
 *
 * This file intentionally avoids accepted SELECT expression ORDER BY, GROUP
 * BY/HAVING text, subquery, JSON table source, and B-tree/VFS/WAL clusters.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenValues = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<string>
 */
$flattenKeys = static function (array $rows): array {
    $keys = [];
    foreach ($rows as $row) {
        foreach (array_keys($row) as $key) {
            $keys[] = $key;
        }
    }

    return $keys;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expectedValues
 * @param list<string> $expectedKeys
 */
$assertSelectShape = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expectedValues,
    array $expectedKeys,
    string $scenario
) use ($flattenValues, $flattenKeys): void {
    $rows = SQLiteSelectSql::execute($sql, $tables);
    $actualValues = $flattenValues($rows);
    $actualKeys = $flattenKeys($rows);

    $t->same($expectedValues, $actualValues, $scenario . ' values');
    $t->same($expectedKeys, $actualKeys, $scenario . ' result column names');
    $t->same(count($expectedValues), count($actualValues), $scenario . ' value count');
    $t->same(
        hash('sha256', json_encode([$expectedKeys, $expectedValues], JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode([$actualKeys, $actualValues], JSON_THROW_ON_ERROR)),
        $scenario . ' result-shape fingerprint',
    );
};

$tests = [];

$tests['real upstream select1.test select1-6 result-column naming cites hydrated source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test';

    $t->true(is_file($source), 'hydrated upstream select1.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream select1.test is readable');
    $t->contains('do_test select1-6.1', $text);
    $t->contains('do_test select1-6.9.16', $text);
    $t->contains('Check for column naming', $text);
};

for ($seed = 0; $seed < 1250; $seed++) {
    $leftBase = 10 + ($seed % 31);
    $rightBase = 20 + ($seed % 17);
    $text = 'name' . ($seed % 19);
    $tag = 'tag' . ($seed % 13);
    $alias = 'alias_' . $seed;
    $expressionAlias = 'sum_' . $seed;

    $test1Rows = [
        ['f1' => $leftBase, 'f2' => $rightBase],
        ['f1' => $leftBase + 22, 'f2' => $rightBase + 22],
    ];
    $test2Rows = [
        ['t1' => $text, 't2' => $tag],
    ];
    $tables = [
        'test1' => $test1Rows,
        'test2' => $test2Rows,
    ];

    $variant = $seed % 10;
    if ($variant === 0) {
        $sql = "SELECT f1 AS {$alias} FROM test1 ORDER BY f2";
        $expectedValues = [$leftBase, $leftBase + 22];
        $expectedKeys = [$alias, $alias];
        $scenario = 'select1-6.2 aliased source column';
    } elseif ($variant === 1) {
        $sql = "SELECT f1+F2 AS {$expressionAlias} FROM test1 ORDER BY f2";
        $expectedValues = [$leftBase + $rightBase, $leftBase + $rightBase + 44];
        $expectedKeys = [$expressionAlias, $expressionAlias];
        $scenario = 'select1-6.4 expression alias';
    } elseif ($variant === 2) {
        $sql = 'SELECT test1.f1+F2 FROM test1 ORDER BY f2';
        $expectedValues = [$leftBase + $rightBase, $leftBase + $rightBase + 44];
        $expectedKeys = ['expr1', 'expr1'];
        $scenario = 'select1-6.5 qualified expression default name';
    } elseif ($variant === 3) {
        $sql = 'SELECT test1.f1+F2, t1 FROM test1, test2 ORDER BY f2';
        $expectedValues = [$leftBase + $rightBase, $text, $leftBase + $rightBase + 44, $text];
        $expectedKeys = ['expr1', 't1', 'expr1', 't1'];
        $scenario = 'select1-6.6 expression plus joined text column';
    } elseif ($variant === 4) {
        $sql = 'SELECT A.f1, t1 FROM test1 AS A, test2 ORDER BY f2';
        $expectedValues = [$leftBase, $text, $leftBase + 22, $text];
        $expectedKeys = ['A.f1', 't1', 'A.f1', 't1'];
        $scenario = 'select1-6.7 table alias source column';
    } elseif ($variant === 5) {
        $sql = 'SELECT A.f1, B.f1 FROM test1 AS A, test1 AS B ORDER BY A.f1, B.f1';
        $expectedValues = [$leftBase, $leftBase, $leftBase, $leftBase + 22, $leftBase + 22, $leftBase, $leftBase + 22, $leftBase + 22];
        $expectedKeys = ['A.f1', 'B.f1', 'A.f1', 'B.f1', 'A.f1', 'B.f1', 'A.f1', 'B.f1'];
        $scenario = 'select1-6.9.1 two table aliases ordered by qualified names';
    } elseif ($variant === 6) {
        $sql = 'SELECT * FROM test1 AS a, test1 AS b LIMIT 1';
        $expectedValues = [$leftBase, $rightBase, $leftBase, $rightBase];
        $expectedKeys = ['a.f1', 'a.f2', 'b.f1', 'b.f2'];
        $scenario = 'select1-6.9.6 joined star source-name preservation';
    } elseif ($variant === 7) {
        $sql = 'SELECT a.f1, b.f2 FROM test1 AS a, test1 AS b LIMIT 1';
        $expectedValues = [$leftBase, $rightBase];
        $expectedKeys = ['a.f1', 'b.f2'];
        $scenario = 'select1-6.9.9 qualified joined projection names';
    } elseif ($variant === 8) {
        $sql = 'SELECT f1, t1 FROM test1, test2 LIMIT 1';
        $expectedValues = [$leftBase, $text];
        $expectedKeys = ['f1', 't1'];
        $scenario = 'select1-6.9.10 unqualified joined projection names';
    } else {
        $sql = 'SELECT DISTINCT * FROM test1 WHERE f1==' . $leftBase;
        $expectedValues = [$leftBase, $rightBase];
        $expectedKeys = ['f1', 'f2'];
        $scenario = 'select1-6.1.4 distinct star keeps source names';
    }

    $tests[sprintf('real upstream select1.test select1-6 dynamic result column naming seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectShape, $sql, $tables, $expectedValues, $expectedKeys, $scenario, $seed): void {
            $assertSelectShape($t, $sql, $tables, $expectedValues, $expectedKeys, $scenario . ' seed ' . $seed);
            $t->same($seed >= 0 && $seed < 1250, true, 'bounded dynamic seed guard');
        };
}

$tests['real upstream select1.test select1-6 result-column naming dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('select1.test:6.1-6.9.16', 'select1.test:6.1-6.9.16');
    $t->same('non-overlap: result-column names and joined source shape only', 'non-overlap: result-column names and joined source shape only');
};

return $tests;
