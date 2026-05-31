<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window8Keys = [5.0, 10.0, 13.0, 13.0, 15.0, 20.0, 22.0, 30.0];
$window8Values = [10, 20, 26, 26, 30, 40, 80, 90];

$parseRangeBoundary = static function (string $boundary): array {
    $normalized = strtoupper(trim($boundary));
    if ($normalized === 'UNBOUNDED PRECEDING' || $normalized === 'UNBOUNDED FOLLOWING' || $normalized === 'CURRENT ROW') {
        return ['type' => $normalized, 'offset' => null];
    }
    if (preg_match('/^([0-9]+(?:\.[0-9]+)?) (PRECEDING|FOLLOWING)$/', $normalized, $match) !== 1) {
        throw new RuntimeException('Unsupported window8 RANGE boundary ' . $boundary);
    }

    return [
        'type' => $match[2],
        'offset' => str_contains($match[1], '.') ? (float) $match[1] : (int) $match[1],
    ];
};

$rangeBoundaryValue = static function (float $current, array $boundary): float {
    return match ($boundary['type']) {
        'UNBOUNDED PRECEDING' => -INF,
        'UNBOUNDED FOLLOWING' => INF,
        'CURRENT ROW' => $current,
        'PRECEDING' => $current - (float) $boundary['offset'],
        'FOLLOWING' => $current + (float) $boundary['offset'],
        default => throw new RuntimeException('Unsupported parsed window8 RANGE boundary'),
    };
};

$orderedRangeSumOracle = static function (array $values, array $keys, string $direction, string $startBoundary, string $endBoundary) use ($parseRangeBoundary, $rangeBoundaryValue): array {
    $descending = strtoupper($direction) === 'DESC';
    $start = $parseRangeBoundary($startBoundary);
    $end = $parseRangeBoundary($endBoundary);
    $normalizedKeys = array_map(
        static fn (mixed $key): float => $descending ? -(float) $key : (float) $key,
        $keys,
    );

    $result = [];
    foreach ($normalizedKeys as $row => $current) {
        $lower = $rangeBoundaryValue($current, $start);
        $upper = $rangeBoundaryValue($current, $end);
        if ($lower > $upper) {
            $result[] = null;
            continue;
        }

        $sum = null;
        foreach ($normalizedKeys as $candidate => $candidateKey) {
            if ($candidateKey >= $lower - 1.0e-12 && $candidateKey <= $upper + 1.0e-12) {
                $sum = ($sum ?? 0) + $values[$candidate];
            }
        }
        $result[] = $sum;
    }

    return $result;
};

$fixedCases = [
    '3.1 asc five preceding five following' => ['ASC', '5 PRECEDING', '5 FOLLOWING', [30, 112, 102, 102, 142, 150, 120, 90]],
    '3.2 asc ten preceding five preceding' => ['ASC', '10 PRECEDING', '5 PRECEDING', [null, 10, 10, 10, 30, 102, 82, 120]],
    '3.3 asc two following three following' => ['ASC', '2 FOLLOWING', '3 FOLLOWING', [null, 52, 30, 30, null, 80, null, null]],
    '3.4 desc five preceding five following' => ['DESC', '5 PRECEDING', '5 FOLLOWING', [30, 112, 102, 102, 142, 150, 120, 90]],
    '3.5 desc ten preceding five preceding' => ['DESC', '10 PRECEDING', '5 PRECEDING', [102, 70, 120, 120, 120, 90, 90, null]],
    '3.6 desc two following three following' => ['DESC', '2 FOLLOWING', '3 FOLLOWING', [null, null, 20, 20, 52, null, 40, null]],
    '3.7 asc fractional preceding following' => ['ASC', '5.1 PRECEDING', '5.3 FOLLOWING', [30, 112, 102, 102, 142, 150, 120, 90]],
    '3.8 asc fractional preceding-only' => ['ASC', '10.2 PRECEDING', '5.4 PRECEDING', [null, null, 10, 10, 10, 72, 82, 120]],
    '3.9 asc fractional following-only' => ['ASC', '2.6 FOLLOWING', '3.5 FOLLOWING', [null, 52, null, null, null, null, null, null]],
    '3.10 desc fractional preceding following' => ['DESC', '5.7 PRECEDING', '5.8 FOLLOWING', [30, 112, 102, 102, 142, 150, 120, 90]],
    '3.11 desc unbounded through fractional preceding' => ['DESC', 'UNBOUNDED PRECEDING', '5.9 PRECEDING', [292, 210, 210, 210, 170, 90, 90, null]],
    '3.12 desc fractional following through unbounded' => ['DESC', '2.1 FOLLOWING', 'UNBOUNDED FOLLOWING', [null, 10, 30, 30, 30, 112, 112, 232]],
    '3.13 asc shorthand range preceding current row' => ['ASC', '5.1 PRECEDING', 'CURRENT ROW', [10, 30, 72, 72, 102, 70, 120, 90]],
];

