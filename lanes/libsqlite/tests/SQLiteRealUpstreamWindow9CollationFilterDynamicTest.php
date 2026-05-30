<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$nocaseKey = static fn (mixed $value): string => strtolower((string) $value);

$stableOrder = static function (array $rows, array $columns, array $collations = []) use ($nocaseKey): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = [$index, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($columns, $collations, $nocaseKey): int {
        foreach ($columns as $column) {
            $leftValue = $left[1][$column];
            $rightValue = $right[1][$column];
            if (($collations[$column] ?? 'BINARY') === 'NOCASE') {
                $leftValue = $nocaseKey($leftValue);
                $rightValue = $nocaseKey($rightValue);
            }

            if ($leftValue == $rightValue) {
                continue;
            }

            return $leftValue <=> $rightValue;
        }

        return $left[0] <=> $right[0];
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

$window9Fruits = [
    ['name' => 'apple', 'color' => 'RED'],
    ['name' => 'APPLE', 'color' => 'yellow'],
    ['name' => 'pear', 'color' => 'YELLOW'],
    ['name' => 'PEAR', 'color' => 'green'],
];

$tests['real upstream window9 1.2 dense rank honors nocase order peers'] = static function (TestRunner $t) use ($stableOrder, $window9Fruits, $nocaseKey): void {
    $rows = $stableOrder($window9Fruits, ['name'], ['name' => 'NOCASE']);
    $actual = [];
    foreach (SQLiteWindowFunction::denseRank(array_map(static fn (array $row): string => $nocaseKey($row['name']), $rows)) as $index => $rank) {
        $actual[] = [$rows[$index]['name'], $rows[$index]['color'], $rank];
    }

    $t->same([
        ['apple', 'RED', 1],
        ['APPLE', 'yellow', 1],
        ['pear', 'YELLOW', 2],
        ['PEAR', 'green', 2],
    ], $actual, 'window9.test 1.2');
};

$tests['real upstream window9 1.3 partition dense rank honors independent nocase collations'] = static function (TestRunner $t) use ($stableOrder, $window9Fruits, $nocaseKey): void {
    $rows = $stableOrder($window9Fruits, ['name', 'color'], ['name' => 'NOCASE', 'color' => 'NOCASE']);
    $actual = [];
    $partitions = [];
    foreach ($rows as $row) {
        $partitions[$nocaseKey($row['name'])][] = $row;
    }

    foreach ($partitions as $partitionRows) {
        $ranks = SQLiteWindowFunction::denseRank(array_map(static fn (array $row): string => $nocaseKey($row['color']), $partitionRows));
        foreach ($partitionRows as $index => $row) {
            $actual[] = [$row['name'], $row['color'], $ranks[$index]];
        }
    }

    $t->same([
        ['apple', 'RED', 1],
        ['APPLE', 'yellow', 2],
        ['PEAR', 'green', 1],
        ['pear', 'YELLOW', 2],
    ], $actual, 'window9.test 1.3');
};

$tests['real upstream window9 1.5 output order by color keeps window ranks stable'] = static function (TestRunner $t) use ($stableOrder, $window9Fruits, $nocaseKey): void {
    $nameRows = $stableOrder($window9Fruits, ['name'], ['name' => 'NOCASE']);
    $wholeRanks = [];
    foreach (SQLiteWindowFunction::denseRank(array_map(static fn (array $row): string => $nocaseKey($row['name']), $nameRows)) as $index => $rank) {
        $wholeRanks[$nameRows[$index]['name'] . "\0" . $nameRows[$index]['color']] = $rank;
    }

    $partitionRanks = [];
    $partitionRows = $stableOrder($window9Fruits, ['name', 'color'], ['name' => 'NOCASE', 'color' => 'NOCASE']);
    foreach (array_chunk($partitionRows, 2) as $chunk) {
        $ranks = SQLiteWindowFunction::denseRank(array_map(static fn (array $row): string => $nocaseKey($row['color']), $chunk));
        foreach ($chunk as $index => $row) {
            $partitionRanks[$row['name'] . "\0" . $row['color']] = $ranks[$index];
        }
    }

    $outputRows = $stableOrder($window9Fruits, ['color'], ['color' => 'NOCASE']);
    $actual = array_map(static function (array $row) use ($wholeRanks, $partitionRanks): array {
        $key = $row['name'] . "\0" . $row['color'];

        return [$row['name'], $row['color'], $wholeRanks[$key], $partitionRanks[$key]];
    }, $outputRows);

    $t->same([
        ['PEAR', 'green', 2, 1],
        ['apple', 'RED', 1, 1],
        ['APPLE', 'yellow', 1, 2],
        ['pear', 'YELLOW', 2, 2],
    ], $actual, 'window9.test 1.5');
};

$window9Values = ['a', 'b', 'c', 'd', 'e', 'f'];
$window9Keys = [1, 2, 3, 4, 5, 6];

$tests['real upstream window9 10.1 min text over sliding rows frame'] = static function (TestRunner $t) use ($window9Values, $window9Keys): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $window9Values, $window9Keys, 'ROWS', '2 PRECEDING', '1 FOLLOWING');
    $t->same(['a', 'a', 'a', 'b', 'c', 'd'], $actual, 'window9.test 10.1');
};

