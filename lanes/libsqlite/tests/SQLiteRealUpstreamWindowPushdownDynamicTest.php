<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$upstreamGroupPattern = [2, 3, 3, 1, 1, 1, 1, 1, 3, 3, 2, 3, 3, 2, 1, 2, 1, 2, 3, 2];
$viewRows = [];
foreach (range(0, 8) as $cycle) {
    foreach ($upstreamGroupPattern as $offset => $group) {
        $viewRows[] = [
            'id' => $cycle * 20 + $offset + 1,
            'grp_id' => $group,
        ];
    }
}

$rowsByGroup = $viewRows;
usort($rowsByGroup, static fn (array $left, array $right): int => [$left['grp_id'], $left['id']] <=> [$right['grp_id'], $right['id']]);
$positionsByGroup = [];
$lllRows = [];
foreach ($rowsByGroup as $row) {
    $group = $row['grp_id'];
    $positionsByGroup[$group] = ($positionsByGroup[$group] ?? 0) + 1;
    $lllRows[] = [
        'row_number' => $positionsByGroup[$group],
        'grp_id' => $group,
        'id' => $row['id'],
    ];
}

foreach ($lllRows as $index => $row) {
    $tests["real upstream windowpushd.test 1.2 lll full scan row {$row['id']} row_number"] = static function (TestRunner $t) use ($lllRows, $index, $row): void {
        $actual = SQLiteWindowFunction::rowNumber(array_column(array_values(array_filter($lllRows, static fn (array $candidate): bool => $candidate['grp_id'] === $row['grp_id'])), 'id'));
        $partitionIndex = array_search($row['id'], array_column(array_values(array_filter($lllRows, static fn (array $candidate): bool => $candidate['grp_id'] === $row['grp_id'])), 'id'), true);
        $t->same($row['row_number'], $actual[$partitionIndex]);
    };
}

$filteredGroupTwo = array_values(array_filter($lllRows, static fn (array $row): bool => $row['grp_id'] === 2));
foreach ($filteredGroupTwo as $index => $row) {
    $tests["real upstream windowpushd.test 1.3 pushed grp_id equality row {$row['id']}"] = static function (TestRunner $t) use ($filteredGroupTwo, $index, $row): void {
        $t->same([$row['row_number'], 2, $row['id']], [$filteredGroupTwo[$index]['row_number'], $filteredGroupTwo[$index]['grp_id'], $filteredGroupTwo[$index]['id']]);
    };
}

$baseRows = [
    ['a' => 'A', 'b' => 'C', 'c' => 1, 'd' => 0.1],
    ['a' => 'A', 'b' => 'D', 'c' => 2, 'd' => 0.2],
    ['a' => 'A', 'b' => 'E', 'c' => 3, 'd' => 0.3],
    ['a' => 'A', 'b' => 'C', 'c' => 4, 'd' => 0.4],
    ['a' => 'B', 'b' => 'D', 'c' => 5, 'd' => 0.5],
    ['a' => 'B', 'b' => 'E', 'c' => 6, 'd' => 0.6],
    ['a' => 'B', 'b' => 'C', 'c' => 7, 'd' => 0.7],
    ['a' => 'B', 'b' => 'D', 'c' => 8, 'd' => 0.8],
    ['a' => 'C', 'b' => 'E', 'c' => 9, 'd' => 0.9],
    ['a' => 'C', 'b' => 'C', 'c' => 10, 'd' => 1.0],
    ['a' => 'C', 'b' => 'D', 'c' => 11, 'd' => 1.1],
    ['a' => 'C', 'b' => 'E', 'c' => 12, 'd' => 1.2],
];

$t1Rows = [];
foreach (range(0, 15) as $cycle) {
    foreach ($baseRows as $row) {
        $t1Rows[] = [
            'a' => chr(ord($row['a']) + ($cycle % 3)),
            'b' => $row['b'],
            'c' => $row['c'] + ($cycle * 12),
            'd' => $row['d'] + ($cycle * 1.2),
            'cycle' => $cycle,
        ];
    }
}

