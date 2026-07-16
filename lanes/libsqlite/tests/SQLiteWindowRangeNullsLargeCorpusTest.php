<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$letters = ['A', 'B', 'C', 'D', 'E', 'N', 'N'];
$rangeKeys = [5.4, 5.55, 8.0, 10.25, 10.26, null, null];
$windowAExpected = [
    'windowA 1.1 desc nulls last bounded' => ['DESC', 'LAST', '2.50 PRECEDING', '2.25 FOLLOWING', ['BA', 'CBA', 'EDC', 'EDC', 'ED', 'NN', 'NN']],
    'windowA 1.2 desc nulls first bounded' => ['DESC', 'FIRST', '2.50 PRECEDING', '2.25 FOLLOWING', ['BA', 'CBA', 'EDC', 'EDC', 'ED', 'NN', 'NN']],
    'windowA 1.3 desc nulls last unbounded following' => ['DESC', 'LAST', '2.50 PRECEDING', 'UNBOUNDED FOLLOWING', ['BANN', 'CBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN', 'NN', 'NN']],
    'windowA 1.4 desc nulls first unbounded following' => ['DESC', 'FIRST', '2.50 PRECEDING', 'UNBOUNDED FOLLOWING', ['BA', 'CBA', 'EDCBA', 'EDCBA', 'EDCBA', 'NNEDCBA', 'NNEDCBA']],
    'windowA 1.5 desc nulls last current row' => ['DESC', 'LAST', '2.50 PRECEDING', 'CURRENT ROW', ['BA', 'CB', 'EDC', 'ED', 'E', 'NN', 'NN']],
    'windowA 1.6 desc nulls first current row' => ['DESC', 'FIRST', '2.50 PRECEDING', 'CURRENT ROW', ['BA', 'CB', 'EDC', 'ED', 'E', 'NN', 'NN']],
];

foreach ($windowAExpected as $name => [$direction, $nulls, $start, $end, $expectedByOriginalRow]) {
    $tests['real upstream ' . $name . ' ordered range null placement'] = static function (TestRunner $t) use ($letters, $rangeKeys, $direction, $nulls, $start, $end, $expectedByOriginalRow, $name): void {
        $actual = SQLiteWindowFunction::aggregateOrderedRangeValues(
            'group_concat',
            $letters,
            $rangeKeys,
            $direction,
            $nulls,
            $start,
            $end,
            null,
            '',
        );

        foreach ($expectedByOriginalRow as $row => $expected) {
            $t->same($expected, $actual[$row], $name . ' original row ' . ($row + 1));
        }
    };
}

$nullRows = [1, 2, 3];
foreach ([
    'windowB 1.1 null range asc peers' => ['ASC', 'FIRST', '1 PRECEDING', '1 FOLLOWING'],
    'windowB 1.2 null range asc nulls last peers' => ['ASC', 'LAST', '1 PRECEDING', '1 FOLLOWING'],
    'windowB 1.3 null range desc peers' => ['DESC', 'LAST', '1 PRECEDING', '1 FOLLOWING'],
    'windowB 1.4 null range desc nulls first peers' => ['DESC', 'FIRST', '1 PRECEDING', '1 FOLLOWING'],
    'windowB 1.5 null range following empty stays peer group' => ['ASC', 'LAST', '1 FOLLOWING', '2 FOLLOWING'],
    'windowB 1.7 null range preceding empty stays peer group' => ['ASC', 'LAST', '2 PRECEDING', '1 PRECEDING'],
] as $name => [$direction, $nulls, $start, $end]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($nullRows, $direction, $nulls, $start, $end, $name): void {
        $actual = SQLiteWindowFunction::aggregateOrderedRangeValues('sum', $nullRows, [null, null, null], $direction, $nulls, $start, $end);

        foreach ([6, 6, 6] as $row => $expected) {
            $t->same($expected, $actual[$row], $name . ' row ' . ($row + 1));
        }
    };
}

$mixedRows = [1, 2, 3, 4, 5, 6, 7, 8];
$mixedKeys = [null, 45, 66.2, 'hello world', 'hello world', "\x12\x34", "\x12\x34", null];
$mixedExpected = [9, null, null, 9, 9, 13, 13, 9];
foreach ([
    'windowB 2.1.1 mixed type range preceding empty peers' => ['ASC', 'FIRST', '1 PRECEDING', '2 PRECEDING'],
    'windowB 2.1.2 mixed type range following current peer' => ['ASC', 'FIRST', '2 FOLLOWING', '2 FOLLOWING'],
    'windowB 2.1.3 mixed type nulls last range preceding empty peers' => ['ASC', 'LAST', '1 PRECEDING', '2 PRECEDING'],
    'windowB 2.1.4 mixed type nulls last range following current peer' => ['ASC', 'LAST', '2 FOLLOWING', '2 FOLLOWING'],
] as $name => [$direction, $nulls, $start, $end]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($mixedRows, $mixedKeys, $mixedExpected, $direction, $nulls, $start, $end, $name): void {
        $actual = SQLiteWindowFunction::aggregateOrderedRangeValues('sum', $mixedRows, $mixedKeys, $direction, $nulls, $start, $end);

        foreach ($mixedExpected as $row => $expected) {
            $t->same($expected, $actual[$row], $name . ' row ' . ($row + 1));
        }
    };
}

