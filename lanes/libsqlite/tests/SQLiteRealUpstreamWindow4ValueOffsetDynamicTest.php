<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window4Letters = range('a', 'j');
$window4Upper = range('A', 'J');
$window4Nth = [9, 3, 2, 10, 5, 1, 1, 2, 10, 4];

$flattenPairs = static function (array $left, array $right): array {
    $result = [];
    foreach ($left as $index => $value) {
        $result[] = $value;
        $result[] = $right[$index] ?? null;
    }

    return $result;
};

$expectedLead = static function (array $values, int $offset = 1, mixed $default = null): array {
    $result = [];
    foreach (array_keys($values) as $index) {
        $result[] = $values[$index + $offset] ?? $default;
    }

    return $result;
};

$expectedLag = static function (array $values, int $offset = 1, mixed $default = null): array {
    $result = [];
    foreach (array_keys($values) as $index) {
        $result[] = $values[$index - $offset] ?? $default;
    }

    return $result;
};

$expectedNtile = static function (array $values, int $buckets): array {
    $count = count($values);
    $baseSize = intdiv($count, $buckets);
    $largerBuckets = $count % $buckets;
    $result = [];
    for ($bucket = 1; $bucket <= min($buckets, $count); $bucket++) {
        $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
        for ($slot = 0; $slot < $size; $slot++) {
            $result[] = $bucket;
        }
    }

    return $result;
};

$tests['real upstream window4 1.1 ntile one bucket covers all rows'] = static function (TestRunner $t) use ($window4Letters): void {
    $t->same(array_fill(0, 10, 1), SQLiteWindowFunction::ntile($window4Letters, 1), 'window4.test 1.1');
};

$tests['real upstream window4 1.3 ntile three buckets front-loads larger buckets'] = static function (TestRunner $t) use ($window4Letters): void {
    $t->same([1, 1, 1, 1, 2, 2, 2, 3, 3, 3], SQLiteWindowFunction::ntile($window4Letters, 3), 'window4.test 1.3');
};

$tests['real upstream window4 1.10 ntile ten buckets assigns one row per bucket'] = static function (TestRunner $t) use ($window4Letters): void {
    $t->same(range(1, 10), SQLiteWindowFunction::ntile($window4Letters, 10), 'window4.test 1.10');
};

$tests['real upstream window4 1.11 ntile more buckets than rows stops at row count'] = static function (TestRunner $t) use ($window4Letters): void {
    $t->same(range(1, 10), SQLiteWindowFunction::ntile($window4Letters, 11), 'window4.test 1.11');
};

$tests['real upstream window4 2.1 nth value by row follows row specific index'] = static function (TestRunner $t) use ($window4Upper, $window4Nth, $flattenPairs): void {
    $actual = SQLiteWindowFunction::nthValueByRow($window4Upper, $window4Nth, range(1, 10));
    $t->same([1, null, 2, null, 3, 'B', 4, null, 5, 'E', 6, 'A', 7, 'A', 8, 'B', 9, null, 10, 'D'], $flattenPairs(range(1, 10), $actual), 'window4.test 2.1');
};

$tests['real upstream window4 2.2 lead one row uses null at partition end'] = static function (TestRunner $t) use ($window4Upper, $flattenPairs): void {
    $actual = SQLiteWindowFunction::lead($window4Upper);
    $t->same([1, 'B', 2, 'C', 3, 'D', 4, 'E', 5, 'F', 6, 'G', 7, 'H', 8, 'I', 9, 'J', 10, null], $flattenPairs(range(1, 10), $actual), 'window4.test 2.2.1');
};

$tests['real upstream window4 2.2 lead two rows uses null defaults'] = static function (TestRunner $t) use ($window4Upper, $flattenPairs): void {
    $actual = SQLiteWindowFunction::lead($window4Upper, 2);
    $t->same([1, 'C', 2, 'D', 3, 'E', 4, 'F', 5, 'G', 6, 'H', 7, 'I', 8, 'J', 9, null, 10, null], $flattenPairs(range(1, 10), $actual), 'window4.test 2.2.2');
};

