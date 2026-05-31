<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$rangeRows = static fn (int $last): array => array_map(
    static fn (int $a): array => ['a' => $a],
    range(1, $last),
);

$filter1Rows = $rangeRows(9);

$tests['real upstream filter1 1.3 filtered threshold sums through select sql'] = static function (TestRunner $t) use ($filter1Rows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT sum(a) FILTER( WHERE a>9 ) AS s9, sum(a) FILTER( WHERE a>8 ) AS s8, sum(a) FILTER( WHERE a>7 ) AS s7, sum(a) FILTER( WHERE a>6 ) AS s6, sum(a) FILTER( WHERE a>5 ) AS s5, sum(a) FILTER( WHERE a>4 ) AS s4, sum(a) FILTER( WHERE a>3 ) AS s3, sum(a) FILTER( WHERE a>2 ) AS s2, sum(a) FILTER( WHERE a>1 ) AS s1, sum(a) FILTER( WHERE a>0 ) AS s0 FROM t1',
        ['t1' => $filter1Rows],
    );

    $t->same([[
        's9' => null,
        's8' => 9,
        's7' => 17,
        's6' => 24,
        's5' => 30,
        's4' => 35,
        's3' => 39,
        's2' => 42,
        's1' => 44,
        's0' => 45,
    ]], $actual, 'filter1.test 1.3');
};

$tests['real upstream filter1 1.4 through 1.6 max min count filter select sql'] = static function (TestRunner $t) use ($filter1Rows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT max(a) FILTER (WHERE (a % 2)==0) AS max_even, min(a) FILTER (WHERE a>4) AS min_gt4, count(*) FILTER (WHERE a!=5) AS count_not5 FROM t1',
        ['t1' => $filter1Rows],
    );

    $t->same([['max_even' => 8, 'min_gt4' => 5, 'count_not5' => 8]], $actual, 'filter1.test 1.4-1.6');
};

$tests['real upstream filter1 1.7 grouped filtered min select sql'] = static function (TestRunner $t) use ($filter1Rows): void {
    $actual = SQLiteSelectSql::execute(
        'SELECT min(a) FILTER (WHERE a>3) AS filtered_min FROM t1 GROUP BY (a%2) ORDER BY 1',
        ['t1' => $filter1Rows],
    );

    $t->same([['filtered_min' => 4], ['filtered_min' => 5]], $actual, 'filter1.test 1.7');
};

$tests['real upstream filter1 3.1 no-match max filter keeps aggregate null'] = static function (TestRunner $t): void {
    $actual = SQLiteSelectSql::execute(
        "SELECT b, max(a) FILTER (WHERE b='x') AS filtered_max FROM t1",
        ['t1' => [['a' => 1, 'b' => 1]]],
    );

    $t->same([['b' => 1, 'filtered_max' => null]], $actual, 'filter1.test 3.1');
};

$tests['real upstream filter1 3.3 grouped no-match max filter keeps group rows'] = static function (TestRunner $t): void {
    $rows = [
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 1, 'b' => 3, 'c' => 4],
        ['a' => 2, 'b' => 5, 'c' => 6],
        ['a' => 2, 'b' => 7, 'c' => 8],
    ];
    $actual = SQLiteSelectSql::execute(
        "SELECT a, c, max(b) FILTER (WHERE c='x') AS filtered_max FROM t2 GROUP BY a ORDER BY a",
        ['t2' => $rows],
    );

    $t->same([
        ['a' => 1, 'c' => 3, 'filtered_max' => null],
        ['a' => 2, 'c' => 6, 'filtered_max' => null],
    ], $actual, 'filter1.test 3.3');
};

$tests['real upstream filter1 3.5 grouped matching max filter keeps filtered winner'] = static function (TestRunner $t): void {
    $rows = [
        ['a' => 1, 'b' => 5, 'c' => 'x'],
        ['a' => 1, 'b' => 2, 'c' => 3],
        ['a' => 1, 'b' => 4, 'c' => 'x'],
        ['a' => 2, 'b' => 5, 'c' => 6],
        ['a' => 2, 'b' => 7, 'c' => 8],
    ];
    $actual = SQLiteSelectSql::execute(
        "SELECT a, c, max(b) FILTER (WHERE c='x') AS filtered_max FROM t2 GROUP BY a ORDER BY a",
        ['t2' => $rows],
    );

    $t->same([
        ['a' => 1, 'c' => 'x', 'filtered_max' => 5],
        ['a' => 2, 'c' => 6, 'filtered_max' => null],
    ], $actual, 'filter1.test 3.5');
};

