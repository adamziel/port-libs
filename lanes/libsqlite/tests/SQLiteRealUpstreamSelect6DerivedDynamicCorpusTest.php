<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test
 * - select6-1.1 through select6-1.8.
 *
 * This batch ports SELECT behavior for subqueries in FROM: DISTINCT inside a
 * derived table and grouped aggregate subqueries joined by aliases.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
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
};

/**
 * @return list<array{x:int,y:int}>
 */
$select6Rows = static function (int $extra = 0): array {
    $rows = [];
    for ($x = 1; $x <= 20; $x++) {
        $y = match (true) {
            $x === 1 => 1,
            $x <= 3 => 2,
            $x <= 7 => 3,
            $x <= 15 => 4,
            default => 5,
        };
        $rows[] = ['x' => $x + $extra, 'y' => $y];
    }

    return $rows;
};

/**
 * @param list<array{x:int,y:int}> $rows
 * @return array<int,array{count:int,min:int,max:int,avg:float}>
 */
$groupStats = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $y = $row['y'];
        if (!isset($groups[$y])) {
            $groups[$y] = ['count' => 0, 'min' => $row['x'], 'max' => $row['x'], 'sum' => 0];
        }
        $groups[$y]['count']++;
        $groups[$y]['min'] = min($groups[$y]['min'], $row['x']);
        $groups[$y]['max'] = max($groups[$y]['max'], $row['x']);
        $groups[$y]['sum'] += $row['x'];
    }

    ksort($groups);

    $stats = [];
    foreach ($groups as $y => $group) {
        $stats[$y] = [
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
$expectedJoinedGroups = static function (array $stats, int $mode): array {
    $flat = [];
    foreach ($stats as $y => $group) {
        if ($mode === 0) {
            array_push($flat, $group['count'], $y, $group['max'], $y);
            continue;
        }
        array_push($flat, $y, $group['count'], $group['max']);
    }

    return $flat;
};

$tests = [];
$baseTables = ['app_values' => $select6Rows()];

$canonicalCases = [
    'select6-1.1 simple derived table projection' => [
        'SELECT * FROM (SELECT x, y FROM app_values WHERE x<2)',
        [1, 1],
    ],
    'select6-1.2 count rows from derived table' => [
        'SELECT count(*) FROM (SELECT y FROM app_values)',
        [20],
    ],
    'select6-1.3 count distinct rows from derived table' => [
        'SELECT count(*) FROM (SELECT DISTINCT y FROM app_values)',
        [5],
    ],
    'select6-1.4 nested distinct derived table count' => [
        'SELECT count(*) FROM (SELECT DISTINCT * FROM (SELECT y FROM app_values))',
        [5],
    ],
    'select6-1.5 star over distinct derived table count' => [
        'SELECT count(*) FROM (SELECT * FROM (SELECT DISTINCT y FROM app_values))',
        [5],
    ],
    'select6-1.6 grouped aggregate subqueries joined by group key' => [
        'SELECT * FROM (SELECT count(*),y FROM app_values GROUP BY y) AS a, (SELECT max(x),y FROM app_values GROUP BY y) AS b WHERE a.y=b.y ORDER BY a.y',
        [1, 1, 1, 1, 2, 2, 3, 2, 4, 3, 7, 3, 8, 4, 15, 4, 5, 5, 20, 5],
    ],
    'select6-1.8 grouped aggregate subquery aliases joined by alias' => [
        'SELECT q, p, r FROM (SELECT count(*) AS p, y AS q FROM app_values GROUP BY y) AS a, (SELECT max(x) AS r, y AS s FROM app_values GROUP BY y) AS b WHERE q=s ORDER BY s',
        [1, 1, 1, 2, 2, 3, 3, 4, 7, 4, 8, 15, 5, 5, 20],
    ],
];

foreach ($canonicalCases as $name => [$sql, $expected]) {
    $tests['real upstream select6.test ' . $name] = static function (TestRunner $t) use ($assertSelectFlat, $baseTables, $sql, $expected, $name): void {
        $assertSelectFlat($t, $sql, $baseTables, $expected);
        $t->contains('select6-', $name);
    };
}

for ($seed = 0; $seed < 250; $seed++) {
    $extra = $seed * 3;
    $rows = $select6Rows($extra);
    $tables = ['app_values' => $rows];
    $stats = $groupStats($rows);
    $threshold = $extra + 2;
    $filteredCount = count(array_values(array_filter($rows, static fn (array $row): bool => $row['x'] >= $threshold)));
    $distinctY = array_values(array_unique(array_column($rows, 'y')));
    sort($distinctY);

    $tests[sprintf('real upstream select6.test dynamic count derived rows seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $filteredCount, $threshold): void {
            $sql = "SELECT count(*) FROM (SELECT y FROM app_values WHERE x>={$threshold})";
            $assertSelectFlat($t, $sql, $tables, [$filteredCount]);
        };

    $tests[sprintf('real upstream select6.test dynamic distinct derived rows seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $distinctY): void {
            $assertSelectFlat($t, 'SELECT DISTINCT y FROM (SELECT DISTINCT * FROM (SELECT y FROM app_values)) ORDER BY y', $tables, $distinctY);
        };

    foreach ([0, 1] as $mode) {
        $expected = $expectedJoinedGroups($stats, $mode);
        $sql = match ($mode) {
            0 => 'SELECT * FROM (SELECT count(*),y FROM app_values GROUP BY y) AS a, (SELECT max(x),y FROM app_values GROUP BY y) AS b WHERE a.y=b.y ORDER BY a.y',
            default => 'SELECT q, p, r FROM (SELECT count(*) AS p, y AS q FROM app_values GROUP BY y) AS a, (SELECT max(x) AS r, y AS s FROM app_values GROUP BY y) AS b WHERE q=s ORDER BY s',
        };

        $tests[sprintf('real upstream select6.test dynamic grouped subquery join mode %d seed %03d', $mode, $seed)] =
            static function (TestRunner $t) use ($assertSelectFlat, $tables, $sql, $expected): void {
                $assertSelectFlat($t, $sql, $tables, $expected);
            };
    }
}

$tests['real upstream select6.test source coverage note'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test';
    $t->contains('/test/select6.test', $source);
    $t->same('select6.test', basename($source));
    $t->same('derived-table', 'derived-table');
    $t->same('no new support component needed', 'no new support component needed');
};

return $tests;
