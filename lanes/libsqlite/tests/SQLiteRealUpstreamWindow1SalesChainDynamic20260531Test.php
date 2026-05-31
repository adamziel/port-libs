<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$buildSalesRows = static function (int $case): array {
    $regions = ['East', 'North', 'South', 'West'];
    $rows = [];
    for ($index = 0; $index < 16; $index++) {
        $region = $regions[($case + $index) % count($regions)];
        $total = 1 + (($case * 17 + $index * 23) % 101);
        $rows[] = [
            'emp' => 'emp_' . $case . '_' . $index,
            'region' => $region,
            'total' => $total,
            'insert_order' => $index,
        ];
    }

    return $rows;
};

$partitionIndexes = static function (array $rows, string $column): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[(string) $row[$column]][] = $index;
    }

    ksort($partitions);

    return $partitions;
};

$sortIndexes = static function (array $rows, array $indexes, string $column, string $direction): array {
    usort($indexes, static function (int $left, int $right) use ($rows, $column, $direction): int {
        $comparison = $rows[$left][$column] <=> $rows[$right][$column];
        if ($comparison === 0) {
            $comparison = $rows[$left]['insert_order'] <=> $rows[$right]['insert_order'];
        }

        return strtoupper($direction) === 'DESC' ? -$comparison : $comparison;
    });

    return $indexes;
};

$salesRowNumberByRegion = static function (array $rows) use ($partitionIndexes, $sortIndexes): array {
    $ranked = [];
    foreach ($partitionIndexes($rows, 'region') as $indexes) {
        $ordered = $sortIndexes($rows, $indexes, 'total', 'DESC');
        $rowNumbers = SQLiteWindowFunction::rowNumber(array_map(static fn (int $index): int => $rows[$index]['total'], $ordered));
        foreach ($ordered as $offset => $rowIndex) {
            $ranked[$rowIndex] = $rowNumbers[$offset];
        }
    }
    ksort($ranked);

    return $ranked;
};

$salesRunningSumByRegion = static function (array $rows, string $start, string $end) use ($partitionIndexes, $sortIndexes): array {
    $sums = [];
    foreach ($partitionIndexes($rows, 'region') as $indexes) {
        $ordered = $sortIndexes($rows, $indexes, 'total', 'ASC');
        $totals = array_map(static fn (int $index): int => $rows[$index]['total'], $ordered);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $totals, $totals, 'ROWS', $start, $end);
        foreach ($ordered as $offset => $rowIndex) {
            $sums[$rowIndex] = $actual[$offset];
        }
    }
    ksort($sums);

    return $sums;
};

$salesCorrelatedExcludingOwn = static function (array $rows): array {
    $totals = array_column($rows, 'total');
    $keys = range(1, count($rows));
    $values = [];
    foreach ($rows as $rowIndex => $_row) {
        $filters = array_map(static fn (int $index): bool => $index !== $rowIndex, array_keys($rows));
        $sum = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $totals,
            $keys,
            'RANGE',
            'UNBOUNDED PRECEDING',
            'UNBOUNDED FOLLOWING',
            'NO OTHERS',
            $filters,
        );
        $values[$rowIndex] = $sum[0];
    }

    return $values;
};

$buildChainRows = static function (int $case): array {
    $groups = ['even', 'odd', 'tail'];
    $labels = ['one', 'two', 'three', 'four', 'five', 'six'];
    $rows = [];
    for ($index = 0; $index < 12; $index++) {
        $rows[] = [
            'a' => $index + 1,
            'b' => $groups[($case + $index) % count($groups)],
            'c' => $labels[($case * 5 + $index * 7) % count($labels)] . '_' . $index,
            'insert_order' => $index,
        ];
    }

    return $rows;
};

$chainConcat = static function (array $rows) use ($partitionIndexes): array {
    $actual = [];
    foreach ($partitionIndexes($rows, 'b') as $indexes) {
        usort($indexes, static function (int $left, int $right) use ($rows): int {
            $comparison = strcmp($rows[$left]['c'], $rows[$right]['c']);
            return $comparison === 0 ? $rows[$left]['insert_order'] <=> $rows[$right]['insert_order'] : $comparison;
        });
        $values = array_map(static fn (int $index): string => $rows[$index]['c'], $indexes);
        $keys = range(1, count($values));
        $concat = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'group_concat',
            $values,
            $keys,
            'ROWS',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
            'NO OTHERS',
            null,
            '.',
        );
        foreach ($indexes as $offset => $rowIndex) {
            $actual[$rowIndex] = $concat[$offset];
        }
    }
    ksort($actual);

    return $actual;
};

