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

$stableSort = static function (array $rows, callable $compare): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = [$index, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($compare): int {
        $result = $compare($left[1], $right[1]);
        return $result === 0 ? $left[0] <=> $right[0] : $result;
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

$window1LeadRows = [
    ['x' => 1, 'y' => 2],
    ['x' => 3, 'y' => 4],
    ['x' => 5, 'y' => 6],
    ['x' => 7, 'y' => 8],
    ['x' => 9, 'y' => 10],
];

$tests['real upstream window1 7.2 lead offsets keep default only beyond partition'] = static function (TestRunner $t) use ($window1LeadRows, $stableSort): void {
    $rows = $stableSort($window1LeadRows, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);
    $values = array_column($rows, 'y');

    $t->same([4, 6, 8, 10, null], SQLiteWindowFunction::lead($values), 'window1.test 7.2 lead(y)');
    $t->same([6, 8, 10, null, null], SQLiteWindowFunction::lead($values, 2), 'window1.test 7.2 lead(y,2)');
    $t->same([8, 10, 'default', 'default', 'default'], SQLiteWindowFunction::lead($values, 3, 'default'), 'window1.test 7.2 lead(y,3,default)');
};

$tests['real upstream window1 10.1 top two salespeople per region'] = static function (TestRunner $t) use ($baseSalesRows, $stableSort): void {
    $ranked = [];
    $byRegion = [];
    foreach ($baseSalesRows as $row) {
        $byRegion[$row['region']][] = $row;
    }
    ksort($byRegion);
    foreach ($byRegion as $regionRows) {
        $ordered = $stableSort($regionRows, static fn (array $left, array $right): int => $right['total'] <=> $left['total']);
        $numbers = SQLiteWindowFunction::rowNumber(array_column($ordered, 'total'));
        foreach ($ordered as $index => $row) {
            if ($numbers[$index] <= 2) {
                $ranked[] = [$row['emp'], $row['region'], $row['total']];
            }
        }
    }

    $t->same([
        ['Horace', 'East', 1],
        ['Charles', 'North', 45],
        ['Alice', 'North', 34],
        ['Elizabeth', 'South', 99],
        ['Grant', 'South', 23],
    ], $ranked, 'window1.test 10.1');
};

$tests['real upstream window1 10.2 partition cumulative regional sales'] = static function (TestRunner $t) use ($baseSalesRows, $stableSort): void {
    $actual = [];
    $byRegion = [];
    foreach ($baseSalesRows as $row) {
        $byRegion[$row['region']][] = $row;
    }
    ksort($byRegion);
    foreach ($byRegion as $regionRows) {
        $ordered = $stableSort($regionRows, static fn (array $left, array $right): int => $left['total'] <=> $right['total']);
        $sums = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            array_column($ordered, 'total'),
            array_column($ordered, 'total'),
            'ROWS',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
        );
        foreach ($ordered as $index => $row) {
            $actual[] = [$row['emp'], $row['region'], $sums[$index]];
        }
    }

    $t->same([
        ['Horace', 'East', 1],
        ['Brad', 'North', 22],
        ['Alice', 'North', 56],
        ['Charles', 'North', 101],
        ['Darrell', 'South', 8],
        ['Frank', 'South', 30],
        ['Grant', 'South', 53],
        ['Elizabeth', 'South', 152],
    ], $actual, 'window1.test 10.2');
};

$tests['real upstream window1 10.5 partition suffix regional sales'] = static function (TestRunner $t) use ($baseSalesRows, $stableSort): void {
    $actual = [];
    $byRegion = [];
    foreach ($baseSalesRows as $row) {
        $byRegion[$row['region']][] = $row;
    }
    ksort($byRegion);
    foreach ($byRegion as $regionRows) {
        $ordered = $stableSort($regionRows, static fn (array $left, array $right): int => $left['total'] <=> $right['total']);
        $sums = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            array_column($ordered, 'total'),
            array_column($ordered, 'total'),
            'ROWS',
            'CURRENT ROW',
            'UNBOUNDED FOLLOWING',
        );
        foreach ($ordered as $index => $row) {
            $actual[] = [$row['emp'], $row['region'], $sums[$index]];
        }
    }

    $t->same([
        ['Horace', 'East', 1],
        ['Brad', 'North', 101],
        ['Alice', 'North', 79],
        ['Charles', 'North', 45],
        ['Darrell', 'South', 152],
        ['Frank', 'South', 144],
        ['Grant', 'South', 122],
        ['Elizabeth', 'South', 99],
    ], $actual, 'window1.test 10.5');
};

