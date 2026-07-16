<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [];
for ($b = 1; $b <= 100; $b++) {
    $rows[] = ['a' => $b % 10, 'b' => $b];
}
usort($rows, static fn (array $left, array $right): int => [$left['a'], $left['b']] <=> [$right['a'], $right['b']]);

$keys = array_column($rows, 'a');
$values = array_column($rows, 'b');

$peerGroups = static function (array $orderKeys): array {
    $groups = [];
    foreach ($orderKeys as $index => $key) {
        if ($index === 0 || $key !== $orderKeys[$index - 1]) {
            $groups[] = [];
        }
        $groups[count($groups) - 1][] = $index;
    }

    return $groups;
};

$groups = $peerGroups($keys);
$groupByIndex = [];
foreach ($groups as $groupIndex => $group) {
    foreach ($group as $rowIndex) {
        $groupByIndex[$rowIndex] = $groupIndex;
    }
}

$groupsFrameOracle = static function (int $rowIndex, int $preceding, int $following) use ($groups, $groupByIndex): array {
    $currentGroup = $groupByIndex[$rowIndex];
    $start = max(0, $currentGroup - $preceding);
    $end = min(count($groups) - 1, $currentGroup + $following);
    $indexes = [];
    for ($group = $start; $group <= $end; $group++) {
        array_push($indexes, ...$groups[$group]);
    }

    return $indexes;
};

$rangeFrameOracle = static function (array $orderKeys, int $rowIndex, int $preceding, int $following, string $direction): array {
    $current = (float) $orderKeys[$rowIndex];
    if ($direction === 'DESC') {
        $lower = $current - $following;
        $upper = $current + $preceding;
    } else {
        $lower = $current - $preceding;
        $upper = $current + $following;
    }

    $indexes = [];
    foreach ($orderKeys as $index => $key) {
        $value = (float) $key;
        if ($value >= $lower - 1.0e-12 && $value <= $upper + 1.0e-12) {
            $indexes[] = $index;
        }
    }

    return $indexes;
};

$sumIndexes = static function (array $sourceValues, array $indexes): int {
    $sum = 0;
    foreach ($indexes as $index) {
        $sum += $sourceValues[$index];
    }

    return $sum;
};

$cases = [
    'window7.test 1.2 groups current row' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'GROUPS', 'CURRENT ROW', 'CURRENT ROW'),
        'count' => SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'GROUPS', 'CURRENT ROW', 'CURRENT ROW'),
        'oracle' => static fn (int $row): array => $groupsFrameOracle($row, 0, 0),
    ],
    'window7.test 1.3 groups zero preceding following' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'GROUPS', '0 PRECEDING', '0 FOLLOWING'),
        'count' => SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'GROUPS', '0 PRECEDING', '0 FOLLOWING'),
        'oracle' => static fn (int $row): array => $groupsFrameOracle($row, 0, 0),
    ],
    'window7.test 1.4 groups two preceding two following' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'GROUPS', '2 PRECEDING', '2 FOLLOWING'),
        'count' => SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'GROUPS', '2 PRECEDING', '2 FOLLOWING'),
        'oracle' => static fn (int $row): array => $groupsFrameOracle($row, 2, 2),
    ],
    'window7.test 1.5 range zero preceding following' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', '0 PRECEDING', '0 FOLLOWING'),
        'count' => SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'RANGE', '0 PRECEDING', '0 FOLLOWING'),
        'oracle' => static fn (int $row): array => $rangeFrameOracle($keys, $row, 0, 0, 'ASC'),
    ],
    'window7.test 1.6 range two preceding two following' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', '2 PRECEDING', '2 FOLLOWING'),
        'count' => SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'RANGE', '2 PRECEDING', '2 FOLLOWING'),
        'oracle' => static fn (int $row): array => $rangeFrameOracle($keys, $row, 2, 2, 'ASC'),
    ],
    'window7.test 1.7 range two preceding one following' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', '2 PRECEDING', '1 FOLLOWING'),
        'count' => SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'RANGE', '2 PRECEDING', '1 FOLLOWING'),
        'oracle' => static fn (int $row): array => $rangeFrameOracle($keys, $row, 2, 1, 'ASC'),
    ],
    'window7.test 1.8.1 range zero preceding one following asc' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', '0 PRECEDING', '1 FOLLOWING'),
        'count' => SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'RANGE', '0 PRECEDING', '1 FOLLOWING'),
        'oracle' => static fn (int $row): array => $rangeFrameOracle($keys, $row, 0, 1, 'ASC'),
    ],
    'window7.test 1.8.2 range zero preceding one following desc' => [
        'actual' => SQLiteWindowFunction::aggregateOrderedRangeValues('sum', $values, $keys, 'DESC', 'LAST', '0 PRECEDING', '1 FOLLOWING'),
        'count' => SQLiteWindowFunction::aggregateOrderedRangeValues('count', $values, $keys, 'DESC', 'LAST', '0 PRECEDING', '1 FOLLOWING'),
        'oracle' => static fn (int $row): array => $rangeFrameOracle($keys, $row, 0, 1, 'DESC'),
    ],
];

foreach ($cases as $caseName => $case) {
    foreach ($rows as $rowIndex => $row) {
        $expectedIndexes = $case['oracle']($rowIndex);
        $expectedSum = $sumIndexes($values, $expectedIndexes);
        $actual = $case['actual'];
        $actualCount = $case['count'];
        $rowLabel = ' row ' . ($rowIndex + 1) . ' a=' . $row['a'] . ' b=' . $row['b'];
        $tests[$caseName . ' sum' . $rowLabel] = static function (TestRunner $t) use ($actual, $expectedSum, $expectedIndexes, $rowIndex): void {
            $t->same($expectedSum, $actual[$rowIndex]);
            $t->same(true, in_array($rowIndex, $expectedIndexes, true));
        };
        $tests[$caseName . ' frame cardinality' . $rowLabel] = static function (TestRunner $t) use ($actualCount, $expectedIndexes, $rowIndex): void {
            $t->same(count($expectedIndexes), $actualCount[$rowIndex]);
            $t->same(true, in_array($rowIndex, $expectedIndexes, true));
        };
    }
}

return $tests;