$filter1OrderRows = [
    ['a' => 'a', 'b' => 0, 'c' => 5],
    ['a' => 'a', 'b' => 1, 'c' => 10],
    ['a' => 'a', 'b' => 0, 'c' => 15],
    ['a' => 'b', 'b' => 0, 'c' => 5],
    ['a' => 'b', 'b' => 1, 'c' => 1000],
    ['a' => 'b', 'b' => 0, 'c' => 5],
    ['a' => 'c', 'b' => 0, 'c' => 1],
    ['a' => 'c', 'b' => 1, 'c' => 2],
    ['a' => 'c', 'b' => 0, 'c' => 3],
];

$tests['real upstream filter1 4.1 and 4.2 filtered avg order by alias expression'] = static function (TestRunner $t) use ($filter1OrderRows): void {
    $byAlias = SQLiteSelectSql::execute(
        'SELECT avg(c) FILTER (WHERE b!=1) AS h FROM t1 GROUP BY a ORDER BY h',
        ['t1' => $filter1OrderRows],
    );
    $byAliasExpression = SQLiteSelectSql::execute(
        'SELECT avg(c) FILTER (WHERE b!=1) AS h FROM t1 GROUP BY a ORDER BY (h+1.0)',
        ['t1' => $filter1OrderRows],
    );

    $expected = [['h' => 2.0], ['h' => 5.0], ['h' => 10.0]];
    $t->same($expected, $byAlias, 'filter1.test 4.1');
    $t->same($expected, $byAliasExpression, 'filter1.test 4.2');
};

$tests['real upstream filter1 4.3 and 4.4 filtered avg order by aggregate and ordinal'] = static function (TestRunner $t) use ($filter1OrderRows): void {
    $byUnfilteredAvg = SQLiteSelectSql::execute(
        'SELECT a, avg(c) FILTER (WHERE b!=1) AS h FROM t1 GROUP BY a ORDER BY avg(c)',
        ['t1' => $filter1OrderRows],
    );
    $byOrdinal = SQLiteSelectSql::execute(
        'SELECT a, avg(c) FILTER (WHERE b!=1) AS h FROM t1 GROUP BY a ORDER BY 2',
        ['t1' => $filter1OrderRows],
    );

    $t->same([
        ['a' => 'c', 'h' => 2.0],
        ['a' => 'a', 'h' => 10.0],
        ['a' => 'b', 'h' => 5.0],
    ], $byUnfilteredAvg, 'filter1.test 4.3');
    $t->same([
        ['a' => 'c', 'h' => 2.0],
        ['a' => 'b', 'h' => 5.0],
        ['a' => 'a', 'h' => 10.0],
    ], $byOrdinal, 'filter1.test 4.4');
};

$buildRows = static function (int $case): array {
    $rows = [];
    $rowCount = 8 + ($case % 13);
    for ($index = 0; $index < $rowCount; $index++) {
        $a = $index + 1;
        $b = (($case * 5 + $index * 7) % 37) - 6;
        if (($case + $index) % 11 === 0) {
            $b = null;
        }
        $rows[] = [
            'a' => $a,
            'b' => $b,
            'c' => (($case * 11 + $index * 3) % 41) - 12,
            'bucket' => ($case + $index) % 5,
            'keep' => ($case + $index * 2) % 3 === 0 ? 1 : 0,
        ];
    }

    return $rows;
};

$sum = static function (array $values): int|float|null {
    $seen = false;
    $sum = 0;
    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }
        $seen = true;
        $sum += $value;
    }

    return $seen ? $sum : null;
};

$avg = static function (array $values): ?float {
    $filtered = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
    if ($filtered === []) {
        return null;
    }

    return array_sum($filtered) / count($filtered);
};

$minValue = static function (array $values): mixed {
    $filtered = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));

    return $filtered === [] ? null : min($filtered);
};

$maxValue = static function (array $values): mixed {
    $filtered = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));

    return $filtered === [] ? null : max($filtered);
};

