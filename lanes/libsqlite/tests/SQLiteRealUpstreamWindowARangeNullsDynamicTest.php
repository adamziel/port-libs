<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$baseRows = [
    ['a' => 1, 'b' => 'A', 'd' => 5.4],
    ['a' => 2, 'b' => 'B', 'd' => 5.55],
    ['a' => 3, 'b' => 'C', 'd' => 8.0],
    ['a' => 4, 'b' => 'D', 'd' => 10.25],
    ['a' => 5, 'b' => 'E', 'd' => 10.26],
    ['a' => 6, 'b' => 'N', 'd' => null],
    ['a' => 7, 'b' => 'N', 'd' => null],
];

/**
 * @param list<array{a:int,b:string,d:float|null}> $rows
 * @return list<int>
 */
$orderedIndexes = static function (array $rows, string $direction, string $nulls): array {
    $indexes = array_keys($rows);
    $descending = strtoupper($direction) === 'DESC';
    $nullsFirst = strtoupper($nulls) === 'FIRST';

    usort($indexes, static function (int $left, int $right) use ($rows, $descending, $nullsFirst): int {
        $leftKey = $rows[$left]['d'];
        $rightKey = $rows[$right]['d'];
        if ($leftKey === null || $rightKey === null) {
            if ($leftKey === null && $rightKey === null) {
                return $rows[$left]['a'] <=> $rows[$right]['a'];
            }

            return ($leftKey === null) === $nullsFirst ? -1 : 1;
        }

        $comparison = $leftKey <=> $rightKey;
        if ($descending) {
            $comparison *= -1;
        }

        return $comparison === 0 ? $rows[$left]['a'] <=> $rows[$right]['a'] : $comparison;
    });

    return array_values($indexes);
};

/**
 * @param list<array{a:int,b:string,d:float|null}> $rows
 * @param array{type:string,offset:float|null} $start
 * @param array{type:string,offset:float|null} $end
 * @return list<string|null>
 */
$rangeConcatOracle = static function (
    array $rows,
    string $direction,
    string $nulls,
    array $start,
    array $end,
) use ($orderedIndexes): array {
    $order = $orderedIndexes($rows, $direction, $nulls);
    $descending = strtoupper($direction) === 'DESC';
    $resultByRow = [];

    foreach ($order as $position => $rowIndex) {
        $current = $rows[$rowIndex]['d'];
        $peerStart = $position;
        $peerEnd = $position;
        while ($peerStart > 0 && $rows[$order[$peerStart - 1]]['d'] === $current) {
            $peerStart--;
        }
        while ($peerEnd + 1 < count($order) && $rows[$order[$peerEnd + 1]]['d'] === $current) {
            $peerEnd++;
        }

        $startPosition = 0;
        if ($start['type'] === 'UNBOUNDED PRECEDING') {
            $startPosition = 0;
        } elseif ($start['type'] === 'UNBOUNDED FOLLOWING') {
            $startPosition = count($order) - 1;
        } elseif ($start['type'] === 'CURRENT ROW' || $current === null) {
            $startPosition = $peerStart;
        } else {
            $target = ($descending ? -$current : $current) - ($start['type'] === 'PRECEDING' ? $start['offset'] : -$start['offset']);
            $startPosition = count($order);
            foreach ($order as $candidatePosition => $candidateRow) {
                $key = $rows[$candidateRow]['d'];
                if ($key !== null && ($descending ? -$key : $key) >= $target - 1.0e-12) {
                    $startPosition = $candidatePosition;
                    break;
                }
            }
        }

        if ($end['type'] === 'UNBOUNDED FOLLOWING') {
            $endPosition = count($order) - 1;
        } elseif ($end['type'] === 'UNBOUNDED PRECEDING') {
            $endPosition = 0;
        } elseif ($end['type'] === 'CURRENT ROW' || $current === null) {
            $endPosition = $peerEnd;
        } else {
            $target = ($descending ? -$current : $current) + ($end['type'] === 'FOLLOWING' ? $end['offset'] : -$end['offset']);
            $endPosition = -1;
            for ($candidatePosition = count($order) - 1; $candidatePosition >= 0; $candidatePosition--) {
                $key = $rows[$order[$candidatePosition]]['d'];
                if ($key !== null && ($descending ? -$key : $key) <= $target + 1.0e-12) {
                    $endPosition = $candidatePosition;
                    break;
                }
            }
        }

        if ($startPosition > $endPosition || $endPosition < 0 || $startPosition > count($order) - 1) {
            $resultByRow[$rowIndex] = null;
            continue;
        }

        $letters = [];
        for ($framePosition = max(0, $startPosition); $framePosition <= min(count($order) - 1, $endPosition); $framePosition++) {
            $letters[] = $rows[$order[$framePosition]]['b'];
        }
        $resultByRow[$rowIndex] = $letters === [] ? null : implode('', $letters);
    }

    return array_map(static fn (int $index): ?string => $resultByRow[$index], array_keys($rows));
};

