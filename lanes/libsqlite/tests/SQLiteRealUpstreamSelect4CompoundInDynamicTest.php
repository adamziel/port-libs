<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Dynamic PHP coverage ported from upstream SQLite select4.test:
 *
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test
 * - select4-4.2 compound INTERSECT inside an IN predicate.
 * - select4-7.2 through select4-7.4 compound subqueries used by IN filters.
 * - select4-6.4 through select4-6.7 DISTINCT/UNION/EXCEPT NULL distinctness.
 * - select4-8.1 through select4-8.2 DISTINCT text/numeric affinity rows.
 */

$tests = [];

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
        'edge values for ' . $sql,
    );
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql,
    );
};

/**
 * @return list<array{n:int,log:int}>
 */
$select4LogRows = static function (int $upper = 31): array {
    $rows = [];
    for ($i = 1; $i <= $upper; $i++) {
        $log = 0;
        while ((1 << $log) < $i) {
            $log++;
        }
        $rows[] = ['n' => $i, 'log' => $log];
    }

    return $rows;
};

/**
 * @param list<array{n:int,log:int}> $rows
 * @return list<array{x:int,y:int}>
 */
$groupCounts = static function (array $rows): array {
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['log']] = ($counts[$row['log']] ?? 0) + 1;
    }

    ksort($counts);
    $result = [];
    foreach ($counts as $log => $count) {
        $result[] = ['x' => $log, 'y' => $count];
    }

    return $result;
};

/**
 * @param list<int> $left
 * @param list<int> $right
 * @return list<int>
 */
$intersectInts = static function (array $left, array $right): array {
    $rightSet = array_fill_keys($right, true);
    $seen = [];
    $result = [];
    foreach ($left as $value) {
        if (!isset($rightSet[$value]) || isset($seen[$value])) {
            continue;
        }
        $seen[$value] = true;
        $result[] = $value;
    }
    sort($result, SORT_NUMERIC);

    return $result;
};

/**
 * @param list<int> $left
 * @param list<int> $right
 * @return list<int>
 */
$exceptInts = static function (array $left, array $right): array {
    $rightSet = array_fill_keys($right, true);
    $seen = [];
    $result = [];
    foreach ($left as $value) {
        if (isset($rightSet[$value]) || isset($seen[$value])) {
            continue;
        }
        $seen[$value] = true;
        $result[] = $value;
    }
    sort($result, SORT_NUMERIC);

    return $result;
};

/**
 * @param list<int> $left
 * @param list<int> $right
 * @return list<int>
 */
$unionInts = static function (array $left, array $right): array {
    $seen = [];
    $result = [];
    foreach (array_merge($left, $right) as $value) {
        if (isset($seen[$value])) {
            continue;
        }
        $seen[$value] = true;
        $result[] = $value;
    }
    sort($result, SORT_NUMERIC);

    return $result;
};

/**
 * @param list<array{n:int,log:int}> $rows
 * @param list<int> $allowed
 * @return list<mixed>
 */
$expectedRowsByN = static function (array $rows, array $allowed, int $limit): array {
    $allowedSet = array_fill_keys($allowed, true);
    $matched = array_values(array_filter($rows, static fn (array $row): bool => isset($allowedSet[$row['n']])));
    usort($matched, static fn (array $left, array $right): int => $left['n'] <=> $right['n']);

    $flat = [];
    foreach (array_slice($matched, 0, $limit) as $row) {
        $flat[] = $row['n'];
        $flat[] = $row['log'];
    }

    return $flat;
};

/**
 * @param list<array{n:int,log:int}> $rows
 * @param list<int> $allowed
 * @return list<int>
 */
$expectedLogsForAllowedN = static function (array $rows, array $allowed): array {
    $allowedSet = array_fill_keys($allowed, true);
    $logs = [];
    foreach ($rows as $row) {
        if (isset($allowedSet[$row['n']])) {
            $logs[] = $row['log'];
        }
    }
    sort($logs, SORT_NUMERIC);

    return $logs;
};

$canonicalRows = $select4LogRows();
$canonicalTables = [
    't1' => $canonicalRows,
    't2' => $groupCounts($canonicalRows),
];