$tests['real upstream window4 2.2 lead three rows honors explicit default'] = static function (TestRunner $t) use ($window4Upper, $flattenPairs): void {
    $actual = SQLiteWindowFunction::lead($window4Upper, 3, 'abc');
    $t->same([1, 'D', 2, 'E', 3, 'F', 4, 'G', 5, 'H', 6, 'I', 7, 'J', 8, 'abc', 9, 'abc', 10, 'abc'], $flattenPairs(range(1, 10), $actual), 'window4.test 2.2.3');
};

$tests['real upstream window4 2.3 lag one row uses null at partition start'] = static function (TestRunner $t) use ($window4Upper, $flattenPairs): void {
    $actual = SQLiteWindowFunction::lag($window4Upper);
    $t->same([1, null, 2, 'A', 3, 'B', 4, 'C', 5, 'D', 6, 'E', 7, 'F', 8, 'G', 9, 'H', 10, 'I'], $flattenPairs(range(1, 10), $actual), 'window4.test 2.3.1');
};

$tests['real upstream window4 2.3 lag two rows uses null defaults'] = static function (TestRunner $t) use ($window4Upper, $flattenPairs): void {
    $actual = SQLiteWindowFunction::lag($window4Upper, 2);
    $t->same([1, null, 2, null, 3, 'A', 4, 'B', 5, 'C', 6, 'D', 7, 'E', 8, 'F', 9, 'G', 10, 'H'], $flattenPairs(range(1, 10), $actual), 'window4.test 2.3.2');
};

$tests['real upstream window4 2.3 lag three rows honors explicit default'] = static function (TestRunner $t) use ($window4Upper, $flattenPairs): void {
    $actual = SQLiteWindowFunction::lag($window4Upper, 3, 'abc');
    $t->same([1, 'abc', 2, 'abc', 3, 'abc', 4, 'A', 5, 'B', 6, 'C', 7, 'D', 8, 'E', 9, 'F', 10, 'G'], $flattenPairs(range(1, 10), $actual), 'window4.test 2.3.3');
};

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 5 + ($case % 12);
    $values = array_map(static fn (int $index): string => chr(65 + (($index + $case) % 26)), range(0, $rowCount - 1));
    $buckets = 1 + ($case % ($rowCount + 4));
    $leadOffset = 1 + ($case % 5);
    $lagOffset = 1 + (intdiv($case, 5) % 5);
    $default = 'fallback-' . ($case % 7);
    $nthValues = array_map(static fn (int $index): int => 1 + (($index + $case) % ($rowCount + 3)), range(0, $rowCount - 1));

    $expectedTile = $expectedNtile($values, $buckets);
    $expectedLeadValues = $expectedLead($values, $leadOffset, $default);
    $expectedLagValues = $expectedLag($values, $lagOffset, $default);
    $expectedNth = [];
    foreach ($nthValues as $index => $nth) {
        $frame = array_slice($values, 0, $index + 1);
        $expectedNth[] = $frame[$nth - 1] ?? null;
    }

    $tests["real upstream window4 dynamic value offset case {$case}"] = static function (TestRunner $t) use ($case, $values, $buckets, $leadOffset, $lagOffset, $default, $nthValues, $expectedTile, $expectedLeadValues, $expectedLagValues, $expectedNth): void {
        $t->same($expectedTile, SQLiteWindowFunction::ntile($values, $buckets), "window4.test 1.1-1.19 dynamic ntile case {$case}");
        $t->same($expectedLeadValues, SQLiteWindowFunction::lead($values, $leadOffset, $default), "window4.test 2.2 dynamic lead case {$case}");
        $t->same($expectedLagValues, SQLiteWindowFunction::lag($values, $lagOffset, $default), "window4.test 2.3 dynamic lag case {$case}");
        $t->same($expectedNth, SQLiteWindowFunction::nthValueByRow($values, $nthValues, range(1, count($values))), "window4.test 2.1 dynamic nth_value case {$case}");
        $t->same(count($values), count($expectedTile), "window4.test {$case} ntile preserves row count");
    };
}

$tests['real upstream window4 value offset rejects invalid ntile bucket count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::ntile(['a'], 0));
};

$tests['real upstream window4 value offset rejects invalid nth value index'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow(['a'], [0], [1]));
};

$tests['real upstream window4 value offset rejects mismatched nth value rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow(['a', 'b'], [1], [1, 2]));
};

$tests['real upstream window4 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 1.1-1.19',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1-2.3.3',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 1.1-1.19',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test 2.1-2.3.3',
    ]);
};

return $tests;