$actualRangeConcat = static function (array $rows, string $direction, string $nulls, array $start, array $end): array {
    $boundary = static function (array $bound): string {
        return match ($bound['type']) {
            'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'CURRENT ROW' => $bound['type'],
            'PRECEDING', 'FOLLOWING' => rtrim(rtrim(sprintf('%.2F', $bound['offset']), '0'), '.') . ' ' . $bound['type'],
            default => throw new RuntimeException('Unsupported boundary'),
        };
    };

    return SQLiteWindowFunction::aggregateOrderedRangeValues(
        'group_concat',
        array_column($rows, 'b'),
        array_column($rows, 'd'),
        $direction,
        $nulls,
        $boundary($start),
        $boundary($end),
        null,
        '',
    );
};

$assertWindowA = static function (
    TestRunner $t,
    array $rows,
    string $direction,
    string $nulls,
    array $start,
    array $end,
    string $label,
) use ($orderedIndexes, $rangeConcatOracle, $actualRangeConcat): void {
    $expectedByRow = $rangeConcatOracle($rows, $direction, $nulls, $start, $end);
    $actualByRow = $actualRangeConcat($rows, $direction, $nulls, $start, $end);
    $order = $orderedIndexes($rows, $direction, $nulls);
    $expectedOrdered = array_map(static fn (int $rowIndex): ?string => $expectedByRow[$rowIndex], $order);
    $actualOrdered = array_map(static fn (int $rowIndex): ?string => $actualByRow[$rowIndex], $order);

    $t->same($expectedOrdered, $actualOrdered, $label . ' ordered group_concat frame values');
    $t->same(count($rows), count($actualByRow), $label . ' row count');
    $t->same($expectedOrdered[0], $actualOrdered[0], $label . ' first ordered row');
    $t->same($expectedOrdered[array_key_last($expectedOrdered)], $actualOrdered[array_key_last($actualOrdered)], $label . ' last ordered row');
    $t->same(md5(json_encode($expectedByRow, JSON_THROW_ON_ERROR)), md5(json_encode($actualByRow, JSON_THROW_ON_ERROR)), $label . ' row fingerprint');
};

$canonicalCases = [
    'windowA.test 1.1 desc nulls last finite range' => [
        'DESC', 'LAST', ['type' => 'PRECEDING', 'offset' => 2.5], ['type' => 'FOLLOWING', 'offset' => 2.25],
        ['ED', 'EDC', 'EDC', 'CBA', 'BA', 'NN', 'NN'],
    ],
    'windowA.test 1.2 desc nulls first finite range' => [
        'DESC', 'FIRST', ['type' => 'PRECEDING', 'offset' => 2.5], ['type' => 'FOLLOWING', 'offset' => 2.25],
        ['NN', 'NN', 'ED', 'EDC', 'EDC', 'CBA', 'BA'],
    ],
    'windowA.test 1.3 desc nulls last through unbounded following' => [
        'DESC', 'LAST', ['type' => 'PRECEDING', 'offset' => 2.5], ['type' => 'UNBOUNDED FOLLOWING', 'offset' => null],
        ['EDCBANN', 'EDCBANN', 'EDCBANN', 'CBANN', 'BANN', 'NN', 'NN'],
    ],
    'windowA.test 2.6 desc nulls first unbounded preceding current' => [
        'DESC', 'FIRST', ['type' => 'UNBOUNDED PRECEDING', 'offset' => null], ['type' => 'CURRENT ROW', 'offset' => null],
        ['NN', 'NN', 'NNE', 'NNED', 'NNEDC', 'NNEDCB', 'NNEDCBA'],
    ],
    'windowA.test 3.4 desc nulls first current through tail' => [
        'DESC', 'FIRST', ['type' => 'CURRENT ROW', 'offset' => null], ['type' => 'UNBOUNDED FOLLOWING', 'offset' => null],
        ['NNEDCBA', 'NNEDCBA', 'EDCBA', 'DCBA', 'CBA', 'BA', 'A'],
    ],
    'windowA.test 4.0 desc nulls first preceding-only sparse frame' => [
        'DESC', 'FIRST', ['type' => 'PRECEDING', 'offset' => 2.5], ['type' => 'PRECEDING', 'offset' => 0.5],
        ['NN', 'NN', null, null, 'ED', 'C', null],
    ],
];

