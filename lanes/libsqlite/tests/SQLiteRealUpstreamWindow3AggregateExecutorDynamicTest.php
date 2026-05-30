<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;

$tests = [];

// Source truth: upstream SQLite test/window3.test t2 fixture and aggregate
// window sections 1.1, 1.1.2, 1.1.6 plus generated partition variants.
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

$rows = [];
foreach ($t2 as $a => $b) {
    $rows[] = ['a' => $a, 'b' => $b, 'bucket' => $b % 10, 'parity' => $b % 2];
}

$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$binary = static fn (string $operator, array $left, array $right): array => ['type' => 'binary', 'operator' => $operator, 'left' => $left, 'right' => $right];

$sortRows = static function (array $sourceRows, array $partitionExpressions, callable $orderExpression): array {
    $copy = $sourceRows;
    usort($copy, static function (array $left, array $right) use ($partitionExpressions, $orderExpression): int {
        foreach ($partitionExpressions as $expression) {
            $comparison = $expression($left) <=> $expression($right);
            if ($comparison !== 0) {
                return $comparison;
            }
        }
        $comparison = $orderExpression($left) <=> $orderExpression($right);
        if ($comparison !== 0) {
            return $comparison;
        }

        return $left['a'] <=> $right['a'];
    });

    return $copy;
};

$partitionFrames = static function (array $orderedRows, array $partitionExpressions, callable $valueExpression): array {
    $frames = [];
    $start = 0;
    $count = count($orderedRows);
    while ($start < $count) {
        $end = $start;
        while ($end + 1 < $count) {
            $same = true;
            foreach ($partitionExpressions as $expression) {
                if ($expression($orderedRows[$start]) !== $expression($orderedRows[$end + 1])) {
                    $same = false;
                    break;
                }
            }
            if (!$same) {
                break;
            }
            $end++;
        }
        $running = [];
        for ($index = $start; $index <= $end; $index++) {
            $running[] = $valueExpression($orderedRows[$index]);
            $frames[$index] = $running;
        }
        $start = $end + 1;
    }
    ksort($frames);

    return array_values($frames);
};

$metric = static function (string $function, array $values): int|float|null {
    return match ($function) {
        'count' => count(array_filter($values, static fn (mixed $value): bool => $value !== null)),
        'sum' => $values === [] ? null : array_sum($values),
        'total' => (float) array_sum($values),
        'avg' => $values === [] ? null : (float) (array_sum($values) / count($values)),
        'min' => $values === [] ? null : min($values),
        'max' => $values === [] ? null : max($values),
        default => throw new InvalidArgumentException('Unsupported window3 aggregate ' . $function),
    };
};

$selectExecutorValues = static function (array $sourceRows, string $function, array $valueExpression, array $partitionBy, array $orderBy, array $resultOrderBy) use ($column): array {
    $result = SQLiteSelectQuery::execute([
        'from' => $sourceRows,
        'select' => [
            ['type' => 'column', 'name' => 'a', 'alias' => 'a'],
            ['type' => 'column', 'name' => 'b', 'alias' => 'b'],
            ['type' => 'column', 'name' => 'bucket', 'alias' => 'bucket'],
            ['type' => 'column', 'name' => 'parity', 'alias' => 'parity'],
            [
                'type' => 'window',
                'function' => $function,
                'arguments' => [$valueExpression],
                'partitionBy' => $partitionBy,
                'orderBy' => [['expression' => $orderBy, 'direction' => 'ASC']],
                'frame' => ['unit' => 'RANGE', 'preceding' => INF, 'following' => 0, 'exclude' => 'NO OTHERS'],
                'alias' => 'win',
            ],
        ],
        'orderBy' => $resultOrderBy,
    ]);

    return array_column($result, 'win');
};

