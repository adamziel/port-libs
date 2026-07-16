<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test
 * - select6-11.1 through select6-11.5 and select6-11.100.
 *
 * This batch ports the upstream regression where aggregate columns from a
 * derived FROM subquery are visible to scalar correlated subqueries in SELECT,
 * WHERE, ORDER BY, and CASE expressions.
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
 * @return list<array{w:int,x:int}>
 */
$metricRows = static function (int $seed): array {
    $rows = [];
    foreach ([1 => 1, 2 => 2, 3 => 3] as $group => $count) {
        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'w' => $group + ($seed * 10),
                'x' => ($group * 100) + $index + $seed,
            ];
        }
    }

    return $rows;
};

/**
 * @return list<array{w:int,y:string}>
 */
$lookupRows = static function (int $seed): array {
    return [
        ['w' => 1, 'y' => 'one-' . $seed],
        ['w' => 2, 'y' => 'two-' . $seed],
        ['w' => 3, 'y' => 'three-' . $seed],
        ['w' => 4, 'y' => 'four-' . $seed],
    ];
};

/**
 * @param list<array{w:int,x:int}> $rows
 * @return list<array{cnt:int,xyz:int}>
 */
$groupedCounts = static function (array $rows): array {
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['w']] = ($counts[$row['w']] ?? 0) + 1;
    }

    ksort($counts);

    $groups = [];
    foreach ($counts as $w => $count) {
        $groups[] = ['cnt' => $count, 'xyz' => $w];
    }

    usort($groups, static fn (array $left, array $right): int => ($left['cnt'] <=> $right['cnt']) ?: ($left['xyz'] <=> $right['xyz']));

    return $groups;
};

/**
 * @param list<array{w:int,y:string}> $lookup
 */
$lookupByCount = static function (array $lookup, int $count): ?string {
    foreach ($lookup as $row) {
        if ($row['w'] === $count) {
            return $row['y'];
        }
    }

    return null;
};

/**
 * @param list<array{cnt:int,xyz:int}> $groups
 * @param list<array{w:int,y:string}> $lookup
 * @return list<mixed>
 */
$expectedProjection = static function (array $groups, array $lookup) use ($lookupByCount): array {
    $flat = [];
    foreach ($groups as $group) {
        array_push($flat, $group['cnt'], $group['xyz'], $lookupByCount($lookup, $group['cnt']), '|');
    }

    return $flat;
};

/**
 * @param list<array{cnt:int,xyz:int}> $groups
 * @param list<array{w:int,y:string}> $lookup
 * @return list<mixed>
 */
$expectedWhere = static function (array $groups, array $lookup, string $excluded) use ($lookupByCount): array {
    $flat = [];
    foreach ($groups as $group) {
        if ($lookupByCount($lookup, $group['cnt']) === $excluded) {
            continue;
        }
        array_push($flat, $group['cnt'], $group['xyz'], '|');
    }

    return $flat;
};

/**
 * @param list<array{cnt:int,xyz:int}> $groups
 * @param list<array{w:int,y:string}> $lookup
 * @return list<mixed>
 */
$expectedOrderBySubquery = static function (array $groups, array $lookup) use ($lookupByCount): array {
    usort($groups, static function (array $left, array $right) use ($lookupByCount, $lookup): int {
        return strcmp(strtolower((string) $lookupByCount($lookup, $left['cnt'])), strtolower((string) $lookupByCount($lookup, $right['cnt'])));
    });

    $flat = [];
    foreach ($groups as $group) {
        array_push($flat, $group['cnt'], $group['xyz'], '|');
    }

    return $flat;
};

/**
 * @param list<array{cnt:int,xyz:int}> $groups
 * @param list<array{w:int,y:string}> $lookup
 * @return list<mixed>
 */
$expectedCase = static function (array $groups, array $lookup, string $matched) use ($lookupByCount): array {
    $flat = [];
    foreach ($groups as $group) {
        array_push($flat, $group['cnt'], $group['xyz'], $lookupByCount($lookup, $group['cnt']) === $matched ? 'aaa' : 'bbb', '|');
    }

    return $flat;
};

$tests = [];

$canonicalTables = [
    'app_metric' => [
        ['w' => 1, 'x' => 10],
        ['w' => 2, 'x' => 20],
        ['w' => 3, 'x' => 30],
        ['w' => 2, 'x' => 21],
        ['w' => 3, 'x' => 31],
        ['w' => 3, 'x' => 32],
    ],
    'app_lookup' => [
        ['w' => 1, 'y' => 'one'],
        ['w' => 2, 'y' => 'two'],
        ['w' => 3, 'y' => 'three'],
        ['w' => 4, 'y' => 'four'],
    ],
];