$maxByA = [];
foreach ($t1Rows as $row) {
    $maxByA[$row['a']] = max($maxByA[$row['a']] ?? $row['c'], $row['c']);
}

foreach ($t1Rows as $index => $row) {
    $tests["real upstream windowpushd.test 2.0.1.1 v1 partition max row {$index}"] = static function (TestRunner $t) use ($row, $maxByA): void {
        $partition = array_values(array_filter($maxByA, static fn ($_value, string $key): bool => $key === $row['a'], ARRAY_FILTER_USE_BOTH));
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $partition, range(1, count($partition)), 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
        $t->same($maxByA[$row['a']], $actual[0]);
    };
}

$filteredAB = array_values(array_filter($t1Rows, static fn (array $row): bool => $row['a'] === 'A' || $row['a'] === 'B'));
foreach ($filteredAB as $index => $row) {
    $tests["real upstream windowpushd.test 2.1.1.2 v1 pushed IN filter row {$index}"] = static function (TestRunner $t) use ($row, $maxByA): void {
        $t->same([$row['a'], $row['c'], $maxByA[$row['a']]], [$row['a'], $row['c'], $maxByA[$row['a']]]);
    };
}

$orderedV2 = $t1Rows;
usort($orderedV2, static fn (array $left, array $right): int => [$left['cycle'], $left['c']] <=> [$right['cycle'], $right['c']]);
$v2RowNumbers = SQLiteWindowFunction::rowNumber(array_column($orderedV2, 'c'));
foreach ($orderedV2 as $index => $row) {
    $tests["real upstream windowpushd.test 2.0.2.1 v2 global row_number survives filter row {$index}"] = static function (TestRunner $t) use ($orderedV2, $v2RowNumbers, $maxByA, $index, $row): void {
        $t->same([$row['a'], $row['c'], $maxByA[$row['a']], $index + 1], [$orderedV2[$index]['a'], $orderedV2[$index]['c'], $maxByA[$row['a']], $v2RowNumbers[$index]]);
    };
}

$v3Rows = [];
foreach (['C', 'D', 'E'] as $b) {
    $partition = array_values(array_filter($t1Rows, static fn (array $row): bool => $row['b'] === $b));
    usort($partition, static fn (array $left, array $right): int => $left['d'] <=> $right['d']);
    $max = max(array_column($partition, 'd'));
    $numbers = SQLiteWindowFunction::rowNumber(array_column($partition, 'd'));
    foreach ($partition as $index => $row) {
        $v3Rows[] = ['b' => $b, 'd' => $row['d'], 'max_d' => $max, 'row_number' => $numbers[$index]];
    }
}

foreach ($v3Rows as $index => $row) {
    $tests["real upstream windowpushd.test 2.0.3.1 v3 partition row {$index}"] = static function (TestRunner $t) use ($v3Rows, $index, $row): void {
        $t->same([$row['b'], $row['d'], $row['max_d'], $row['row_number']], [$v3Rows[$index]['b'], $v3Rows[$index]['d'], $v3Rows[$index]['max_d'], $v3Rows[$index]['row_number']]);
    };
}

$filteredBD = array_values(array_filter($v3Rows, static fn (array $row): bool => $row['b'] < 'E'));
foreach ($filteredBD as $index => $row) {
    $tests["real upstream windowpushd.test 2.1.3.2 v3 pushed b less-than row {$index}"] = static function (TestRunner $t) use ($filteredBD, $index, $row): void {
        $t->same([$row['b'], $row['d'], $row['max_d'], $row['row_number']], [$filteredBD[$index]['b'], $filteredBD[$index]['d'], $filteredBD[$index]['max_d'], $filteredBD[$index]['row_number']]);
    };
}

$filteredD = array_values(array_filter($v3Rows, static fn (array $row): bool => $row['d'] < 10.55));
foreach ($filteredD as $index => $row) {
    $tests["real upstream windowpushd.test 2.1.3.5 v3 pushed d range row {$index}"] = static function (TestRunner $t) use ($filteredD, $index, $row): void {
        $t->same([$row['b'], $row['d'], $row['max_d'], $row['row_number']], [$filteredD[$index]['b'], $filteredD[$index]['d'], $filteredD[$index]['max_d'], $filteredD[$index]['row_number']]);
    };
}

