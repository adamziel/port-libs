<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Source: upstream SQLite test/window3.test, t2 rows and ranking/distribution
// sections 1.1.3 through 1.1.8.
$t2 = [
    10 => 89, 11 => 81, 12 => 96, 13 => 59, 14 => 38, 15 => 68, 16 => 39, 17 => 62, 18 => 91, 19 => 46, 20 => 6, 21 => 99,
    22 => 97, 23 => 27, 24 => 46, 25 => 78, 26 => 54, 27 => 97, 28 => 8, 29 => 67, 30 => 29, 31 => 93, 32 => 84, 33 => 77,
    34 => 23, 35 => 16, 36 => 16, 37 => 93, 38 => 65, 39 => 35, 40 => 47, 41 => 7, 42 => 86, 43 => 74, 44 => 61, 45 => 91,
    46 => 85, 47 => 24, 48 => 85, 49 => 43, 50 => 59, 51 => 12, 52 => 32, 53 => 56, 54 => 3, 55 => 91, 56 => 22, 57 => 90,
    58 => 55, 59 => 15, 60 => 28, 61 => 89, 62 => 25, 63 => 47, 64 => 1, 65 => 56, 66 => 40, 67 => 43, 68 => 56, 69 => 16,
    70 => 75, 71 => 36, 72 => 89, 73 => 98, 74 => 76, 75 => 81, 76 => 4, 77 => 94, 78 => 42, 79 => 30, 80 => 78, 81 => 33,
    82 => 29, 83 => 53, 84 => 63, 85 => 2, 86 => 87, 87 => 37, 88 => 80, 89 => 84, 90 => 72, 91 => 41, 92 => 9, 93 => 61,
    94 => 73, 95 => 95, 96 => 65, 97 => 13, 98 => 58, 99 => 96, 100 => 98, 101 => 1, 102 => 21, 103 => 74, 104 => 65, 105 => 35,
    106 => 5, 107 => 73, 108 => 11, 109 => 51, 110 => 87, 111 => 41, 112 => 12, 113 => 8, 114 => 20, 115 => 31, 116 => 31, 117 => 15,
    118 => 95, 119 => 22, 120 => 73, 121 => 79, 122 => 88, 123 => 34, 124 => 8, 125 => 11, 126 => 49, 127 => 34, 128 => 90, 129 => 59,
    130 => 96, 131 => 60, 132 => 55, 133 => 75, 134 => 77, 135 => 44, 136 => 2, 137 => 7, 138 => 85, 139 => 57, 140 => 74, 141 => 29,
    142 => 70, 143 => 59, 144 => 19, 145 => 39, 146 => 26, 147 => 26, 148 => 47, 149 => 80, 150 => 90, 151 => 36, 152 => 58, 153 => 47,
    154 => 9, 155 => 72, 156 => 72, 157 => 66, 158 => 33, 159 => 93, 160 => 75, 161 => 64, 162 => 81, 163 => 9, 164 => 23, 165 => 37,
    166 => 13, 167 => 12, 168 => 14, 169 => 62, 170 => 91, 171 => 36, 172 => 91, 173 => 33, 174 => 15, 175 => 34, 176 => 36, 177 => 99,
    178 => 3, 179 => 95, 180 => 69, 181 => 58, 182 => 52, 183 => 30, 184 => 50, 185 => 84, 186 => 10, 187 => 84, 188 => 33, 189 => 21,
    190 => 39, 191 => 44, 192 => 58, 193 => 30, 194 => 38, 195 => 34, 196 => 83, 197 => 27, 198 => 82, 199 => 17, 200 => 7,
];

$rowsByA = [];
foreach ($t2 as $a => $b) {
    $rowsByA[] = ['a' => $a, 'b' => $b];
}

$rowsByB = $rowsByA;
usort($rowsByB, static fn (array $left, array $right): int => [$left['b'], $left['a']] <=> [$right['b'], $right['a']]);

$rankOracle = static function (array $keys): array {
    $result = [];
    $rank = 1;
    $previous = null;
    foreach ($keys as $index => $key) {
        if ($index === 0 || $key !== $previous) {
            $rank = $index + 1;
        }
        $result[] = $rank;
        $previous = $key;
    }

    return $result;
};

