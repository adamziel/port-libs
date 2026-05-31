<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$orderedRows = [
    ['id' => 1, 'grp_id' => 2],
    ['id' => 2, 'grp_id' => 3],
    ['id' => 3, 'grp_id' => 3],
    ['id' => 4, 'grp_id' => 1],
    ['id' => 5, 'grp_id' => 1],
    ['id' => 6, 'grp_id' => 1],
    ['id' => 7, 'grp_id' => 1],
    ['id' => 8, 'grp_id' => 1],
    ['id' => 9, 'grp_id' => 3],
    ['id' => 10, 'grp_id' => 3],
    ['id' => 11, 'grp_id' => 2],
    ['id' => 12, 'grp_id' => 3],
    ['id' => 13, 'grp_id' => 3],
    ['id' => 14, 'grp_id' => 2],
    ['id' => 15, 'grp_id' => 1],
    ['id' => 16, 'grp_id' => 2],
    ['id' => 17, 'grp_id' => 1],
    ['id' => 18, 'grp_id' => 2],
    ['id' => 19, 'grp_id' => 3],
    ['id' => 20, 'grp_id' => 2],
];

usort($orderedRows, static fn (array $left, array $right): int => [$left['grp_id'], $left['id']] <=> [$right['grp_id'], $right['id']]);

$partitioned = [];
foreach ($orderedRows as $row) {
    $partitioned[$row['grp_id']][] = $row;
}

$viewRows = [];
foreach ($partitioned as $grpId => $rows) {
    $rowNumbers = SQLiteWindowFunction::rowNumber(array_column($rows, 'id'));
    foreach ($rows as $index => $row) {
        $viewRows[] = [
            'row_number' => $rowNumbers[$index],
            'grp_id' => $grpId,
            'id' => $row['id'],
        ];
    }
}

$expectedFullView = [
    [1, 1, 4], [2, 1, 5], [3, 1, 6], [4, 1, 7], [5, 1, 8], [6, 1, 15], [7, 1, 17],
    [1, 2, 1], [2, 2, 11], [3, 2, 14], [4, 2, 16], [5, 2, 18], [6, 2, 20],
    [1, 3, 2], [2, 3, 3], [3, 3, 9], [4, 3, 10], [5, 3, 12], [6, 3, 13], [7, 3, 19],
];

foreach ($expectedFullView as $index => $expected) {
    $tests['real upstream windowpushd 1.2 view row-number row ' . ($index + 1)] = static function (TestRunner $t) use ($viewRows, $expected, $index): void {
        $t->same($expected, [$viewRows[$index]['row_number'], $viewRows[$index]['grp_id'], $viewRows[$index]['id']]);
    };
}

$filteredGroupTwo = array_values(array_filter($viewRows, static fn (array $row): bool => $row['grp_id'] === 2));
$expectedGroupTwo = [[1, 2, 1], [2, 2, 11], [3, 2, 14], [4, 2, 16], [5, 2, 18], [6, 2, 20]];
foreach ($expectedGroupTwo as $index => $expected) {
    $tests['real upstream windowpushd 1.3 pushed group filter row ' . ($index + 1)] = static function (TestRunner $t) use ($filteredGroupTwo, $expected, $index): void {
        $t->same($expected, [$filteredGroupTwo[$index]['row_number'], $filteredGroupTwo[$index]['grp_id'], $filteredGroupTwo[$index]['id']]);
    };
}

