<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Real upstream source: SQLite test/window3.test generated sections 1.18 and 1.20.
// Those sections stress ROWS frames with EXCLUDE CURRENT ROW and EXCLUDE GROUP over
// the generated t2(a,b) corpus. This PHP port keeps the same t2 rows and varies
// bounded slices of that corpus to exercise frame exclusion behavior broadly.
$upstreamT2 = [
    10 => 89, 11 => 81, 12 => 96, 13 => 59, 14 => 38, 15 => 68, 16 => 39, 17 => 62,
    18 => 91, 19 => 46, 20 => 6, 21 => 99, 22 => 97, 23 => 27, 24 => 46, 25 => 78,
    26 => 54, 27 => 97, 28 => 8, 29 => 67, 30 => 29, 31 => 93, 32 => 84, 33 => 77,
    34 => 23, 35 => 16, 36 => 16, 37 => 93, 38 => 65, 39 => 35, 40 => 47, 41 => 7,
    42 => 86, 43 => 74, 44 => 61, 45 => 91, 46 => 85, 47 => 24, 48 => 85, 49 => 43,
    50 => 59, 51 => 12, 52 => 32, 53 => 56, 54 => 3, 55 => 91, 56 => 22, 57 => 90,
    58 => 55, 59 => 15, 60 => 28, 61 => 89, 62 => 25, 63 => 47, 64 => 1, 65 => 56,
    66 => 40, 67 => 43, 68 => 56, 69 => 16, 70 => 75, 71 => 36, 72 => 89, 73 => 98,
    74 => 76, 75 => 81, 76 => 4, 77 => 94, 78 => 42, 79 => 30, 80 => 78, 81 => 33,
    82 => 29, 83 => 53, 84 => 63, 85 => 2, 86 => 87, 87 => 37, 88 => 80, 89 => 84,
    90 => 72, 91 => 41, 92 => 9, 93 => 61, 94 => 73, 95 => 95, 96 => 65, 97 => 13,
    98 => 58, 99 => 96, 100 => 98, 101 => 1, 102 => 21, 103 => 74, 104 => 65,
    105 => 35, 106 => 5, 107 => 73, 108 => 11, 109 => 51, 110 => 87, 111 => 41,
    112 => 12, 113 => 8, 114 => 20, 115 => 31, 116 => 31, 117 => 15, 118 => 95,
    119 => 22, 120 => 73, 121 => 79, 122 => 88, 123 => 34, 124 => 8, 125 => 11,
    126 => 49, 127 => 34, 128 => 90, 129 => 59, 130 => 96, 131 => 60, 132 => 55,
    133 => 75, 134 => 77, 135 => 44, 136 => 2, 137 => 7, 138 => 85, 139 => 57,
    140 => 74, 141 => 29, 142 => 70, 143 => 59, 144 => 19, 145 => 39, 146 => 26,
    147 => 26, 148 => 47, 149 => 80, 150 => 90, 151 => 36, 152 => 58, 153 => 47,
    154 => 9, 155 => 72, 156 => 72, 157 => 66, 158 => 33, 159 => 93, 160 => 75,
    161 => 64, 162 => 81, 163 => 9, 164 => 23, 165 => 37, 166 => 13, 167 => 12,
    168 => 14, 169 => 62, 170 => 91, 171 => 36, 172 => 91, 173 => 33, 174 => 15,
    175 => 34, 176 => 36, 177 => 99, 178 => 3, 179 => 95, 180 => 69, 181 => 58,
    182 => 52, 183 => 30, 184 => 50, 185 => 84, 186 => 10, 187 => 84, 188 => 33,
    189 => 21, 190 => 39, 191 => 44, 192 => 58, 193 => 30, 194 => 38, 195 => 34,
    196 => 83, 197 => 27, 198 => 82, 199 => 17, 200 => 7,
];

$upstreamA = array_keys($upstreamT2);
$upstreamB = array_values($upstreamT2);

$frameIndexes = static function (
    array $keys,
    string $start,
    string $end,
    string $exclude,
): array {
    $sentinels = range(1, count($keys));
    $rows = [];
    $values = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'group_concat',
        $sentinels,
        $keys,
        'ROWS',
        $start,
        $end,
        $exclude,
    );

    foreach ($values as $value) {
        $rows[] = $value === null
            ? []
            : array_map(static fn (string $piece): int => (int) $piece - 1, explode(',', $value));
    }

    return $rows;
};