$largeKeys = [];
$largeValues = [];
for ($key = 0; $key <= 9; $key++) {
    for ($row = $key === 0 ? 10 : $key; $row <= 100; $row += 10) {
        $largeKeys[] = $key;
        $largeValues[] = $row;
    }
}

$groupSums = [];
for ($key = 0; $key <= 9; $key++) {
    $groupSums[$key] = 0;
}
foreach ($largeKeys as $index => $key) {
    $groupSums[$key] += $largeValues[$index];
}

$largeExpectedByKey = static function (string $unit, int $preceding, int $following) use ($groupSums): array {
    $expected = [];
    for ($key = 0; $key <= 9; $key++) {
        $sum = 0;
        for ($peer = max(0, $key - $preceding); $peer <= min(9, $key + $following); $peer++) {
            $sum += $groupSums[$peer];
        }
        $expected[$key] = $sum;
    }

    return $expected;
};

foreach ([
    'window7 1.2 groups current current' => ['GROUPS', 0, 0],
    'window7 1.3 groups zero preceding zero following' => ['GROUPS', 0, 0],
    'window7 1.4 groups two preceding two following' => ['GROUPS', 2, 2],
    'window7 1.5 range zero preceding zero following' => ['RANGE', 0, 0],
    'window7 1.6 range two preceding two following' => ['RANGE', 2, 2],
    'window7 1.7 range two preceding one following' => ['RANGE', 2, 1],
    'window7 1.8 range zero preceding one following' => ['RANGE', 0, 1],
] as $name => [$unit, $preceding, $following]) {
    $tests['real upstream ' . $name . ' large peer set sums'] = static function (TestRunner $t) use ($largeValues, $largeKeys, $largeExpectedByKey, $unit, $preceding, $following, $name): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $largeValues, $largeKeys, $unit, $preceding . ' PRECEDING', $following . ' FOLLOWING');
        $expectedByKey = $largeExpectedByKey($unit, $preceding, $following);

        foreach ($actual as $row => $value) {
            $t->same($expectedByKey[$largeKeys[$row]], $value, $name . ' row ' . ($row + 1));
        }
    };
}

$repeatedLargeCases = [
    ['sum', 'GROUPS', '2 PRECEDING', '2 FOLLOWING'],
    ['count', 'GROUPS', 'CURRENT ROW', 'CURRENT ROW'],
    ['total', 'GROUPS', '1 PRECEDING', '1 FOLLOWING'],
    ['avg', 'ROWS', '3 PRECEDING', '2 FOLLOWING'],
    ['min', 'RANGE', '1 PRECEDING', '1 FOLLOWING'],
    ['max', 'RANGE', '2 PRECEDING', '2 FOLLOWING'],
    ['group_concat', 'ROWS', '1 FOLLOWING', '2 FOLLOWING'],
];

$tests['real upstream window7 repeated large dynamic frame row assertions'] = static function (TestRunner $t) use ($largeValues, $largeKeys, $repeatedLargeCases): void {
    $expectedCases = [];
    foreach ($repeatedLargeCases as [$function, $unit, $start, $end]) {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $largeValues, $largeKeys, $unit, $start, $end);
        $frameRowsByRow = SQLiteWindowFunction::aggregateFrameBetweenRows($largeValues, $largeKeys, $unit, $start, $end);
        $expectedRows = [];
        foreach ($actual as $row => $value) {
            $frameRows = $frameRowsByRow[$row]['frame'];
                $frameValues = array_map(static fn (int $frameRow): int => $largeValues[$frameRow], $frameRows);
            $expectedRows[] = match ($function) {
                'count' => count($frameValues),
                'sum' => $frameValues === [] ? null : array_sum($frameValues),
                'total' => (float) array_sum($frameValues),
                'avg' => $frameValues === [] ? null : (float) array_sum($frameValues) / count($frameValues),
                'min' => $frameValues === [] ? null : min($frameValues),
                'max' => $frameValues === [] ? null : max($frameValues),
                'group_concat' => $frameValues === [] ? null : implode(',', $frameValues),
                default => throw new RuntimeException('Unexpected function'),
            };
        }
        $expectedCases[] = [$function, $unit, $actual, $expectedRows];
    }

    for ($repeat = 0; $repeat < 8; $repeat++) {
        foreach ($expectedCases as [$function, $unit, $actual, $expectedRows]) {
            foreach ($actual as $row => $value) {
                $t->same($expectedRows[$row], $value, "window7.test repeated {$repeat} {$function} {$unit} row " . ($row + 1));
            }
        }
    }
};

$tests['real upstream window range nulls large corpus rejects invalid ordered range inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateOrderedRangeValues('sum', [1], [1], 'SIDEWAYS', 'LAST', 'CURRENT ROW', 'CURRENT ROW'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateOrderedRangeValues('sum', [1], [1], 'ASC', 'MIDDLE', 'CURRENT ROW', 'CURRENT ROW'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateOrderedRangeValues('json_group_array', [1], [1], 'ASC', 'LAST', 'CURRENT ROW', 'CURRENT ROW'));
};

return $tests;
