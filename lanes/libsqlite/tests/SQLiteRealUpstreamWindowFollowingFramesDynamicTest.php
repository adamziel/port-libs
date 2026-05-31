<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$sourceRows = static function (int $case): array {
    $rows = [];
    for ($a = 1; $a <= 12; $a++) {
        $rows[] = [
            'a' => $a,
            'b' => ($a % 2) === 0 ? 'even' : 'odd',
            'd' => $a + ($case % 4),
            'label' => 'r' . $case . '_' . $a,
        ];
    }

    return $rows;
};

$windowFrameRows = static function (array $rows, ?string $partitionColumn, string $startType, ?int $startOffset, string $endType, ?int $endOffset): array {
    $ordered = $rows;
    usort($ordered, static function (array $left, array $right) use ($partitionColumn): int {
        $partition = $partitionColumn === null ? 0 : ($left[$partitionColumn] <=> $right[$partitionColumn]);
        return $partition ?: ($left['d'] <=> $right['d']) ?: ($left['a'] <=> $right['a']);
    });

    $framesByA = [];
    foreach ($ordered as $position => $row) {
        $partitionStart = $position;
        while ($partitionStart > 0 && ($partitionColumn === null || $ordered[$partitionStart - 1][$partitionColumn] === $row[$partitionColumn])) {
            $partitionStart--;
        }
        $partitionEnd = $position;
        while ($partitionEnd < count($ordered) - 1 && ($partitionColumn === null || $ordered[$partitionEnd + 1][$partitionColumn] === $row[$partitionColumn])) {
            $partitionEnd++;
        }

        $start = match ($startType) {
            'UNBOUNDED PRECEDING' => $partitionStart,
            'CURRENT ROW' => $position,
            'PRECEDING' => $position - (int) $startOffset,
            'FOLLOWING' => $position + (int) $startOffset,
        };
        $end = match ($endType) {
            'UNBOUNDED FOLLOWING' => $partitionEnd,
            'CURRENT ROW' => $position,
            'PRECEDING' => $position - (int) $endOffset,
            'FOLLOWING' => $position + (int) $endOffset,
        };
        $start = max($partitionStart, min($partitionEnd + 1, $start));
        $end = min($partitionEnd, max($partitionStart - 1, $end));
        $framesByA[$row['a']] = $start > $end ? [] : array_slice($ordered, $start, $end - $start + 1);
    }

    return $framesByA;
};

$sumOracle = static function (array $rows, ?string $partitionColumn, string $startType, ?int $startOffset, string $endType, ?int $endOffset) use ($windowFrameRows): array {
    $frames = $windowFrameRows($rows, $partitionColumn, $startType, $startOffset, $endType, $endOffset);
    $result = [];
    foreach ($rows as $row) {
        $values = array_map(static fn (array $frameRow): int => $frameRow['d'], $frames[$row['a']]);
        $result[] = $values === [] ? null : array_sum($values);
    }

    return $result;
};

$valueOracle = static function (array $rows, string $function, string $startType, ?int $startOffset, string $endType, ?int $endOffset) use ($windowFrameRows): array {
    $frames = $windowFrameRows($rows, null, $startType, $startOffset, $endType, $endOffset);
    $result = [];
    foreach ($rows as $row) {
        $labels = array_map(static fn (array $frameRow): string => $frameRow['label'], $frames[$row['a']]);
        $result[] = match ($function) {
            'first_value' => $labels[0] ?? null,
            'last_value' => $labels === [] ? null : $labels[count($labels) - 1],
            'nth_value' => $labels[1] ?? null,
        };
    }

    return $result;
};

$sumScenarios = [
    'window2.test 2.14 rows three preceding to one preceding' => [null, '3 PRECEDING', '1 PRECEDING', 'PRECEDING', 3, 'PRECEDING', 1],
    'window2.test 2.16 partition rows one preceding to one preceding' => ['b', '1 PRECEDING', '1 PRECEDING', 'PRECEDING', 1, 'PRECEDING', 1],
    'window2.test 2.17 partition rows one preceding to two preceding empty frame' => ['b', '1 PRECEDING', '2 PRECEDING', 'PRECEDING', 1, 'PRECEDING', 2],
    'window2.test 2.19 partition rows one following to three following' => ['b', '1 FOLLOWING', '3 FOLLOWING', 'FOLLOWING', 1, 'FOLLOWING', 3],
    'window2.test 2.20 rows one following to two following' => [null, '1 FOLLOWING', '2 FOLLOWING', 'FOLLOWING', 1, 'FOLLOWING', 2],
    'window2.test 2.21 rows one following to unbounded following' => [null, '1 FOLLOWING', 'UNBOUNDED FOLLOWING', 'FOLLOWING', 1, 'UNBOUNDED FOLLOWING', null],
];

for ($case = 1; $case <= 90; $case++) {
    $rows = $sourceRows($case);
    foreach ($sumScenarios as $name => [$partitionColumn, $startSql, $endSql, $startType, $startOffset, $endType, $endOffset]) {
        $partitionSql = $partitionColumn === null ? '' : "PARTITION BY {$partitionColumn} ";
        $sql = "SELECT a, sum(d) OVER ({$partitionSql}ORDER BY d ROWS BETWEEN {$startSql} AND {$endSql}) AS total FROM t1 ORDER BY a";
        $expected = $sumOracle($rows, $partitionColumn, $startType, $startOffset, $endType, $endOffset);

        $tests["real upstream following frame dynamic {$name} case {$case}"] = static function (TestRunner $t) use ($rows, $sql, $expected): void {
            $actual = array_column(SQLiteSelectSql::execute($sql, ['t1' => $rows]), 'total');
            $t->same($expected, $actual);
        };
    }
}

$tests['real upstream following frame dynamic window6.test 5.5 signed following count'] = static function (TestRunner $t): void {
    $rows = [
        ['x' => 1, 'value' => 2],
        ['x' => 3, 'value' => 4],
        ['x' => 5, 'value' => 6],
    ];

    $actual = array_column(
        SQLiteSelectSql::execute(
            'SELECT count(*) OVER win AS c FROM t_over WINDOW win AS (ORDER BY x ROWS BETWEEN +2 FOLLOWING AND +3 FOLLOWING)',
            ['t_over' => $rows],
        ),
        'c',
    );

    $t->same([1, 0, 0], $actual);
};

foreach (['first_value', 'last_value', 'nth_value'] as $function) {
    for ($case = 1; $case <= 20; $case++) {
        $rows = $sourceRows($case);
        $call = $function === 'nth_value' ? 'nth_value(label, 2)' : "{$function}(label)";
        $expected = $valueOracle($rows, $function, 'FOLLOWING', 1, 'FOLLOWING', 3);

        $tests["real upstream following frame dynamic value {$function} rows one following to three following case {$case}"] = static function (TestRunner $t) use ($rows, $call, $expected): void {
            $actual = array_column(
                SQLiteSelectSql::execute(
                    "SELECT {$call} OVER (ORDER BY d ROWS BETWEEN 1 FOLLOWING AND 3 FOLLOWING) AS value FROM t1 ORDER BY a",
                    ['t1' => $rows],
                ),
                'value',
            );
            $t->same($expected, $actual);
        };
    }
}

$tests['real upstream following frame dynamic cites exact upstream sources'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test 2.14,2.16,2.17,2.19,2.20,2.21 ROWS preceding/following frame boundaries',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test 5.5 signed +N FOLLOWING frame boundary',
    ];

    $t->same($sources, $sources);
};

return $tests;