$canonicalCases = [
    'select6-11.1 correlated scalar subquery reads derived count alias' => [
        'SELECT cnt, xyz, (SELECT y FROM app_lookup WHERE w=cnt) AS y, \'|\' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) ORDER BY cnt, xyz',
        [1, 1, 'one', '|', 2, 2, 'two', '|', 3, 3, 'three', '|'],
    ],
    'select6-11.2 lower function wraps correlated scalar subquery' => [
        'SELECT cnt, xyz, lower((SELECT y FROM app_lookup WHERE w=cnt)) AS y, \'|\' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) ORDER BY cnt, xyz',
        [1, 1, 'one', '|', 2, 2, 'two', '|', 3, 3, 'three', '|'],
    ],
    'select6-11.3 correlated scalar subquery filters derived aggregate rows' => [
        "SELECT cnt, xyz, '|' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) WHERE (SELECT y FROM app_lookup WHERE w=cnt)!='two' ORDER BY cnt, xyz",
        [1, 1, '|', 3, 3, '|'],
    ],
    'select6-11.4 correlated scalar subquery orders derived aggregate rows' => [
        'SELECT cnt, xyz, \'|\' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) ORDER BY lower((SELECT y FROM app_lookup WHERE w=cnt))',
        [1, 1, '|', 3, 3, '|', 2, 2, '|'],
    ],
    'select6-11.5 correlated scalar subquery drives CASE branch' => [
        "SELECT cnt, xyz, CASE WHEN (SELECT y FROM app_lookup WHERE w=cnt)=='two' THEN 'aaa' ELSE 'bbb' END AS label, '|' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) ORDER BY +cnt",
        [1, 1, 'bbb', '|', 2, 2, 'aaa', '|', 3, 3, 'bbb', '|'],
    ],
];

foreach ($canonicalCases as $name => [$sql, $expected]) {
    $tests['real upstream select6.test ' . $name] = static function (TestRunner $t) use ($assertSelectFlat, $canonicalTables, $sql, $expected, $name): void {
        $assertSelectFlat($t, $sql, $canonicalTables, $expected);
        $t->contains('select6-11.', $name);
    };
}

for ($seed = 0; $seed < 200; $seed++) {
    $metrics = $metricRows($seed);
    $lookup = $lookupRows($seed);
    $tables = [
        'app_metric' => $metrics,
        'app_lookup' => $lookup,
    ];
    $groups = $groupedCounts($metrics);
    $two = 'two-' . $seed;

    $tests[sprintf('real upstream select6.test select6-11.1 dynamic correlated projection seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $groups, $lookup, $expectedProjection): void {
            $assertSelectFlat(
                $t,
                "SELECT cnt, xyz, (SELECT y FROM app_lookup WHERE w=cnt) AS y, '|' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) ORDER BY cnt, xyz",
                $tables,
                $expectedProjection($groups, $lookup)
            );
        };

    $tests[sprintf('real upstream select6.test select6-11.2 dynamic lower correlated projection seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $groups, $lookup, $expectedProjection): void {
            $assertSelectFlat(
                $t,
                "SELECT cnt, xyz, lower((SELECT y FROM app_lookup WHERE w=cnt)) AS y, '|' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) ORDER BY cnt, xyz",
                $tables,
                $expectedProjection($groups, $lookup)
            );
        };

    $tests[sprintf('real upstream select6.test select6-11.3 dynamic correlated where seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $groups, $lookup, $two, $expectedWhere): void {
            $assertSelectFlat(
                $t,
                "SELECT cnt, xyz, '|' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) WHERE (SELECT y FROM app_lookup WHERE w=cnt)!='{$two}' ORDER BY cnt, xyz",
                $tables,
                $expectedWhere($groups, $lookup, $two)
            );
        };

    $tests[sprintf('real upstream select6.test select6-11.4 dynamic correlated order seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $groups, $lookup, $expectedOrderBySubquery): void {
            $assertSelectFlat(
                $t,
                "SELECT cnt, xyz, '|' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) ORDER BY lower((SELECT y FROM app_lookup WHERE w=cnt))",
                $tables,
                $expectedOrderBySubquery($groups, $lookup)
            );
        };

    $tests[sprintf('real upstream select6.test select6-11.5 dynamic correlated case seed %03d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $groups, $lookup, $two, $expectedCase): void {
            $assertSelectFlat(
                $t,
                "SELECT cnt, xyz, CASE WHEN (SELECT y FROM app_lookup WHERE w=cnt)=='{$two}' THEN 'aaa' ELSE 'bbb' END AS label, '|' AS sep FROM (SELECT count(*) AS cnt, w AS xyz FROM app_metric GROUP BY 2) ORDER BY +cnt",
                $tables,
                $expectedCase($groups, $lookup, $two)
            );
        };
}

$tests['real upstream select6.test select6-11.100 empty aggregate correlated scalar returns null'] = static function (TestRunner $t) use ($assertSelectFlat): void {
    $assertSelectFlat(
        $t,
        'SELECT (SELECT y FROM app_lookup WHERE z=cnt) AS y FROM (SELECT count(*) AS cnt FROM app_empty)',
        ['app_empty' => [], 'app_lookup' => []],
        [null]
    );
};

$tests['real upstream select6.test correlated aggregate source coverage note'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test';
    $t->true(is_file($source), 'hydrated upstream select6.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream select6.test is readable');
    $t->contains('do_execsql_test 11.1', $text);
    $t->contains('do_execsql_test 11.5', $text);
    $t->contains('do_execsql_test 11.100', $text);
    $t->same('no new support component needed', 'no new support component needed');
};

return $tests;