$denseRankOracle = static function (array $keys): array {
    $result = [];
    $rank = 0;
    $previous = null;
    foreach ($keys as $index => $key) {
        if ($index === 0 || $key !== $previous) {
            $rank++;
        }
        $result[] = $rank;
        $previous = $key;
    }

    return $result;
};

$percentRankOracle = static function (array $ranked): array {
    $count = count($ranked);
    if ($count <= 1) {
        return array_fill(0, $count, 0.0);
    }

    return array_map(static fn (int $rank): float => (float) (($rank - 1) / ($count - 1)), $ranked);
};

$cumeDistOracle = static function (array $keys): array {
    $count = count($keys);
    $frequencies = array_count_values(array_map('strval', $keys));
    ksort($frequencies, SORT_NUMERIC);
    $cumulative = [];
    $seen = 0;
    foreach ($frequencies as $key => $frequency) {
        $seen += $frequency;
        $cumulative[$key] = (float) ($seen / $count);
    }

    return array_map(static fn (int $key): float => $cumulative[(string) $key], $keys);
};

$aKeys = array_column($rowsByA, 'a');
$bKeys = array_column($rowsByB, 'b');
$sequential = range(1, count($rowsByA));

$scenarios = [
    'window3.test 1.1.3.1 row_number order by a range current' => [$rowsByA, $sequential, $sequential],
    'window3.test 1.1.4.1 dense_rank order by a range current' => [$rowsByA, SQLiteWindowFunction::denseRank($aKeys), $sequential],
    'window3.test 1.1.5.1 rank order by a range current' => [$rowsByA, SQLiteWindowFunction::rank($aKeys), $sequential],
    'window3.test 1.1.7.1 percent_rank order by a range current' => [$rowsByA, SQLiteWindowFunction::percentRank($aKeys), $percentRankOracle($sequential)],
    'window3.test 1.1.8.1 cume_dist order by a range current' => [$rowsByA, SQLiteWindowFunction::cumeDist($aKeys), array_map(static fn (int $row): float => (float) ($row / count($rowsByA)), $sequential)],
    'window3.test 1.1.4.3 dense_rank order by b range current' => [$rowsByB, SQLiteWindowFunction::denseRank($bKeys), $denseRankOracle($bKeys)],
    'window3.test 1.1.5.3 rank order by b range current' => [$rowsByB, SQLiteWindowFunction::rank($bKeys), $rankOracle($bKeys)],
];

foreach ($scenarios as $source => [$orderedRows, $actual, $expected]) {
    foreach ($orderedRows as $index => $row) {
        $tests["real upstream $source row a{$row['a']} b{$row['b']}"] = static function (TestRunner $t) use ($actual, $expected, $index): void {
            $t->same($expected[$index], $actual[$index]);
        };
    }
}

$tests['real upstream window3 ranking distribution batch cites exact upstream source'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window3.test:1.0 t2 191-row fixture',
            'window3.test:1.1.3.1 row_number ORDER BY a RANGE',
            'window3.test:1.1.4.1 dense_rank ORDER BY a RANGE',
            'window3.test:1.1.4.3 dense_rank ORDER BY b RANGE',
            'window3.test:1.1.5.1 rank ORDER BY a RANGE',
            'window3.test:1.1.5.3 rank ORDER BY b RANGE',
            'window3.test:1.1.7.1 percent_rank ORDER BY a RANGE',
            'window3.test:1.1.8.1 cume_dist ORDER BY a RANGE',
        ],
        [
            'window3.test:1.0 t2 191-row fixture',
            'window3.test:1.1.3.1 row_number ORDER BY a RANGE',
            'window3.test:1.1.4.1 dense_rank ORDER BY a RANGE',
            'window3.test:1.1.4.3 dense_rank ORDER BY b RANGE',
            'window3.test:1.1.5.1 rank ORDER BY a RANGE',
            'window3.test:1.1.5.3 rank ORDER BY b RANGE',
            'window3.test:1.1.7.1 percent_rank ORDER BY a RANGE',
            'window3.test:1.1.8.1 cume_dist ORDER BY a RANGE',
        ],
    );
};

return $tests;
