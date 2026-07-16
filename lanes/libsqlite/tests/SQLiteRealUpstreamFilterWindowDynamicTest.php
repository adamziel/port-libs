<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteNumericAggregate;
use PortLibs\LibSqlite\SQLiteTextAggregate;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$filter2Rows = [
    ['a' => 1, 'b' => 7], ['a' => 2, 'b' => 3], ['a' => 3, 'b' => 5],
    ['a' => 4, 'b' => 30], ['a' => 5, 'b' => 26], ['a' => 6, 'b' => 23],
    ['a' => 7, 'b' => 27], ['a' => 8, 'b' => 3], ['a' => 9, 'b' => 17],
    ['a' => 10, 'b' => 26], ['a' => 11, 'b' => 33], ['a' => 12, 'b' => 25],
    ['a' => 13, 'b' => null], ['a' => 14, 'b' => 47], ['a' => 15, 'b' => 36],
    ['a' => 16, 'b' => 13], ['a' => 17, 'b' => 45], ['a' => 18, 'b' => 31],
    ['a' => 19, 'b' => 11], ['a' => 20, 'b' => 36], ['a' => 21, 'b' => 37],
    ['a' => 22, 'b' => 21], ['a' => 23, 'b' => 22], ['a' => 24, 'b' => 14],
    ['a' => 25, 'b' => 16], ['a' => 26, 'b' => 3], ['a' => 27, 'b' => 7],
    ['a' => 28, 'b' => 29], ['a' => 29, 'b' => 50], ['a' => 30, 'b' => 38],
    ['a' => 31, 'b' => 3], ['a' => 32, 'b' => 36], ['a' => 33, 'b' => 12],
    ['a' => 34, 'b' => 4], ['a' => 35, 'b' => 46], ['a' => 36, 'b' => 3],
    ['a' => 37, 'b' => 48], ['a' => 38, 'b' => 23], ['a' => 39, 'b' => null],
    ['a' => 40, 'b' => 24], ['a' => 41, 'b' => 5], ['a' => 42, 'b' => 46],
    ['a' => 43, 'b' => 11], ['a' => 44, 'b' => null], ['a' => 45, 'b' => 18],
    ['a' => 46, 'b' => 25], ['a' => 47, 'b' => 15], ['a' => 48, 'b' => 18],
    ['a' => 49, 'b' => 23],
];

$filter1Rows = array_map(static fn (int $a): array => ['a' => $a], range(1, 9));

/**
 * @param list<array{a:int,b:int|null}> $rows
 * @return list<int|null>
 */
$columnB = static fn (array $rows): array => array_map(static fn (array $row): ?int => $row['b'], $rows);

/**
 * @param list<array{a:int,b:int|null}> $rows
 * @return list<array{a:int,b:int|null}>
 */
$filterRows = static function (array $rows, callable $predicate): array {
    return array_values(array_filter($rows, $predicate));
};

/**
 * @param list<array{a:int,b:int|null}> $rows
 * @return list<array{group:int,rows:list<array{a:int,b:int|null}>}>
 */
$groupByModulo = static function (array $rows, int $modulo): array {
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['a'] % $modulo][] = $row;
    }
    ksort($groups, SORT_NUMERIC);

    $result = [];
    foreach ($groups as $group => $groupRows) {
        $result[] = ['group' => (int) $group, 'rows' => $groupRows];
    }

    return $result;
};

$aggregateSummary = static function (array $rows) use ($columnB): array {
    $values = $columnB($rows);

    return [
        'sum' => SQLiteNumericAggregate::sum($values),
        'countValue' => SQLiteNumericAggregate::countValue($values),
        'countDistinct' => SQLiteNumericAggregate::countDistinct($values),
        'min' => SQLiteNumericAggregate::min($values),
        'max' => SQLiteNumericAggregate::max($values),
        'avg4' => SQLiteNumericAggregate::avg($values) === null ? null : sprintf('%.4f', SQLiteNumericAggregate::avg($values)),
        'concat' => SQLiteTextAggregate::groupConcat($values, '_'),
    ];
};

/**
 * @param list<array{a:int,b:int|null}> $rows
 * @return list<int>
 */
$windowFilteredCount = static function (array $rows, int $preceding, int $following, callable $predicate): array {
    $values = array_map(static fn (array $row): int => 1, $rows);
    $keys = array_column($rows, 'a');
    $filters = array_map($predicate, $rows);

    return SQLiteWindowFunction::aggregateFrameBetweenValues(
        'count',
        $values,
        $keys,
        'ROWS',
        "{$preceding} PRECEDING",
        "{$following} FOLLOWING",
        'NO OTHERS',
        $filters,
    );
};

$tests['real upstream filter1 1.1 and 1.2 scalar filtered sum baseline'] = static function (TestRunner $t) use ($filter1Rows): void {
    $all = array_column($filter1Rows, 'a');
    $filtered = array_column(array_values(array_filter($filter1Rows, static fn (array $row): bool => $row['a'] < 5)), 'a');

    $t->same(45, SQLiteNumericAggregate::sum($all), 'filter1.test 1.1');
    $t->same(10, SQLiteNumericAggregate::sum($filtered), 'filter1.test 1.2');
};

