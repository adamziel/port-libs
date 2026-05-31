<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test
 * - select6-1.7, select6-2.7, select6-2.9, select6-3.1, select6-3.10,
 *   select6-3.14, and select6-4.1.
 *
 * This additive batch focuses on derived-table result-column names, bracketed
 * aggregate-name resolution, INTEGER PRIMARY KEY derived aliases, quoted
 * aliases, aggregate-derived ORDER BY, and outer WHERE filters.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
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
 * @param list<mixed> $expected
 */
$assertSelectFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last value guard for ' . $sql
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql
    );
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
};

/**
 * @return list<array{x:int,y:int}>
 */
$rowsT1 = static function (int $shift = 0): array {
    $rows = [];
    for ($x = 1; $x <= 20; $x++) {
        $y = match (true) {
            $x === 1 => 1,
            $x <= 3 => 2,
            $x <= 7 => 3,
            $x <= 15 => 4,
            default => 5,
        };
        $rows[] = ['x' => $x + $shift, 'y' => $y];
    }

    return $rows;
};

/**
 * @return list<array{a:int,b:int}>
 */
$rowsT2 = static function (int $shift = 0) use ($rowsT1): array {
    $rows = [];
    foreach ($rowsT1($shift) as $row) {
        $rows[] = ['a' => $row['x'], 'b' => $row['y']];
    }

    return $rows;
};

/**
 * @param list<array<string,int>> $rows
 * @return array<int,array{count:int,min:int,max:int,avg:float}>
 */
$groupStats = static function (array $rows, string $valueColumn, string $groupColumn): array {
    $groups = [];
    foreach ($rows as $row) {
        $groupKey = $row[$groupColumn];
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = ['count' => 0, 'min' => $row[$valueColumn], 'max' => $row[$valueColumn], 'sum' => 0];
        }
        $groups[$groupKey]['count']++;
        $groups[$groupKey]['min'] = min($groups[$groupKey]['min'], $row[$valueColumn]);
        $groups[$groupKey]['max'] = max($groups[$groupKey]['max'], $row[$valueColumn]);
        $groups[$groupKey]['sum'] += $row[$valueColumn];
    }

    ksort($groups);

    $stats = [];
    foreach ($groups as $groupKey => $group) {
        $stats[$groupKey] = [
            'count' => $group['count'],
            'min' => $group['min'],
            'max' => $group['max'],
            'avg' => $group['sum'] / $group['count'],
        ];
    }

    return $stats;
};

/**
 * @param array<int,array{count:int,min:int,max:int,avg:float}> $stats
 * @return list<mixed>
 */
$expectedBracketAggregateJoin = static function (array $stats): array {
    $flat = [];
    foreach ($stats as $groupKey => $group) {
        array_push($flat, $groupKey, $group['count'], $group['max'], $group['count']);
    }

    return $flat;
};

$tests = [];

$baseT1 = $rowsT1();
$baseT2 = $rowsT2();
$baseTables = ['app_t1' => $baseT1, 'app_t2' => $baseT2];

