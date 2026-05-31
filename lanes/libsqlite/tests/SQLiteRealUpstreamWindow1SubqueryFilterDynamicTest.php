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

$windowFullSum = static function (array $rows, ?string $excludedEmp = null) use ($stableSort): mixed {
    $ordered = $stableSort(
        $rows,
        static fn (array $left, array $right): int => ($left['total'] <=> $right['total']) ?: strcmp($left['emp'], $right['emp']),
    );
    $totals = array_column($ordered, 'total');
    $filters = $excludedEmp === null
        ? null
        : array_map(static fn (array $row): bool => $row['emp'] !== $excludedEmp, $ordered);

    return SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        $totals,
        $totals,
        'RANGE',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
        'NO OTHERS',
        $filters,
    )[0] ?? null;
};

$tests['real upstream window1 10.7 correlated full-partition sum is repeated per outer row'] = static function (TestRunner $t) use ($baseSalesRows, $windowFullSum): void {
    $actual = [];
    foreach ($baseSalesRows as $outer) {
        $actual[] = [$outer['emp'], $outer['region'], (string) $windowFullSum($baseSalesRows) . $outer['emp']];
    }

    $t->same([
        ['Alice', 'North', '254Alice'],
        ['Frank', 'South', '254Frank'],
        ['Charles', 'North', '254Charles'],
        ['Darrell', 'South', '254Darrell'],
        ['Grant', 'South', '254Grant'],
        ['Brad', 'North', '254Brad'],
        ['Elizabeth', 'South', '254Elizabeth'],
        ['Horace', 'East', '254Horace'],
    ], $actual, 'window1.test 10.7');
};

$tests['real upstream window1 10.8 correlated FILTER removes the current outer row'] = static function (TestRunner $t) use ($baseSalesRows, $windowFullSum): void {
    $actual = [];
    foreach ($baseSalesRows as $outer) {
        $actual[] = [$outer['emp'], $outer['region'], $windowFullSum($baseSalesRows, $outer['emp'])];
    }

    $t->same([
        ['Alice', 'North', 220],
        ['Frank', 'South', 232],
        ['Charles', 'North', 209],
        ['Darrell', 'South', 246],
        ['Grant', 'South', 231],
        ['Brad', 'North', 232],
        ['Elizabeth', 'South', 155],
        ['Horace', 'East', 253],
    ], $actual, 'window1.test 10.8');
};

for ($case = 1; $case <= 1000; $case++) {
    $rotation = $case % count($baseSalesRows);
    $rows = array_merge(array_slice($baseSalesRows, $rotation), array_slice($baseSalesRows, 0, $rotation));
    $boostRegion = ['North', 'South', 'East'][$case % 3];
    $boost = $case % 11;
    $renamed = [];
    foreach ($rows as $index => $row) {
        $row['emp'] .= "-{$case}-{$index}";
        if ($row['region'] === $boostRegion) {
            $row['total'] += $boost;
        }
        $renamed[] = $row;
    }

    $outerIndex = $case % count($renamed);
    $outer = $renamed[$outerIndex];
    $total = array_sum(array_column($renamed, 'total'));
    $filteredTotal = $total - $outer['total'];
    $fullWindow = $windowFullSum($renamed);
    $filteredWindow = $windowFullSum($renamed, $outer['emp']);
    $stringified = (string) $fullWindow . $outer['emp'];
    $expectedStringified = (string) $total . $outer['emp'];

    $tests["real upstream window1 10.7 10.8 dynamic correlated filtered sum case {$case}"] = static function (TestRunner $t) use ($case, $renamed, $outer, $total, $filteredTotal, $fullWindow, $filteredWindow, $stringified, $expectedStringified): void {
        $t->same($total, $fullWindow, "window1.test 10.7 dynamic full window sum case {$case}");
        $t->same($expectedStringified, $stringified, "window1.test 10.7 dynamic correlated concatenation case {$case}");
        $t->same($filteredTotal, $filteredWindow, "window1.test 10.8 dynamic filtered window sum case {$case}");
        $t->same($outer['total'], $total - $filteredWindow, "window1.test 10.8 dynamic excluded outer row case {$case}");
        $t->same(count($renamed) - 1, count(array_filter($renamed, static fn (array $row): bool => $row['emp'] !== $outer['emp'])), "window1.test 10.8 dynamic filter cardinality case {$case}");
    };
}

$tests['real upstream window1 subquery filter dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.7',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.8',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.7',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.8',
    ]);
};

return $tests;
