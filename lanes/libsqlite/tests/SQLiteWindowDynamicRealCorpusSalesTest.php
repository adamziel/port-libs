<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$baseSalesRows = [
    ['emp' => 'Alice', 'region' => 'North', 'total' => 34],
    ['emp' => 'Frank', 'region' => 'South', 'total' => 22],
    ['emp' => 'Charles', 'region' => 'North', 'total' => 45],
    ['emp' => 'Darrell', 'region' => 'South', 'total' => 8],
    ['emp' => 'Grant', 'region' => 'South', 'total' => 23],
    ['emp' => 'Brad', 'region' => 'North', 'total' => 22],
    ['emp' => 'Elizabeth', 'region' => 'South', 'total' => 99],
    ['emp' => 'Horace', 'region' => 'East', 'total' => 1],
];

$orderIndexesByRegion = static function (array $rows, bool $descending = false): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[$row['region']][] = $index;
    }
    ksort($partitions);

    $ordered = [];
    foreach ($partitions as $indexes) {
        usort($indexes, static function (int $left, int $right) use ($rows, $descending): int {
            $comparison = $rows[$left]['total'] <=> $rows[$right]['total'];
            if ($descending) {
                $comparison *= -1;
            }

            return $comparison ?: strcmp($rows[$left]['emp'], $rows[$right]['emp']);
        });
        array_push($ordered, ...$indexes);
    }

    return $ordered;
};

$runningSumByRegion = static function (array $rows, bool $descending = false, string $start = 'UNBOUNDED PRECEDING', string $end = 'CURRENT ROW') use ($orderIndexesByRegion): array {
    $orderedIndexes = $orderIndexesByRegion($rows, $descending);
    $result = array_fill(0, count($rows), null);
    $byRegion = [];
    foreach ($orderedIndexes as $index) {
        $byRegion[$rows[$index]['region']][] = $index;
    }

    foreach ($byRegion as $indexes) {
        $values = array_map(static fn (int $index): int => $rows[$index]['total'], $indexes);
        $keys = range(1, count($values));
        $window = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', $start, $end);
        foreach ($indexes as $offset => $index) {
            $result[$index] = $window[$offset];
        }
    }

    return $result;
};

$topNByRegion = static function (array $rows, int $limit) use ($orderIndexesByRegion): array {
    $orderedIndexes = $orderIndexesByRegion($rows, true);
    $ranked = [];
    $byRegion = [];
    foreach ($orderedIndexes as $index) {
        $byRegion[$rows[$index]['region']][] = $index;
    }
    foreach ($byRegion as $indexes) {
        $keys = array_map(static fn (int $index): int => -$rows[$index]['total'], $indexes);
        $rowNumbers = SQLiteWindowFunction::rowNumber($keys);
        foreach ($indexes as $offset => $index) {
            if ($rowNumbers[$offset] <= $limit) {
                $ranked[] = [$rows[$index]['emp'], $rows[$index]['region'], $rows[$index]['total'], $rowNumbers[$offset]];
            }
        }
    }

    usort($ranked, static fn (array $left, array $right): int => strcmp((string) $left[1], (string) $right[1]) ?: ((int) $right[2] <=> (int) $left[2]) ?: strcmp((string) $left[0], (string) $right[0]));

    return $ranked;
};

$mutateSalesRows = static function (int $case) use ($baseSalesRows): array {
    $regions = ['North', 'South', 'East', 'West'];
    $rows = [];
    foreach ($baseSalesRows as $index => $row) {
        $total = (($row['total'] + ($case * ($index + 3))) % 113) + 1;
        $rows[] = [
            'emp' => $row['emp'] . '_' . $case,
            'region' => $regions[($index + $case) % count($regions)],
            'total' => $total,
        ];
    }

    return $rows;
};

$tests['real upstream window1.test 10.1 best two salespeople per region base rows'] = static function (TestRunner $t) use ($topNByRegion, $baseSalesRows): void {
    $t->same([
        ['Horace', 'East', 1, 1],
        ['Charles', 'North', 45, 1],
        ['Alice', 'North', 34, 2],
        ['Elizabeth', 'South', 99, 1],
        ['Grant', 'South', 23, 2],
    ], $topNByRegion($baseSalesRows, 2));
};