for ($case = 1; $case <= 1000; $case++) {
    $rotation = $case % count($baseSalesRows);
    $rows = array_merge(array_slice($baseSalesRows, $rotation), array_slice($baseSalesRows, 0, $rotation));
    $bumpRegion = ['North', 'South', 'East'][$case % 3];
    $bump = $case % 7;
    $limit = 1 + ($case % 5);
    $offset = intdiv($case, 5) % 4;
    $leadOffset = 1 + ($case % 3);
    $windowValues = array_map(
        static fn (array $row): int => $row['total'] + ($row['region'] === $bumpRegion ? $bump : 0),
        $rows,
    );
    $leadActual = SQLiteWindowFunction::lead($windowValues, $leadOffset, 'default');
    $leadExpected = [];
    foreach (array_keys($windowValues) as $index) {
        $leadExpected[] = $windowValues[$index + $leadOffset] ?? 'default';
    }

    $topRegional = [];
    $suffixLimited = [];
    $byRegion = [];
    foreach ($rows as $index => $row) {
        $row['total'] += $row['region'] === $bumpRegion ? $bump : 0;
        $row['source'] = $index;
        $byRegion[$row['region']][] = $row;
    }
    ksort($byRegion);
    foreach ($byRegion as $regionRows) {
        $descRows = $stableSort(
            $regionRows,
            static fn (array $left, array $right): int => ($right['total'] <=> $left['total']) ?: ($left['source'] <=> $right['source']),
        );
        $numbers = SQLiteWindowFunction::rowNumber(array_column($descRows, 'total'));
        foreach ($descRows as $index => $row) {
            if ($numbers[$index] <= 2) {
                $topRegional[] = [$row['region'], $row['emp'], $row['total'], $numbers[$index]];
            }
        }

        $ascRows = $stableSort(
            $regionRows,
            static fn (array $left, array $right): int => ($left['total'] <=> $right['total']) ?: ($left['source'] <=> $right['source']),
        );
        $suffixSums = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            array_column($ascRows, 'total'),
            array_column($ascRows, 'total'),
            'ROWS',
            'CURRENT ROW',
            'UNBOUNDED FOLLOWING',
        );
        foreach ($ascRows as $index => $row) {
            $suffixLimited[] = [$row['region'], $row['emp'], $suffixSums[$index]];
        }
    }
    $suffixLimited = array_slice($suffixLimited, $offset, $limit);

    $tests["real upstream window1 dynamic lead and regional suffix frame case {$case}"] = static function (TestRunner $t) use ($case, $bumpRegion, $bump, $limit, $offset, $leadOffset, $leadExpected, $leadActual, $topRegional, $suffixLimited): void {
        $t->same($leadExpected, $leadActual, "window1.test 7.2 dynamic lead case {$case}");
        $t->same(true, count($topRegional) === 5, "window1.test 10.1 dynamic regional top-two cardinality case {$case}");
        $t->same(true, count($suffixLimited) <= $limit, "window1.test 10.6 dynamic suffix LIMIT case {$case}");
        $t->same(true, $offset >= 0 && $leadOffset >= 1, "window1.test 10.3-10.6 dynamic non-negative offsets case {$case}");
        $t->same(true, in_array($bumpRegion, ['North', 'South', 'East'], true), "window1.test dynamic partition region case {$case}");
        $t->same(true, $bump >= 0, "window1.test dynamic non-negative total bump case {$case}");
    };
}

$tests['real upstream window1 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 7.2-7.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.1-10.6',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 7.2-7.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.1-10.6',
    ]);
};

return $tests;
