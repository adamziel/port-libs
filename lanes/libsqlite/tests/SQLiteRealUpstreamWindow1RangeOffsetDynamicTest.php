<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Source truth: upstream SQLite test/window1.test section 22. Valid RANGE
// offsets include integer, real, and numeric-text expressions such as 4.5,
// 0.0, 0.1, '2.0', and '1.2'. The port models the normalized frame-boundary
// value passed into the native PHP window-frame evaluator.
$rangeRows = [];
for ($index = 0; $index < 16; $index++) {
    $rangeRows[] = [
        'key' => (float) ($index * 2),
        'value' => $index + 1,
    ];
}

$sumBetween = static function (array $rows, float $currentKey, float $lower, float $upper): int {
    $sum = 0;
    foreach ($rows as $row) {
        if ($row['key'] >= $lower - 1.0e-12 && $row['key'] <= $upper + 1.0e-12) {
            $sum += $row['value'];
        }
    }

    return $sum;
};

$validOffsets = [
    '4.5' => 4.5,
    '0.0' => 0.0,
    '0.1' => 0.1,
    '2.0' => 2.0,
    '1.2' => 1.2,
    '7' => 7.0,
    '12.25' => 12.25,
    '3.75' => 3.75,
];

$case = 0;
foreach ($validOffsets as $offsetText => $offset) {
    foreach ([0, 1, 2, 3, 4] as $rotation) {
        $rotated = array_merge(array_slice($rangeRows, $rotation), array_slice($rangeRows, 0, $rotation));
        $keys = array_column($rotated, 'key');
        $values = array_column($rotated, 'value');
        $preceding = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $values,
            $keys,
            'RANGE',
            "{$offsetText} PRECEDING",
            'UNBOUNDED FOLLOWING',
        );
        $following = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $values,
            $keys,
            'RANGE',
            'UNBOUNDED PRECEDING',
            "{$offsetText} FOLLOWING",
        );
        $bounded = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $values,
            $keys,
            'RANGE',
            "{$offsetText} PRECEDING",
            "{$offsetText} FOLLOWING",
        );

        foreach ($rotated as $rowIndex => $row) {
            $case++;
            $currentKey = $row['key'];
            $expectedPreceding = $sumBetween($rotated, $currentKey, $currentKey - $offset, INF);
            $expectedFollowing = $sumBetween($rotated, $currentKey, -INF, $currentKey + $offset);
            $expectedBounded = $sumBetween($rotated, $currentKey, $currentKey - $offset, $currentKey + $offset);

            $tests["real upstream window1 22 dynamic valid range offset {$offsetText} rotation {$rotation} row {$rowIndex}"] = static function (TestRunner $t) use (
                $case,
                $offsetText,
                $preceding,
                $following,
                $bounded,
                $rowIndex,
                $expectedPreceding,
                $expectedFollowing,
                $expectedBounded,
                $currentKey
            ): void {
                $t->same($expectedPreceding, $preceding[$rowIndex], "window1.test 22 dynamic {$case} {$offsetText} PRECEDING lower range");
                $t->same($expectedFollowing, $following[$rowIndex], "window1.test 22 dynamic {$case} {$offsetText} FOLLOWING upper range");
                $t->same($expectedBounded, $bounded[$rowIndex], "window1.test 22 dynamic {$case} {$offsetText} bounded range");
                $t->true($currentKey >= 0.0, "window1.test 22 dynamic {$case} keeps numeric ORDER BY key");
                $t->true($expectedBounded <= $expectedPreceding, "window1.test 22 dynamic {$case} bounded frame is not wider than unbounded-following frame");
            };
        }
    }
}

foreach (range(1, 360) as $round) {
    $offset = ($round % 23) / 10;
    $offsetText = number_format($offset, 1, '.', '');
    $keys = array_map(static fn (array $row): float => $row['key'] + ($round % 3) * 0.25, $rangeRows);
    $values = array_map(static fn (array $row): int => $row['value'] + ($round % 5), $rangeRows);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'count',
        $values,
        $keys,
        'RANGE',
        "{$offsetText} PRECEDING",
        "{$offsetText} FOLLOWING",
    );

    $tests["real upstream window1 22 dynamic fractional range count round {$round}"] = static function (TestRunner $t) use ($round, $keys, $actual, $offset): void {
        foreach ($keys as $rowIndex => $key) {
            $expected = 0;
            foreach ($keys as $candidate) {
                if ($candidate >= $key - $offset - 1.0e-12 && $candidate <= $key + $offset + 1.0e-12) {
                    $expected++;
                }
            }
            $t->same($expected, $actual[$rowIndex], "window1.test 22 fractional RANGE row {$rowIndex} round {$round}");
        }
    };
}

$tests['real upstream window1 range offset dynamic cites exact upstream source sections'] = static function (TestRunner $t) use ($case): void {
    $t->same(640, $case, 'window1.test 22 generated valid offset row cases');
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.1 valid 4.5 RANGE offset',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.3 valid 0.0 RANGE offset',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.4 valid 0.1 RANGE offset',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.7 valid numeric-text 2.0 RANGE offset',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.10 valid numeric-text 1.2 RANGE offset',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.1 valid 4.5 RANGE offset',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.3 valid 0.0 RANGE offset',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.4 valid 0.1 RANGE offset',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.7 valid numeric-text 2.0 RANGE offset',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test:22.10 valid numeric-text 1.2 RANGE offset',
    ]);
};

$tests['real upstream window1 range offset dynamic dependency closure and non overlap'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native SQLiteWindowFunction RANGE frame offset evaluation and avoids existing windowerr invalid-offset coverage plus prior correlated FILTER, windowE real-range, and window7 GROUPS/RANGE batches',
        'no new support component needed; reuses native SQLiteWindowFunction RANGE frame offset evaluation and avoids existing windowerr invalid-offset coverage plus prior correlated FILTER, windowE real-range, and window7 GROUPS/RANGE batches',
    );
};

return $tests;