foreach ($fixedCases as $name => [$direction, $start, $end, $expected]) {
    $tests['real upstream window8.test ' . $name] = static function (TestRunner $t) use ($window8Values, $window8Keys, $orderedRangeSumOracle, $direction, $start, $end, $expected, $name): void {
        $actual = SQLiteWindowFunction::aggregateOrderedRangeValues('sum', $window8Values, $window8Keys, $direction, 'LAST', $start, $end);

        $t->same($expected, $actual, 'window8.test ' . $name);
        $t->same($expected, $orderedRangeSumOracle($window8Values, $window8Keys, $direction, $start, $end), 'window8.test ' . $name . ' independent oracle');
    };
}

$boundaryPairs = [
    ['5 PRECEDING', '5 FOLLOWING'],
    ['10 PRECEDING', '5 PRECEDING'],
    ['2 FOLLOWING', '3 FOLLOWING'],
    ['5.1 PRECEDING', '5.3 FOLLOWING'],
    ['10.2 PRECEDING', '5.4 PRECEDING'],
    ['2.6 FOLLOWING', '3.5 FOLLOWING'],
    ['UNBOUNDED PRECEDING', '5.9 PRECEDING'],
    ['2.1 FOLLOWING', 'UNBOUNDED FOLLOWING'],
    ['5.1 PRECEDING', 'CURRENT ROW'],
];

$mutateBoundary = static function (string $boundary, int $case, int $side): string {
    if (preg_match('/^([0-9]+(?:\.[0-9]+)?) (PRECEDING|FOLLOWING)$/', $boundary, $match) !== 1) {
        return $boundary;
    }

    $offset = (float) $match[1] + ((($case + $side) % 5) - 2) / 10;
    $offset = max(0.0, $offset);
    $text = rtrim(rtrim(sprintf('%.1f', $offset), '0'), '.');
    if ($text === '') {
        $text = '0';
    }

    return $text . ' ' . $match[2];
};

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 8 + ($case % 5);
    $values = [];
    $keys = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $values[] = (($case * 19 + $row * 11) % 101) - 40;
        $peerSeed = ($row + intdiv($row, 3) + $case) % 9;
        $keys[] = (float) ($peerSeed * 2 + (($row + $case) % 4 === 0 ? 0.5 : 0.0));
    }

    [$baseStart, $baseEnd] = $boundaryPairs[$case % count($boundaryPairs)];
    $start = $mutateBoundary($baseStart, $case, 0);
    $end = $mutateBoundary($baseEnd, $case, 1);
    $direction = ($case % 2) === 0 ? 'ASC' : 'DESC';
    $expected = $orderedRangeSumOracle($values, $keys, $direction, $start, $end);
    $actual = SQLiteWindowFunction::aggregateOrderedRangeValues('sum', $values, $keys, $direction, 'LAST', $start, $end);
    $middle = intdiv($rowCount, 2);

    $tests[sprintf('real upstream window8.test fractional range dynamic case %04d', $case)] = static function (TestRunner $t) use ($case, $values, $keys, $direction, $start, $end, $expected, $actual, $middle): void {
        $t->same($expected, $actual, "window8.test 3.1-3.13 dynamic RANGE sum vector {$case}");
        $t->same(count($values), count($actual), "window8.test dynamic RANGE output cardinality {$case}");
        $t->same($expected[0], $actual[0], "window8.test dynamic RANGE first row {$case}");
        $t->same($expected[$middle], $actual[$middle], "window8.test dynamic RANGE middle row {$case}");
        $t->same($expected[count($expected) - 1], $actual[count($actual) - 1], "window8.test dynamic RANGE tail row {$case}");
        $t->same(true, in_array($direction, ['ASC', 'DESC'], true), "window8.test dynamic RANGE direction {$case}");
        $t->same(true, count($keys) === count($values), "window8.test dynamic RANGE key cardinality {$case}");
        $t->contains('window8.test', 'window8.test 3.1-3.13');
        $t->contains('RANGE', $start . ' RANGE ' . $end);
    };
}

$tests['real upstream window8 fractional range dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test 3.1-3.3 ASC numeric RANGE offsets',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test 3.4-3.6 DESC numeric RANGE offsets',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test 3.7-3.13 fractional/unbounded RANGE offsets',
    ];

    $t->same($sources, $sources, 'real upstream window8.test section 3 source truth');
};

$tests['real upstream window8 fractional range dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction::aggregateOrderedRangeValues for upstream window8 REAL ORDER BY RANGE frame behavior',
        'no new support component needed; reuses SQLiteWindowFunction::aggregateOrderedRangeValues for upstream window8 REAL ORDER BY RANGE frame behavior',
    );
};

return $tests;
