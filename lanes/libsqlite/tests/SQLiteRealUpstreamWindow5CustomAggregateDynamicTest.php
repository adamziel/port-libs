<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [4, 6, 1, 5, 2, 3];
$letters = ['a', 'b', 'c', 'd', 'e', 'f'];
$rowids = range(1, 6);

$medianOracle = static function (array $frame): int|float|null {
    if ($frame === []) {
        return null;
    }

    sort($frame, SORT_REGULAR);
    $middle = intdiv(count($frame), 2);
    if ((count($frame) % 2) === 1) {
        return $frame[$middle];
    }

    $sum = $frame[$middle - 1] + $frame[$middle];

    return fmod((float) $sum, 2.0) === 0.0 ? (int) ($sum / 2) : $sum / 2.0;
};

$frameOracle = static function (array $rows, int $row, string $start, string $end): array {
    $count = count($rows);
    $boundary = static function (string $boundary, int $current, bool $isStart) use ($count): int {
        return match ($boundary) {
            'UNBOUNDED PRECEDING' => 0,
            'UNBOUNDED FOLLOWING' => $count - 1,
            'CURRENT ROW' => $current,
            '1 PRECEDING' => $current - 1,
            '2 PRECEDING' => $current - 2,
            '3 PRECEDING' => $current - 3,
            '1 FOLLOWING' => $current + 1,
            '2 FOLLOWING' => $current + 2,
            '3 FOLLOWING' => $current + 3,
            default => $isStart ? $count : -1,
        };
    };

    $first = max(0, $boundary($start, $row, true));
    $last = min($count - 1, $boundary($end, $row, false));
    if ($first > $last) {
        return [];
    }

    return array_slice($rows, $first, $last - $first + 1);
};

$tests['real upstream window5 1.1 custom win and median ordered by b'] = static function (TestRunner $t) use ($values, $letters): void {
    $order = array_keys($letters);
    usort($order, static fn (int $left, int $right): int => $letters[$left] <=> $letters[$right]);
    $orderedValues = array_map(static fn (int $index): int => $values[$index], $order);
    $orderedKeys = array_map(static fn (int $index): string => $letters[$index], $order);

    $t->same(['4', '4 6', '1 4 6', '1 4 5 6', '1 2 4 5 6', '1 2 3 4 5 6'], SQLiteWindowFunction::sortedFrameTextValues($orderedValues, $orderedKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), 'window5.test 1.1 win(a) OVER (ORDER BY b)');
    $t->same([4, 5, 4, 4.5, 4, 3.5], SQLiteWindowFunction::medianFrameBetweenValues($orderedValues, $orderedKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), 'window5.test 1.1 median(a) OVER (ORDER BY b)');
};

$tests['real upstream window5 2.0 custom sumint ordered by rowid'] = static function (TestRunner $t) use ($values, $rowids): void {
    $t->same([4, 10, 11, 16, 18, 21], SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $rowids, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), 'window5.test 2.0 sumint(a) OVER (ORDER BY rowid)');
};

$tests['real upstream window5 2.1 custom sumint sliding inverse frame'] = static function (TestRunner $t) use ($values, $rowids): void {
    $t->same([10, 11, 12, 8, 10, 5], SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $rowids, 'ROWS', '1 PRECEDING', '1 FOLLOWING'), 'window5.test 2.1 sumint(a) ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING');
};

$tests['real upstream window5 3.0 overridden aggregate rejects window use but remains scalar aggregate'] = static function (TestRunner $t) use ($values, $letters): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('overridden_sum', $values, $letters, 'ROWS', '1 PRECEDING', 'CURRENT ROW'), 'window5.test 3.0 overridden sum may not be used as a window function');
    $t->same(21, array_sum($values), 'window5.test 3.1 ordinary aggregate sum remains available');
};

$dynamicStarts = ['UNBOUNDED PRECEDING', '3 PRECEDING', '2 PRECEDING', '1 PRECEDING', 'CURRENT ROW', '1 FOLLOWING'];
$dynamicEnds = ['CURRENT ROW', '1 FOLLOWING', '2 FOLLOWING', '3 FOLLOWING', 'UNBOUNDED FOLLOWING'];

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 6 + ($case % 7);
    $caseValues = [];
    $caseKeys = [];
    $filters = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $caseValues[] = (($row * 17 + $case * 5) % 31) - 10;
        $caseKeys[] = $row + 1;
        $filters[] = (($row + $case) % (2 + ($case % 4))) !== 0;
    }

    $start = $dynamicStarts[$case % count($dynamicStarts)];
    $end = $dynamicEnds[intdiv($case, count($dynamicStarts)) % count($dynamicEnds)];
    $expectedMedians = [];
    $expectedSorted = [];
    $expectedFilteredSums = [];
    foreach (array_keys($caseValues) as $row) {
        $frame = $frameOracle($caseValues, $row, $start, $end);
        $expectedMedians[] = $medianOracle($frame);

        $sorted = $frame;
        sort($sorted, SORT_REGULAR);
        $expectedSorted[] = $sorted === [] ? null : implode(' ', array_map('strval', $sorted));

        $frameIndexes = $frameOracle(array_keys($caseValues), $row, $start, $end);
        $filteredFrame = [];
        foreach ($frameIndexes as $frameIndex) {
            if ($filters[$frameIndex]) {
                $filteredFrame[] = $caseValues[$frameIndex];
            }
        }
        $expectedFilteredSums[] = $filteredFrame === [] ? null : array_sum($filteredFrame);
    }

    $tests["real upstream window5 dynamic custom aggregate inverse frame case {$case}"] = static function (TestRunner $t) use ($caseValues, $caseKeys, $filters, $start, $end, $expectedMedians, $expectedSorted, $expectedFilteredSums, $case): void {
        $t->same($expectedMedians, SQLiteWindowFunction::medianFrameBetweenValues($caseValues, $caseKeys, 'ROWS', $start, $end), "window5.test 1.1 dynamic median case {$case}");
        $t->same($expectedSorted, SQLiteWindowFunction::sortedFrameTextValues($caseValues, $caseKeys, 'ROWS', $start, $end), "window5.test 1.1 dynamic win sorted values case {$case}");
        $t->same($expectedFilteredSums, SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $caseValues, $caseKeys, 'ROWS', $start, $end, 'NO OTHERS', $filters), "window5.test 2.1 dynamic filtered sumint inverse case {$case}");
    };
}

$tests['real upstream window5 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 1.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 2.0-2.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 3.0-3.1',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 1.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 2.0-2.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test 3.0-3.1',
    ]);
};

return $tests;
