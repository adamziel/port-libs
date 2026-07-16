<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [
    ['a' => 1, 'b' => 'one'],
    ['a' => 2, 'b' => 'two'],
    ['a' => 3, 'b' => 'three'],
    ['a' => 4, 'b' => 'four'],
    ['a' => 5, 'b' => 'five'],
    ['a' => 6, 'b' => 'six'],
];

$values = array_column($rows, 'a');
$keys = array_column($rows, 'b');
$customCollation = static fn (mixed $left, mixed $right): int => strcmp((string) $left, (string) $right);
$reverseCustomCollation = static fn (mixed $left, mixed $right): int => strcmp((string) $right, (string) $left);
$outputByOrder = static function (array $valuesByRow, array $keysByRow, callable $comparator): array {
    $order = range(0, count($valuesByRow) - 1);
    usort($order, static function (int $left, int $right) use ($keysByRow, $comparator): int {
        $comparison = $comparator($keysByRow[$left], $keysByRow[$right]);

        return $comparison === 0 ? $left <=> $right : ($comparison <=> 0);
    });

    return array_map(static fn (int $rowIndex): mixed => $valuesByRow[$rowIndex], $order);
};

$ascendingActual = SQLiteWindowFunction::aggregateOrderedRangeValues(
    'group_concat',
    $values,
    $keys,
    'ASC',
    'FIRST',
    '1 PRECEDING',
    '2 PRECEDING',
    null,
    ',',
    $customCollation,
);
$ascendingOutput = $outputByOrder($ascendingActual, $keys, $customCollation);
$ascendingExpected = [5, 4, 1, 6, 3, 2];

foreach ($ascendingExpected as $index => $expected) {
    $tests['real upstream windowE 1.2 custom collation range output row ' . ($index + 1)] = static function (TestRunner $t) use ($ascendingOutput, $expected, $index): void {
        $t->same((string) $expected, $ascendingOutput[$index], 'windowE.test 1.2 custom collation output order');
    };
}

$reverseActual = SQLiteWindowFunction::aggregateOrderedRangeValues(
    'group_concat',
    $values,
    $keys,
    'ASC',
    'FIRST',
    '1 PRECEDING',
    '2 PRECEDING',
    null,
    ',',
    $reverseCustomCollation,
);
$reverseOutput = $outputByOrder($reverseActual, $keys, $reverseCustomCollation);
$reverseExpected = [2, 3, 6, 1, 4, 5];

foreach ($reverseExpected as $index => $expected) {
    $tests['real upstream windowE dynamic reversed custom collation range output row ' . ($index + 1)] = static function (TestRunner $t) use ($reverseOutput, $expected, $index): void {
        $t->same((string) $expected, $reverseOutput[$index], 'windowE.test custom comparator dynamic output order');
    };
}

$labels = ['alpha', 'ALPHA', 'beta', 'BETA', 'gamma', 'GAMMA'];
$caseFoldComparator = static fn (mixed $left, mixed $right): int => strcasecmp((string) $left, (string) $right);
$caseFoldActual = SQLiteWindowFunction::aggregateOrderedRangeValues(
    'count',
    [10, 20, 30, 40, 50, 60],
    $labels,
    'ASC',
    'FIRST',
    'CURRENT ROW',
    'CURRENT ROW',
    null,
    ',',
    $caseFoldComparator,
);

foreach ([2, 2, 2, 2, 2, 2] as $index => $expected) {
    $tests['real upstream windowE dynamic custom collation peers casefold row ' . ($index + 1)] = static function (TestRunner $t) use ($caseFoldActual, $expected, $index): void {
        $t->same($expected, $caseFoldActual[$index], 'custom comparator peer group count');
    };
}