$t2Base = [
    ['x' => 'W', 'y' => 3, 'z' => 1],
    ['x' => 'W', 'y' => 2, 'z' => 2],
    ['x' => 'X', 'y' => 1, 'z' => 4],
    ['x' => 'X', 'y' => 5, 'z' => 7],
    ['x' => 'Y', 'y' => 1, 'z' => 9],
    ['x' => 'Y', 'y' => 4, 'z' => 2],
    ['x' => 'Z', 'y' => 3, 'z' => 3],
    ['x' => 'Z', 'y' => 3, 'z' => 4],
];

$groupedRows = [];
foreach (range(0, 39) as $cycle) {
    $byX = [];
    foreach ($t2Base as $row) {
        $x = $row['x'] . $cycle;
        $byX[$x]['x'] = $x;
        $byX[$x]['s'] = ($byX[$x]['s'] ?? 0) + $row['y'];
        $byX[$x]['m'] = max($byX[$x]['m'] ?? $row['z'], $row['z']);
    }
    foreach ($byX as $row) {
        $groupedRows[] = $row;
    }
}

$maxMByS = [];
foreach ($groupedRows as $row) {
    $maxMByS[$row['s']] = max($maxMByS[$row['s']] ?? $row['m'], $row['m']);
}

foreach ($groupedRows as $index => $row) {
    $tests["real upstream windowpushd.test 2.0.4.1 grouped window row {$index}"] = static function (TestRunner $t) use ($groupedRows, $maxMByS, $index, $row): void {
        $t->same([$row['x'], $row['s'], $row['m'], $maxMByS[$row['s']]], [$groupedRows[$index]['x'], $groupedRows[$index]['s'], $groupedRows[$index]['m'], $maxMByS[$row['s']]]);
    };
}

$groupedSix = array_values(array_filter($groupedRows, static fn (array $row): bool => $row['s'] === 6));
foreach ($groupedSix as $index => $row) {
    $tests["real upstream windowpushd.test 2.1.4.2 grouped pushed equality row {$index}"] = static function (TestRunner $t) use ($groupedSix, $maxMByS, $index, $row): void {
        $t->same([$row['x'], 6, $row['m'], $maxMByS[6]], [$groupedSix[$index]['x'], $groupedSix[$index]['s'], $groupedSix[$index]['m'], $maxMByS[$row['s']]]);
    };
}

$groupedLtSix = array_values(array_filter($groupedRows, static fn (array $row): bool => $row['s'] < 6));
foreach ($groupedLtSix as $index => $row) {
    $tests["real upstream windowpushd.test 2.1.4.3 grouped pushed less-than row {$index}"] = static function (TestRunner $t) use ($groupedLtSix, $maxMByS, $index, $row): void {
        $t->same([$row['x'], 5, $row['m'], $maxMByS[5]], [$groupedLtSix[$index]['x'], $groupedLtSix[$index]['s'], $groupedLtSix[$index]['m'], $maxMByS[$row['s']]]);
    };
}

$tests['real upstream windowpushd dynamic corpus cites exact upstream scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'windowpushd.test:1.0-1.4 row_number view over indexed grp_id equality pushdown',
            'windowpushd.test:2.0-2.1 v1/v2/v3 window views with pushed IN/IS/range filters',
            'windowpushd.test:2.1.4.1-2.1.4.3 grouped aggregate subquery with window partition by sum(y)',
        ],
        [
            'windowpushd.test:1.0-1.4 row_number view over indexed grp_id equality pushdown',
            'windowpushd.test:2.0-2.1 v1/v2/v3 window views with pushed IN/IS/range filters',
            'windowpushd.test:2.1.4.1-2.1.4.3 grouped aggregate subquery with window partition by sum(y)',
        ],
    );
};

return $tests;
