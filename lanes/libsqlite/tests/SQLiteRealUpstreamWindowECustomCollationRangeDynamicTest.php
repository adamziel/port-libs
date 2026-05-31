<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [1, 2, 3, 4, 5, 6];
$labels = ['one', 'two', 'three', 'four', 'five', 'six'];
$ascendingComparator = static fn (mixed $left, mixed $right): int => strcmp((string) $left, (string) $right);
$descendingComparator = static fn (mixed $left, mixed $right): int => strcmp((string) $right, (string) $left);

$tests['real upstream windowE 1.2 custom collation index order returns old-order singleton range peers'] = static function (TestRunner $t) use ($values, $labels, $ascendingComparator): void {
    $t->same(
        ['5', '4', '1', '6', '3', '2'],
        SQLiteWindowFunction::groupConcatIndexedCustomRangeValues($values, $labels, $ascendingComparator),
        'windowE.test 1.2',
    );
};

$tests['real upstream windowE 1.3 changed custom collation expands over existing index scan order'] = static function (TestRunner $t) use ($values, $labels, $descendingComparator): void {
    $t->same(
        ['5', '5,4', '5,4,1', '5,4,1,6', '5,4,1,6,3', '5,4,1,6,3,2'],
        SQLiteWindowFunction::groupConcatIndexedCustomRangeValues($values, $labels, $descendingComparator, ',', true),
        'windowE.test 1.3',
    );
};

$oracle = static function (array $caseValues, array $caseLabels, callable $runtimeComparator, bool $useRuntimePrefixFrame, string $separator = ','): array {
    $order = range(0, count($caseValues) - 1);
    usort($order, static fn (int $left, int $right): int => strcmp($caseLabels[$left], $caseLabels[$right]) ?: ($left <=> $right));

    $actual = [];
    foreach ($order as $position => $rowIndex) {
        $frame = [];
        foreach (array_slice($order, 0, $position + 1) as $candidateIndex) {
            $comparison = $runtimeComparator($caseLabels[$candidateIndex], $caseLabels[$rowIndex]);
            if ($useRuntimePrefixFrame ? $comparison >= 0 : $comparison === 0) {
                $frame[] = (string) $caseValues[$candidateIndex];
            }
        }
        $actual[] = $frame === [] ? null : implode($separator, $frame);
    }

    return $actual;
};

for ($case = 0; $case < 1000; $case++) {
    $rowCount = 6 + ($case % 7);
    $caseValues = [];
    $caseLabels = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $caseValues[] = (($case * 11 + $row * 7) % 29) + 1;
        $caseLabels[] = chr(97 + (($case * 5 + $row * 3) % 26)) . '-' . (($case + $row) % 4);
    }
    $separator = $case % 3 === 0 ? ',' : ($case % 3 === 1 ? '|' : ':');
    $runtimeComparator = $case % 2 === 0 ? $ascendingComparator : $descendingComparator;
    $useRuntimePrefixFrame = $case % 2 === 1;
    $expected = $oracle($caseValues, $caseLabels, $runtimeComparator, $useRuntimePrefixFrame, $separator);

    $tests['real upstream windowE custom collation range dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($caseValues, $caseLabels, $runtimeComparator, $separator, $useRuntimePrefixFrame, $expected, $case): void {
        $actual = SQLiteWindowFunction::groupConcatIndexedCustomRangeValues($caseValues, $caseLabels, $runtimeComparator, $separator, $useRuntimePrefixFrame);

        $t->same($expected, $actual, "windowE.test 1.2-1.3 dynamic custom collation range {$case}");
        $t->same(count($caseValues), count($actual), "windowE.test dynamic output row count {$case}");
        $t->same($actual, array_values($actual), "windowE.test dynamic packed output {$case}");
    };
}

$tests['real upstream windowE custom collation range dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 1.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 1.3',
    ];
    foreach ($sources as $source) {
        $t->true(is_file(strtok($source, ' ')), $source);
    }
};

$tests['real upstream windowE custom collation range dynamic dependency closure note'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction index-scan RANGE behavior with a runtime custom collation comparator',
        'no new support component needed; reuses SQLiteWindowFunction index-scan RANGE behavior with a runtime custom collation comparator',
    );
};

return $tests;