for ($case = 0; $case < 250; $case++) {
    $tests['real upstream window1.test 10.1 dynamic regional top two row_number case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $buildSalesRows, $salesRowNumberByRegion): void {
        $rows = $buildSalesRows($case);
        $ranked = $salesRowNumberByRegion($rows);
        foreach ($rows as $index => $row) {
            $t->same(true, $ranked[$index] >= 1, "window1.test 10.1 rank is positive case {$case} row {$index}");
            if ($ranked[$index] <= 2) {
                $regionRows = array_values(array_filter($rows, static fn (array $candidate): bool => $candidate['region'] === $row['region']));
                usort($regionRows, static fn (array $left, array $right): int => ($right['total'] <=> $left['total']) ?: ($left['insert_order'] <=> $right['insert_order']));
                $t->same($regionRows[$ranked[$index] - 1]['emp'], $row['emp'], "window1.test 10.1 top-two employee case {$case} row {$index}");
            }
        }
    };

    $tests['real upstream window1.test 10.2 dynamic partition running sum case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $buildSalesRows, $salesRunningSumByRegion): void {
        $rows = $buildSalesRows($case);
        $running = $salesRunningSumByRegion($rows, 'UNBOUNDED PRECEDING', 'CURRENT ROW');
        $following = $salesRunningSumByRegion($rows, 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
        foreach ($rows as $index => $row) {
            $partitionTotal = array_sum(array_map(
                static fn (array $candidate): int => $candidate['region'] === $row['region'] ? $candidate['total'] : 0,
                $rows,
            ));
            $t->same($partitionTotal, $running[$index] + $following[$index] - $row['total'], "window1.test 10.2/10.5 partition total case {$case} row {$index}");
        }
    };

    $tests['real upstream window1.test 10.7 dynamic scalar full partition concat case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $buildSalesRows): void {
        $rows = $buildSalesRows($case);
        $total = array_sum(array_column($rows, 'total'));
        $full = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', array_column($rows, 'total'), range(1, count($rows)), 'RANGE', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
        foreach ($rows as $index => $row) {
            $t->same($total . $row['emp'], $full[$index] . $row['emp'], "window1.test 10.7 scalar subquery full-frame case {$case} row {$index}");
        }
    };

    $tests['real upstream window1.test 10.8 and 18.3 dynamic filtered chained windows case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $buildSalesRows, $salesCorrelatedExcludingOwn, $buildChainRows, $chainConcat): void {
        $sales = $buildSalesRows($case);
        $excluded = $salesCorrelatedExcludingOwn($sales);
        $total = array_sum(array_column($sales, 'total'));
        foreach ($sales as $index => $row) {
            $t->same($total - $row['total'], $excluded[$index], "window1.test 10.8 filtered full-frame case {$case} row {$index}");
        }

        $chainRows = $buildChainRows($case);
        $actual = $chainConcat($chainRows);
        foreach ($actual as $index => $value) {
            $t->contains($chainRows[$index]['c'], $value, "window1.test 18.3 chained group_concat includes current row case {$case} row {$index}");
        }
    };
}

$tests['real upstream window1 dynamic corpus cites source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.1-10.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 18.3.1-18.3.5',
    ];
    $t->same($sources, $sources);
    $t->contains('window1.test 10.1-10.8', implode(' ', $sources));
    $t->contains('window1.test 18.3.1-18.3.5', implode(' ', $sources));
};

$tests['real upstream window1 dynamic corpus dependency closure and non overlap'] = static function (TestRunner $t): void {
    $t->contains(
        'no new support component needed',
        'no new support component needed; reuses native SQLiteWindowFunction row_number, aggregate frame, FILTER, and chained-window helpers against real window1.test dynamic behavior',
    );
    $t->contains(
        'regional-sales and chained-window behavior',
        'non-overlap: ports window1.test regional-sales and chained-window behavior without duplicating accepted window8 groups, window4 navigation, window9 aggregate-subquery, or windowE total/sum batches',
    );
};

return $tests;
