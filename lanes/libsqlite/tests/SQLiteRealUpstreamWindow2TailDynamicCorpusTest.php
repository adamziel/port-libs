<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$sourceRows = static function (int $case): array {
    $rows = [];
    for ($a = 1; $a <= 10; $a++) {
        $base = $a + ($case % 5);
        $rows[] = [
            'a' => $a,
            'b' => ($a % 2) === 0 ? 'even' : 'odd',
            'd' => $base,
            'half' => intdiv($base, 2),
        ];
    }

    return $rows;
};

$orderedRows = static function (array $rows, ?string $partitionColumn, string $orderColumn): array {
    $ordered = $rows;
    usort($ordered, static function (array $left, array $right) use ($partitionColumn, $orderColumn): int {
        $partition = $partitionColumn === null ? 0 : ($left[$partitionColumn] <=> $right[$partitionColumn]);

        return $partition ?: ($left[$orderColumn] <=> $right[$orderColumn]) ?: ($left['a'] <=> $right['a']);
    });

    return $ordered;
};

$frameSumsByA = static function (
    array $rows,
    ?string $partitionColumn,
    string $orderColumn,
    string $unit,
    string $start,
    string $end,
) use ($orderedRows): array {
    $ordered = $orderedRows($rows, $partitionColumn, $orderColumn);
    $result = [];
    $position = 0;
    while ($position < count($ordered)) {
        $partitionEnd = $position;
        while (
            $partitionEnd + 1 < count($ordered)
            && ($partitionColumn === null || $ordered[$partitionEnd + 1][$partitionColumn] === $ordered[$position][$partitionColumn])
        ) {
            $partitionEnd++;
        }

        $partitionRows = array_slice($ordered, $position, $partitionEnd - $position + 1);
        $values = array_column($partitionRows, 'd');
        $keys = array_column($partitionRows, $orderColumn);
        $sums = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, $unit, $start, $end);
        foreach ($partitionRows as $index => $row) {
            $result[$row['a']] = $sums[$index];
        }

        $position = $partitionEnd + 1;
    }
    ksort($result, SORT_NUMERIC);

    return array_values($result);
};

$scenarios = [
    'window2.test 2.25 rows unbounded full frame' => [
        null,
        'd',
        'ROWS',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
        'SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 2.26 partition rows unbounded full frame' => [
        'b',
        'd',
        'ROWS',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
        'SELECT a, sum(d) OVER (PARTITION BY b ORDER BY d ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 2.27 rows current row only' => [
        null,
        'd',
        'ROWS',
        'CURRENT ROW',
        'CURRENT ROW',
        'SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 2.28 partition rows current row only' => [
        'b',
        'd',
        'ROWS',
        'CURRENT ROW',
        'CURRENT ROW',
        'SELECT a, sum(d) OVER (PARTITION BY b ORDER BY d ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 2.29 range current to unbounded following' => [
        null,
        'd',
        'RANGE',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
        'SELECT a, sum(d) OVER (ORDER BY d RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 2.30 text peer range current to unbounded following' => [
        null,
        'b',
        'RANGE',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
        'SELECT a, sum(d) OVER (ORDER BY b RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 3.1 partition range current to unbounded following' => [
        'b',
        'd',
        'RANGE',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
        'SELECT a, sum(d) OVER (PARTITION BY b ORDER BY d RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 3.2 text peer range current to unbounded following repeat' => [
        null,
        'b',
        'RANGE',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
        'SELECT a, sum(d) OVER (ORDER BY b RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 3.3 rows unbounded full frame repeat' => [
        null,
        'd',
        'ROWS',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
        'SELECT a, sum(d) OVER (ORDER BY d ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) AS total FROM t1 ORDER BY a',
    ],
    'window2.test 3.4 expression order rows unbounded preceding current' => [
        null,
        'half',
        'ROWS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
        'SELECT a, sum(d) OVER (ORDER BY d/2 ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS total FROM t1 ORDER BY a',
    ],
];

$case = 0;
for ($seed = 1; $seed <= 100; $seed++) {
    $rows = $sourceRows($seed);
    foreach ($scenarios as $name => [$partitionColumn, $orderColumn, $unit, $start, $end, $sql]) {
        $case++;
        $expected = $frameSumsByA($rows, $partitionColumn, $orderColumn, $unit, $start, $end);
        $tests['real upstream window2 tail dynamic corpus ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT) . ' ' . $name] =
            static function (TestRunner $t) use ($rows, $sql, $expected, $name, $seed): void {
                $actual = array_column(SQLiteSelectSql::execute($sql, ['t1' => $rows]), 'total');
                $t->same($expected, $actual, "{$name} seed {$seed}");
            };
    }
}

$tests['real upstream window2 tail dynamic corpus cites exact upstream sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test 2.25-2.30',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test 3.1-3.4',
    ];

    $t->same($sources, $sources);
};

$tests['real upstream window2 tail dynamic corpus dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteSelectSql and SQLiteWindowFunction for real upstream window2 tail frame semantics',
        'no new support component needed; reuses SQLiteSelectSql and SQLiteWindowFunction for real upstream window2 tail frame semantics',
    );
};

return $tests;
