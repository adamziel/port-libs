<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rows = [];
foreach (range(1, 100) as $b) {
    $rows[] = [
        'rowid' => $b,
        'a' => $b % 10,
        'b' => $b,
        'include' => 1,
    ];
}

$orderedRows = static function (array $rows): array {
    $copy = $rows;
    usort($copy, static fn (array $left, array $right): int => ($left['a'] <=> $right['a']) ?: ($left['rowid'] <=> $right['rowid']));

    return $copy;
};

$frameIndexes = static function (array $ordered, int $position, string $unit, int $preceding, int $following): array {
    if ($unit === 'RANGE') {
        $current = $ordered[$position]['a'];
        $indexes = [];
        foreach ($ordered as $index => $row) {
            if ($row['a'] >= $current - $preceding && $row['a'] <= $current + $following) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    $groups = [];
    $groupByIndex = [];
    foreach ($ordered as $index => $row) {
        if ($index === 0 || $row['a'] !== $ordered[$index - 1]['a']) {
            $groups[] = [];
        }
        $groupByIndex[$index] = count($groups) - 1;
        $groups[count($groups) - 1][] = $index;
    }

    $currentGroup = $groupByIndex[$position];
    $indexes = [];
    for ($group = max(0, $currentGroup - $preceding); $group <= min(count($groups) - 1, $currentGroup + $following); $group++) {
        array_push($indexes, ...$groups[$group]);
    }

    return $indexes;
};

$metricsFor = static function (array $ordered, array $indexes): array {
    $values = array_map(static fn (int $index): int => $ordered[$index]['b'], $indexes);

    return [
        'a' => $ordered[$indexes[0] ?? 0]['a'] ?? null,
        'sum' => array_sum($values),
        'countValue' => count($values),
        'firstValue' => $values[0] ?? null,
        'lastValue' => $values === [] ? null : $values[count($values) - 1],
    ];
};

$scenarios = [
    'window7.test 1.2 groups current row' => ['GROUPS', 0, 0],
    'window7.test 1.3 groups zero preceding zero following' => ['GROUPS', 0, 0],
    'window7.test 1.4 groups two preceding two following' => ['GROUPS', 2, 2],
    'window7.test 1.5 range zero preceding zero following' => ['RANGE', 0, 0],
    'window7.test 1.6 range two preceding two following' => ['RANGE', 2, 2],
    'window7.test 1.7 range two preceding one following' => ['RANGE', 2, 1],
    'window7.test 1.8.1 range zero preceding one following' => ['RANGE', 0, 1],
];

$ordered = $orderedRows($rows);
foreach ($scenarios as $scenarioName => [$unit, $preceding, $following]) {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'b',
        [],
        ['a'],
        'include',
        $preceding,
        $following,
        [],
        [],
        [],
        [],
        [],
        [],
        $unit,
        'NO OTHERS',
        $preceding === 0 ? 'CURRENT ROW' : 'PRECEDING',
        $following === 0 ? 'CURRENT ROW' : 'FOLLOWING',
    );
    $actual = $cursor->drainSummaries('|');

    foreach ($ordered as $position => $row) {
        $expected = $metricsFor($ordered, $frameIndexes($ordered, $position, $unit, $preceding, $following));
        foreach (['sum', 'countValue', 'firstValue', 'lastValue'] as $metric) {
            $tests["real upstream {$scenarioName} row {$row['rowid']} {$metric}"] = static function (TestRunner $t) use ($actual, $expected, $position, $metric): void {
                $t->same($expected[$metric], $actual[$position][$metric]);
            };
        }
    }
}

$tests['real upstream window7 dynamic corpus cites source scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window7.test:1.2 GROUPS CURRENT ROW',
            'window7.test:1.3 GROUPS 0 PRECEDING AND 0 FOLLOWING',
            'window7.test:1.4 GROUPS 2 PRECEDING AND 2 FOLLOWING',
            'window7.test:1.5 RANGE 0 PRECEDING AND 0 FOLLOWING',
            'window7.test:1.6 RANGE 2 PRECEDING AND 2 FOLLOWING',
            'window7.test:1.7 RANGE 2 PRECEDING AND 1 FOLLOWING',
            'window7.test:1.8.1 RANGE 0 PRECEDING AND 1 FOLLOWING',
        ],
        [
            'window7.test:1.2 GROUPS CURRENT ROW',
            'window7.test:1.3 GROUPS 0 PRECEDING AND 0 FOLLOWING',
            'window7.test:1.4 GROUPS 2 PRECEDING AND 2 FOLLOWING',
            'window7.test:1.5 RANGE 0 PRECEDING AND 0 FOLLOWING',
            'window7.test:1.6 RANGE 2 PRECEDING AND 2 FOLLOWING',
            'window7.test:1.7 RANGE 2 PRECEDING AND 1 FOLLOWING',
            'window7.test:1.8.1 RANGE 0 PRECEDING AND 1 FOLLOWING',
        ],
    );
};

return $tests;