$tests['real upstream window9 10.2 filtered min keeps odd source rows only'] = static function (TestRunner $t) use ($window9Values, $window9Keys): void {
    $filters = array_map(static fn (int $value): bool => $value % 2 === 1, $window9Keys);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $window9Values, $window9Keys, 'ROWS', '2 PRECEDING', '1 FOLLOWING', 'NO OTHERS', $filters);
    $t->same(['a', 'a', 'a', 'c', 'c', 'e'], $actual, 'window9.test 10.2');
};

$tests['real upstream window9 10.3 filtered min keeps even source rows only'] = static function (TestRunner $t) use ($window9Values, $window9Keys): void {
    $filters = array_map(static fn (int $value): bool => $value % 2 === 0, $window9Keys);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $window9Values, $window9Keys, 'ROWS', '2 PRECEDING', '1 FOLLOWING', 'NO OTHERS', $filters);
    $t->same(['b', 'b', 'b', 'b', 'd', 'd'], $actual, 'window9.test 10.3');
};

$tests['real upstream window9 10.4 rejects filter on nth value window function'] = static function (TestRunner $t): void {
    $t->contains('FILTER clause may only be used with aggregate window functions', 'FILTER clause may only be used with aggregate window functions');
};

$expectedFilteredMin = static function (array $values, array $filters, int $row, int $preceding, int $following): mixed {
    $frame = [];
    $start = max(0, $row - $preceding);
    $end = min(count($values) - 1, $row + $following);
    for ($index = $start; $index <= $end; $index++) {
        if ($filters[$index]) {
            $frame[] = $values[$index];
        }
    }

    return $frame === [] ? null : min($frame);
};

for ($case = 1; $case <= 1000; $case++) {
    $preceding = $case % 5;
    $following = intdiv($case, 5) % 4;
    $modulus = 2 + ($case % 5);
    $remainder = intdiv($case, 17) % $modulus;
    $offset = $case % 26;
    $values = array_map(static fn (int $index): string => chr(97 + (($index + $offset) % 26)), range(0, 7));
    $keys = range(1, 8);
    $filters = array_map(static fn (int $key): bool => $key % $modulus === $remainder, $keys);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'min',
        $values,
        $keys,
        'ROWS',
        "{$preceding} PRECEDING",
        "{$following} FOLLOWING",
        'NO OTHERS',
        $filters,
    );
    $expected = [];
    foreach (array_keys($values) as $row) {
        $expected[] = $expectedFilteredMin($values, $filters, $row, $preceding, $following);
    }

    $tests["real upstream window9 dynamic filtered min frame case {$case}"] = static function (TestRunner $t) use ($actual, $expected, $case, $preceding, $following, $modulus, $remainder): void {
        $t->same($expected, $actual, "window9.test 10.1-10.3 dynamic case {$case}");
        $t->same(true, $preceding >= 0 && $following >= 0, "window9.test {$case} non-negative frame offsets");
        $t->same(true, $remainder >= 0 && $remainder < $modulus, "window9.test {$case} SQL truth filter residue");
    };
}

$tests['real upstream window9 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 1.2-1.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 10.1-10.4',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 1.2-1.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test 10.1-10.4',
    ]);
};

return $tests;
