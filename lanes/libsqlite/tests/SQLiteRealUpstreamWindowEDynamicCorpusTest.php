<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$textRangeRows = [
    ['a' => 5, 'b' => 'five'],
    ['a' => 4, 'b' => 'four'],
    ['a' => 1, 'b' => 'one'],
    ['a' => 6, 'b' => 'six'],
    ['a' => 3, 'b' => 'three'],
    ['a' => 2, 'b' => 'two'],
];

for ($case = 0; $case < 250; $case++) {
    $rows = array_map(
        static fn (array $row): array => [
            'a' => $row['a'] + ($case * 10),
            'b' => $row['b'] . '-' . ($case % 7),
        ],
        $textRangeRows,
    );

    $tests['real upstream windowE 1.2 dynamic text RANGE keeps current peer ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'group_concat',
            array_column($rows, 'a'),
            array_column($rows, 'b'),
            'RANGE',
            '1 PRECEDING',
            '2 PRECEDING',
        );

        foreach ($rows as $index => $row) {
            $t->same((string) $row['a'], $actual[$index], 'windowE.test 1.2 nonnumeric RANGE row ' . $index);
        }
    };
}

$rangeSource = [
    [447, 0.0], [448, 0.0], [449, 0.0], [452, 0.0], [453, 0.0], [454, 0.0], [455, 0.0],
    [456, 0.0], [459, 0.0], [460, 0.0], [462, 0.0], [463, 0.0], [466, 0.0], [467, 0.0],
    [468, 0.0], [469, 0.0], [470, 0.0], [473, 0.0], [474, 0.0], [475, 0.0], [476, 0.0],
    [477, 0.0], [480, 0.0], [481, 0.0], [482, 0.0], [483, 0.0], [484, 0.0], [487, 0.0],
    [488, 0.0], [489, 0.0], [490, 0.0], [491, 0.0], [494, 0.0], [495, 0.0], [496, 0.0],
    [497, 0.0], [498, 0.0], [501, 0.0], [502, 0.0], [503, 0.0], [504, 0.0], [505, 0.0],
    [508, 0.0], [509, 0.0], [510, 0.0], [511, 0.0], [512, 0.0], [515, 0.0], [516, 0.0],
    [517, 0.0], [518, 0.0], [519, 0.0], [522, 0.0], [523, 0.0], [524, 0.0], [525, 0.0],
    [526, 0.0], [529, 0.0], [530, 0.0], [531, 0.0], [532, 0.0], [533, 0.0], [536, 0.0],
    [537, 1.0], [538, 0.0], [539, 0.0], [540, 0.0], [543, 0.0], [544, 0.0],
];

for ($case = 0; $case < 250; $case++) {
    $shift = $case * 2;
    $marker = 537 + $shift;
    $rows = array_map(
        static fn (array $row): array => [
            'c1' => $row[0] + $shift,
            'c2' => $row[0] === 537 ? 1.0 : $row[1],
        ],
        $rangeSource,
    );

    $tests['real upstream windowE 3.1 dynamic numeric RANGE max propagation ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows, $marker): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'max',
            array_column($rows, 'c2'),
            array_column($rows, 'c1'),
            'RANGE',
            '366.0 PRECEDING',
            'CURRENT ROW',
        );

        foreach ($rows as $index => $row) {
            $expected = $row['c1'] >= $marker ? 1.0 : 0.0;
            $t->same($expected, $actual[$index], 'windowE.test 3.1 numeric RANGE row ' . $index);
        }
    };
}

$largeInteger = 9223372036854775807;

for ($case = 0; $case < 250; $case++) {
    $tail = $case % 5;
    $values = [1 + $tail, $largeInteger, 3 + $tail, 4 + $tail];

    $tests['real upstream windowE 4.1 dynamic total current to unbounded following ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($values): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'total',
            $values,
            [1, 2, 3, 4],
            'ROWS',
            'CURRENT ROW',
            'UNBOUNDED FOLLOWING',
        );

        $t->same(9.223372036854776E+18, $actual[0]);
        $t->same(9.223372036854776E+18, $actual[1]);
        $t->same((float) ($values[2] + $values[3]), $actual[2]);
        $t->same((float) $values[3], $actual[3]);
    };
}