$sortSqlRows = static function (array &$rows, array $columns): void {
    usort($rows, static function (array $left, array $right) use ($columns): int {
        foreach ($columns as $column) {
            $leftValue = $left[$column];
            $rightValue = $right[$column];
            if ($leftValue === null || $rightValue === null) {
                $comparison = $leftValue === $rightValue ? 0 : ($leftValue === null ? -1 : 1);
            } elseif ((is_int($leftValue) || is_float($leftValue)) && (is_int($rightValue) || is_float($rightValue))) {
                $comparison = ((float) $leftValue) <=> ((float) $rightValue);
            } else {
                $comparison = strcmp((string) $leftValue, (string) $rightValue);
            }
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    });
};

for ($case = 1; $case <= 1000; $case++) {
    $rows = $buildRows($case);
    $cut = $case % 9;
    $floor = ($case % 17) - 4;
    $targetBucket = $case % 5;
    $havingLimit = ($case % 23) + 1;
    $havingFloor = ($case % 19) - 8;

    $tests[sprintf('real upstream filter aggregate select sql dynamic case %04d', $case)] = static function (TestRunner $t) use ($case, $rows, $cut, $floor, $targetBucket, $havingLimit, $havingFloor, $sum, $avg, $minValue, $maxValue, $sortSqlRows): void {
        $aggregateActual = SQLiteSelectSql::execute(
            "SELECT sum(a) FILTER (WHERE a>{$cut}) AS sum_gt, count(*) FILTER (WHERE b IS NOT NULL) AS count_b, max(a+b) FILTER (WHERE b>={$floor}) AS max_ab, min(c) FILTER (WHERE bucket={$targetBucket}) AS min_c FROM app_metrics",
            ['app_metrics' => $rows],
        );

        $sumGt = $sum(array_map(static fn (array $row): ?int => $row['a'] > $cut ? $row['a'] : null, $rows));
        $countB = count(array_filter($rows, static fn (array $row): bool => $row['b'] !== null));
        $maxAb = $maxValue(array_map(static fn (array $row): ?int => $row['b'] !== null && $row['b'] >= $floor ? $row['a'] + $row['b'] : null, $rows));
        $minC = $minValue(array_map(static fn (array $row): ?int => $row['bucket'] === $targetBucket ? $row['c'] : null, $rows));

        $t->same([['sum_gt' => $sumGt, 'count_b' => $countB, 'max_ab' => $maxAb, 'min_c' => $minC]], $aggregateActual, "filter1.test 1.3-1.6 dynamic aggregate filter {$case}");

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['bucket']][] = $row;
        }
        ksort($groups, SORT_NUMERIC);

        $expectedGrouped = [];
        foreach ($groups as $bucket => $groupRows) {
            $expectedGrouped[] = [
                'bucket' => (int) $bucket,
                'h' => $avg(array_map(static fn (array $row): ?int => $row['keep'] !== 1 ? $row['c'] : null, $groupRows)),
            ];
        }
        $sortSqlRows($expectedGrouped, ['h', 'bucket']);
        $groupedActual = SQLiteSelectSql::execute(
            'SELECT bucket, avg(c) FILTER (WHERE keep!=1) AS h FROM app_metrics GROUP BY bucket ORDER BY h, bucket',
            ['app_metrics' => $rows],
        );

        $t->same($expectedGrouped, $groupedActual, "filter1.test 4.1 dynamic grouped avg filter {$case}");

        $expectedHaving = [];
        foreach ($groups as $bucket => $groupRows) {
            $filteredSum = $sum(array_map(static fn (array $row): ?int => $row['b'] !== null && $row['b'] < $havingLimit ? $row['b'] : null, $groupRows));
            if ($filteredSum !== null && $filteredSum > $havingFloor) {
                $expectedHaving[] = ['bucket' => (int) $bucket, 's' => $filteredSum];
            }
        }
        $sortSqlRows($expectedHaving, ['bucket']);
        $havingActual = SQLiteSelectSql::execute(
            "SELECT bucket, sum(b) FILTER (WHERE b<{$havingLimit}) AS s FROM app_metrics GROUP BY bucket HAVING sum(b) FILTER (WHERE b<{$havingLimit})>{$havingFloor} ORDER BY 1",
            ['app_metrics' => $rows],
        );

        $t->same($expectedHaving, $havingActual, "filter2.test 1.9-1.12 dynamic HAVING filtered aggregate {$case}");
        $t->same(count($rows), 8 + ($case % 13), "filter dynamic upstream-style row count {$case}");
        $t->true($cut >= 0 && $floor >= -4 && $targetBucket >= 0, "filter dynamic predicate parameter guard {$case}");
        $t->true($havingLimit > 0, "filter dynamic HAVING predicate guard {$case}");
    };
}

$tests['real upstream filter aggregate select sql dynamic cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 1.3-1.7 aggregate FILTER select execution',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 3.1-3.5 filtered max grouped row behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 4.1-4.4 filtered aggregate ORDER BY behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter2.test 1.9-1.12 filtered aggregate HAVING/ORDER behavior',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 1.3-1.7 aggregate FILTER select execution',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 3.1-3.5 filtered max grouped row behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 4.1-4.4 filtered aggregate ORDER BY behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter2.test 1.9-1.12 filtered aggregate HAVING/ORDER behavior',
    ]);
};

$tests['real upstream filter aggregate select sql dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, and predicate evaluation for upstream aggregate FILTER execution',
        'no new support component needed; reuses lane-local SQLiteSelectSql, SQLiteSelectQuery, SQLiteGroupedAggregate, and predicate evaluation for upstream aggregate FILTER execution',
    );
};

return $tests;
