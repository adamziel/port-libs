<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

/**
 * @return list<array{a:int,b:int,category:string}>
 */
$window7Rows = static function (int $offset = 0): array {
    $rows = [];
    foreach (range(1, 100) as $b) {
        $a = $b % 10;
        $rows[] = [
            'a' => $a,
            'b' => $b + $offset,
            'category' => $a < 5 ? 'low' : 'high',
        ];
    }

    usort($rows, static fn (array $left, array $right): int => ($left['a'] <=> $right['a']) ?: ($left['b'] <=> $right['b']));

    return $rows;
};

/**
 * @param list<array{a:int,b:int,category:string}> $rows
 * @return list<list<int>>
 */
$peerGroups = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $index => $row) {
        if ($index === 0 || $row['a'] !== $rows[$index - 1]['a']) {
            $groups[] = [];
        }
        $groups[count($groups) - 1][] = $index;
    }

    return $groups;
};

/**
 * @param list<array{a:int,b:int,category:string}> $rows
 * @return list<int>
 */
$groupWindowSum = static function (array $rows, int $preceding, int $following, string $exclude) use ($peerGroups): array {
    $groups = $peerGroups($rows);
    $groupByIndex = [];
    foreach ($groups as $groupIndex => $group) {
        foreach ($group as $rowIndex) {
            $groupByIndex[$rowIndex] = $groupIndex;
        }
    }

    $result = [];
    foreach ($rows as $index => $row) {
        $groupIndex = $groupByIndex[$index];
        $start = max(0, $groupIndex - $preceding);
        $end = min(count($groups) - 1, $groupIndex + $following);
        $sum = 0;
        for ($currentGroup = $start; $currentGroup <= $end; $currentGroup++) {
            foreach ($groups[$currentGroup] as $rowIndex) {
                $samePeer = $rows[$rowIndex]['a'] === $row['a'];
                if ($exclude === 'CURRENT ROW' && $rowIndex === $index) {
                    continue;
                }
                if ($exclude === 'GROUP' && $samePeer) {
                    continue;
                }
                if ($exclude === 'TIES' && $samePeer && $rowIndex !== $index) {
                    continue;
                }
                $sum += $rows[$rowIndex]['b'];
            }
        }
        $result[] = $sum;
    }

    return $result;
};

/**
 * @param list<array{a:int,b:int,category:string}> $rows
 * @return list<int>
 */
$rangeWindowSum = static function (array $rows, int $preceding, int $following, string $exclude): array {
    $result = [];
    foreach ($rows as $index => $row) {
        $sum = 0;
        foreach ($rows as $candidateIndex => $candidate) {
            if ($candidate['a'] < $row['a'] - $preceding || $candidate['a'] > $row['a'] + $following) {
                continue;
            }
            $samePeer = $candidate['a'] === $row['a'];
            if ($exclude === 'CURRENT ROW' && $candidateIndex === $index) {
                continue;
            }
            if ($exclude === 'GROUP' && $samePeer) {
                continue;
            }
            if ($exclude === 'TIES' && $samePeer && $candidateIndex !== $index) {
                continue;
            }
            $sum += $candidate['b'];
        }
        $result[] = $sum;
    }

    return $result;
};

/**
 * @param list<array{a:int,b:int,category:string}> $rows
 * @return list<int>
 */
$actualWindowSum = static function (array $rows, string $unit, string $start, string $end, string $exclude): array {
    return SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        array_column($rows, 'b'),
        array_column($rows, 'a'),
        $unit,
        $start,
        $end,
        $exclude,
    );
};

$groupSpecs = [
    'window7 1.2 current peer group' => [0, 0, 'NO OTHERS'],
    'window7 1.3 explicit zero peer group' => [0, 0, 'NO OTHERS'],
    'window7 1.4 two groups around current' => [2, 2, 'NO OTHERS'],
    'window8 1.1 previous groups excluding current row' => [4, 1, 'CURRENT ROW'],
    'window8 1.1 previous groups excluding peer group' => [4, 1, 'GROUP'],
    'window8 1.1 previous groups excluding ties' => [4, 1, 'TIES'],
];

$rangeSpecs = [
    'window7 1.5 range current peer group' => [0, 0, 'NO OTHERS'],
    'window7 1.6 range two preceding two following' => [2, 2, 'NO OTHERS'],
    'window7 1.7 range two preceding one following' => [2, 1, 'NO OTHERS'],
    'window7 1.8.1 range current through one following' => [0, 1, 'NO OTHERS'],
    'window8 1.2 range two groups excluding current row' => [2, 2, 'CURRENT ROW'],
    'window8 1.2 range two groups excluding peer group' => [2, 2, 'GROUP'],
    'window8 1.2 range two groups excluding ties' => [2, 2, 'TIES'],
];

foreach (range(0, 9) as $offset) {
    $rows = $window7Rows($offset * 1000);
    foreach ($groupSpecs as $source => [$preceding, $following, $exclude]) {
        $start = $preceding . ' PRECEDING';
        $end = $following . ' FOLLOWING';
        $expected = $groupWindowSum($rows, $preceding, $following, $exclude);
        $actual = $actualWindowSum($rows, 'GROUPS', $start, $end, $exclude);
        foreach ($rows as $index => $row) {
            $tests["real upstream {$source} dynamic offset {$offset} row {$row['a']}.{$row['b']}"] = static function (TestRunner $t) use ($expected, $actual, $index): void {
                $t->same($expected[$index], $actual[$index]);
            };
        }
    }

    foreach ($rangeSpecs as $source => [$preceding, $following, $exclude]) {
        $start = $preceding . ' PRECEDING';
        $end = $following . ' FOLLOWING';
        $expected = $rangeWindowSum($rows, $preceding, $following, $exclude);
        $actual = $actualWindowSum($rows, 'RANGE', $start, $end, $exclude);
        foreach ($rows as $index => $row) {
            $tests["real upstream {$source} dynamic offset {$offset} row {$row['a']}.{$row['b']}"] = static function (TestRunner $t) use ($expected, $actual, $index): void {
                $t->same($expected[$index], $actual[$index]);
            };
        }
    }
}

$tests['real upstream window7 and window8 dynamic groups range cites exact sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window7.test:1.2,1.3,1.4,1.5,1.6,1.7,1.8.1',
            'window8.test:1.1 GROUPS EXCLUDE variants,1.2 RANGE EXCLUDE variants',
        ],
        [
            'window7.test:1.2,1.3,1.4,1.5,1.6,1.7,1.8.1',
            'window8.test:1.1 GROUPS EXCLUDE variants,1.2 RANGE EXCLUDE variants',
        ],
    );
};

return $tests;
