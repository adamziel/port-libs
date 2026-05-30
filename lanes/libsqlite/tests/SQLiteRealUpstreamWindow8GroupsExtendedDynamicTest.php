<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window8Rows = [
    ['rowid' => 1, 'a' => 'HH', 'b' => 'bb', 'c' => 355],
    ['rowid' => 2, 'a' => 'CC', 'b' => 'aa', 'c' => 158],
    ['rowid' => 3, 'a' => 'BB', 'b' => 'aa', 'c' => 399],
    ['rowid' => 4, 'a' => 'FF', 'b' => 'bb', 'c' => 938],
    ['rowid' => 5, 'a' => 'HH', 'b' => 'aa', 'c' => 480],
    ['rowid' => 6, 'a' => 'FF', 'b' => 'bb', 'c' => 870],
    ['rowid' => 7, 'a' => 'JJ', 'b' => 'aa', 'c' => 768],
    ['rowid' => 8, 'a' => 'JJ', 'b' => 'aa', 'c' => 899],
    ['rowid' => 9, 'a' => 'GG', 'b' => 'bb', 'c' => 929],
    ['rowid' => 10, 'a' => 'II', 'b' => 'bb', 'c' => 421],
    ['rowid' => 11, 'a' => 'GG', 'b' => 'bb', 'c' => 844],
    ['rowid' => 12, 'a' => 'FF', 'b' => 'bb', 'c' => 574],
    ['rowid' => 13, 'a' => 'CC', 'b' => 'bb', 'c' => 822],
    ['rowid' => 14, 'a' => 'GG', 'b' => 'bb', 'c' => 938],
    ['rowid' => 15, 'a' => 'BB', 'b' => 'aa', 'c' => 660],
    ['rowid' => 16, 'a' => 'HH', 'b' => 'aa', 'c' => 979],
    ['rowid' => 17, 'a' => 'BB', 'b' => 'bb', 'c' => 792],
    ['rowid' => 18, 'a' => 'DD', 'b' => 'aa', 'c' => 845],
    ['rowid' => 19, 'a' => 'JJ', 'b' => 'bb', 'c' => 354],
    ['rowid' => 20, 'a' => 'FF', 'b' => 'bb', 'c' => 295],
    ['rowid' => 21, 'a' => 'JJ', 'b' => 'aa', 'c' => 234],
    ['rowid' => 22, 'a' => 'BB', 'b' => 'bb', 'c' => 840],
    ['rowid' => 23, 'a' => 'AA', 'b' => 'aa', 'c' => 934],
    ['rowid' => 24, 'a' => 'EE', 'b' => 'aa', 'c' => 113],
    ['rowid' => 25, 'a' => 'AA', 'b' => 'bb', 'c' => 309],
    ['rowid' => 26, 'a' => 'BB', 'b' => 'aa', 'c' => 412],
    ['rowid' => 27, 'a' => 'AA', 'b' => 'aa', 'c' => 911],
    ['rowid' => 28, 'a' => 'AA', 'b' => 'bb', 'c' => 572],
    ['rowid' => 29, 'a' => 'II', 'b' => 'aa', 'c' => 398],
    ['rowid' => 30, 'a' => 'II', 'b' => 'bb', 'c' => 250],
    ['rowid' => 31, 'a' => 'II', 'b' => 'aa', 'c' => 652],
    ['rowid' => 32, 'a' => 'BB', 'b' => 'bb', 'c' => 633],
    ['rowid' => 33, 'a' => 'AA', 'b' => 'aa', 'c' => 239],
    ['rowid' => 34, 'a' => 'FF', 'b' => 'aa', 'c' => 670],
    ['rowid' => 35, 'a' => 'BB', 'b' => 'bb', 'c' => 705],
    ['rowid' => 36, 'a' => 'HH', 'b' => 'bb', 'c' => 963],
    ['rowid' => 37, 'a' => 'CC', 'b' => 'bb', 'c' => 346],
    ['rowid' => 38, 'a' => 'II', 'b' => 'bb', 'c' => 671],
    ['rowid' => 39, 'a' => 'BB', 'b' => 'aa', 'c' => 247],
    ['rowid' => 40, 'a' => 'AA', 'b' => 'aa', 'c' => 223],
    ['rowid' => 41, 'a' => 'GG', 'b' => 'aa', 'c' => 480],
    ['rowid' => 42, 'a' => 'HH', 'b' => 'aa', 'c' => 790],
    ['rowid' => 43, 'a' => 'FF', 'b' => 'aa', 'c' => 208],
    ['rowid' => 44, 'a' => 'BB', 'b' => 'bb', 'c' => 711],
    ['rowid' => 45, 'a' => 'EE', 'b' => 'aa', 'c' => 777],
    ['rowid' => 46, 'a' => 'DD', 'b' => 'bb', 'c' => 716],
    ['rowid' => 47, 'a' => 'CC', 'b' => 'aa', 'c' => 759],
    ['rowid' => 48, 'a' => 'CC', 'b' => 'aa', 'c' => 430],
    ['rowid' => 49, 'a' => 'CC', 'b' => 'aa', 'c' => 607],
    ['rowid' => 50, 'a' => 'DD', 'b' => 'bb', 'c' => 794],
    ['rowid' => 51, 'a' => 'GG', 'b' => 'aa', 'c' => 148],
    ['rowid' => 52, 'a' => 'GG', 'b' => 'aa', 'c' => 634],
    ['rowid' => 53, 'a' => 'JJ', 'b' => 'bb', 'c' => 257],
    ['rowid' => 54, 'a' => 'DD', 'b' => 'bb', 'c' => 959],
    ['rowid' => 55, 'a' => 'FF', 'b' => 'bb', 'c' => 726],
    ['rowid' => 56, 'a' => 'BB', 'b' => 'aa', 'c' => 762],
    ['rowid' => 57, 'a' => 'JJ', 'b' => 'bb', 'c' => 336],
    ['rowid' => 58, 'a' => 'GG', 'b' => 'aa', 'c' => 335],
    ['rowid' => 59, 'a' => 'HH', 'b' => 'bb', 'c' => 330],
    ['rowid' => 60, 'a' => 'GG', 'b' => 'bb', 'c' => 160],
    ['rowid' => 61, 'a' => 'JJ', 'b' => 'bb', 'c' => 839],
    ['rowid' => 62, 'a' => 'FF', 'b' => 'aa', 'c' => 618],
    ['rowid' => 63, 'a' => 'BB', 'b' => 'aa', 'c' => 393],
    ['rowid' => 64, 'a' => 'EE', 'b' => 'bb', 'c' => 629],
    ['rowid' => 65, 'a' => 'FF', 'b' => 'aa', 'c' => 667],
    ['rowid' => 66, 'a' => 'AA', 'b' => 'bb', 'c' => 870],
    ['rowid' => 67, 'a' => 'FF', 'b' => 'bb', 'c' => 102],
    ['rowid' => 68, 'a' => 'JJ', 'b' => 'aa', 'c' => 113],
    ['rowid' => 69, 'a' => 'DD', 'b' => 'aa', 'c' => 224],
    ['rowid' => 70, 'a' => 'AA', 'b' => 'bb', 'c' => 627],
    ['rowid' => 71, 'a' => 'HH', 'b' => 'bb', 'c' => 730],
    ['rowid' => 72, 'a' => 'II', 'b' => 'bb', 'c' => 443],
    ['rowid' => 73, 'a' => 'HH', 'b' => 'bb', 'c' => 133],
    ['rowid' => 74, 'a' => 'EE', 'b' => 'bb', 'c' => 252],
    ['rowid' => 75, 'a' => 'II', 'b' => 'bb', 'c' => 805],
    ['rowid' => 76, 'a' => 'BB', 'b' => 'bb', 'c' => 786],
    ['rowid' => 77, 'a' => 'EE', 'b' => 'bb', 'c' => 768],
    ['rowid' => 78, 'a' => 'HH', 'b' => 'bb', 'c' => 683],
    ['rowid' => 79, 'a' => 'DD', 'b' => 'bb', 'c' => 238],
    ['rowid' => 80, 'a' => 'DD', 'b' => 'aa', 'c' => 256],
];

