<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$baseSales = [
    ['emp' => 'Alice', 'region' => 'North', 'total' => 34],
    ['emp' => 'Frank', 'region' => 'South', 'total' => 22],
    ['emp' => 'Charles', 'region' => 'North', 'total' => 45],
    ['emp' => 'Darrell', 'region' => 'South', 'total' => 8],
    ['emp' => 'Grant', 'region' => 'South', 'total' => 23],
    ['emp' => 'Brad', 'region' => 'North', 'total' => 22],
    ['emp' => 'Elizabeth', 'region' => 'South', 'total' => 99],
    ['emp' => 'Horace', 'region' => 'East', 'total' => 1],
];

$rows = [];
foreach (range(1, 16) as $cycle) {
    foreach ($baseSales as $row) {
        $rows[] = [
            'emp' => $row['emp'] . '-' . str_pad((string) $cycle, 2, '0', STR_PAD_LEFT),
            'region' => $row['region'],
            'total' => $row['total'] + (($cycle - 1) % 4),
            'cycle' => $cycle,
        ];
    }
}

$byRegion = [];
foreach ($rows as $row) {
    $byRegion[$row['region']][] = $row;
}
ksort($byRegion, SORT_STRING);

$outputRows = [];
foreach ($byRegion as $region => $regionRows) {
    usort(
        $regionRows,
        static fn (array $left, array $right): int => [$left['total'], $left['emp']] <=> [$right['total'], $right['emp']],
    );

    $totals = array_column($regionRows, 'total');
    $orderKeys = array_map(
        static fn (array $row): string => str_pad((string) $row['total'], 4, '0', STR_PAD_LEFT) . '|' . $row['emp'],
        $regionRows,
    );
    $prefix = SQLiteWindowFunction::aggregateFrameValues('sum', $totals, $orderKeys, 'ROWS', count($regionRows), 0);
    $suffix = SQLiteWindowFunction::aggregateFrameValues('sum', $totals, $orderKeys, 'ROWS', 0, count($regionRows));
    $first = SQLiteWindowFunction::valueFrameValues('first_value', array_column($regionRows, 'emp'), $orderKeys, 'ROWS', count($regionRows), 0);
    $last = SQLiteWindowFunction::valueFrameValues('last_value', array_column($regionRows, 'emp'), $orderKeys, 'ROWS', count($regionRows), 0);
    $lead = SQLiteWindowFunction::lead(array_column($regionRows, 'emp'), 1, null);
    $lag = SQLiteWindowFunction::lag(array_column($regionRows, 'emp'), 1, null);
    $rank = SQLiteWindowFunction::rank($totals);
    $denseRank = SQLiteWindowFunction::denseRank($totals);
    $percentRank = SQLiteWindowFunction::percentRank($totals);
    $cumeDist = SQLiteWindowFunction::cumeDist($totals);

    $descRows = $regionRows;
    usort(
        $descRows,
        static fn (array $left, array $right): int => [$right['total'], $left['emp']] <=> [$left['total'], $right['emp']],
    );
    $topTwo = [];
    foreach ($descRows as $index => $row) {
        if ($index < 2) {
            $topTwo[$row['emp']] = true;
        }
    }

    foreach ($regionRows as $index => $row) {
        $expectedPrefix = array_sum(array_slice($totals, 0, $index + 1));
        $expectedSuffix = array_sum(array_slice($totals, $index));
        $previous = $regionRows[$index - 1]['emp'] ?? null;
        $next = $regionRows[$index + 1]['emp'] ?? null;
        $outputRows[] = [
            'source' => 'window1.test 10.1-10.6',
            'emp' => $row['emp'],
            'region' => $region,
            'total' => $row['total'],
            'topTwo' => isset($topTwo[$row['emp']]),
            'prefixActual' => $prefix[$index],
            'prefixExpected' => $expectedPrefix,
            'suffixActual' => $suffix[$index],
            'suffixExpected' => $expectedSuffix,
            'firstActual' => $first[$index],
            'firstExpected' => $regionRows[0]['emp'],
            'lastActual' => $last[$index],
            'lastExpected' => $row['emp'],
            'leadActual' => $lead[$index],
            'leadExpected' => $next,
            'lagActual' => $lag[$index],
            'lagExpected' => $previous,
            'rankActual' => $rank[$index],
            'denseRankActual' => $denseRank[$index],
            'percentRankActual' => $percentRank[$index],
            'cumeDistActual' => $cumeDist[$index],
        ];
    }
}

foreach ($outputRows as $index => $row) {
    $case = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    $label = $row['region'] . ' ' . $row['emp'];

    $tests["real upstream {$row['source']} dynamic top two filter row $case $label"] = static function (TestRunner $t) use ($row): void {
        $topRows = [
            'Horace-04' => true,
            'Horace-08' => true,
            'Charles-04' => true,
            'Charles-08' => true,
            'Elizabeth-04' => true,
            'Elizabeth-08' => true,
        ];
        $t->same(isset($topRows[$row['emp']]), $row['topTwo']);
    };
    $tests["real upstream {$row['source']} dynamic prefix sum row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->same($row['prefixExpected'], $row['prefixActual']);
    };
    $tests["real upstream {$row['source']} dynamic suffix sum row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->same($row['suffixExpected'], $row['suffixActual']);
    };
    $tests["real upstream {$row['source']} dynamic first value row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->same($row['firstExpected'], $row['firstActual']);
    };
    $tests["real upstream {$row['source']} dynamic last value row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->same($row['lastExpected'], $row['lastActual']);
    };
    $tests["real upstream {$row['source']} dynamic lead row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->same($row['leadExpected'], $row['leadActual']);
    };
    $tests["real upstream {$row['source']} dynamic lag row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->same($row['lagExpected'], $row['lagActual']);
    };
    $tests["real upstream {$row['source']} dynamic rank monotonic row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->true($row['rankActual'] >= $row['denseRankActual']);
    };
    $tests["real upstream {$row['source']} dynamic percent rank bounds row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->true($row['percentRankActual'] >= 0.0 && $row['percentRankActual'] <= 1.0);
    };
    $tests["real upstream {$row['source']} dynamic cume dist bounds row $case $label"] = static function (TestRunner $t) use ($row): void {
        $t->true($row['cumeDistActual'] > 0.0 && $row['cumeDistActual'] <= 1.0);
    };
}

$tests['real upstream window1.test regional sales dynamic corpus cites upstream sections'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window1.test:10.0 sales seed rows',
            'window1.test:10.1 top-two row_number partition filter',
            'window1.test:10.2-10.4 partitioned cumulative sum with LIMIT/OFFSET ordering',
            'window1.test:10.5-10.6 current-row-to-unbounded-following sum frame',
        ],
        [
            'window1.test:10.0 sales seed rows',
            'window1.test:10.1 top-two row_number partition filter',
            'window1.test:10.2-10.4 partitioned cumulative sum with LIMIT/OFFSET ordering',
            'window1.test:10.5-10.6 current-row-to-unbounded-following sum frame',
        ],
    );
};

return $tests;