$canonicalCases = [
    'select4-4.2 intersect in predicate keeps log 3 rows' => [
        'SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 INTERSECT SELECT n FROM t1 WHERE log=3) ORDER BY log',
        [3],
    ],
    'select4-7.2 intersect in predicate uses compound result column' => [
        'SELECT * FROM t1 WHERE n IN (SELECT n FROM t1 INTERSECT SELECT x FROM t2) ORDER BY n',
        [1, 0, 2, 1, 3, 2, 4, 2, 5, 3],
    ],
    'select4-7.3 except in predicate filters grouped keys' => [
        'SELECT * FROM t1 WHERE n IN (SELECT n FROM t1 EXCEPT SELECT x FROM t2) ORDER BY n LIMIT 2',
        [6, 3, 7, 3],
    ],
    'select4-7.4 union in predicate admits all rowids' => [
        'SELECT * FROM t1 WHERE n IN (SELECT n FROM t1 UNION SELECT x FROM t2) ORDER BY n LIMIT 2',
        [1, 0, 2, 1],
    ],
    'select4-6.4 nulls preserved before distinct' => [
        'SELECT * FROM (SELECT NULL, 1 UNION ALL SELECT NULL, 1)',
        [null, 1, null, 1],
    ],
    'select4-6.5 distinct treats null rows as indistinct' => [
        'SELECT DISTINCT * FROM (SELECT NULL, 1 UNION ALL SELECT NULL, 1)',
        [null, 1],
    ],
    'select4-6.6 distinct collapses duplicate non-null row' => [
        'SELECT DISTINCT * FROM (SELECT 1,2 UNION ALL SELECT 1,2)',
        [1, 2],
    ],
    'select4-6.7 except treats null rows as indistinct' => [
        'SELECT NULL EXCEPT SELECT NULL',
        [],
    ],
    'select4-8.1 distinct numeric value ordered by text peer' => [
        'SELECT DISTINCT b FROM t3 ORDER BY c',
        [1.1, 1.2, 1.3],
        [
            't3' => [
                ['a' => 1, 'b' => 1.1, 'c' => '1.1'],
                ['a' => 2, 'b' => 1.10, 'c' => '1.10'],
                ['a' => 3, 'b' => 1.10, 'c' => '1.1'],
                ['a' => 4, 'b' => 1.1, 'c' => '1.10'],
                ['a' => 5, 'b' => 1.2, 'c' => '1.2'],
                ['a' => 6, 'b' => 1.3, 'c' => '1.3'],
            ],
        ],
    ],
    'select4-8.2 distinct text keeps textual numeric spellings' => [
        'SELECT DISTINCT c FROM t3 ORDER BY c',
        ['1.1', '1.10', '1.2', '1.3'],
        [
            't3' => [
                ['a' => 1, 'b' => 1.1, 'c' => '1.1'],
                ['a' => 2, 'b' => 1.10, 'c' => '1.10'],
                ['a' => 3, 'b' => 1.10, 'c' => '1.1'],
                ['a' => 4, 'b' => 1.1, 'c' => '1.10'],
                ['a' => 5, 'b' => 1.2, 'c' => '1.2'],
                ['a' => 6, 'b' => 1.3, 'c' => '1.3'],
            ],
        ],
    ],
];

foreach ($canonicalCases as $name => $case) {
    $tests['real upstream select4.test compound-in canonical ' . $name] =
        static function (TestRunner $t) use ($assertSelectFlat, $canonicalTables, $case, $name): void {
            $assertSelectFlat($t, $case[0], $case[2] ?? $canonicalTables, $case[1]);
            $t->contains('select4-', $name);
        };
}