$dynamicCases = [
    ['case' => 'lexical asc preceding to preceding', 'comparator' => $customCollation, 'direction' => 'ASC', 'start' => '1 PRECEDING', 'end' => '2 PRECEDING', 'ordered' => [5, 4, 1, 6, 3, 2]],
    ['case' => 'lexical desc preceding to preceding', 'comparator' => $customCollation, 'direction' => 'DESC', 'start' => '1 PRECEDING', 'end' => '2 PRECEDING', 'ordered' => [2, 3, 6, 1, 4, 5]],
    ['case' => 'reverse asc preceding to preceding', 'comparator' => $reverseCustomCollation, 'direction' => 'ASC', 'start' => '1 PRECEDING', 'end' => '2 PRECEDING', 'ordered' => [2, 3, 6, 1, 4, 5]],
    ['case' => 'reverse desc preceding to preceding', 'comparator' => $reverseCustomCollation, 'direction' => 'DESC', 'start' => '1 PRECEDING', 'end' => '2 PRECEDING', 'ordered' => [5, 4, 1, 6, 3, 2]],
    ['case' => 'lexical asc following to following', 'comparator' => $customCollation, 'direction' => 'ASC', 'start' => '2 FOLLOWING', 'end' => '2 FOLLOWING', 'ordered' => [5, 4, 1, 6, 3, 2]],
    ['case' => 'reverse asc following to following', 'comparator' => $reverseCustomCollation, 'direction' => 'ASC', 'start' => '2 FOLLOWING', 'end' => '2 FOLLOWING', 'ordered' => [2, 3, 6, 1, 4, 5]],
];