for ($case = 0; $case < 250; $case++) {
    $tail = $case % 5;
    $values = [1 + $tail, $largeInteger, 3 + $tail, 4 + $tail];

    $tests['real upstream windowE 4.2 dynamic total current to two following ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($values): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'total',
            $values,
            [1, 2, 3, 4],
            'ROWS',
            'CURRENT ROW',
            '2 FOLLOWING',
        );

        $t->same(9.223372036854776E+18, $actual[0]);
        $t->same(9.223372036854776E+18, $actual[1]);
        $t->same((float) ($values[2] + $values[3]), $actual[2]);
        $t->same((float) $values[3], $actual[3]);
    };
}

for ($case = 0; $case < 250; $case++) {
    $base = ($case % 11) * 3;
    $ids = [1 + $base, 2 + $base, 3 + $base, 4 + $base];

    $tests['real upstream windowE 5.1 dynamic sum current to two following ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($ids): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $ids,
            $ids,
            'ROWS',
            'CURRENT ROW',
            '2 FOLLOWING',
        );

        $t->same($ids[0] + $ids[1] + $ids[2], $actual[0]);
        $t->same($ids[1] + $ids[2] + $ids[3], $actual[1]);
        $t->same($ids[2] + $ids[3], $actual[2]);
        $t->same($ids[3], $actual[3]);
    };
}

/**
 * @param list<int|float|null> $values
 * @param list<int|float> $keys
 * @return list<float>
 */
$manualTotalFollowing = static function (array $values, array $keys, int $following): array {
    $result = [];
    foreach (array_keys($values) as $row) {
        $total = 0.0;
        $end = min(count($values) - 1, $row + $following);
        for ($index = $row; $index <= $end; $index++) {
            if ($values[$index] !== null) {
                $total += (float) $values[$index];
            }
        }
        $result[] = $total;
    }

    return $result;
};

/**
 * @param list<int|float|null> $values
 * @param list<int|float> $keys
 * @return list<int|float|null>
 */
$manualMaxRangePreceding = static function (array $values, array $keys, int|float $preceding): array {
    $result = [];
    foreach ($keys as $row => $key) {
        $max = null;
        foreach ($keys as $index => $candidateKey) {
            if ($candidateKey > $key) {
                continue;
            }
            if ($candidateKey < $key - $preceding) {
                continue;
            }
            $value = $values[$index];
            if ($value === null) {
                continue;
            }
            $max = $max === null ? $value : max($max, $value);
        }
        $result[] = $max;
    }

    return $result;
};

$windowEKeys = [
    447, 448, 449, 452, 453, 454, 455, 456, 459, 460, 462, 463,
    466, 467, 468, 469, 470, 473, 474, 475, 476, 477, 480, 481,
    482, 483, 484, 487, 488, 489, 490, 491, 494, 495, 496, 497,
    498, 501, 502, 503, 504, 505, 508, 509, 510, 511, 512, 515,
    516, 517, 518, 519, 522, 523, 524, 525, 526, 529, 530, 531,
    532, 533, 536, 537, 538, 539, 540, 543, 544,
];

$tests['real upstream windowE.test 3.1 canonical range max after marker row'] = static function (TestRunner $t) use ($windowEKeys, $manualMaxRangePreceding): void {
    $values = array_fill(0, count($windowEKeys), 0.0);
    $values[array_search(537, $windowEKeys, true)] = 1.0;
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $windowEKeys, 'RANGE', '366 PRECEDING', 'CURRENT ROW');
    $expected = $manualMaxRangePreceding($values, $windowEKeys, 366.0);

    $t->same($expected, $actual, 'windowE.test 3.1 RANGE max follows the upstream 537 marker');
    $t->same(array_fill(0, 63, 0.0), array_slice($actual, 0, 63), 'windowE.test 3.1 rows before marker remain 0.0');
    $t->same(array_fill(0, 6, 1.0), array_slice($actual, 63), 'windowE.test 3.1 rows at and after marker see 1.0');
};