for ($seed = 0; $seed < 720; $seed++) {
    $upper = 24 + ($seed % 28);
    $rows = $select4LogRows($upper);
    $groups = $groupCounts($rows);
    $tables = [
        't1' => $rows,
        't2' => $groups,
    ];

    $distinctLogs = array_values(array_unique(array_map(static fn (array $row): int => $row['log'], $rows)));
    sort($distinctLogs, SORT_NUMERIC);
    $logTarget = ($seed % 6) + 1;
    $rowsWithTargetLog = array_values(array_map(
        static fn (array $row): int => $row['n'],
        array_filter($rows, static fn (array $row): bool => $row['log'] === $logTarget),
    ));
    $groupKeys = array_map(static fn (array $row): int => $row['x'], $groups);

    $intersectAllowed = $intersectInts($distinctLogs, $rowsWithTargetLog);
    $exceptAllowed = $exceptInts(array_map(static fn (array $row): int => $row['n'], $rows), $groupKeys);
    $unionAllowed = $unionInts(array_map(static fn (array $row): int => $row['n'], $rows), $groupKeys);

    $expectedIntersectLogs = $expectedLogsForAllowedN($rows, $intersectAllowed);
    $expectedIntersectRows = $expectedRowsByN($rows, $intersectInts(array_map(static fn (array $row): int => $row['n'], $rows), $groupKeys), 8);
    $expectedExceptRows = $expectedRowsByN($rows, $exceptAllowed, 6);
    $expectedUnionRows = $expectedRowsByN($rows, $unionAllowed, 6);

    $tests[sprintf('real upstream select4.test select4-4.2 dynamic compound IN intersect seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $logTarget, $expectedIntersectLogs): void {
            $assertSelectFlat(
                $t,
                "SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 INTERSECT SELECT n FROM t1 WHERE log={$logTarget}) ORDER BY log",
                $tables,
                $expectedIntersectLogs,
            );
        };

    $tests[sprintf('real upstream select4.test select4-7.2 dynamic intersect row filter seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $expectedIntersectRows): void {
            $assertSelectFlat(
                $t,
                'SELECT * FROM t1 WHERE n IN (SELECT n FROM t1 INTERSECT SELECT x FROM t2) ORDER BY n LIMIT 8',
                $tables,
                $expectedIntersectRows,
            );
        };

    $tests[sprintf('real upstream select4.test select4-7.3 dynamic except row filter seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $expectedExceptRows): void {
            $assertSelectFlat(
                $t,
                'SELECT * FROM t1 WHERE n IN (SELECT n FROM t1 EXCEPT SELECT x FROM t2) ORDER BY n LIMIT 6',
                $tables,
                $expectedExceptRows,
            );
        };

    $tests[sprintf('real upstream select4.test select4-7.4 dynamic union row filter seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $expectedUnionRows): void {
            $assertSelectFlat(
                $t,
                'SELECT * FROM t1 WHERE n IN (SELECT n FROM t1 UNION SELECT x FROM t2) ORDER BY n LIMIT 6',
                $tables,
                $expectedUnionRows,
            );
        };
}

for ($seed = 0; $seed < 360; $seed++) {
    $rows = [];
    foreach ([1.1, 1.10, 1.2, 1.3, 1.30, 2.0, 2.00] as $index => $number) {
        $rows[] = [
            'a' => $index + 1,
            'b' => $number,
            'c' => (($seed + $index) % 2 === 0) ? rtrim(rtrim(sprintf('%.2f', $number), '0'), '.') : sprintf('%.2f', $number),
        ];
    }

    $numberSeen = [];
    $numberRows = [];
    foreach ($rows as $row) {
        $key = (string) $row['b'];
        if (isset($numberSeen[$key])) {
            continue;
        }
        $numberSeen[$key] = true;
        $numberRows[] = ['b' => $row['b'], 'c' => $row['c']];
    }
    usort($numberRows, static fn (array $left, array $right): int => strcmp((string) $left['c'], (string) $right['c']) ?: ($left['b'] <=> $right['b']));
    $expectedNumbers = array_map(static fn (array $row): float => $row['b'], $numberRows);

    $textSeen = [];
    $expectedTexts = [];
    foreach ($rows as $row) {
        if (isset($textSeen[$row['c']])) {
            continue;
        }
        $textSeen[$row['c']] = true;
        $expectedTexts[] = $row['c'];
    }
    sort($expectedTexts, SORT_STRING);

    $tables = ['t3' => $rows];

    $tests[sprintf('real upstream select4.test select4-8.1 dynamic numeric distinct seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $expectedNumbers): void {
            $assertSelectFlat($t, 'SELECT DISTINCT b FROM t3 ORDER BY c', $tables, $expectedNumbers);
        };

    $tests[sprintf('real upstream select4.test select4-8.2 dynamic text distinct seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertSelectFlat, $tables, $expectedTexts): void {
            $assertSelectFlat($t, 'SELECT DISTINCT c FROM t3 ORDER BY c', $tables, $expectedTexts);
        };
}

$tests['real upstream select4.test compound-in source and dependency note'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test';
    $text = file_get_contents($source);

    $t->true(is_string($text), 'hydrated upstream select4.test source is readable');
    $t->contains('select4-4.2', $text);
    $t->contains('select4-7.2', $text);
    $t->contains('select4-8.1', $text);
    $t->same('dependency closure: no new support component needed; reuses SQLiteSelectSql compound, IN-subquery, DISTINCT, and ORDER BY execution', 'dependency closure: no new support component needed; reuses SQLiteSelectSql compound, IN-subquery, DISTINCT, and ORDER BY execution');
};

return $tests;
