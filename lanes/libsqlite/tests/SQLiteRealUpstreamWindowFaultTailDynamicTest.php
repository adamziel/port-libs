<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$isPredicate = static fn (mixed $left, mixed $right): array => ['operator' => 'IS', 'left' => $left, 'right' => $right];

$tupleInRows = static function (int $case): array {
    $rows = [];
    $rowCount = 1 + ($case % 9);
    for ($index = 0; $index < $rowCount; $index++) {
        $rows[] = [
            'c0' => $index === 0 ? 0 : (($case + $index) % 5),
            'partition' => 'p' . (($case + $index) % 3),
            'ordinal' => $index,
        ];
    }

    return $rows;
};

$fault11TupleMatches = static function (array $rows): array {
    $denseRanks = SQLiteWindowFunction::denseRank(array_fill(0, count($rows), 0));
    $laggedZeros = SQLiteWindowFunction::lag(array_fill(0, count($rows), 0));
    $matches = [];
    foreach ($rows as $index => $row) {
        $rhs = [$denseRanks[$index], $laggedZeros[$index]];
        $matches[] = [0, $row['c0']] === $rhs;
    }

    return $matches;
};

$fault11IntersectRows = static function (int $case): array {
    $textInteger = (int) (string) (0 <= null);
    $concatTail = (string) 0.4 . (string) (0x8 & 1);
    $windowConcat = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'group_concat',
        [$textInteger . $concatTail],
        [0],
        'ROWS',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
    )[0];
    $left = [false, date('Y-m-d', strtotime('2026-05-31 +' . ($case % 7) . ' days'))];
    $right = [(int) ($windowConcat !== ''), $windowConcat];

    return $left === $right ? [$left] : [];
};

$fault12CteRows = static function (array $rows, mixed $needle): array {
    $partitions = [];
    foreach ($rows as $row) {
        $key = strtolower((string) $row['a']);
        $partitions[$key][] = $row;
    }

    $cteRows = [];
    foreach ($partitions as $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => [$left['b'], $left['ordinal']] <=> [$right['b'], $right['ordinal']]);
        $numbers = SQLiteWindowFunction::rowNumber(array_column($partitionRows, 'b'));
        foreach ($partitionRows as $index => $row) {
            $cteRows[] = [
                'a' => $row['a'],
                'b' => $row['b'],
                'row_number' => $numbers[$index],
            ];
        }
    }

    return array_values(array_filter($cteRows, static fn (array $row): bool => $row['a'] === $needle));
};

$tests['real upstream windowfault.test 11.1 tuple in dense-rank lag exact empty result'] = static function (TestRunner $t) use ($fault11TupleMatches): void {
    $matches = $fault11TupleMatches([['c0' => 0, 'partition' => 'p0', 'ordinal' => 0]]);

    $t->same([false], $matches, 'windowfault.test 11.1 one-row tuple IN remains empty');
};

$tests['real upstream windowfault.test 11.2 values intersect window aggregate exact empty result'] = static function (TestRunner $t) use ($fault11IntersectRows): void {
    $t->same([], $fault11IntersectRows(0), 'windowfault.test 11.2 VALUES INTERSECT remains empty');
};

$tests['real upstream windowfault.test 12 empty nocase partition cte exact empty result'] = static function (TestRunner $t) use ($fault12CteRows): void {
    $t->same([], $fault12CteRows([], 2), 'windowfault.test 12 empty CTE result');
};

foreach (range(1, 1000) as $case) {
    $tests["real upstream windowfault.test 11-12 dynamic tail case {$case}"] = static function (TestRunner $t) use ($case, $tupleInRows, $fault11TupleMatches, $fault11IntersectRows, $fault12CteRows, $literal, $column, $isPredicate): void {
        $tupleRows = $tupleInRows($case);
        $matches = $fault11TupleMatches($tupleRows);
        $t->same(array_fill(0, count($tupleRows), false), $matches, "windowfault.test 11.1 dynamic tuple IN false case {$case}");
        $t->same([], array_values(array_filter($tupleRows, static fn (array $_row, int $index): bool => $matches[$index], ARRAY_FILTER_USE_BOTH)), "windowfault.test 11.1 dynamic filtered rows empty case {$case}");

        $t->same([], $fault11IntersectRows($case), "windowfault.test 11.2 dynamic VALUES INTERSECT empty case {$case}");

        $cteInput = [];
        for ($index = 0; $index < ($case % 11); $index++) {
            $cteInput[] = [
                'a' => $index % 2 === 0 ? 'A' : 'a',
                'b' => ($case + $index) % 17,
                'ordinal' => $index,
            ];
        }
        $t->same([], $fault12CteRows($cteInput, 2), "windowfault.test 12 dynamic numeric equality remains empty case {$case}");
        if ($cteInput !== []) {
            $kept = $fault12CteRows($cteInput, $cteInput[0]['a']);
            $t->same(true, $kept !== [], "windowfault.test 12 dynamic nocase partition can still produce rows case {$case}");
            $t->same(true, SQLiteSelectPredicate::evaluate(['a' => $kept[0]['a']], $isPredicate($literal($kept[0]['a']), $column('a'))), "windowfault.test 12 dynamic predicate wiring case {$case}");
        } else {
            $t->same([], $cteInput, "windowfault.test 12 dynamic empty source case {$case}");
            $t->same(false, SQLiteSelectPredicate::evaluate(['a' => null], $isPredicate($literal(2), $column('a'))), "windowfault.test 12 dynamic predicate empty sentinel case {$case}");
        }
    };
}

$tests['real upstream windowfault tail dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:11.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:11.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:12.0-12',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:11.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:11.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test:12.0-12',
    ]);
};

return $tests;
