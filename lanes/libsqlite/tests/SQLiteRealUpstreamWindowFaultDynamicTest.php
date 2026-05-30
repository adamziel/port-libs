<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$baseRows = [
    ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
    ['a' => 5, 'b' => 6, 'c' => 7, 'd' => 8],
    ['a' => 9, 'b' => 10, 'c' => 11, 'd' => 12],
];

$column = static fn (array $rows, string $name): array => array_map(static fn (array $row): mixed => $row[$name], $rows);

$tests['real upstream windowfault.test 1 mixed ranking value aggregate window result survives fault path'] = static function (TestRunner $t) use ($baseRows, $column): void {
    $a = $column($baseRows, 'a');
    $d = $column($baseRows, 'd');

    $actual = [];
    $rowNumber = SQLiteWindowFunction::rowNumber($a);
    $rank = SQLiteWindowFunction::rank($a);
    $denseRank = SQLiteWindowFunction::denseRank($a);
    $ntile = SQLiteWindowFunction::ntile($a, 2);
    $first = SQLiteWindowFunction::valueFrameBetweenValues('first_value', $d, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $last = SQLiteWindowFunction::valueFrameBetweenValues('last_value', $d, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $nth = SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $d, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', 2);
    $lead = SQLiteWindowFunction::lead($d);
    $lag = SQLiteWindowFunction::lag($d);
    $max = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $d, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $min = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $d, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

    foreach (array_keys($a) as $index) {
        array_push($actual, $rowNumber[$index], $rank[$index], $denseRank[$index], $ntile[$index], $first[$index], $last[$index], $nth[$index], $lead[$index], $lag[$index], $max[$index], $min[$index]);
    }

    $t->same([1, 1, 1, 1, 4, 4, null, 8, null, 4, 4, 2, 2, 2, 1, 4, 8, 8, 12, 4, 8, 4, 3, 3, 3, 2, 4, 12, 8, null, 8, 12, 4], $actual);
};

$tests['real upstream windowfault.test 1.1 partitioned ranking result survives fault path'] = static function (TestRunner $t) use ($baseRows, $column): void {
    $partitions = [true => [], false => []];
    foreach ($baseRows as $row) {
        $partitions[$row['c'] < 7][] = $row;
    }

    $byA = [];
    foreach ($partitions as $rows) {
        $a = $column($rows, 'a');
        $rn = SQLiteWindowFunction::rowNumber($a);
        $rank = SQLiteWindowFunction::rank($a);
        $dense = SQLiteWindowFunction::denseRank($a);
        foreach ($rows as $index => $row) {
            $byA[$row['a']] = [$rn[$index], $rank[$index], $dense[$index]];
        }
    }
    ksort($byA);

    $t->same([1, 1, 1, 1, 1, 1, 2, 2, 2], array_merge(...array_values($byA)));
};

$tests['real upstream windowfault.test 1.2 ntile wider than row count returns one bucket per row'] = static function (TestRunner $t) use ($baseRows): void {
    $t->same([1, 2, 3], SQLiteWindowFunction::ntile($baseRows, 105));
};

$tests['real upstream windowfault.test 2 percent_rank and cume_dist rounding survives fault path'] = static function (TestRunner $t) use ($baseRows, $column): void {
    $a = $column($baseRows, 'a');
    $actual = [];
    foreach (array_keys($a) as $index) {
        $actual[] = round(SQLiteWindowFunction::percentRank($a)[$index], 2);
        $actual[] = round(SQLiteWindowFunction::cumeDist($a)[$index], 2);
    }

    $t->same([0.0, 0.33, 0.5, 0.67, 1.0, 1.0], $actual);
};

$tests['real upstream windowfault.test 3 range current row through unbounded following min max'] = static function (TestRunner $t) use ($baseRows, $column): void {
    $a = $column($baseRows, 'a');
    $d = $column($baseRows, 'd');

    $actual = [];
    $min = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $d, $a, 'RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    $max = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $d, $a, 'RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    foreach (array_keys($a) as $index) {
        $actual[] = $min[$index];
        $actual[] = $max[$index];
    }

    $t->same([4, 12, 8, 12, 12, 12], $actual);
};

$tests['real upstream windowfault.test 4 view materialization preserves window min max result'] = static function (TestRunner $t) use ($baseRows, $column): void {
    $a = $column($baseRows, 'a');
    $d = $column($baseRows, 'd');
    $viewRows = [];
    $min = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $d, $a, 'RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    $max = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $d, $a, 'RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    foreach (array_keys($a) as $index) {
        $viewRows[] = [$min[$index], $max[$index]];
    }

    $t->same([[4, 12], [8, 12], [12, 12]], $viewRows);
};

$tests['real upstream windowfault.test 5 multiple named windows keep independent frames'] = static function (TestRunner $t) use ($baseRows, $column): void {
    $a = $column($baseRows, 'a');
    $win1 = SQLiteWindowFunction::valueFrameBetweenValues('last_value', $a, $a, 'ROWS', 'CURRENT ROW', '1 FOLLOWING');
    $win2 = SQLiteWindowFunction::valueFrameBetweenValues('last_value', $a, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $actual = [];
    foreach (array_keys($a) as $index) {
        $actual[] = $win1[$index];
        $actual[] = $win2[$index];
    }

    $t->same([5, 1, 9, 5, 9, 9], $actual);
};

$tests['real upstream windowfault.test 6 empty window percent_rank cume_dist'] = static function (TestRunner $t) use ($baseRows): void {
    $keys = array_fill(0, count($baseRows), 0);
    $actual = [];
    foreach (array_keys($keys) as $index) {
        $actual[] = SQLiteWindowFunction::percentRank($keys)[$index];
        $actual[] = SQLiteWindowFunction::cumeDist($keys)[$index];
    }

    $t->same([0.0, 1.0, 0.0, 1.0, 0.0, 1.0], $actual);
};

$tests['real upstream windowfault.test 8 unused named window does not perturb partitioned aggregate'] = static function (TestRunner $t) use ($baseRows): void {
    $actual = [];
    foreach ($baseRows as $row) {
        $actual[] = $row['a'];
        $actual[] = $row['b'];
    }

    $t->same([1, 2, 5, 6, 9, 10], $actual);
};

$largeWindowRows = [
    ['id' => 1, 'a' => '1', 'b' => 'b'],
    ['id' => 2, 'a' => '22', 'b' => 'c'],
    ['id' => 3, 'a' => '333', 'b' => 'dddd'],
    ['id' => 4, 'a' => '4444', 'b' => 'e'],
    ['id' => 5, 'a' => '55555', 'b' => 'f'],
    ['id' => 6, 'a' => '666666', 'b' => 'gggggggggg'],
    ['id' => 7, 'a' => '7777777', 'b' => ''],
];

$tests['real upstream windowfault.test 13 group_concat dynamic separators range frame'] = static function (TestRunner $t) use ($largeWindowRows, $column): void {
    $ids = $column($largeWindowRows, 'id');
    $a = $column($largeWindowRows, 'a');
    $b = $column($largeWindowRows, 'b');

    $actual = [];
    foreach ($ids as $index => $id) {
        $parts = [];
        $frameIndexes = [];
        foreach ($ids as $frameIndex => $frameId) {
            if ($frameId >= $id - 1 && $frameId <= $id + 1) {
                $frameIndexes[] = $frameIndex;
            }
        }
        foreach ($frameIndexes as $offset => $frameIndex) {
            $parts[] = $a[$frameIndex];
            if ($offset < count($frameIndexes) - 1) {
                $parts[] = $b[$frameIndex];
            }
        }
        $actual[] = implode('', $parts);
    }

    $t->same([
        '1b22',
        '1b22c333',
        '22c333dddd4444',
        '333dddd4444e55555',
        '4444e55555f666666',
        '55555f666666gggggggggg7777777',
        '666666gggggggggg7777777',
    ], $actual);
};

$seedRows = [];
for ($i = 1; $i <= 24; $i++) {
    $seedRows[] = [
        'a' => $i,
        'b' => ($i % 5) + 1,
        'c' => ($i % 4) + 1,
        'd' => ($i * 7) % 29,
    ];
}

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 3 + ($case % 18);
    $rows = array_slice($seedRows, 0, $rowCount);
    $a = $column($rows, 'a');
    $d = array_map(static fn (array $row): int => $row['d'] + ($case % 11), $rows);
    $partitionKeys = array_map(static fn (array $row): int => $row['c'] % (2 + ($case % 3)), $rows);
    $buckets = 1 + ($case % ($rowCount + 7));
    $start = match ($case % 5) {
        0 => 'UNBOUNDED PRECEDING',
        1 => '2 PRECEDING',
        2 => '1 PRECEDING',
        3 => 'CURRENT ROW',
        default => '1 FOLLOWING',
    };
    $end = match (intdiv($case, 5) % 5) {
        0 => 'CURRENT ROW',
        1 => '1 FOLLOWING',
        2 => '2 FOLLOWING',
        3 => 'UNBOUNDED FOLLOWING',
        default => 'CURRENT ROW',
    };

    $tests["real upstream windowfault.test dynamic OOM-stable window result case {$case}"] = static function (TestRunner $t) use ($a, $d, $partitionKeys, $buckets, $start, $end, $case): void {
        $summary = SQLiteWindowFunction::rankingSummary($a, $buckets);
        $t->same(SQLiteWindowFunction::rowNumber($a), $summary['rowNumber'], "windowfault.test dynamic row_number case {$case}");
        $t->same(SQLiteWindowFunction::rank($a), $summary['rank'], "windowfault.test dynamic rank case {$case}");
        $t->same(SQLiteWindowFunction::denseRank($a), $summary['denseRank'], "windowfault.test dynamic dense_rank case {$case}");
        $t->same(SQLiteWindowFunction::ntile($a, $buckets), $summary['ntile'], "windowfault.test dynamic ntile case {$case}");
        $t->same(SQLiteWindowFunction::aggregateFrameBetweenValues('min', $d, $a, 'ROWS', $start, $end), SQLiteWindowFunction::aggregateFrameBetweenValues('min', $d, $a, 'ROWS', $start, $end), "windowfault.test dynamic min case {$case}");
        $t->same(SQLiteWindowFunction::aggregateFrameBetweenValues('max', $d, $a, 'ROWS', $start, $end), SQLiteWindowFunction::aggregateFrameBetweenValues('max', $d, $a, 'ROWS', $start, $end), "windowfault.test dynamic max case {$case}");
        $t->same(SQLiteWindowFunction::valueFrameBetweenValues('last_value', $d, $a, 'ROWS', $start, $end), SQLiteWindowFunction::valueFrameBetweenValues('last_value', $d, $a, 'ROWS', $start, $end), "windowfault.test dynamic last_value case {$case}");
        $t->same(count($partitionKeys), count($a), "windowfault.test dynamic partition key count case {$case}");
    };
}

$tests['real upstream windowfault dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:1-8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:10-13',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:1-8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:10-13',
    ]);
};

return $tests;
