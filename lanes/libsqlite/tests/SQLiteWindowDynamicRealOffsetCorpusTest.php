<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Real upstream source: SQLite test/window3.test generated sections 1.19.12
// and 1.19.13, which exercise lead(b,b) and lag(b,b) over generated t2 rows
// with row-dependent offsets, varied partitions, ORDER BY terms, and EXCLUDE
// clauses. lead()/lag() ignore frame exclusion in SQLite, so this corpus
// focuses on row-offset navigation after partition/order materialization.
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

$baseRows = [];
foreach ($upstreamT2 as $a => $b) {
    $baseRows[] = ['a' => $a, 'b' => $b];
}

$partitionKey = static function (array $row, string $mode): string {
    return match ($mode) {
        'none' => '__all',
        'b_mod_10' => (string) ($row['b'] % 10),
        'b_mod_2_a' => ($row['b'] % 2) . ':' . $row['a'],
        default => throw new RuntimeException('Unsupported upstream window3 partition mode'),
    };
};

$orderValues = static function (array $row, string $mode): array {
    return match ($mode) {
        'a' => [$row['a']],
        'b_a' => [$row['b'], $row['a']],
        'b_mod_10_a' => [$row['b'] % 10, $row['a']],
        default => throw new RuntimeException('Unsupported upstream window3 order mode'),
    };
};

$compareRows = static function (array $left, array $right, string $mode) use ($orderValues): int {
    $leftValues = $orderValues($left, $mode);
    $rightValues = $orderValues($right, $mode);
    foreach ($leftValues as $index => $leftValue) {
        $comparison = $leftValue <=> $rightValues[$index];
        if ($comparison !== 0) {
            return $comparison;
        }
    }

    return $left['a'] <=> $right['a'];
};

$orderedPartitions = static function (array $rows, string $partitionMode, string $orderMode) use ($partitionKey, $compareRows): array {
    $partitions = [];
    foreach ($rows as $row) {
        $partitions[$partitionKey($row, $partitionMode)][] = $row;
    }
    foreach ($partitions as &$partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => $compareRows($left, $right, $orderMode));
    }
    unset($partitionRows);
    ksort($partitions);

    return array_values($partitions);
};

$oracleOffsetValues = static function (array $values, array $offsets, int $direction): array {
    $actual = [];
    foreach ($values as $index => $_value) {
        $target = $index + ($direction * $offsets[$index]);
        $actual[] = array_key_exists($target, $values) ? $values[$target] : null;
    }

    return $actual;
};

$partitionModes = ['none', 'b_mod_10', 'b_mod_2_a'];
$orderModes = ['a', 'b_a', 'b_mod_10_a'];
$directions = ['lead', 'lag'];

for ($case = 0; $case < 1200; $case++) {
    $start = ($case * 11) % (count($baseRows) - 30);
    $length = 18 + ($case % 13);
    $rows = array_slice($baseRows, $start, $length);
    $partitionMode = $partitionModes[$case % count($partitionModes)];
    $orderMode = $orderModes[intdiv($case, count($partitionModes)) % count($orderModes)];
    $direction = $directions[intdiv($case, count($partitionModes) * count($orderModes)) % count($directions)];

    $tests['real upstream window3 dynamic row offset ' . $direction . ' corpus ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $rows, $partitionMode, $orderMode, $direction, $orderedPartitions, $oracleOffsetValues): void {
        foreach ($orderedPartitions($rows, $partitionMode, $orderMode) as $partitionIndex => $partitionRows) {
            $values = array_column($partitionRows, 'b');
            $offsets = array_column($partitionRows, 'b');
            $actual = $direction === 'lead'
                ? SQLiteWindowFunction::leadByRow($values, $offsets)
                : SQLiteWindowFunction::lagByRow($values, $offsets);
            $expected = $oracleOffsetValues($values, $offsets, $direction === 'lead' ? 1 : -1);

            foreach ([0, intdiv(count($values), 2), count($values) - 1] as $row) {
                $t->same($expected[$row], $actual[$row], "window3.test 1.19 row-offset {$direction} case {$case} partition {$partitionIndex} row {$row}");
            }
        }
    };
}

$tests['real upstream window3 dynamic row offset validates offsets and row counts'] = static function (TestRunner $t): void {
    $t->same([30, null, 30], SQLiteWindowFunction::leadByRow([10, 20, 30], [2, 2, 0]));
    $t->same([10, 10, null], SQLiteWindowFunction::lagByRow([10, 20, 30], [0, 1, 3]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::leadByRow([1, 2], [1]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::leadByRow([1], [-1]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::lagByRow([1], ['1.5']));
};

return $tests;