foreach ($canonicalCases as $name => [$direction, $nulls, $start, $end, $orderedExpected]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($baseRows, $direction, $nulls, $start, $end, $orderedExpected, $assertWindowA, $actualRangeConcat, $orderedIndexes, $name): void {
        $assertWindowA($t, $baseRows, $direction, $nulls, $start, $end, $name);
        $actualByRow = $actualRangeConcat($baseRows, $direction, $nulls, $start, $end);
        $actualOrdered = array_map(static fn (int $rowIndex): ?string => $actualByRow[$rowIndex], $orderedIndexes($baseRows, $direction, $nulls));
        $t->same($orderedExpected, $actualOrdered, $name . ' exact upstream expected list');
    };
}

$starts = [
    ['type' => 'UNBOUNDED PRECEDING', 'offset' => null],
    ['type' => 'CURRENT ROW', 'offset' => null],
    ['type' => 'PRECEDING', 'offset' => 0.5],
    ['type' => 'PRECEDING', 'offset' => 2.5],
    ['type' => 'PRECEDING', 'offset' => 3.75],
];
$ends = [
    ['type' => 'CURRENT ROW', 'offset' => null],
    ['type' => 'UNBOUNDED FOLLOWING', 'offset' => null],
    ['type' => 'FOLLOWING', 'offset' => 0.5],
    ['type' => 'FOLLOWING', 'offset' => 2.25],
    ['type' => 'PRECEDING', 'offset' => 0.5],
];

foreach (range(1, 1000) as $case) {
    $rows = [];
    foreach ($baseRows as $rowIndex => $row) {
        $row['a'] = $rowIndex + 1;
        if ($row['d'] !== null && $case % 7 !== 0) {
            $row['d'] = round($row['d'] + (($case % 5) - 2) * 0.01, 2);
        }
        if ($rowIndex === 1 && $case % 11 === 0) {
            $row['d'] = 5.4;
        }
        if ($rowIndex === 4 && $case % 13 === 0) {
            $row['d'] = 10.25;
        }
        if ($rowIndex >= 5 && $case % 17 === 0) {
            $row['b'] = $rowIndex === 5 ? 'X' : 'Y';
        }
        $rows[] = $row;
    }

    $direction = 'DESC';
    $nulls = $case % 2 === 0 ? 'FIRST' : 'LAST';
    $start = $starts[($case - 1) % count($starts)];
    $end = $ends[intdiv($case - 1, count($starts)) % count($ends)];
    $label = sprintf('windowA.test dynamic RANGE NULLS case %04d', $case);

    $tests['real upstream ' . $label] = static function (TestRunner $t) use ($rows, $direction, $nulls, $start, $end, $label, $assertWindowA): void {
        $assertWindowA($t, $rows, $direction, $nulls, $start, $end, $label);
    };
}

$tests['real upstream windowA dynamic cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:1.1-1.6',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:2.1-2.6',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:3.1-3.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:4.0',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:1.1-1.6',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:2.1-2.6',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:3.1-3.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:4.0',
    ]);
};

return $tests;
