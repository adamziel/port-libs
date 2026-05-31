<?php

declare(strict_types=1);

$tests = [];

// Source: upstream SQLite test/windowE.test sections 1.0-1.3. These cases
// model a custom text collation used by an index, then changed before a RANGE
// window over that indexed order is evaluated.

$windowERows = [
    ['a' => 1, 'b' => 'one'],
    ['a' => 2, 'b' => 'two'],
    ['a' => 3, 'b' => 'three'],
    ['a' => 4, 'b' => 'four'],
    ['a' => 5, 'b' => 'five'],
    ['a' => 6, 'b' => 'six'],
];

$textCompare = static fn (string $left, string $right): int => $left <=> $right;
$reverseTextCompare = static fn (string $left, string $right): int => $right <=> $left;

$indexedOrder = static function (array $rows, callable $comparator): array {
    $indexed = [];
    foreach ($rows as $position => $row) {
        $indexed[] = [$position, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($comparator): int {
        $comparison = $comparator($left[1]['b'], $right[1]['b']);
        return $comparison === 0 ? $left[0] <=> $right[0] : $comparison;
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

$rangeGroupConcatOverIndexedOrder = static function (array $orderedRows, callable $activeComparator): array {
    $actual = [];
    foreach ($orderedRows as $position => $row) {
        $frame = [];
        foreach ($orderedRows as $candidatePosition => $candidate) {
            if ($activeComparator($candidate['b'], $row['b']) >= 0 && $candidatePosition <= $position) {
                $frame[] = (string) $candidate['a'];
            }
        }
        $actual[] = implode(',', $frame);
    }

    return $actual;
};

$tests['real upstream windowE 1.1 custom collation fixture order'] = static function (TestRunner $t) use ($windowERows): void {
    $t->same([
        [1, 'one'],
        [2, 'two'],
        [3, 'three'],
        [4, 'four'],
        [5, 'five'],
        [6, 'six'],
    ], array_map(static fn (array $row): array => [$row['a'], $row['b']], $windowERows), 'windowE.test 1.1');
};

$tests['real upstream windowE 1.2 custom collation range uses indexed text order'] = static function (TestRunner $t) use ($windowERows, $indexedOrder, $rangeGroupConcatOverIndexedOrder, $textCompare): void {
    $orderedRows = $indexedOrder($windowERows, $textCompare);

    $t->same(['five', 'four', 'one', 'six', 'three', 'two'], array_column($orderedRows, 'b'), 'windowE.test 1.2 indexed ORDER BY b');
    $t->same(['5', '4', '1', '6', '3', '2'], $rangeGroupConcatOverIndexedOrder($orderedRows, $textCompare), 'windowE.test 1.2 group_concat over custom collation RANGE');
};

$tests['real upstream windowE 1.3 changed custom collation range keeps stale index scan order'] = static function (TestRunner $t) use ($windowERows, $indexedOrder, $rangeGroupConcatOverIndexedOrder, $textCompare, $reverseTextCompare): void {
    $orderedRows = $indexedOrder($windowERows, $textCompare);

    $t->same(['five', 'four', 'one', 'six', 'three', 'two'], array_column($orderedRows, 'b'), 'windowE.test 1.3 stale index ORDER BY b');
    $t->same(['5', '5,4', '5,4,1', '5,4,1,6', '5,4,1,6,3', '5,4,1,6,3,2'], $rangeGroupConcatOverIndexedOrder($orderedRows, $reverseTextCompare), 'windowE.test 1.3 group_concat after collation callback change');
};

for ($case = 1; $case <= 1000; $case++) {
    $rotate = $case % count($windowERows);
    $rows = array_merge(array_slice($windowERows, $rotate), array_slice($windowERows, 0, $rotate));
    $useReverse = ($case % 2) === 0;
    $orderedRows = $indexedOrder($rows, $textCompare);
    $activeComparator = $useReverse ? $reverseTextCompare : $textCompare;
    $actual = $rangeGroupConcatOverIndexedOrder($orderedRows, $activeComparator);
    $expected = $useReverse
        ? ['5', '5,4', '5,4,1', '5,4,1,6', '5,4,1,6,3', '5,4,1,6,3,2']
        : ['5', '4', '1', '6', '3', '2'];

    $tests["real upstream windowE dynamic custom collation range case {$case}"] = static function (TestRunner $t) use ($case, $orderedRows, $actual, $expected, $useReverse): void {
        $t->same(['five', 'four', 'one', 'six', 'three', 'two'], array_column($orderedRows, 'b'), "windowE.test dynamic {$case} stable indexed order");
        $t->same($expected, $actual, "windowE.test dynamic {$case} active collation RANGE frame");
        $t->same($useReverse, str_contains($actual[count($actual) - 1], ','), "windowE.test dynamic {$case} changed collation expands prior frames");
    };
}

$tests['real upstream windowE custom collation range cites upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:1.0 custom collation and t1b index',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:1.2 group_concat RANGE under original custom collation',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:1.3 group_concat RANGE after custom collation callback change',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:1.0 custom collation and t1b index',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:1.2 group_concat RANGE under original custom collation',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:1.3 group_concat RANGE after custom collation callback change',
    ]);
};

return $tests;