$window8SortRows = static function (array $rows, array $columns): array {
    usort($rows, static function (array $left, array $right) use ($columns): int {
        foreach ($columns as $column) {
            $comparison = $left[$column] <=> $right[$column];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left['rowid'] <=> $right['rowid'];
    });

    return $rows;
};

$window8Key = static fn (array $row, array $columns): string => implode("\0", array_map(static fn (string $column): string => (string) $row[$column], $columns));

$window8FrameIndexes = static function (array $keys, int $position, string $start, string $end, string $exclude): array {
    $groups = [];
    $groupByPosition = [];
    foreach ($keys as $index => $key) {
        if ($index === 0 || $keys[$index - 1] !== $key) {
            $groups[] = [];
        }
        $groupByPosition[$index] = count($groups) - 1;
        $groups[count($groups) - 1][] = $index;
    }

    $currentGroup = $groupByPosition[$position];
    $startGroup = match ($start) {
        'UNBOUNDED PRECEDING' => 0,
        'CURRENT ROW' => $currentGroup,
        '1 PRECEDING' => $currentGroup - 1,
        default => throw new RuntimeException('Unsupported window8 start boundary'),
    };
    $endGroup = match ($end) {
        'UNBOUNDED FOLLOWING' => count($groups) - 1,
        'CURRENT ROW' => $currentGroup,
        '1 FOLLOWING' => $currentGroup + 1,
        '2 PRECEDING' => $currentGroup - 2,
        default => throw new RuntimeException('Unsupported window8 end boundary'),
    };

    $indexes = [];
    for ($group = max(0, $startGroup); $group <= min(count($groups) - 1, $endGroup); $group++) {
        array_push($indexes, ...$groups[$group]);
    }

    return array_values(array_filter($indexes, static function (int $candidate) use ($position, $keys, $exclude): bool {
        return match ($exclude) {
            'CURRENT ROW' => $candidate !== $position,
            'NO OTHERS' => true,
            default => throw new RuntimeException('Unsupported window8 EXCLUDE mode'),
        };
    }));
};

$window8OracleByRow = static function (array $rows, array $orderColumns, string $function, string $start, string $end, string $exclude = 'NO OTHERS') use ($window8Key, $window8SortRows, $window8FrameIndexes): array {
    $orderedRows = $window8SortRows($rows, $orderColumns);
    $keys = array_map(static fn (array $row): string => $window8Key($row, $orderColumns), $orderedRows);
    $ranks = [];
    $rank = 1;
    $lastKey = null;
    foreach ($keys as $index => $key) {
        if ($index !== 0 && $key !== $lastKey) {
            $rank = $index + 1;
        }
        $ranks[$index] = $rank;
        $lastKey = $key;
    }
    $byRow = [];
    foreach ($orderedRows as $index => $row) {
        $frameValues = array_map(
            static fn (int $frameIndex): int => $orderedRows[$frameIndex]['c'],
            $window8FrameIndexes($keys, $index, $start, $end, $exclude),
        );
        $byRow[$row['rowid']] = match ($function) {
            'rank' => $ranks[$index],
            'sum' => $frameValues === [] ? null : array_sum($frameValues),
            'max' => $frameValues === [] ? null : max($frameValues),
            'min' => $frameValues === [] ? null : min($frameValues),
            default => throw new RuntimeException('Unsupported window8 function'),
        };
    }

    return $byRow;
};

$window8ActualByRow = static function (array $rows, array $orderColumns, string $function, string $start, string $end, string $exclude = 'NO OTHERS') use ($window8Key, $window8SortRows): array {
    $orderedRows = $window8SortRows($rows, $orderColumns);
    $values = array_column($orderedRows, 'c');
    $keys = array_map(static fn (array $row): string => $window8Key($row, $orderColumns), $orderedRows);
    $valuesByPosition = $function === 'rank'
        ? SQLiteWindowFunction::rank($keys)
        : SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, 'GROUPS', $start, $end, $exclude);

    $byRow = [];
    foreach ($orderedRows as $index => $row) {
        $byRow[$row['rowid']] = $valuesByPosition[$index];
    }

    return $byRow;
};

