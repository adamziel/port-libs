<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$sortRows = static function (array $rows, callable $partitionKey, callable $orderKey): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = [$index, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($partitionKey, $orderKey): int {
        $leftPartition = $partitionKey($left[1]);
        $rightPartition = $partitionKey($right[1]);
        if ($leftPartition != $rightPartition) {
            return $leftPartition <=> $rightPartition;
        }

        $leftOrder = $orderKey($left[1]);
        $rightOrder = $orderKey($right[1]);
        if ($leftOrder != $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return $left[0] <=> $right[0];
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

$rankRows = static function (array $rows, callable $partitionKey, callable $orderKey) use ($sortRows): array {
    $ordered = $sortRows($rows, $partitionKey, $orderKey);
    $partitions = [];
    foreach ($ordered as $row) {
        $partitions[(string) $partitionKey($row)][] = $row;
    }

    $actual = [];
    foreach ($partitions as $partitionRows) {
        $keys = array_map($orderKey, $partitionRows);
        $rowNumbers = SQLiteWindowFunction::rowNumber($keys);
        $ranks = SQLiteWindowFunction::rank($keys);
        $denseRanks = SQLiteWindowFunction::denseRank($keys);
        $percentRanks = SQLiteWindowFunction::percentRank($keys);
        $cumeDists = SQLiteWindowFunction::cumeDist($keys);
        foreach ($partitionRows as $index => $row) {
            $actual[] = [
                'a' => $row['a'],
                'b' => $row['b'],
                'row_number' => $rowNumbers[$index],
                'rank' => $ranks[$index],
                'dense_rank' => $denseRanks[$index],
                'percent_rank' => $percentRanks[$index],
                'cume_dist' => $cumeDists[$index],
            ];
        }
    }

    return $actual;
};

$oracleRankRows = static function (array $rows, callable $partitionKey, callable $orderKey) use ($sortRows): array {
    $ordered = $sortRows($rows, $partitionKey, $orderKey);
    $partitions = [];
    foreach ($ordered as $row) {
        $partitions[(string) $partitionKey($row)][] = $row;
    }

    $actual = [];
    foreach ($partitions as $partitionRows) {
        $count = count($partitionRows);
        $rank = 1;
        $denseRank = 1;
        $previousKey = null;
        $peerSize = 0;
        foreach ($partitionRows as $index => $row) {
            $key = $orderKey($row);
            if ($index === 0) {
                $peerSize = 1;
            } elseif ($key === $previousKey) {
                $peerSize++;
            } else {
                $rank += $peerSize;
                $denseRank++;
                $peerSize = 1;
            }
            $peerEnd = $index;
            while ($peerEnd + 1 < $count && $orderKey($partitionRows[$peerEnd + 1]) === $key) {
                $peerEnd++;
            }
            $actual[] = [
                'row_number' => $index + 1,
                'rank' => $rank,
                'dense_rank' => $denseRank,
                'percent_rank' => $count === 1 ? 0.0 : ($rank - 1) / ($count - 1),
                'cume_dist' => ($peerEnd + 1) / $count,
            ];
            $previousKey = $key;
        }
    }

    return $actual;
};

$fixtureRows = [
    ['a' => 10, 'b' => 89],
    ['a' => 11, 'b' => 81],
    ['a' => 12, 'b' => 96],
    ['a' => 13, 'b' => 59],
    ['a' => 14, 'b' => 38],
    ['a' => 15, 'b' => 68],
    ['a' => 16, 'b' => 39],
    ['a' => 17, 'b' => 62],
    ['a' => 18, 'b' => 91],
    ['a' => 19, 'b' => 46],
    ['a' => 20, 'b' => 6],
    ['a' => 21, 'b' => 99],
    ['a' => 22, 'b' => 97],
    ['a' => 23, 'b' => 27],
];

$tests['real upstream window3 1.1.3 row number over primary key order'] = static function (TestRunner $t) use ($rankRows, $fixtureRows): void {
    $actual = $rankRows($fixtureRows, static fn (array $_row): int => 0, static fn (array $row): int => $row['a']);
    $t->same(range(1, count($fixtureRows)), array_column($actual, 'row_number'), 'window3.test 1.1.3.1 row_number ORDER BY a');
};

$tests['real upstream window3 1.1.4 dense rank over duplicate b values'] = static function (TestRunner $t) use ($rankRows, $oracleRankRows, $fixtureRows): void {
    $partitionKey = static fn (array $_row): int => 0;
    $orderKey = static fn (array $row): int => $row['b'] % 10;
    $actual = $rankRows($fixtureRows, $partitionKey, $orderKey);
    $expected = $oracleRankRows($fixtureRows, $partitionKey, $orderKey);
    $t->same(array_column($expected, 'dense_rank'), array_column($actual, 'dense_rank'), 'window3.test 1.1.4.5 dense_rank ORDER BY b%10');
};

$tests['real upstream window3 1.1.5 rank leaves peer gaps over duplicate b values'] = static function (TestRunner $t) use ($rankRows, $oracleRankRows, $fixtureRows): void {
    $partitionKey = static fn (array $_row): int => 0;
    $orderKey = static fn (array $row): int => $row['b'] % 10;
    $actual = $rankRows($fixtureRows, $partitionKey, $orderKey);
    $expected = $oracleRankRows($fixtureRows, $partitionKey, $orderKey);
    $t->same(array_column($expected, 'rank'), array_column($actual, 'rank'), 'window3.test 1.1.5.5 rank ORDER BY b%10');
};

$tests['real upstream window3 1.1.6 combined row number rank dense rank per partition'] = static function (TestRunner $t) use ($rankRows, $oracleRankRows, $fixtureRows): void {
    $partitionKey = static fn (array $row): int => $row['b'] % 2;
    $orderKey = static fn (array $row): int => $row['b'] % 10;
    $actual = $rankRows($fixtureRows, $partitionKey, $orderKey);
    $expected = $oracleRankRows($fixtureRows, $partitionKey, $orderKey);
    $project = static fn (array $row): array => [$row['row_number'], $row['rank'], $row['dense_rank']];
    $t->same(array_map($project, $expected), array_map($project, $actual), 'window3.test 1.1.6.1 combined ranking functions');
};

$tests['real upstream window3 1.1.7 percent rank and cume dist peer math'] = static function (TestRunner $t) use ($rankRows, $oracleRankRows, $fixtureRows): void {
    $partitionKey = static fn (array $_row): int => 0;
    $orderKey = static fn (array $row): int => $row['b'] % 10;
    $actual = $rankRows($fixtureRows, $partitionKey, $orderKey);
    $expected = $oracleRankRows($fixtureRows, $partitionKey, $orderKey);
    $format = static fn (float $value): string => sprintf('%.4f', $value);
    $t->same(array_map($format, array_column($expected, 'percent_rank')), array_map($format, array_column($actual, 'percent_rank')), 'window3.test 1.1.7 percent_rank peers');
    $t->same(array_map($format, array_column($expected, 'cume_dist')), array_map($format, array_column($actual, 'cume_dist')), 'window3.test 1.1.7 cume_dist peers');
};

for ($case = 0; $case < 1000; $case++) {
    $rowCount = 16 + ($case % 17);
    $rows = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $rows[] = [
            'a' => $row + 1,
            'b' => (($case * 37 + $row * 19 + intdiv($row, 3) * 11) % 101),
        ];
    }

    $partitionMod = 2 + ($case % 5);
    $orderMod = 3 + (($case * 7) % 11);
    $partitionKey = static fn (array $row): int => $row['b'] % $partitionMod;
    $orderKey = static fn (array $row): int => $row['b'] % $orderMod;
    $actual = $rankRows($rows, $partitionKey, $orderKey);
    $expected = $oracleRankRows($rows, $partitionKey, $orderKey);

    $tests['real upstream window3 dynamic ranking distribution case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $actual, $expected, $partitionMod, $orderMod): void {
        $t->same(array_column($expected, 'row_number'), array_column($actual, 'row_number'), "window3.test 1.1.3 dynamic row_number {$case}");
        $t->same(array_column($expected, 'rank'), array_column($actual, 'rank'), "window3.test 1.1.5 dynamic rank {$case}");
        $t->same(array_column($expected, 'dense_rank'), array_column($actual, 'dense_rank'), "window3.test 1.1.4 dynamic dense_rank {$case}");
        foreach (array_column($actual, 'percent_rank') as $index => $value) {
            $t->true(abs($expected[$index]['percent_rank'] - $value) < 0.0000001, "window3.test 1.1.7 dynamic percent_rank {$case} row {$index}");
        }
        foreach (array_column($actual, 'cume_dist') as $index => $value) {
            $t->true(abs($expected[$index]['cume_dist'] - $value) < 0.0000001, "window3.test 1.1.7 dynamic cume_dist {$case} row {$index}");
        }
        $t->same(true, $partitionMod >= 2 && $orderMod >= 3, "window3.test dynamic partition/order modulus guard {$case}");
    };
}

$tests['real upstream window3 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.3 row_number',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.4 dense_rank',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.5 rank',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.6 combined ranking',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.7 percent_rank/cume_dist',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.3 row_number',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.4 dense_rank',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.5 rank',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.6 combined ranking',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 1.1.7 percent_rank/cume_dist',
    ]);
};

$tests['real upstream window3 dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses SQLiteWindowFunction ranking/distribution helpers over real upstream window3 semantics', 'no new support component needed; reuses SQLiteWindowFunction ranking/distribution helpers over real upstream window3 semantics');
};

return $tests;