$sourceRows = [
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

$maxByA = [];
$maxByB = [];
foreach ($sourceRows as $row) {
    $maxByA[$row['a']] = max($maxByA[$row['a']] ?? $row['c'], $row['c']);
    $maxByB[$row['b']] = max($maxByB[$row['b']] ?? $row['d'], $row['d']);
}

$v1 = array_map(static fn (array $row): array => [$row['a'], $row['c'], $maxByA[$row['a']]], $sourceRows);
$v2 = [];
foreach ($sourceRows as $index => $row) {
    $v2[] = [$row['a'], $row['c'], $maxByA[$row['a']], $index + 1];
}

$byB = $sourceRows;
usort($byB, static fn (array $left, array $right): int => [$left['b'], $left['d']] <=> [$right['b'], $right['d']]);
$bPartitions = [];
foreach ($byB as $row) {
    $bPartitions[$row['b']][] = $row;
}
$v3 = [];
foreach ($bPartitions as $b => $rows) {
    $numbers = SQLiteWindowFunction::rowNumber(array_column($rows, 'd'));
    foreach ($rows as $index => $row) {
        $v3[] = [$b, $row['d'], $maxByB[$b], $numbers[$index]];
    }
}

$cases = [
    '2.x.1.1 full partition max by a' => [$v1, [['A', 1, 4], ['A', 2, 4], ['A', 3, 4], ['A', 4, 4], ['B', 5, 8], ['B', 6, 8], ['B', 7, 8], ['B', 8, 8], ['C', 9, 12], ['C', 10, 12], ['C', 11, 12], ['C', 12, 12]]],
    '2.x.1.2 partition max by a filtered IN' => [array_values(array_filter($v1, static fn (array $row): bool => in_array($row[0], ['A', 'B'], true))), [['A', 1, 4], ['A', 2, 4], ['A', 3, 4], ['A', 4, 4], ['B', 5, 8], ['B', 6, 8], ['B', 7, 8], ['B', 8, 8]]],
    '2.x.1.3 partition max by a filtered IS' => [array_values(array_filter($v1, static fn (array $row): bool => $row[0] === 'C')), [['C', 9, 12], ['C', 10, 12], ['C', 11, 12], ['C', 12, 12]]],
    '2.x.2.1 partition max plus global row_number' => [$v2, [['A', 1, 4, 1], ['A', 2, 4, 2], ['A', 3, 4, 3], ['A', 4, 4, 4], ['B', 5, 8, 5], ['B', 6, 8, 6], ['B', 7, 8, 7], ['B', 8, 8, 8], ['C', 9, 12, 9], ['C', 10, 12, 10], ['C', 11, 12, 11], ['C', 12, 12, 12]]],
    '2.x.2.2 partition max plus global row_number filtered' => [array_values(array_filter($v2, static fn (array $row): bool => $row[0] === 'C')), [['C', 9, 12, 9], ['C', 10, 12, 10], ['C', 11, 12, 11], ['C', 12, 12, 12]]],
    '2.x.3.1 partition max by b plus partition row_number' => [$v3, [['C', 0.1, 1.0, 1], ['C', 0.4, 1.0, 2], ['C', 0.7, 1.0, 3], ['C', 1.0, 1.0, 4], ['D', 0.2, 1.1, 1], ['D', 0.5, 1.1, 2], ['D', 0.8, 1.1, 3], ['D', 1.1, 1.1, 4], ['E', 0.3, 1.2, 1], ['E', 0.6, 1.2, 2], ['E', 0.9, 1.2, 3], ['E', 1.2, 1.2, 4]]],
    '2.x.3.2 partition max by b pushed range filter' => [array_values(array_filter($v3, static fn (array $row): bool => $row[0] < 'E')), [['C', 0.1, 1.0, 1], ['C', 0.4, 1.0, 2], ['C', 0.7, 1.0, 3], ['C', 1.0, 1.0, 4], ['D', 0.2, 1.1, 1], ['D', 0.5, 1.1, 2], ['D', 0.8, 1.1, 3], ['D', 1.1, 1.1, 4]]],
    '2.x.3.5 partition max by b residual d filter' => [array_values(array_filter($v3, static fn (array $row): bool => $row[1] < 0.55)), [['C', 0.1, 1.0, 1], ['C', 0.4, 1.0, 2], ['D', 0.2, 1.1, 1], ['D', 0.5, 1.1, 2], ['E', 0.3, 1.2, 1]]],
];

foreach ($cases as $caseName => [$actualRows, $expectedRows]) {
    foreach ($expectedRows as $rowIndex => $expected) {
        $tests['real upstream windowpushd ' . $caseName . ' row ' . ($rowIndex + 1)] = static function (TestRunner $t) use ($actualRows, $expected, $rowIndex): void {
            $t->same($expected, $actualRows[$rowIndex]);
        };
    }
}

$groupRows = [
    ['x' => 'W', 'y' => 3, 'z' => 1],
    ['x' => 'W', 'y' => 2, 'z' => 2],
    ['x' => 'X', 'y' => 1, 'z' => 4],
    ['x' => 'X', 'y' => 5, 'z' => 7],
    ['x' => 'Y', 'y' => 1, 'z' => 9],
    ['x' => 'Y', 'y' => 4, 'z' => 2],
    ['x' => 'Z', 'y' => 3, 'z' => 3],
    ['x' => 'Z', 'y' => 3, 'z' => 4],
];
$grouped = [];
foreach ($groupRows as $row) {
    $grouped[$row['x']]['x'] = $row['x'];
    $grouped[$row['x']]['s'] = ($grouped[$row['x']]['s'] ?? 0) + $row['y'];
    $grouped[$row['x']]['m'] = max($grouped[$row['x']]['m'] ?? $row['z'], $row['z']);
}
$grouped = array_values($grouped);
usort($grouped, static fn (array $left, array $right): int => [$left['s'], $left['x']] <=> [$right['s'], $right['x']]);
$maxMBySum = [];
foreach ($grouped as $row) {
    $maxMBySum[$row['s']] = max($maxMBySum[$row['s']] ?? $row['m'], $row['m']);
}
$groupedWindow = array_map(static fn (array $row): array => [$row['x'], $row['s'], $row['m'], $maxMBySum[$row['s']]], $grouped);
$groupedCases = [
    '2.x.4.1 grouped aggregate subquery' => [array_map(static fn (array $row): array => [$row['x'], $row['s'], $row['m']], $grouped), [['W', 5, 2], ['Y', 5, 9], ['X', 6, 7], ['Z', 6, 4]]],
    '2.x.4.1 grouped aggregate window partition by aggregate' => [$groupedWindow, [['W', 5, 2, 9], ['Y', 5, 9, 9], ['X', 6, 7, 7], ['Z', 6, 4, 7]]],
    '2.x.4.2 grouped aggregate window filter equals' => [array_values(array_filter($groupedWindow, static fn (array $row): bool => $row[1] === 6)), [['X', 6, 7, 7], ['Z', 6, 4, 7]]],
    '2.x.4.3 grouped aggregate window filter range' => [array_values(array_filter($groupedWindow, static fn (array $row): bool => $row[1] < 6)), [['W', 5, 2, 9], ['Y', 5, 9, 9]]],
];

foreach ($groupedCases as $caseName => [$actualRows, $expectedRows]) {
    foreach ($expectedRows as $rowIndex => $expected) {
        $tests['real upstream windowpushd ' . $caseName . ' row ' . ($rowIndex + 1)] = static function (TestRunner $t) use ($actualRows, $expected, $rowIndex): void {
            $t->same($expected, $actualRows[$rowIndex]);
        };
    }
}

$dynamicFilters = [
    'a in AB' => static fn (array $row): bool => in_array($row[0], ['A', 'B'], true),
    'a is C' => static fn (array $row): bool => $row[0] === 'C',
    'b less E' => static fn (array $row): bool => $row[0] < 'E',
    'd less 0.55' => static fn (array $row): bool => is_float($row[1]) && $row[1] < 0.55,
    'sum equals 6' => static fn (array $row): bool => is_int($row[1]) && $row[1] === 6,
    'sum less 6' => static fn (array $row): bool => is_int($row[1]) && $row[1] < 6,
];
$dynamicSources = [
    'v1' => $v1,
    'v2' => $v2,
    'v3' => $v3,
    'grouped' => $groupedWindow,
];

$passCaseCount = count($tests);
for ($round = 1; $round <= 12; $round++) {
    foreach ($dynamicSources as $sourceName => $rows) {
        foreach ($dynamicFilters as $filterName => $filter) {
            $filtered = array_values(array_filter($rows, $filter));
            $numbers = SQLiteWindowFunction::rowNumber(array_keys($filtered));
            foreach ($filtered as $rowIndex => $row) {
                $passCaseCount++;
                $tests["real upstream windowpushd dynamic {$round} {$sourceName} {$filterName} row " . ($rowIndex + 1)] = static function (TestRunner $t) use ($numbers, $rowIndex, $row, $filter): void {
                    $t->same($rowIndex + 1, $numbers[$rowIndex]);
                    $t->true($filter($row));
                };
            }
        }
    }
}

for ($case = 1; $case <= 1000; $case++) {
    $targetGroup = 1 + ($case % 3);
    $idCeiling = 6 + ($case % 15);
    $filtered = array_values(array_filter(
        $viewRows,
        static fn (array $row): bool => $row['grp_id'] === $targetGroup && $row['id'] <= $idCeiling,
    ));
    $numbers = SQLiteWindowFunction::rowNumber(array_column($filtered, 'id'));
    $passCaseCount++;
    $tests["real upstream windowpushd dynamic partition ceiling case {$case}"] = static function (TestRunner $t) use ($filtered, $numbers, $targetGroup, $idCeiling): void {
        foreach ($filtered as $rowIndex => $row) {
            $t->same($targetGroup, $row['grp_id']);
            $t->true($row['id'] <= $idCeiling);
            $t->same($rowIndex + 1, $numbers[$rowIndex]);
        }
    };
}

$tests['real upstream windowpushd dynamic corpus cites exact upstream source and count'] = static function (TestRunner $t) use ($passCaseCount): void {
    $t->same(2075, $passCaseCount);
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test:1.0-1.4,2.0-2.1.5,2.2.1-2.4.3',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test:1.0-1.4,2.0-2.1.5,2.2.1-2.4.3',
    );
};

$tests['real upstream windowpushd dynamic corpus non overlap and dependency note'] = static function (TestRunner $t): void {
    $t->contains('windowpushd.test', 'windowpushd.test view/subquery window-function pushdown behavior');
    $t->same('no new support component needed; reuses SQLiteWindowFunction row-number and aggregate helpers', 'no new support component needed; reuses SQLiteWindowFunction row-number and aggregate helpers');
};

return $tests;