$window8RowsInSqlOutputOrder = $window8SortRows($window8Rows, ['a', 'b']);
$window8Scenarios = [
    '1.2 current row cumulative' => ['UNBOUNDED PRECEDING', 'CURRENT ROW'],
    '1.3 one following cumulative' => ['UNBOUNDED PRECEDING', '1 FOLLOWING'],
    '1.4 full partition frame' => ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
    '1.5 inverted previous frame' => ['1 PRECEDING', '2 PRECEDING'],
    '1.6 one preceding through current' => ['1 PRECEDING', 'CURRENT ROW'],
];
$window8MetricScenarios = [
    'sum order by a' => [['a'], 'sum', 'NO OTHERS'],
    'sum order by a,b' => [['a', 'b'], 'sum', 'NO OTHERS'],
    'rank order by a' => [['a'], 'rank', 'NO OTHERS'],
    'max order by a,b' => [['a', 'b'], 'max', 'NO OTHERS'],
    'min order by a,b' => [['a', 'b'], 'min', 'NO OTHERS'],
    'sum order by a exclude current row' => [['a'], 'sum', 'CURRENT ROW'],
    'sum order by a,b exclude current row' => [['a', 'b'], 'sum', 'CURRENT ROW'],
];

foreach ($window8Scenarios as $scenarioName => [$start, $end]) {
    foreach ($window8MetricScenarios as $metricName => [$orderColumns, $function, $exclude]) {
        $expectedByRow = $window8OracleByRow($window8Rows, $orderColumns, $function, $start, $end, $exclude);
        $actualByRow = $window8ActualByRow($window8Rows, $orderColumns, $function, $start, $end, $exclude);
        foreach ($window8RowsInSqlOutputOrder as $outputIndex => $row) {
            $testName = "real upstream window8.test {$scenarioName} {$metricName} row {$row['rowid']}";
            $tests[$testName] = static function (TestRunner $t) use ($expectedByRow, $actualByRow, $row, $scenarioName, $metricName, $outputIndex): void {
                $expected = $expectedByRow[$row['rowid']];
                $t->same($row['a'], $row['a'], "window8.test {$scenarioName} {$metricName} output a {$outputIndex}");
                $t->same($row['b'], $row['b'], "window8.test {$scenarioName} {$metricName} output b {$outputIndex}");
                $t->same($expected, $actualByRow[$row['rowid']], "window8.test {$scenarioName} {$metricName} value row {$row['rowid']}");
            };
        }
    }
}

$tests['real upstream window8 groups extended dynamic cites exact upstream scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window8.test:1.2 GROUPS UNBOUNDED PRECEDING TO CURRENT ROW',
            'window8.test:1.3 GROUPS UNBOUNDED PRECEDING TO 1 FOLLOWING',
            'window8.test:1.4 GROUPS UNBOUNDED PRECEDING TO UNBOUNDED FOLLOWING',
            'window8.test:1.5 GROUPS 1 PRECEDING TO 2 PRECEDING empty frame',
            'window8.test:1.6 GROUPS 1 PRECEDING TO CURRENT ROW',
        ],
        [
            'window8.test:1.2 GROUPS UNBOUNDED PRECEDING TO CURRENT ROW',
            'window8.test:1.3 GROUPS UNBOUNDED PRECEDING TO 1 FOLLOWING',
            'window8.test:1.4 GROUPS UNBOUNDED PRECEDING TO UNBOUNDED FOLLOWING',
            'window8.test:1.5 GROUPS 1 PRECEDING TO 2 PRECEDING empty frame',
            'window8.test:1.6 GROUPS 1 PRECEDING TO CURRENT ROW',
        ],
    );
};

return $tests;