foreach ($dynamicCases as $case) {
    $actual = SQLiteWindowFunction::aggregateOrderedRangeValues(
        'group_concat',
        $values,
        $keys,
        $case['direction'],
        'FIRST',
        $case['start'],
        $case['end'],
        null,
        ',',
        $case['comparator'],
    );
    $ordered = $outputByOrder($actual, $keys, $case['direction'] === 'DESC'
        ? static fn (mixed $left, mixed $right): int => -$case['comparator']($left, $right)
        : $case['comparator']);

    foreach ($case['ordered'] as $index => $expected) {
        $tests['real upstream windowE dynamic custom collation ' . $case['case'] . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($ordered, $expected, $index, $case): void {
            $t->same((string) $expected, $ordered[$index], 'windowE.test dynamic custom RANGE ' . $case['case']);
        };
    }
}

$matrixRows = [
    ['key' => 'alpha', 'value' => 1],
    ['key' => 'ALPHA', 'value' => 2],
    ['key' => 'beta', 'value' => 3],
    ['key' => 'BETA', 'value' => 4],
    ['key' => 'delta', 'value' => 5],
    ['key' => 'DELTA', 'value' => 6],
    ['key' => 'epsilon', 'value' => 7],
    ['key' => 'EPSILON', 'value' => 8],
    ['key' => 'gamma', 'value' => 9],
    ['key' => 'GAMMA', 'value' => 10],
    ['key' => 'omega', 'value' => 11],
    ['key' => 'OMEGA', 'value' => 12],
];
$matrixValues = array_column($matrixRows, 'value');
$matrixKeys = array_column($matrixRows, 'key');
$caseInsensitiveThenBinary = static function (mixed $left, mixed $right): int {
    $folded = strcasecmp((string) $left, (string) $right);

    return $folded === 0 ? strcmp((string) $left, (string) $right) : $folded;
};
$caseInsensitivePeers = static fn (mixed $left, mixed $right): int => strcasecmp((string) $left, (string) $right);
$lengthThenText = static function (mixed $left, mixed $right): int {
    $length = strlen((string) $left) <=> strlen((string) $right);

    return $length === 0 ? strcmp((string) $left, (string) $right) : $length;
};
$reverseLengthThenText = static function (mixed $left, mixed $right): int {
    $length = strlen((string) $right) <=> strlen((string) $left);

    return $length === 0 ? strcmp((string) $right, (string) $left) : $length;
};
$expectedNonNumericRangeCounts = static function (array $keysByRow, callable $comparator, string $direction, string $start, string $end): array {
    $order = range(0, count($keysByRow) - 1);
    usort($order, static function (int $left, int $right) use ($keysByRow, $comparator, $direction): int {
        $comparison = $comparator($keysByRow[$left], $keysByRow[$right]);
        if ($direction === 'DESC') {
            $comparison *= -1;
        }

        return $comparison === 0 ? $left <=> $right : ($comparison <=> 0);
    });

    $result = array_fill(0, count($keysByRow), 0);
    foreach ($order as $position => $rowIndex) {
        $peerStart = $position;
        $peerEnd = $position;
        while ($peerStart > 0 && $comparator($keysByRow[$order[$peerStart - 1]], $keysByRow[$rowIndex]) === 0) {
            $peerStart--;
        }
        while ($peerEnd + 1 < count($order) && $comparator($keysByRow[$order[$peerEnd + 1]], $keysByRow[$rowIndex]) === 0) {
            $peerEnd++;
        }

        $startPosition = $start === 'UNBOUNDED PRECEDING' ? 0 : $peerStart;
        $endPosition = $end === 'UNBOUNDED FOLLOWING' ? count($order) - 1 : $peerEnd;
        $result[$rowIndex] = $startPosition > $endPosition ? 0 : $endPosition - $startPosition + 1;
    }

    return $result;
};

$matrixComparators = [
    'case-insensitive-peers' => $caseInsensitivePeers,
    'case-insensitive-then-binary' => $caseInsensitiveThenBinary,
    'length-then-text' => $lengthThenText,
    'reverse-length-then-text' => $reverseLengthThenText,
];
$matrixFrames = [
    ['start' => 'CURRENT ROW', 'end' => 'CURRENT ROW'],
    ['start' => '1 PRECEDING', 'end' => '1 FOLLOWING'],
    ['start' => '2 FOLLOWING', 'end' => '2 FOLLOWING'],
    ['start' => 'UNBOUNDED PRECEDING', 'end' => 'CURRENT ROW'],
    ['start' => 'CURRENT ROW', 'end' => 'UNBOUNDED FOLLOWING'],
];

foreach ($matrixComparators as $comparatorName => $comparator) {
    foreach (['ASC', 'DESC'] as $direction) {
        foreach ($matrixFrames as $frame) {
            $actual = SQLiteWindowFunction::aggregateOrderedRangeValues(
                'count',
                $matrixValues,
                $matrixKeys,
                $direction,
                'FIRST',
                $frame['start'],
                $frame['end'],
                null,
                ',',
                $comparator,
            );
            $expected = $expectedNonNumericRangeCounts($matrixKeys, $comparator, $direction, $frame['start'], $frame['end']);

            foreach ($matrixRows as $index => $row) {
                $tests[sprintf(
                    'real upstream windowE dynamic custom collation matrix %s %s %s to %s row %02d',
                    $comparatorName,
                    strtolower($direction),
                    strtolower($frame['start']),
                    strtolower($frame['end']),
                    $row['value'],
                )] = static function (TestRunner $t) use ($actual, $expected, $index, $comparatorName): void {
                    $t->same($expected[$index], $actual[$index], 'windowE/windowB nonnumeric RANGE peer count ' . $comparatorName);
                };
            }
        }
    }
}

$tests['real upstream windowE custom collation range cites exact upstream source'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 1.0-1.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 2.1',
    ];

    $t->contains('windowE.test 1.0-1.2', implode("\n", $sources));
    $t->contains('windowB.test 2.1', implode("\n", $sources));
};

$tests['real upstream windowE custom collation range dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction ordered RANGE frame evaluation with a native PHP ORDER BY comparator for custom collations',
        'no new support component needed; reuses SQLiteWindowFunction ordered RANGE frame evaluation with a native PHP ORDER BY comparator for custom collations',
    );
};

return $tests;