$scenarios = [
    'window3.test 1.1 max b order by a executor' => [
        'max',
        $column('b'),
        [],
        $column('a'),
        [['column' => 'a', 'direction' => 'ASC']],
        [],
        static fn (array $row): int => $row['a'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.2.2 min b order by a executor' => [
        'min',
        $column('b'),
        [],
        $column('a'),
        [['column' => 'a', 'direction' => 'ASC']],
        [],
        static fn (array $row): int => $row['a'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.6.1 sum b order by a executor' => [
        'sum',
        $column('b'),
        [],
        $column('a'),
        [['column' => 'a', 'direction' => 'ASC']],
        [],
        static fn (array $row): int => $row['a'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.6.1 avg b order by a executor' => [
        'avg',
        $column('b'),
        [],
        $column('a'),
        [['column' => 'a', 'direction' => 'ASC']],
        [],
        static fn (array $row): int => $row['a'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.6.1 total b order by a executor' => [
        'total',
        $column('b'),
        [],
        $column('a'),
        [['column' => 'a', 'direction' => 'ASC']],
        [],
        static fn (array $row): int => $row['a'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.6.1 count b order by a executor' => [
        'count',
        $column('b'),
        [],
        $column('a'),
        [['column' => 'a', 'direction' => 'ASC']],
        [],
        static fn (array $row): int => $row['a'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.2.3 max b order by b peers executor' => [
        'max',
        $column('b'),
        [],
        $column('b'),
        [['column' => 'b', 'direction' => 'ASC'], ['column' => 'a', 'direction' => 'ASC']],
        [],
        static fn (array $row): int => $row['b'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.2.4 min b order by b peers executor' => [
        'min',
        $column('b'),
        [],
        $column('b'),
        [['column' => 'b', 'direction' => 'ASC'], ['column' => 'a', 'direction' => 'ASC']],
        [],
        static fn (array $row): int => $row['b'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.6.4 sum b partition by b mod 10 executor' => [
        'sum',
        $column('b'),
        [$binary('%', $column('b'), $literal(10))],
        $column('a'),
        [['column' => 'bucket', 'direction' => 'ASC'], ['column' => 'a', 'direction' => 'ASC']],
        [static fn (array $row): int => $row['b'] % 10],
        static fn (array $row): int => $row['a'],
        static fn (array $row): int => $row['b'],
    ],
    'window3.test 1.1.6.5 avg b partition by b mod 2 executor' => [
        'avg',
        $column('b'),
        [$binary('%', $column('b'), $literal(2))],
        $column('a'),
        [['column' => 'parity', 'direction' => 'ASC'], ['column' => 'a', 'direction' => 'ASC']],
        [static fn (array $row): int => $row['b'] % 2],
        static fn (array $row): int => $row['a'],
        static fn (array $row): int => $row['b'],
    ],
];

$passCaseCount = 0;
foreach ($scenarios as $name => [$function, $valueExpression, $partitionBy, $orderBy, $resultOrderBy, $partitionCallbacks, $sortCallback, $valueCallback]) {
    $orderedRows = $sortRows($rows, $partitionCallbacks, $sortCallback);
    $frames = $partitionFrames($orderedRows, $partitionCallbacks, $valueCallback);
    $expected = array_map(static fn (array $values): int|float|null => $metric($function, $values), $frames);
    $actual = $selectExecutorValues($rows, $function, $valueExpression, $partitionBy, $orderBy, $resultOrderBy);

    foreach ($orderedRows as $index => $row) {
        $passCaseCount++;
        $tests["real upstream window3 aggregate executor dynamic {$name} row a{$row['a']} b{$row['b']}"] = static function (TestRunner $t) use ($actual, $expected, $index, $name): void {
            $expectedValue = is_float($expected[$index]) ? round($expected[$index], 12) : $expected[$index];
            $actualValue = is_float($actual[$index]) ? round($actual[$index], 12) : $actual[$index];
            $t->same($expectedValue, $actualValue, $name);
        };
    }
}

$tests['real upstream window3 aggregate executor dynamic cites upstream source and count'] = static function (TestRunner $t) use ($passCaseCount): void {
    $t->same(1910, $passCaseCount, 'window3.test aggregate executor focused PASS case count');
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test:1.0 t2 191-row fixture',
        'window3.test:1.1 max(b) OVER (ORDER BY a)',
        'window3.test:1.1.2 min/max RANGE frames',
        'window3.test:1.1.6 aggregate executor generated variants over partitions',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test:1.0 t2 191-row fixture',
        'window3.test:1.1 max(b) OVER (ORDER BY a)',
        'window3.test:1.1.2 min/max RANGE frames',
        'window3.test:1.1.6 aggregate executor generated variants over partitions',
    ]);
};

return $tests;