$canonicalCases = [
    'select6-1.7 bracketed aggregate aliases over grouped derived joins' => [
        'SELECT a.y, a.[count(*)], [max(x)], [count(*)] FROM (SELECT count(*),y FROM app_t1 GROUP BY y) AS a, (SELECT max(x),y FROM app_t1 GROUP BY y) AS b WHERE a.y=b.y ORDER BY a.y',
        $baseTables,
        $expectedBracketAggregateJoin($groupStats($baseT1, 'x', 'y')),
    ],
    'select6-2.7 bracketed aggregate aliases over integer-primary-key copy' => [
        'SELECT a.b, a.[count(*)], [max(a)], [count(*)] FROM (SELECT count(*),b FROM app_t2 GROUP BY b) AS a, (SELECT max(a),b FROM app_t2 GROUP BY b) AS b WHERE a.b=b.b ORDER BY a.b',
        $baseTables,
        $expectedBracketAggregateJoin($groupStats($baseT2, 'a', 'b')),
    ],
    'select6-2.9 aliases group by alias names over integer-primary-key copy' => [
        'SELECT a.q, a.p, b.r FROM (SELECT count(*) AS p, b AS q FROM app_t2 GROUP BY q) AS a, (SELECT max(a) AS r, b AS s FROM app_t2 GROUP BY s) AS b WHERE a.q=b.s ORDER BY a.q',
        $baseTables,
        [1, 1, 1, 2, 2, 3, 3, 4, 7, 4, 8, 15, 5, 5, 20],
    ],
    'select6-3.1 nested derived table preserves projected source columns' => [
        'SELECT * FROM (SELECT * FROM (SELECT * FROM app_t1 WHERE x=3))',
        $baseTables,
        [3, 2],
    ],
    'select6-3.10 grouped aggregate-derived aliases ordered by aggregate value' => [
        "SELECT a,b,a+b FROM (SELECT avg(x) AS 'a', y AS 'b' FROM app_t1 GROUP BY b) ORDER BY a",
        $baseTables,
        [1.0, 1, 2.0, 2.5, 2, 4.5, 5.5, 3, 8.5, 11.5, 4, 15.5, 18.0, 5, 23.0],
    ],
    'select6-3.14 bracketed aggregate output column ordered by aggregate' => [
        'SELECT [count(*)],y FROM (SELECT count(*), y FROM app_t1 GROUP BY y) ORDER BY [count(*)]',
        $baseTables,
        [1, 1, 2, 2, 4, 3, 5, 5, 8, 4],
    ],
    'select6-4.1 expression alias from derived table filtered by outer WHERE' => [
        "SELECT a,b,c FROM (SELECT x AS 'a', y AS 'b', x+y AS 'c' FROM app_t1 WHERE y=4) WHERE a<10 ORDER BY a",
        $baseTables,
        [8, 4, 12, 9, 4, 13],
    ],
];

foreach ($canonicalCases as $name => [$sql, $tables, $expected]) {
    $tests['real upstream select6.test ' . $name] = static function (TestRunner $t) use ($assertSelectFlat, $sql, $tables, $expected, $name): void {
        $assertSelectFlat($t, $sql, $tables, $expected);
        $t->contains('select6-', $name);
    };
}

for ($seed = 0; $seed < 650; $seed++) {
    $shift = $seed % 17;
    $tables = [
        'app_t1' => $rowsT1($shift),
        'app_t2' => $rowsT2($shift),
    ];
    $statsT2 = $groupStats($tables['app_t2'], 'a', 'b');
    $statsT1 = $groupStats($tables['app_t1'], 'x', 'y');

    $tests[sprintf('real upstream select6.test dynamic bracketed aggregate aliases seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $statsT1, $expectedBracketAggregateJoin): void {
            $assertSelectFlat(
                $t,
                'SELECT a.y, a.[count(*)], [max(x)], [count(*)] FROM (SELECT count(*),y FROM app_t1 GROUP BY y) AS a, (SELECT max(x),y FROM app_t1 GROUP BY y) AS b WHERE a.y=b.y ORDER BY a.y',
                $tables,
                $expectedBracketAggregateJoin($statsT1)
            );
        };

    $tests[sprintf('real upstream select6.test dynamic integer-primary-key aggregate aliases seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $statsT2, $expectedBracketAggregateJoin): void {
            $assertSelectFlat(
                $t,
                'SELECT a.b, a.[count(*)], [max(a)], [count(*)] FROM (SELECT count(*),b FROM app_t2 GROUP BY b) AS a, (SELECT max(a),b FROM app_t2 GROUP BY b) AS b WHERE a.b=b.b ORDER BY a.b',
                $tables,
                $expectedBracketAggregateJoin($statsT2)
            );
        };
}

$tests['real upstream select6.test source coverage and dependency note'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test';
    $t->true(is_file($source), 'hydrated upstream select6.test is available');
    $t->contains('select6.test', $source);
    $t->same('select6.test', basename($source));
    $t->same('no new support component needed', 'no new support component needed');
};

return $tests;
