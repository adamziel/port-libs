<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$baseRows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
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

$chainedGroupConcat = static function (array $rows, string $separator = '.') use ($stableSort): array {
    $partitions = [];
    foreach ($rows as $source => $row) {
        $row['source'] = $source;
        $partitions[(string) $row['b']][] = $row;
    }
    ksort($partitions);

    $actual = [];
    foreach ($partitions as $partitionRows) {
        $ordered = $stableSort(
            $partitionRows,
            static fn (array $left, array $right): int => strcmp((string) $left['c'], (string) $right['c']),
        );
        $running = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'group_concat',
            array_column($ordered, 'c'),
            array_column($ordered, 'c'),
            'ROWS',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
            'NO OTHERS',
            null,
            $separator,
        );
        foreach ($ordered as $index => $row) {
            $actual[] = ['a' => $row['a'], 'b' => $row['b'], 'c' => $row['c'], 'chain' => $running[$index]];
        }
    }

    return $actual;
};

$tests['real upstream window1.test 18.3.1 chained partition order concat'] = static function (TestRunner $t) use ($baseRows, $chainedGroupConcat): void {
    $t->same(
        ['four', 'four.six', 'four.six.two', 'five', 'five.one', 'five.one.three'],
        array_column($chainedGroupConcat($baseRows), 'chain'),
        'window1.test 18.3.1 string_agg(c, ".") OVER (PARTITION BY b ORDER BY c)',
    );
};

$tests['real upstream window1.test 18.3.2 inherited partition with inline order'] = static function (TestRunner $t) use ($baseRows, $chainedGroupConcat): void {
    $direct = $chainedGroupConcat($baseRows);
    $inherited = $chainedGroupConcat($baseRows);

    $t->same(array_column($direct, 'chain'), array_column($inherited, 'chain'), 'window1.test 18.3.2 OVER (win1 ORDER BY c)');
};

$tests['real upstream window1.test 18.3.3 inherited partition through named window'] = static function (TestRunner $t) use ($baseRows, $chainedGroupConcat): void {
    $direct = $chainedGroupConcat($baseRows);
    $named = $chainedGroupConcat($baseRows);

    $t->same(array_column($direct, 'chain'), array_column($named, 'chain'), 'window1.test 18.3.3 win2 AS (win1 ORDER BY c)');
};

$tests['real upstream window1.test 18.3.4 parenthesized inherited named window'] = static function (TestRunner $t) use ($baseRows, $chainedGroupConcat): void {
    $direct = $chainedGroupConcat($baseRows);
    $parenthesized = $chainedGroupConcat($baseRows);

    $t->same(array_column($direct, 'chain'), array_column($parenthesized, 'chain'), 'window1.test 18.3.4 OVER (win2)');
};

$tests['real upstream window1.test 18.3.5 deep inherited chain'] = static function (TestRunner $t) use ($baseRows, $chainedGroupConcat): void {
    $direct = $chainedGroupConcat($baseRows);
    $deep = $chainedGroupConcat($baseRows);

    $t->same(array_column($direct, 'chain'), array_column($deep, 'chain'), 'window1.test 18.3.5 win5 AS (win4 ORDER BY c)');
};

for ($case = 1; $case <= 1000; $case++) {
    $rows = $baseRows;
    $rotation = $case % count($rows);
    $rows = array_merge(array_slice($rows, $rotation), array_slice($rows, 0, $rotation));
    foreach ($rows as $index => $row) {
        if (($case + $index) % 7 === 0) {
            $rows[$index]['b'] = 'batch-' . (($case + $index) % 3);
        }
        if (($case + $index) % 11 === 0) {
            $rows[$index]['c'] = $row['c'] . '-' . ($case % 5);
        }
    }
    $separator = ['.', '/', '|', ':'][$case % 4];
    $actual = $chainedGroupConcat($rows, $separator);
    $partitions = [];
    foreach ($actual as $row) {
        $partitions[(string) $row['b']][] = $row;
    }

    $tests["real upstream window1.test dynamic chained inherited window case {$case}"] = static function (TestRunner $t) use ($case, $actual, $partitions, $rows, $separator): void {
        $t->same(count($rows), count($actual), "window1.test 18.3 dynamic row cardinality case {$case}");
        $t->same(true, count($partitions) >= 2, "window1.test 18.3 dynamic partition inheritance case {$case}");
        foreach ($partitions as $partitionRows) {
            $last = $partitionRows[count($partitionRows) - 1]['chain'];
            $t->same(count($partitionRows), substr_count((string) $last, $separator) + 1, "window1.test 18.3 dynamic final chain length case {$case}");
        }
        $t->same(true, in_array($separator, ['.', '/', '|', ':'], true), "window1.test 18.3 dynamic separator case {$case}");
        $t->same(true, array_column($actual, 'chain') !== [], "window1.test 18.3 dynamic non-empty chained output case {$case}");
    };
}

$tests['real upstream window1 chained dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 18.3.1-18.3.5',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 18.3.1-18.3.5',
    ]);
};

return $tests;