$expectAggregate = static function (string $function, array $frameValues): int|float|string|null {
    return match ($function) {
        'count' => count($frameValues),
        'sum' => $frameValues === [] ? null : array_sum($frameValues),
        'total' => (float) array_sum($frameValues),
        'avg' => $frameValues === [] ? null : array_sum($frameValues) / count($frameValues),
        'min' => $frameValues === [] ? null : min($frameValues),
        'max' => $frameValues === [] ? null : max($frameValues),
        'group_concat' => $frameValues === [] ? null : implode('.', array_map('strval', $frameValues)),
        default => throw new RuntimeException('Unsupported upstream window3 aggregate'),
    };
};

$boundaries = [
    ['4 PRECEDING', 'UNBOUNDED FOLLOWING'],
    ['3 PRECEDING', '1 FOLLOWING'],
    ['CURRENT ROW', '4 FOLLOWING'],
    ['UNBOUNDED PRECEDING', '2 FOLLOWING'],
    ['2 PRECEDING', 'CURRENT ROW'],
];
$exclusions = ['CURRENT ROW', 'GROUP', 'TIES', 'NO OTHERS'];
$orderModes = ['a', 'b', 'b_mod_10', 'b_mod_2_then_10'];
$functions = ['sum', 'count', 'total', 'avg', 'min', 'max', 'group_concat'];

for ($case = 0; $case < 1000; $case++) {
    $startOffset = ($case * 7) % (count($upstreamB) - 24);
    $length = 12 + ($case % 13);
    $values = array_slice($upstreamB, $startOffset, $length);
    $aValues = array_slice($upstreamA, $startOffset, $length);
    $mode = $orderModes[$case % count($orderModes)];
    $keys = match ($mode) {
        'a' => $aValues,
        'b' => $values,
        'b_mod_10' => array_map(static fn (int $value): int => $value % 10, $values),
        'b_mod_2_then_10' => array_map(static fn (int $value): string => ($value % 2) . ':' . ($value % 10), $values),
    };
    [$start, $end] = $boundaries[intdiv($case, count($orderModes)) % count($boundaries)];
    $exclude = $exclusions[intdiv($case, count($orderModes) * count($boundaries)) % count($exclusions)];
    $function = $functions[intdiv($case, count($orderModes) * count($boundaries) * count($exclusions)) % count($functions)];

    $tests['real upstream window3 dynamic exclude frame corpus ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $values, $keys, $start, $end, $exclude, $function, $frameIndexes, $expectAggregate): void {
        $frames = $frameIndexes($keys, $start, $end, $exclude);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            $function,
            $values,
            $keys,
            'ROWS',
            $start,
            $end,
            $exclude,
            null,
            '.',
        );
        $firstValues = SQLiteWindowFunction::valueFrameBetweenValues('first_value', $values, $keys, 'ROWS', $start, $end, $exclude);
        $lastValues = SQLiteWindowFunction::valueFrameBetweenValues('last_value', $values, $keys, 'ROWS', $start, $end, $exclude);
        $nthValues = SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, $keys, 'ROWS', $start, $end, $exclude, 2);

        foreach ([0, intdiv(count($values), 2), count($values) - 1] as $row) {
            $frameValues = array_map(static fn (int $frameRow): int => $values[$frameRow], $frames[$row]);
            $expected = $expectAggregate($function, $frameValues);
            if ($function === 'avg' || $function === 'total') {
                $t->same($expected === null ? null : (float) $expected, $actual[$row] === null ? null : (float) $actual[$row], "window3.test 1.18/1.20 case {$case} {$function} row {$row}");
            } else {
                $t->same($expected, $actual[$row], "window3.test 1.18/1.20 case {$case} {$function} row {$row}");
            }

            $t->same($frameValues === [] ? null : $frameValues[0], $firstValues[$row], "window3.test 1.18/1.20 case {$case} first row {$row}");
            $t->same($frameValues === [] ? null : $frameValues[count($frameValues) - 1], $lastValues[$row], "window3.test 1.18/1.20 case {$case} last row {$row}");
            $t->same($frameValues[1] ?? null, $nthValues[$row], "window3.test 1.18/1.20 case {$case} nth row {$row}");
        }
    };
}

return $tests;