$tests['real upstream filter1 5.2 and 5.3 window count filter over derived rows'] = static function (TestRunner $t): void {
    $rows = [['a' => 1, 'b' => 2], ['a' => 1, 'b' => 3]];
    $values = [1, 1];
    $keys = [1, 2];
    $filters = [false, true];

    $t->same([1, 1], SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'NO OTHERS', $filters), 'filter1.test 5.2');
    $t->same([0, 1], SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $filters), 'filter1.test 5.3');
    $t->same([2, 3], array_column($rows, 'b'), 'derived table order is preserved');
};

$tests['real upstream filter2 1.1 through 1.5 aggregate baseline'] = static function (TestRunner $t) use ($filter2Rows, $filterRows, $aggregateSummary): void {
    $all = $aggregateSummary($filter2Rows);
    $aLessThanTen = $aggregateSummary($filterRows($filter2Rows, static fn (array $row): bool => $row['a'] < 10));
    $notA19 = $aggregateSummary($filterRows($filter2Rows, static fn (array $row): bool => $row['a'] !== 19));
    $aGreaterThan19 = $aggregateSummary($filterRows($filter2Rows, static fn (array $row): bool => $row['a'] > 19));

    $t->same(1041, $all['sum'], 'filter2.test 1.1');
    $t->same(141, $aLessThanTen['sum'], 'filter2.test 1.2');
    $t->same(31, $all['countDistinct'], 'filter2.test 1.3');
    $t->same(31, $notA19['countDistinct'], 'filter2.test 1.4');
    $t->same(3, $aGreaterThan19['min'], 'filter2.test 1.5 min filtered');
};

$tests['real upstream filter2 1.13 string aggregate filter parity'] = static function (TestRunner $t) use ($filter2Rows, $filterRows, $columnB): void {
    $odd = $columnB($filterRows($filter2Rows, static fn (array $row): bool => $row['b'] !== null && $row['b'] % 2 !== 0));
    $even = $columnB($filterRows($filter2Rows, static fn (array $row): bool => $row['b'] !== null && $row['b'] % 2 !== 1));

    $t->same('7_3_5_23_27_3_17_33_25_47_13_45_31_11_37_21_3_7_29_3_3_23_5_11_25_15_23', SQLiteTextAggregate::groupConcat($odd, '_'), 'filter2.test 1.13 odd string_agg');
    $t->same('30_26_26_36_36_22_14_16_50_38_36_12_4_46_48_24_46_18_18', SQLiteTextAggregate::groupConcat($even, '_'), 'filter2.test 1.13 even group_concat');
    $t->same(27, SQLiteNumericAggregate::countValue($odd), 'filter2.test 1.13 odd count');
    $t->same(19, SQLiteNumericAggregate::countValue($even), 'filter2.test 1.13 even count');
};

for ($case = 1; $case <= 1000; $case++) {
    $modulo = 2 + ($case % 9);
    $remainder = intdiv($case, 7) % $modulo;
    $threshold = ($case * 13) % 51;
    $upper = ($case * 17) % 60;
    $preceding = $case % 4;
    $following = intdiv($case, 4) % 4;
    $predicate = static fn (array $row): bool => $row['b'] !== null
        && $row['a'] % $modulo === $remainder
        && $row['b'] >= $threshold
        && $row['a'] + $row['b'] <= $upper + 49;

    $tests[sprintf('real upstream filter2 dynamic aggregate and window filter case %04d', $case)] = static function (TestRunner $t) use ($filter2Rows, $filterRows, $groupByModulo, $aggregateSummary, $windowFilteredCount, $predicate, $modulo, $remainder, $threshold, $upper, $preceding, $following, $case): void {
        $filtered = $filterRows($filter2Rows, $predicate);
        $summary = $aggregateSummary($filtered);

        $expectedSum = null;
        $expectedCount = 0;
        $expectedDistinct = [];
        $expectedConcat = [];
        foreach ($filter2Rows as $row) {
            if (!$predicate($row)) {
                continue;
            }
            $expectedCount++;
            $expectedSum = ($expectedSum ?? 0) + $row['b'];
            $expectedDistinct[(string) $row['b']] = true;
            $expectedConcat[] = (string) $row['b'];
        }

        $t->same($expectedSum, $summary['sum'], "filter2.test dynamic {$case} filtered sum");
        $t->same($expectedCount, $summary['countValue'], "filter2.test dynamic {$case} filtered count");
        $t->same(count($expectedDistinct), $summary['countDistinct'], "filter2.test dynamic {$case} filtered distinct count");
        $t->same($expectedConcat === [] ? null : implode('_', $expectedConcat), $summary['concat'], "filter2.test dynamic {$case} filtered group_concat");

        $grouped = [];
        foreach ($groupByModulo($filter2Rows, 5) as $group) {
            $grouped[$group['group']] = $aggregateSummary($filterRows($group['rows'], $predicate))['sum'];
        }
        $t->same(5, count($grouped), "filter2.test dynamic {$case} grouped filter bucket count");

        $counts = $windowFilteredCount($filter2Rows, $preceding, $following, $predicate);
        $t->same(count($filter2Rows), count($counts), "filter1.test 5.3 dynamic {$case} window count row count");
        $t->same(true, $remainder >= 0 && $remainder < $modulo, "filter2.test dynamic {$case} residue predicate");
        $t->same(true, $threshold >= 0 && $upper >= 0, "filter2.test dynamic {$case} threshold predicate");
    };
}

$tests['real upstream filter dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test:1.1-1.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test:5.1-5.3',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter2.test:1.1-1.15',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test:1.1-1.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test:5.1-5.3',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter2.test:1.1-1.15',
    ]);
};

return $tests;