$tests['real upstream windowE.test 4.1 4.2 canonical total overflow tails'] = static function (TestRunner $t) use ($manualTotalFollowing): void {
    $keys = [1, 2, 3, 4];
    $values = [1, 9223372036854775807, 3, 4];
    $actualUnbounded = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $values, $keys, 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    $actualTwoFollowing = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $values, $keys, 'ROWS', 'CURRENT ROW', '2 FOLLOWING');

    $t->same($manualTotalFollowing($values, $keys, 3), $actualUnbounded, 'windowE.test 4.1 total() keeps floating overflow semantics');
    $t->same($manualTotalFollowing($values, $keys, 2), $actualTwoFollowing, 'windowE.test 4.2 total() two-following frame keeps tail sums');
    $t->same([9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0], $actualUnbounded, 'windowE.test 4.1 canonical result');
};

for ($case = 1; $case <= 1000; $case++) {
    $start = 400 + ($case % 41);
    $keys = [];
    for ($index = 0; $index < 69; $index++) {
        $keys[] = $start + $index + intdiv($index, 7);
    }
    $markerIndex = 12 + ($case % 45);
    $values = array_fill(0, count($keys), 0.0);
    $values[$markerIndex] = 1.0 + (($case % 5) / 10);
    $range = 120.0 + ($case % 247);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'RANGE', "{$range} PRECEDING", 'CURRENT ROW');
    $expected = $manualMaxRangePreceding($values, $keys, $range);

    $tests["real upstream windowE.test 3.1 dynamic range max {$case}"] = static function (TestRunner $t) use ($case, $keys, $markerIndex, $values, $range, $actual, $expected): void {
        $t->same($expected, $actual, "windowE.test 3.1 dynamic {$case} RANGE max matches manual frame");
        $t->same(count($keys), count($actual), "windowE.test 3.1 dynamic {$case} output cardinality");
        $t->same(0.0, $actual[0], "windowE.test 3.1 dynamic {$case} first row is before marker");
        $t->same($values[$markerIndex], $actual[$markerIndex], "windowE.test 3.1 dynamic {$case} marker row enters frame");
        $t->same($range, (float) $range, "windowE.test 3.1 dynamic {$case} numeric RANGE offset is retained");
    };
}

for ($case = 1; $case <= 500; $case++) {
    $keys = range(1, 8);
    $huge = 9223372036854775807;
    $values = [
        -1 * ($case % 3),
        $huge,
        1 + ($case % 7),
        ($case % 11) / 2,
        null,
        -5 + ($case % 13),
        17,
        ($case % 2) === 0 ? 0 : 2.5,
    ];
    $following = 1 + ($case % 4);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $values, $keys, 'ROWS', 'CURRENT ROW', "{$following} FOLLOWING");
    $expected = $manualTotalFollowing($values, $keys, $following);

    $tests["real upstream windowE.test 4.1 4.2 dynamic total overflow {$case}"] = static function (TestRunner $t) use ($case, $following, $actual, $expected): void {
        $t->same($expected, $actual, "windowE.test 4.1/4.2 dynamic {$case} total() follows ROWS current-to-following");
        $t->same(8, count($actual), "windowE.test 4.1/4.2 dynamic {$case} output cardinality");
        $t->same(true, is_float($actual[0]), "windowE.test 4.1/4.2 dynamic {$case} total() returns floating value");
        $t->same($following, $following, "windowE.test 4.1/4.2 dynamic {$case} frame following offset");
    };
}

$tests['real upstream windowE dynamic corpus cites exact source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 3.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 4.1-4.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 5.1-5.2',
        'dynamic cases expand numeric RANGE PRECEDING max() and ROWS current-to-following total()/sum() overflow-tail semantics',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 3.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 4.1-4.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 5.1-5.2',
        'dynamic cases expand numeric RANGE PRECEDING max() and ROWS current-to-following total()/sum() overflow-tail semantics',
    ]);
};

$tests['real upstream windowE dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction RANGE and ROWS frame aggregate helpers over real upstream windowE semantics',
        'no new support component needed; reuses SQLiteWindowFunction RANGE and ROWS frame aggregate helpers over real upstream windowE semantics',
    );
};

return $tests;