$tests['real upstream window1.test 10.2 partition running sums base rows'] = static function (TestRunner $t) use ($runningSumByRegion, $baseSalesRows): void {
    $t->same([56, 30, 101, 8, 53, 22, 152, 1], $runningSumByRegion($baseSalesRows));
};

$tests['real upstream window1.test 10.5 current row to following sums base rows'] = static function (TestRunner $t) use ($runningSumByRegion, $baseSalesRows): void {
    $t->same([79, 144, 45, 152, 122, 101, 99, 1], $runningSumByRegion($baseSalesRows, false, 'CURRENT ROW', 'UNBOUNDED FOLLOWING'));
};

for ($case = 0; $case < 250; $case++) {
    $tests['real upstream window1.test 10 dynamic best two partition ranks case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $mutateSalesRows, $topNByRegion): void {
        $rows = $mutateSalesRows($case);
        $actual = $topNByRegion($rows, 2);
        $seenByRegion = [];
        foreach ($actual as [$emp, $region, $total, $rank]) {
            $t->true(is_string($emp) && $emp !== '', "window1.test 10.1 case {$case} employee label");
            $t->true(is_int($total) && $total > 0, "window1.test 10.1 case {$case} total");
            $t->true($rank === 1 || $rank === 2, "window1.test 10.1 case {$case} rank bound");
            $seenByRegion[$region] = ($seenByRegion[$region] ?? 0) + 1;
            $t->true($seenByRegion[$region] <= 2, "window1.test 10.1 case {$case} region limit {$region}");
        }
    };

    $tests['real upstream window1.test 10 dynamic running sum partition case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $mutateSalesRows, $runningSumByRegion, $orderIndexesByRegion): void {
        $rows = $mutateSalesRows($case);
        $actual = $runningSumByRegion($rows);
        $ordered = $orderIndexesByRegion($rows);
        $prefix = [];
        foreach ($ordered as $index) {
            $region = $rows[$index]['region'];
            $prefix[$region] = ($prefix[$region] ?? 0) + $rows[$index]['total'];
            $t->same($prefix[$region], $actual[$index], "window1.test 10.2 case {$case} row {$index}");
        }
    };

    $tests['real upstream window1.test 10 dynamic following frame partition case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $mutateSalesRows, $runningSumByRegion, $orderIndexesByRegion): void {
        $rows = $mutateSalesRows($case);
        $actual = $runningSumByRegion($rows, false, 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
        $ordered = $orderIndexesByRegion($rows);
        $suffixTotals = [];
        foreach (array_reverse($ordered) as $index) {
            $region = $rows[$index]['region'];
            $suffixTotals[$region] = ($suffixTotals[$region] ?? 0) + $rows[$index]['total'];
            $t->same($suffixTotals[$region], $actual[$index], "window1.test 10.5 case {$case} row {$index}");
        }
    };

    $tests['real upstream window1.test 10 dynamic limit offset preserves window values case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $mutateSalesRows, $runningSumByRegion, $orderIndexesByRegion): void {
        $rows = $mutateSalesRows($case);
        $windowValues = $runningSumByRegion($rows, false, 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
        $ordered = $orderIndexesByRegion($rows);
        $slice = array_slice($ordered, 2, 5);
        $t->same(5, count($slice), "window1.test 10.6 case {$case} sliced count");
        foreach ($slice as $index) {
            $sameRegionTotals = array_values(array_filter(
                $ordered,
                static fn (int $candidate): bool => $rows[$candidate]['region'] === $rows[$index]['region']
                    && ($rows[$candidate]['total'] > $rows[$index]['total'] || ($rows[$candidate]['total'] === $rows[$index]['total'] && strcmp($rows[$candidate]['emp'], $rows[$index]['emp']) >= 0))
            ));
            $expected = array_sum(array_map(static fn (int $candidate): int => $rows[$candidate]['total'], $sameRegionTotals));
            $t->same($expected, $windowValues[$index], "window1.test 10.6 case {$case} row {$index}");
        }
    };
}

return $tests;
